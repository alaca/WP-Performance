<?php

declare(strict_types=1);

namespace WPP\Support;

/**
 * URL <-> filesystem helpers for front-end assets.
 */
final class Assets
{
    public static function host(string $url): string
    {
        return strtolower((string) parse_url($url, PHP_URL_HOST));
    }

    /** Add a scheme to protocol-relative URLs. */
    public static function normalize(string $url): string
    {
        if (str_starts_with($url, '//')) {
            return (is_ssl() ? 'https:' : 'http:') . $url;
        }
        return $url;
    }

    public static function stripQuery(string $url): string
    {
        $pos = strpos($url, '?');
        return $pos === false ? $url : substr($url, 0, $pos);
    }

    public static function isLocal(string $url): bool
    {
        $url = self::normalize($url);
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }
        $host = self::host($url);
        return $host === '' || $host === self::host(home_url());
    }

    /** Asset types this plugin is ever allowed to read from disk. */
    private const READABLE = ['css', 'js'];

    /**
     * Resolve a local asset URL to a readable filesystem path, or null.
     *
     * The URL can originate from page markup rather than from settings, so the
     * resolved path is confined to the install and restricted to asset types.
     * Without that, a crafted <link href="/wp-config.php"> would be read and its
     * contents written into a public file in the cache directory.
     */
    public static function toPath(string $url): ?string
    {
        $url = self::stripQuery(self::normalize($url));

        $contentUrl = content_url();
        $home       = home_url();

        if (str_starts_with($url, $contentUrl)) {
            $path = WP_CONTENT_DIR . substr($url, strlen($contentUrl));
        } elseif (str_starts_with($url, $home)) {
            $path = ABSPATH . ltrim(substr($url, strlen($home)), '/');
        } elseif (str_starts_with($url, '/')) {
            $path = ABSPATH . ltrim($url, '/');
        } else {
            return null;
        }

        $real = realpath($path);
        if ($real === false || ! is_file($real)) {
            return null;
        }

        if (! in_array(strtolower(pathinfo($real, PATHINFO_EXTENSION)), self::READABLE, true)) {
            return null;
        }

        return self::inRoots($real) ? $real : null;
    }

    /** True when the resolved path sits inside the WordPress install. */
    private static function inRoots(string $real): bool
    {
        $roots = [];
        foreach ([ABSPATH, WP_CONTENT_DIR] as $root) {
            $resolved = realpath($root);
            if ($resolved !== false) {
                $roots[] = rtrim($resolved, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            }
        }

        foreach ($roots as $root) {
            if (str_starts_with($real, $root)) {
                return true;
            }
        }

        return false;
    }

    public static function contents(string $url): ?string
    {
        $path = self::toPath($url);
        if ($path === null) {
            return null;
        }
        $data = file_get_contents($path);
        return $data === false ? null : $data;
    }
}
