<?php

declare(strict_types=1);

namespace WPP\Admin;

use WPP\Foundation\Hooks\HookProvider;

/**
 * Warns when another caching/optimization plugin is active, since running two
 * page-cache layers commonly causes conflicts.
 */
final class CompatibilityNotice extends HookProvider
{
    private const KNOWN = [
        'wp-rocket/wp-rocket.php'                    => 'WP Rocket',
        'w3-total-cache/w3-total-cache.php'          => 'W3 Total Cache',
        'wp-super-cache/wp-cache.php'                => 'WP Super Cache',
        'litespeed-cache/litespeed-cache.php'        => 'LiteSpeed Cache',
        'wp-fastest-cache/wpFastestCache.php'        => 'WP Fastest Cache',
        'cache-enabler/cache-enabler.php'            => 'Cache Enabler',
        'comet-cache/comet-cache.php'                => 'Comet Cache',
        'sg-cachepress/sg-cachepress.php'            => 'SiteGround Optimizer',
        'breeze/breeze.php'                          => 'Breeze',
        'autoptimize/autoptimize.php'                => 'Autoptimize',
        'hummingbird-performance/wp-hummingbird.php' => 'Hummingbird',
        'wp-optimize/wp-optimize.php'                => 'WP-Optimize',
    ];

    protected function actions(): array
    {
        return ['admin_notices' => 'render'];
    }

    public function render(): void
    {
        // The notice is the only warning before a drop-in is replaced, so it is
        // pointless for anyone who cannot deactivate the other plugin.
        if (! current_user_can('manage_options')) {
            return;
        }

        $active = (array) get_option('active_plugins', []);

        // Network-activated plugins live in a site option keyed by plugin file.
        if (is_multisite()) {
            $active = array_merge($active, array_keys((array) get_site_option('active_sitewide_plugins', [])));
        }

        $found = [];
        foreach (self::KNOWN as $file => $name) {
            if (in_array($file, $active, true)) {
                $found[] = $name;
            }
        }

        if ($found === []) {
            return;
        }

        printf(
            '<div class="notice notice-warning"><p>%s <strong>%s</strong>. %s</p></div>',
            esc_html__('WP Performance detected another caching/optimization plugin:', 'wpp'),
            esc_html(implode(', ', $found)),
            esc_html__('Running more than one can cause conflicts; consider deactivating the others.', 'wpp')
        );
    }
}
