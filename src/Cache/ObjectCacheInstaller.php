<?php

declare(strict_types=1);

namespace WPP\Cache;

/**
 * Installs/removes the object-cache.php drop-in. Only ever removes our own
 * drop-in (identified by a marker), never another plugin's.
 */
final class ObjectCacheInstaller
{
    private const MARKER = 'WPP_Object_Cache';

    public function available(): bool
    {
        return extension_loaded('redis');
    }

    public function install(): bool
    {
        // Never replace another plugin's object cache: overwriting it would take
        // its caching offline and the original file is not recoverable.
        if ($this->foreignDropin() || ! DropinInstaller::fileModsAllowed()) {
            return false;
        }

        $src  = WPP_DIR . 'object-cache.php';
        $dest = WP_CONTENT_DIR . '/object-cache.php';
        return file_exists($src) ? (bool) @copy($src, $dest) : false;
    }

    public function uninstall(): void
    {
        if ($this->installed()) {
            @unlink(WP_CONTENT_DIR . '/object-cache.php');
        }
    }

    public function installed(): bool
    {
        $dest = WP_CONTENT_DIR . '/object-cache.php';
        return is_file($dest) && str_contains((string) @file_get_contents($dest), self::MARKER);
    }

    /** True when an object-cache.php from another source is present. */
    public function foreignDropin(): bool
    {
        $dest = WP_CONTENT_DIR . '/object-cache.php';
        return is_file($dest) && ! str_contains((string) @file_get_contents($dest), self::MARKER);
    }
}
