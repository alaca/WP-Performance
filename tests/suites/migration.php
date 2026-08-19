<?php

declare(strict_types=1);

use WPP\Settings\Migration;
use WPP\Settings\SettingsService;

/**
 * Migration from the original plugin's individual wpp_* options. Getting this
 * wrong silently discards an existing user's configuration, so every mapping
 * shape is pinned here.
 */
return static function (): void {
    add_filter('wpp.settings.groups', static function (array $g): array {
        $g['cloudflare'] = ['option' => 'wpp_cloudflare', 'defaults' => [
            'enabled' => false, 'api_key' => '', 'email' => '', 'zone_id' => '',
            'dev_mode' => false, 'cache_level' => 'aggressive', 'browser_expire' => 14400,
            'minify_css' => false, 'minify_js' => false, 'minify_html' => false,
            'rocket_loader' => false, 'brotli' => false, 'custom_purge_urls' => [],
        ]];
        $g['varnish'] = ['option' => 'wpp_varnish', 'defaults' => ['enabled' => false, 'custom_host' => '']];
        $g['prefetch'] = ['option' => 'wpp_prefetch', 'defaults' => [
            'enabled' => false, 'mode' => 'prerender', 'eagerness' => 'moderate', 'exclude' => [],
        ]];
        return $g;
    });

    // A legacy install, as the original plugin would have left it.
    WPP_Test_State::$options = [
        'wpp_cache'                 => '1',
        'wpp_mobile_cache'          => '1',
        'wpp_cache_time'            => '20',
        'wpp_cache_length'          => '86400',
        'wpp_update_clear'          => '1',
        'wpp_clear_assets'          => '1',
        'wpp_cache_url_exclude'     => ['/foo', '/bar', ''],
        'wpp_gzip_compression'      => '',
        'wpp_sitemaps_list'         => ['https://x/sitemap.xml'],
        'wpp_search_bots_exclude'   => '1',
        'wpp_css_minify_inline'     => '1',
        'wpp_css_defer'             => '1',
        'wpp_css_font_display'      => 'swap',
        'wpp_css_minify'            => ['/a.css' => '1'],
        'wpp_css_prefetch'          => ['//fonts.gstatic.com'],
        'wpp_js_prefetch'           => ['//cdn.example.com'],
        'wpp_css_preconnect'        => ['//fonts.googleapis.com'],
        'wpp_html_optimization'     => '1',
        'wpp_html_remove_qoutes'    => '1',
        'wpp_images_lazy'           => '1',
        'wpp_images_resp'           => '1',
        'wpp_disable_lazy_mobile'   => '1',
        'wpp_image_url_exclude'     => ['/logo.png'],
        'wpp_cdn'                   => '1',
        'wpp_cdn_hostname'          => 'https://cdn.test',
        'wpp_db_cleanup_revisions'  => '1',
        'wpp_db_cleanup_frequency'  => 'weekly',
        'wpp_enable_log'            => '1',
        'wpp_cf_enabled'            => '1',
        'wpp_cf_email'              => 'me@test.com',
        'wpp_cf_browser_expire'     => '14400',
        'wpp_varnish_auto_purge'    => '1',
        'wpp_varnish_custom_host'   => '127.0.0.1',
        'wpp_prefetch_pages'        => '1',
        'wpp_cache_post_exclude'    => [12, 34],
        'wpp_css_post_exclude'      => [56],
        'wpp_image_sizes'           => ['hero' => [1200, 600, 1], 'thumb' => [150, 150, '']],
        'wpp_image_sizes_remove'    => ['medium_large'],
        'wpp_current_settings'      => '12345',
        'wpp_local_css'             => '1',
        'wpp_plugin_css_list'       => ['x'],
    ];

    $settings = new SettingsService();
    (new Migration($settings))->run();

    // cache
    $cache = $settings->get('cache');
    wpp_same(true, $cache['enabled'], 'cache enabled migrated');
    wpp_same(true, $cache['mobile'], 'mobile cache migrated');
    wpp_same(20, $cache['clear_time'], 'clear_time migrated as int');
    wpp_same(86400, $cache['clear_unit'], 'clear_unit migrated as int');
    wpp_same(true, $cache['clear_on_publish'], 'update_clear mapped to clear_on_publish');
    wpp_same(true, $cache['keep_assets'], 'clear_assets mapped to keep_assets (same meaning)');
    wpp_same(false, $cache['gzip'], 'empty legacy value becomes false');
    wpp_same(['/foo', '/bar'], $cache['exclude_urls'], 'empty list entries filtered out');
    wpp_same(['https://x/sitemap.xml'], $cache['sitemaps'], 'sitemaps migrated');
    wpp_same(true, $cache['exclude_search_bots'], 'search bot exclusion migrated');

    // css and js
    $css = $settings->get('css');
    wpp_same(true, $css['minify_inline'], 'css minify_inline migrated');
    wpp_same('swap', $css['font_display'], 'font_display migrated');
    wpp_same(['/a.css' => '1'], $css['minify'], 'per-file minify map migrated');
    wpp_same(['//fonts.gstatic.com', '//cdn.example.com'], $css['dns_prefetch'], 'css and js prefetch lists merged');
    wpp_same(['//fonts.googleapis.com'], $css['preconnect'], 'preconnect migrated');

    // html: the legacy key is misspelled
    wpp_same(true, $settings->get('html')['enabled'], 'html optimization migrated');
    wpp_same(true, $settings->get('html')['remove_quotes'], 'misspelled html_remove_qoutes migrated');

    // media
    $media = $settings->get('media');
    wpp_same(true, $media['images_lazy'], 'lazy load migrated');
    wpp_same(true, $media['images_responsive'], 'images_resp mapped to images_responsive');
    wpp_same(true, $media['images_lazy_disable_mobile'], 'disable_lazy_mobile migrated');
    wpp_same(['/logo.png'], $media['images_exclude_urls'], 'image url exclusions migrated');

    // cdn, database, tools
    wpp_same(true, $settings->get('cdn')['enabled'], 'cdn enabled migrated');
    wpp_same('https://cdn.test', $settings->get('cdn')['hostname'], 'cdn hostname migrated');
    wpp_same(true, $settings->get('database')['cleanup_revisions'], 'db cleanup migrated');
    wpp_same('weekly', $settings->get('database')['frequency'], 'cleanup frequency migrated');
    wpp_same(true, $settings->get('tools')['enable_log'], 'logging migrated');

    // add-ons
    wpp_same(true, $settings->get('cloudflare')['enabled'], 'cloudflare enabled migrated');
    wpp_same('me@test.com', $settings->get('cloudflare')['email'], 'cloudflare email migrated');
    wpp_same(14400, $settings->get('cloudflare')['browser_expire'], 'cloudflare browser_expire migrated as int');
    wpp_same(true, $settings->get('varnish')['enabled'], 'varnish migrated');
    wpp_same('127.0.0.1', $settings->get('varnish')['custom_host'], 'varnish host migrated');
    wpp_same(true, $settings->get('prefetch')['enabled'], 'prefetch migrated');

    // Per-post exclusions become post meta.
    wpp_same(1, WPP_Test_State::$postmeta[12]['_wpp_exclude_cache'] ?? null, 'post 12 cache exclusion migrated');
    wpp_same(1, WPP_Test_State::$postmeta[34]['_wpp_exclude_cache'] ?? null, 'post 34 cache exclusion migrated');
    wpp_same(1, WPP_Test_State::$postmeta[56]['_wpp_exclude_css'] ?? null, 'post 56 css exclusion migrated');

    // Image sizes are reshaped in place.
    wpp_same(
        ['hero' => ['width' => 1200, 'height' => 600, 'crop' => true],
         'thumb' => ['width' => 150, 'height' => 150, 'crop' => false]],
        get_option('wpp_image_sizes'),
        'legacy image sizes reshaped to the keyed format'
    );
    wpp_same(['medium_large'], get_option('wpp_image_sizes_remove'), 'removed sizes preserved');

    // Obsolete options are deleted.
    foreach ([
        'wpp_mobile_cache', 'wpp_css_minify', 'wpp_html_remove_qoutes', 'wpp_cf_email',
        'wpp_cache_post_exclude', 'wpp_current_settings', 'wpp_local_css', 'wpp_plugin_css_list',
    ] as $gone) {
        wpp_ok(! array_key_exists($gone, WPP_Test_State::$options), "legacy option deleted: {$gone}");
    }

    // Colliding option names now hold the new grouped arrays and must survive.
    wpp_ok(is_array(get_option('wpp_cache')), 'wpp_cache now holds the grouped array');
    wpp_ok(is_array(get_option('wpp_cdn')), 'wpp_cdn now holds the grouped array');
    wpp_ok(array_key_exists('wpp_image_sizes', WPP_Test_State::$options), 'wpp_image_sizes retained');

    // maybeRun is guarded and idempotent.
    wpp_same(false, get_option('wpp_migrated'), 'run() alone does not set the flag');
    (new Migration($settings))->maybeRun();
    wpp_same('2.0.0', get_option('wpp_migrated'), 'maybeRun sets the version flag');
    $before = WPP_Test_State::$options;
    (new Migration($settings))->maybeRun();
    wpp_same($before, WPP_Test_State::$options, 'a second maybeRun is a no-op');
};
