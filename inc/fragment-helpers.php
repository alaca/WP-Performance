<?php

declare(strict_types=1);

/**
 * Global template helpers for the block / fragment cache. Thin wrappers over the
 * container-resolved FragmentCache (a single shared instance, so the start/end
 * buffer stack is preserved across calls).
 *
 * Usage:
 *   if ( wpp_cache_start( 'sidebar', [ 'ttl' => 3600 ] ) ) {
 *       // expensive render
 *       wpp_cache_end();
 *   }
 *
 *   wpp_cache_fragment( 'menu', [ 'vary_url' => false ], function () {
 *       wp_nav_menu( [ 'theme_location' => 'primary' ] );
 *   } );
 */

use WPP\Cache\FragmentCache;
use WPP\Foundation\Plugin;

if (! function_exists('wpp_cache_start')) {
    /**
     * @param array<string, mixed> $opts ttl, vary_url, vary_role, vary_device
     */
    function wpp_cache_start(string $key, array $opts = []): bool
    {
        return Plugin::instance()->container->get(FragmentCache::class)->start($key, $opts);
    }
}

if (! function_exists('wpp_cache_end')) {
    function wpp_cache_end(): void
    {
        Plugin::instance()->container->get(FragmentCache::class)->end();
    }
}

if (! function_exists('wpp_cache_fragment')) {
    /**
     * @param array<string, mixed> $opts
     */
    function wpp_cache_fragment(string $key, array $opts, callable $callback): void
    {
        echo Plugin::instance()->container->get(FragmentCache::class)->wrap($key, $opts, $callback);
    }
}
