<?php

declare(strict_types=1);

namespace WPP\Support;

use WPP\Foundation\Plugin;
use WPP\Cache\RuntimeSettings;
use WPP\Settings\SettingsService;

/**
 * Timestamped activity log under the cache directory, written only while the
 * troubleshooting log is enabled. Survives a cache clear (CacheStore keeps .log).
 */
final class Logger
{
    private const MAX_BYTES = 1048576; // 1 MB

    public function __construct(private SettingsService $settings)
    {
    }

    public function register(): void
    {
        add_action('wpp.cache.after_clear', fn () => $this->log('Cache cleared'));
        add_action('wpp.cache.saved', fn ($url) => $this->log('Cache saved: ' . (string) $url), 10, 1);
        add_action('wpp.settings.updated', fn ($group) => $this->log('Settings saved: ' . (string) $group), 10, 1);
        add_action('wpp.db.cleaned', fn ($type) => $this->log('Database cleanup: ' . (string) $type), 10, 1);
    }

    public function enabled(): bool
    {
        return ! empty($this->settings->get('tools')['enable_log']);
    }

    public function file(): string
    {
        $name = (function_exists('is_multisite') && is_multisite())
            ? get_current_blog_id() . '_wpp.log.php'
            : 'wpp.log.php';
        return WPP_CACHE_DIR . $name;
    }

    public function log(string $message): void
    {
        if (! $this->enabled()) {
            return;
        }

        Plugin::protectCacheDir();

        if (! is_dir(dirname($this->file()))) {
            return;
        }

        $line = sprintf('[%s] %s%s', current_time('mysql'), $message, PHP_EOL);
        if (! is_file($this->file())) {
            $line = RuntimeSettings::GUARD . $line;
        }
        file_put_contents($this->file(), $line, FILE_APPEND | LOCK_EX);

        $this->trim();
    }

    public function read(): string
    {
        $file = $this->file();

        return is_file($file)
            ? ltrim(RuntimeSettings::payload((string) file_get_contents($file)), "\n")
            : '';
    }

    public function clear(): void
    {
        $file = $this->file();
        if (is_file($file)) {
            @unlink($file);
        }
    }

    private function trim(): void
    {
        $file = $this->file();

        // The append above just changed the size and PHP serves a cached stat,
        // so the check below is only correct as long as something evicts the
        // entry first. Do not leave that to the caller.
        clearstatcache(true, $file);

        if (! is_file($file) || (int) filesize($file) <= self::MAX_BYTES) {
            return;
        }

        // Trimming from the front would take the guard with it and leave a
        // plain-text log the web server would happily serve.
        $contents = ltrim(RuntimeSettings::payload((string) file_get_contents($file)), "\n");
        $contents = substr($contents, -self::MAX_BYTES);
        $cut = strpos($contents, PHP_EOL);
        if ($cut !== false) {
            $contents = substr($contents, $cut + 1);
        }

        file_put_contents($file, RuntimeSettings::GUARD . $contents, LOCK_EX);
    }
}
