<?php

declare(strict_types=1);

namespace WPP\Media;

use WP_Query;

/**
 * Custom image-size management and thumbnail regeneration.
 * Custom sizes live in wpp_image_sizes; removed (core) sizes in wpp_image_sizes_remove.
 */
final class ImageService
{
    private const OPT_CUSTOM  = 'wpp_image_sizes';
    private const OPT_REMOVED = 'wpp_image_sizes_remove';
    private const CORE_SIZES  = ['thumbnail', 'medium', 'medium_large', 'large'];

    /** Register custom sizes (call on init). */
    public function register(): void
    {
        foreach ($this->custom() as $name => $size) {
            add_image_size((string) $name, (int) $size['width'], (int) $size['height'], (bool) $size['crop']);
        }
    }

    /**
     * Drop removed sizes during attachment metadata generation.
     *
     * @param array<string, mixed> $sizes
     * @return array<string, mixed>
     */
    public function filterSizes(array $sizes): array
    {
        foreach ($this->removed() as $name) {
            unset($sizes[$name]);
        }
        return $sizes;
    }

    /** @return list<array{name:string,width:int,height:int,crop:bool,core:bool}> */
    public function definedSizes(): array
    {
        global $_wp_additional_image_sizes;

        $removed = $this->removed();
        $custom  = $this->custom();
        $out     = [];

        foreach (self::CORE_SIZES as $name) {
            if (in_array($name, $removed, true)) {
                continue;
            }
            $out[] = [
                'name'   => $name,
                'width'  => (int) get_option($name . '_size_w'),
                'height' => (int) get_option($name . '_size_h'),
                'crop'   => (bool) get_option($name . '_crop'),
                'core'   => true,
            ];
        }

        if (is_array($_wp_additional_image_sizes)) {
            foreach ($_wp_additional_image_sizes as $name => $size) {
                if (in_array($name, $removed, true)) {
                    continue;
                }
                $out[] = [
                    'name'   => (string) $name,
                    'width'  => (int) $size['width'],
                    'height' => (int) $size['height'],
                    'crop'   => (bool) $size['crop'],
                    'core'   => ! isset($custom[$name]),
                ];
            }
        }

        return $out;
    }

    public function add(string $name, int $width, int $height, bool $crop): void
    {
        $name = sanitize_key($name);
        if ($name === '') {
            return;
        }
        $custom = $this->custom();
        $custom[$name] = ['width' => $width, 'height' => $height, 'crop' => $crop];
        update_option(self::OPT_CUSTOM, $custom, false);
        $this->setRemoved(array_values(array_diff($this->removed(), [$name])));
    }

    public function remove(string $name): void
    {
        $custom = $this->custom();
        if (isset($custom[$name])) {
            unset($custom[$name]);
            update_option(self::OPT_CUSTOM, $custom, false);
            return;
        }
        $removed   = $this->removed();
        $removed[] = $name;
        $this->setRemoved(array_values(array_unique($removed)));
    }

    public function restore(): void
    {
        delete_option(self::OPT_CUSTOM);
        delete_option(self::OPT_REMOVED);
    }

    /**
     * Regenerate a slice of image attachments.
     *
     * @return array{processed:int,total:int,done:bool}
     */
    public function regenerate(int $offset, int $limit): array
    {
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $query = new WP_Query([
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'fields'         => 'ids',
            'posts_per_page' => $limit,
            'offset'         => $offset,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'no_found_rows'  => false,
        ]);

        /** @var int[] $ids */
        $ids = $query->posts;

        foreach ($ids as $id) {
            $file = get_attached_file($id);
            if ($file && file_exists($file)) {
                $meta = wp_generate_attachment_metadata($id, $file);
                if (! is_wp_error($meta)) {
                    wp_update_attachment_metadata($id, $meta);
                }
            }
        }

        $total     = (int) $query->found_posts;
        $processed = $offset + count($ids);

        return [
            'processed' => $processed,
            'total'     => $total,
            'done'      => count($ids) === 0 || $processed >= $total,
        ];
    }

    /** @return array<string, array{width:int,height:int,crop:bool}> */
    private function custom(): array
    {
        $value = get_option(self::OPT_CUSTOM, []);
        return is_array($value) ? $value : [];
    }

    /** @return string[] */
    private function removed(): array
    {
        $value = get_option(self::OPT_REMOVED, []);
        return is_array($value) ? $value : [];
    }

    /** @param string[] $removed */
    private function setRemoved(array $removed): void
    {
        update_option(self::OPT_REMOVED, $removed, false);
    }
}
