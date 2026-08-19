<?php

declare(strict_types=1);

namespace WPP\Media;

use GdImage;
use WP_Query;

/**
 * Converts JPEG/PNG images to next-gen formats (WebP, and AVIF where the GD
 * build supports it). Outputs a sibling file: image.jpg -> image.jpg.webp.
 */
final class ImageConverter
{
    public function supportsWebp(): bool
    {
        return function_exists('imagewebp');
    }

    public function supportsAvif(): bool
    {
        return function_exists('imageavif');
    }

    /**
     * Convert one image file. Returns the formats produced.
     *
     * @return string[]
     */
    public function convert(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            return [];
        }

        $image = $ext === 'png' ? @imagecreatefrompng($path) : @imagecreatefromjpeg($path);
        if (! $image) {
            return [];
        }
        if ($ext === 'png') {
            imagepalettetotruecolor($image);
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        $made = [];
        if ($this->supportsAvif() && $this->missing($path . '.avif') && $this->encode('avif', $image, $path . '.avif')) {
            $made[] = 'avif';
        }
        if ($this->supportsWebp() && $this->missing($path . '.webp') && $this->encode('webp', $image, $path . '.webp')) {
            $made[] = 'webp';
        }

        imagedestroy($image);
        return $made;
    }

    /** A zero-byte output is a failed encode from an earlier run, so retry it. */
    private function missing(string $file): bool
    {
        clearstatcache(true, $file);

        return ! is_file($file) || (int) @filesize($file) === 0;
    }

    /**
     * GD creates the destination before it encodes, so an encode that runs out
     * of memory or is killed leaves a truncated file behind. Nothing would ever
     * remove it and every AVIF-capable browser would pick it over the original,
     * so the output is only moved into place once it is known to be complete.
     */
    private function encode(string $format, GdImage $image, string $dest): bool
    {
        $tmp = $dest . '.tmp';

        $ok = $format === 'avif'
            ? @imageavif($image, $tmp, 70)
            : @imagewebp($image, $tmp, 82);

        clearstatcache(true, $tmp);

        if ($ok && is_file($tmp) && (int) @filesize($tmp) > 0 && @rename($tmp, $dest)) {
            return true;
        }

        @unlink($tmp);
        return false;
    }

    /** Convert every size file of an attachment. */
    public function convertAttachment(int $id): void
    {
        $file = get_attached_file($id);
        if (! $file) {
            return;
        }
        $this->convert($file);

        $meta = wp_get_attachment_metadata($id);
        if (! empty($meta['sizes']) && is_array($meta['sizes'])) {
            $dir = dirname($file);
            foreach ($meta['sizes'] as $size) {
                if (! empty($size['file'])) {
                    $this->convert($dir . '/' . $size['file']);
                }
            }
        }
    }

    /**
     * Convert a slice of image attachments.
     *
     * @return array{processed:int,total:int,done:bool}
     */
    public function bulk(int $offset, int $limit): array
    {
        $query = new WP_Query([
            'post_type'      => 'attachment',
            'post_mime_type' => ['image/jpeg', 'image/png'],
            'post_status'    => 'inherit',
            'fields'         => 'ids',
            'posts_per_page' => $limit,
            'offset'         => $offset,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ]);

        /** @var int[] $ids */
        $ids = $query->posts;
        foreach ($ids as $id) {
            $this->convertAttachment($id);
        }

        $total     = (int) $query->found_posts;
        $processed = $offset + count($ids);

        return [
            'processed' => $processed,
            'total'     => $total,
            'done'      => count($ids) === 0 || $processed >= $total,
        ];
    }
}
