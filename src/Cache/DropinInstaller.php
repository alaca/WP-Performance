<?php

declare(strict_types=1);

namespace WPP\Cache;

/**
 * Installs/removes the advanced-cache.php drop-in and toggles the WP_CACHE
 * constant in wp-config.php so WordPress loads the drop-in.
 */
final class DropinInstaller
{
    private const MARKER  = '// WP Performance';
    private const STAMP   = 'WPP_ADVANCED_CACHE';
    private const PLACEHOLDER = '@wpp-version@';

    /**
     * Copies installed before the version stamp existed are still ours, and so
     * is the 1.x loader an updating site still has in wp-content: mistaking it
     * for a third-party drop-in blocks the install forever and leaves 1.x
     * serving pages that 2.0 writes.
     */
    private const LEGACY = [
        'WP Performance advanced-cache.php drop-in',
        'WP Performance Optimizer - Cache loader',
        '_wpp_get_cache_file',
        '_wpp_get_site_settings',
    ];

    public function install(): bool
    {
        if (! self::fileModsAllowed()) {
            return false;
        }

        $copied = $this->copyDropin();
        if ($copied) {
            $this->setWpCache(true);
        }
        return $copied;
    }

    /**
     * wp-content/advanced-cache.php and wp-config.php are network-wide root
     * files. A multisite subsite administrator has manage_options but is
     * deliberately not allowed to modify files, and DISALLOW_FILE_MODS is how a
     * host locks writes down entirely.
     */
    public static function fileModsAllowed(): bool
    {
        if (defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS) {
            return false;
        }

        // No current user means WP-CLI or cron, where there is nobody to check.
        return ! is_multisite() || get_current_user_id() === 0 || is_super_admin();
    }

    public function uninstall(): void
    {
        // Only ever remove our own drop-in, and only disable WP_CACHE when we
        // were the ones serving it. Another cache plugin may own this file.
        if ($this->ours()) {
            @unlink($this->dest());
            $this->setWpCache(false);
        }
    }

    /** Installed, ours, and matching the running plugin version. */
    public function dropinInstalled(): bool
    {
        return $this->ours() && $this->installedVersion() === $this->version();
    }

    /** A drop-in from another plugin is present and must not be touched. */
    public function foreignDropin(): bool
    {
        return is_file($this->dest()) && ! $this->ours();
    }

    private function dest(): string
    {
        return WP_CONTENT_DIR . '/advanced-cache.php';
    }

    private function version(): string
    {
        return defined('WPP_VERSION') ? (string) WPP_VERSION : '0';
    }

    private function ours(): bool
    {
        $dest = $this->dest();
        if (! is_file($dest)) {
            return false;
        }

        $contents = (string) @file_get_contents($dest);
        if (str_contains($contents, self::STAMP)) {
            return true;
        }

        foreach (self::LEGACY as $marker) {
            if (str_contains($contents, $marker)) {
                return true;
            }
        }

        return false;
    }

    private function installedVersion(): ?string
    {
        $contents = is_file($this->dest()) ? (string) @file_get_contents($this->dest()) : '';
        return preg_match("/'" . self::STAMP . "',\s*'([^']*)'/", $contents, $m) ? $m[1] : null;
    }

    private function copyDropin(): bool
    {
        $src = WPP_DIR . 'advanced-cache.php';
        if (! file_exists($src) || $this->foreignDropin()) {
            return false;
        }

        $contents = file_get_contents($src);
        if ($contents === false) {
            return false;
        }

        // Stamp the version so a plugin update refreshes a stale copy.
        $contents = str_replace(self::PLACEHOLDER, $this->version(), $contents);

        return file_put_contents($this->dest(), $contents, LOCK_EX) !== false;
    }

    private function setWpCache(bool $on): void
    {
        $config = $this->wpConfigPath();
        if ($config === null || ! is_writable($config)) {
            return;
        }

        $contents = file_get_contents($config);
        if ($contents === false) {
            return;
        }

        // Turning off only removes the line we added. A WP_CACHE define placed
        // there by the host or another plugin is left alone.
        $pattern = $on
            ? '/^[ \t]*define\(\s*[\'"]WP_CACHE[\'"]\s*,.*?\);.*$\n?/m'
            : '/^[ \t]*define\(\s*[\'"]WP_CACHE[\'"]\s*,.*?\);[ \t]*' . preg_quote(self::MARKER, '/') . '.*$\n?/m';

        $contents = preg_replace($pattern, '', $contents);

        if ($on && $contents !== null) {
            $line = "define( 'WP_CACHE', true ); " . self::MARKER . "\n";
            $contents = preg_replace('/^<\?php\s*\n/', "<?php\n" . $line, (string) $contents, 1);
        }

        if ($contents !== null) {
            file_put_contents($config, $contents, LOCK_EX);
        }
    }

    private function wpConfigPath(): ?string
    {
        if (file_exists(ABSPATH . 'wp-config.php')) {
            return ABSPATH . 'wp-config.php';
        }
        $parent = dirname(ABSPATH) . '/wp-config.php';
        if (file_exists($parent) && ! file_exists(dirname(ABSPATH) . '/wp-settings.php')) {
            return $parent;
        }
        return null;
    }
}
