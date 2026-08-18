<?php

declare(strict_types=1);

namespace WPP\Rest\Admin;

use WP_REST_Response;
use WP_REST_Server;
use WPP\Cache\CacheStore;
use WPP\Cache\DropinInstaller;
use WPP\Cache\FragmentStore;
use WPP\Cache\ObjectCacheInstaller;
use WPP\Server\ServerRules;
use WPP\Settings\SettingsService;

/**
 * GET /wpp/v1/overview -> dashboard payload: cache stats, active-feature summary,
 * and a list of environment health checks.
 */
final class OverviewController
{
    private const NS = 'wpp/v1';

    /** PHP version we recommend for best performance. */
    private const RECOMMENDED_PHP = '8.1';

    public function __construct(
        private CacheStore $store,
        private SettingsService $settings,
        private ServerRules $rules,
        private FragmentStore $fragments
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NS, '/overview', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'show'],
                'permission_callback' => [$this, 'canAccess'],
            ],
        ]);
    }

    public function canAccess(): bool
    {
        return current_user_can('manage_options');
    }

    public function show(): WP_REST_Response
    {
        return new WP_REST_Response([
            'cache'    => $this->cache(),
            'features' => $this->features(),
            'health'   => $this->health(),
        ], 200);
    }

    /** @return array{enabled:bool, pages:int, bytes:int, blocks:array{enabled:bool, count:int, bytes:int, backend:string}} */
    private function cache(): array
    {
        $stats   = $this->store->stats();
        $cache   = $this->settings->get('cache');
        $fragStats = $this->fragments->stats();

        return [
            'enabled' => ! empty($cache['enabled']),
            'pages'   => $this->store->pages(),
            'bytes'   => (int) $stats['total'],
            'blocks'  => [
                'enabled' => ! empty($cache['block_cache_enabled']),
                'count'   => (int) $fragStats['count'],
                'bytes'   => (int) $fragStats['bytes'],
                'backend' => $this->fragments->usesObjectCache() ? 'object' : 'disk',
            ],
        ];
    }

    /** @return list<array{label:string, enabled:bool}> */
    private function features(): array
    {
        $cache = $this->settings->get('cache');
        $css   = $this->settings->get('css');
        $js    = $this->settings->get('js');
        $html  = $this->settings->get('html');
        $media = $this->settings->get('media');
        $cdn   = $this->settings->get('cdn');
        $db    = $this->settings->get('database');
        $tools = $this->settings->get('tools');

        $cssOn = ! empty($css['minify']) || ! empty($css['combine']) || ! empty($css['defer'])
            || ! empty($css['remove_unused']) || ! empty($css['minify_inline']);
        $jsOn  = ! empty($js['minify']) || ! empty($js['combine']) || ! empty($js['defer'])
            || ! empty($js['delay']) || ! empty($js['minify_inline']);

        return [
            ['label' => __('Page cache', 'wpp'),        'enabled' => ! empty($cache['enabled'])],
            ['label' => __('Block cache', 'wpp'),       'enabled' => ! empty($cache['block_cache_enabled'])],
            ['label' => __('Browser caching', 'wpp'),   'enabled' => ! empty($cache['browser_cache'])],
            ['label' => __('GZIP compression', 'wpp'),  'enabled' => ! empty($cache['gzip'])],
            ['label' => __('CSS optimization', 'wpp'),  'enabled' => $cssOn],
            ['label' => __('JavaScript optimization', 'wpp'), 'enabled' => $jsOn],
            ['label' => __('HTML minification', 'wpp'), 'enabled' => ! empty($html['enabled'])],
            ['label' => __('Lazy loading', 'wpp'),      'enabled' => ! empty($media['images_lazy']) || ! empty($media['videos_lazy'])],
            ['label' => __('WebP/AVIF images', 'wpp'),  'enabled' => ! empty($media['webp'])],
            ['label' => __('Database cleanup', 'wpp'),  'enabled' => ! empty($db['frequency']) && $db['frequency'] !== 'none'],
            ['label' => __('CDN', 'wpp'),               'enabled' => ! empty($cdn['enabled'])],
            ['label' => __('Object cache', 'wpp'),      'enabled' => (new ObjectCacheInstaller())->installed()],
        ];
    }

    /** @return list<array{id:string, label:string, status:string, message:string}> status: good|warn|bad */
    private function health(): array
    {
        $checks   = [];
        $dropin   = new DropinInstaller();
        $objCache = new ObjectCacheInstaller();

        // Page cache drop-in.
        $cacheEnabled = ! empty($this->settings->get('cache')['enabled']);
        $dropinOk     = $dropin->dropinInstalled() && defined('WP_CACHE') && WP_CACHE;
        if (! $cacheEnabled) {
            $checks[] = $this->check('page_cache', __('Page cache', 'wpp'), 'warn', __('Page caching is turned off.', 'wpp'));
        } elseif ($dropinOk) {
            $checks[] = $this->check('page_cache', __('Page cache', 'wpp'), 'good', __('Page caching is active and serving cached pages.', 'wpp'));
        } elseif ($dropin->foreignDropin()) {
            // Nothing the admin can change in this plugin will fix it, so saying
            // the file is "missing" sends them looking in the wrong place.
            $checks[] = $this->check('page_cache', __('Page cache', 'wpp'), 'bad', __('Another plugin owns advanced-cache.php, so page caching cannot start. Remove that plugin or its drop-in first.', 'wpp'));
        } else {
            $checks[] = $this->check('page_cache', __('Page cache', 'wpp'), 'bad', __('The advanced-cache.php drop-in or WP_CACHE constant is missing.', 'wpp'));
        }

        // Object cache.
        if ($objCache->installed()) {
            $checks[] = $this->check('object_cache', __('Object cache', 'wpp'), 'good', __('A persistent object cache is active.', 'wpp'));
        } elseif ($objCache->foreignDropin()) {
            $checks[] = $this->check('object_cache', __('Object cache', 'wpp'), 'warn', __('Another object cache drop-in is installed.', 'wpp'));
        } elseif ($objCache->available()) {
            $checks[] = $this->check('object_cache', __('Object cache', 'wpp'), 'warn', __('Redis is available but the object cache is not enabled.', 'wpp'));
        } else {
            $checks[] = $this->check('object_cache', __('Object cache', 'wpp'), 'warn', __('No object cache backend detected (Redis recommended).', 'wpp'));
        }

        // GZIP / compression support.
        $checks[] = function_exists('gzencode')
            ? $this->check('gzip', __('Compression', 'wpp'), 'good', __('GZIP compression is available.', 'wpp'))
            : $this->check('gzip', __('Compression', 'wpp'), 'warn', __('The zlib extension is missing, so GZIP is unavailable.', 'wpp'));

        // OPcache.
        $opcacheOn = function_exists('opcache_get_status');
        if ($opcacheOn) {
            $status    = @opcache_get_status(false);
            $opcacheOn = is_array($status) && ! empty($status['opcache_enabled']);
        }
        $checks[] = $opcacheOn
            ? $this->check('opcache', __('PHP OPcache', 'wpp'), 'good', __('OPcache is enabled.', 'wpp'))
            : $this->check('opcache', __('PHP OPcache', 'wpp'), 'warn', __('OPcache is not enabled. Enabling it speeds up PHP execution.', 'wpp'));

        // PHP version.
        $checks[] = version_compare(PHP_VERSION, self::RECOMMENDED_PHP, '>=')
            ? $this->check('php', __('PHP version', 'wpp'), 'good', sprintf(/* translators: %s: PHP version. */ __('Running PHP %s.', 'wpp'), PHP_VERSION))
            : $this->check('php', __('PHP version', 'wpp'), 'bad', sprintf(/* translators: 1: current PHP version, 2: recommended version. */ __('PHP %1$s is below the recommended %2$s.', 'wpp'), PHP_VERSION, self::RECOMMENDED_PHP));

        // Cache directory writable.
        $cacheDir = defined('WPP_CACHE_DIR') ? WPP_CACHE_DIR : '';
        $writable = $cacheDir !== '' && (is_writable($cacheDir) || (! file_exists($cacheDir) && is_writable(dirname($cacheDir))));
        $checks[] = $writable
            ? $this->check('cache_dir', __('Cache directory', 'wpp'), 'good', __('The cache directory is writable.', 'wpp'))
            : $this->check('cache_dir', __('Cache directory', 'wpp'), 'bad', __('The cache directory is not writable.', 'wpp'));

        // Image conversion support.
        $checks[] = function_exists('imagewebp')
            ? $this->check('webp', __('Image conversion', 'wpp'), 'good', __('GD can generate WebP images.', 'wpp'))
            : $this->check('webp', __('Image conversion', 'wpp'), 'warn', __('GD cannot generate WebP images on this server.', 'wpp'));

        // Server rules.
        $type = $this->rules->serverType();
        if ($type === 'apache') {
            $htaccess = ABSPATH . '.htaccess';
            $checks[] = (file_exists($htaccess) ? is_writable($htaccess) : is_writable(ABSPATH))
                ? $this->check('server', __('Server rules', 'wpp'), 'good', __('Apache detected; .htaccess is writable.', 'wpp'))
                : $this->check('server', __('Server rules', 'wpp'), 'warn', __('Apache detected but .htaccess is not writable.', 'wpp'));
        } elseif ($type === 'nginx') {
            $checks[] = $this->check('server', __('Server rules', 'wpp'), 'good', __('Nginx detected; add the generated rules from Settings.', 'wpp'));
        } else {
            $checks[] = $this->check('server', __('Server rules', 'wpp'), 'warn', __('Server type could not be determined.', 'wpp'));
        }

        return $checks;
    }

    /** @return array{id:string, label:string, status:string, message:string} */
    private function check(string $id, string $label, string $status, string $message): array
    {
        return ['id' => $id, 'label' => $label, 'status' => $status, 'message' => $message];
    }
}
