<?php

declare(strict_types=1);

namespace WPP\Admin;

use WPP\Foundation\Hooks\HookProvider;
use WPP\Settings\SettingsService;

/**
 * Enqueues the built React admin bundle on the WP Performance page and
 * publishes the window.WPP config object (REST root, nonce, urls, preloaded settings).
 */
final class AdminAssets extends HookProvider
{
    private const HANDLE = 'wpp-admin';

    public function __construct(private SettingsService $settings)
    {
    }

    protected function actions(): array
    {
        return ['admin_enqueue_scripts' => 'enqueue'];
    }

    public function enqueue(): void
    {
        if (! $this->isPluginPage()) {
            return;
        }

        $asset = $this->asset('admin/app');

        wp_enqueue_script(
            self::HANDLE,
            WPP_URL . 'build/admin/app/index.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        wp_set_script_translations(self::HANDLE, 'wpp', WPP_DIR . 'languages');

        // Version the stylesheet by its own mtime: wp-scripts ties the CSS version to
        // the JS content hash, so a CSS-only change would otherwise not bust the cache.
        $cssFile = WPP_DIR . 'build/admin/app.css';
        $cssVer  = file_exists($cssFile) ? (string) filemtime($cssFile) : $asset['version'];

        wp_enqueue_style(
            self::HANDLE,
            WPP_URL . 'build/admin/app.css',
            [],
            $cssVer
        );

        // Swaps in the built app-rtl.css on right-to-left locales.
        wp_style_add_data(self::HANDLE, 'rtl', 'replace');

        $payload = [
            'rest'     => esc_url_raw(rest_url('wpp/v1/')),
            'nonce'    => wp_create_nonce('wp_rest'),
            'slug'     => WPP_SLUG,
            'version'  => WPP_VERSION,
            'adminUrl' => esc_url_raw(admin_url('admin.php?page=' . WPP_SLUG)),
            'siteUrl'  => esc_url_raw(home_url('/')),
            'settings' => $this->settings->all(),
            'caps'     => [
                'redis'    => extension_loaded('redis'),
                'memcached' => class_exists('Memcached'),
                'webp'     => function_exists('imagewebp'),
                'avif'     => function_exists('imageavif'),
                'opcache'  => function_exists('opcache_get_status'),
            ],
        ];

        wp_add_inline_script(
            self::HANDLE,
            'window.WPP = Object.assign(window.WPP || {}, ' . wp_json_encode($payload) . ');',
            'before'
        );
    }

    /** @return array{dependencies: string[], version: string} */
    private function asset(string $name): array
    {
        $file = WPP_DIR . "build/{$name}/index.asset.php";
        if (file_exists($file)) {
            /** @var array{dependencies: string[], version: string} $data */
            $data = require $file;
            return $data;
        }
        return ['dependencies' => [], 'version' => WPP_VERSION];
    }

    private function isPluginPage(): bool
    {
        $page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
        return $page === WPP_SLUG;
    }
}
