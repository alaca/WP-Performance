<?php

declare(strict_types=1);

namespace WPP\Cache;

use WPP\Settings\SettingsService;

/**
 * Writes a small per-site JSON file that the advanced-cache.php drop-in reads
 * to decide whether/how to serve a cached page. The drop-in loads too early to
 * use plugin classes, so all state it needs is flattened into this file.
 */
final class RuntimeSettings
{
    /** Used when the configured expiry is missing or non-positive; matches the shipped default. */
    private const FALLBACK_EXPIRE = 36000;

    public function __construct(private SettingsService $settings)
    {
    }

    /**
     * The drop-in serves nothing without this file, so a missing one silently
     * disables page caching until settings happen to be saved again. A file
     * written by an older release is also treated as missing: a plugin update
     * can add keys the drop-in now expects, and nothing else would rewrite it.
     */
    public function written(): bool
    {
        $file = $this->file();
        if (! is_file($file)) {
            return false;
        }

        $data = json_decode(self::payload((string) file_get_contents($file)), true);

        return is_array($data) && ($data['version'] ?? null) === $this->version();
    }

    /** Contents with the guard prefix removed. */
    public static function payload(string $contents): string
    {
        return str_starts_with($contents, '<?php') ? (string) strstr($contents, "\n") : $contents;
    }

    private function version(): string
    {
        return defined('WPP_VERSION') ? (string) WPP_VERSION : '0';
    }

    /**
     * Guard prefix so a direct HTTP request returns nothing. The cache directory
     * is inside the web root and .htaccess is ignored by nginx, so the extension
     * plus this prefix is the only protection that holds on every server.
     */
    public const GUARD = "<?php exit; ?>\n";

    public function file(): string
    {
        $home = (string) home_url();
        $host = CacheStore::sanitizeHost((string) parse_url($home, PHP_URL_HOST));

        return WPP_CACHE_DIR . $host . self::pathKey((string) parse_url($home, PHP_URL_PATH)) . '.json.php';
    }

    /**
     * Sites in a subdirectory network share a host and each has its own settings,
     * so the path goes into the filename: without it every site in the network
     * overwrites the same config and the last one to save owns them all.
     *
     * Mirrored by wpp_dropin_path_key() in advanced-cache.php.
     */
    public static function pathKey(string $path): string
    {
        $key = '';
        foreach (array_slice(array_values(array_filter(explode('/', $path), 'strlen')), 0, 3) as $segment) {
            $key .= '~' . preg_replace('/[^a-z0-9\-_]/', '-', strtolower($segment));
        }

        return $key;
    }

    public function write(): void
    {
        if (! is_dir(WPP_CACHE_DIR)) {
            wp_mkdir_p(WPP_CACHE_DIR);
        }
        $this->guard();

        $cache = $this->settings->get('cache');

        // An emptied "Clear cache after" field stores '', and an expiry of 0
        // means "never stale", which would freeze every cached page for good.
        $expire = (int) ($cache['clear_time'] ?? 0) * (int) ($cache['clear_unit'] ?? 0);
        if ($expire <= 0) {
            $expire = self::FALLBACK_EXPIRE;
        }

        $permalinks = (string) get_option('permalink_structure', '');

        $data = [
            'version'     => $this->version(),
            'enabled'     => (bool) ($cache['enabled'] ?? false),
            'disabled'    => (bool) get_option('wpp_disabled', false),
            'mobile'      => (bool) ($cache['mobile'] ?? false),
            'expire'      => $expire,
            'permalinks'  => (bool) $permalinks,
            'trailing_slash' => str_ends_with($permalinks, '/'),
            'exclude'     => array_values((array) ($cache['exclude_urls'] ?? [])),
            'user_agents' => array_values((array) ($cache['exclude_user_agents'] ?? [])),
            'cache_query_strings' => (bool) ($cache['cache_query_strings'] ?? false),
            'search_bots' => (bool) ($cache['exclude_search_bots'] ?? false),
        ];

        file_put_contents($this->file(), self::GUARD . (string) wp_json_encode($data), LOCK_EX);
    }

    /**
     * This config lists the URLs the admin deliberately keeps out of the cache,
     * and the log next to it records every cached request, so neither may be
     * readable over HTTP.
     */
    private function guard(): void
    {
        $index = WPP_CACHE_DIR . 'index.php';
        if (! is_file($index)) {
            file_put_contents($index, "<?php\n// Silence is golden.\n");
        }

        $htaccess = WPP_CACHE_DIR . '.htaccess';
        if (is_file($htaccess)) {
            return;
        }

        file_put_contents($htaccess, implode("\n", [
            'Options -Indexes',
            '<FilesMatch "\.(json|log)(\.php)?$">',
            '<IfModule mod_authz_core.c>',
            'Require all denied',
            '</IfModule>',
            '<IfModule !mod_authz_core.c>',
            'Order allow,deny',
            'Deny from all',
            '</IfModule>',
            '</FilesMatch>',
            '',
        ]));
    }
}
