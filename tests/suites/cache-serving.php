<?php

declare(strict_types=1);

use WPP\Cache\CacheStore;
use WPP\Cache\RuntimeSettings;
use WPP\Settings\SettingsService;

/**
 * Serving correctness: what the drop-in is allowed to hand back, and what the
 * writer must leave on disk for it.
 */
return static function (): void {
    $store    = new CacheStore();
    $settings = new SettingsService();
    $file     = WPP_CACHE_DIR . 'example.test/serving/index.html';

    // ---- a stale .gz must never outlive the html it mirrors ----
    $store->save($file, '<html>v1</html>', true);
    wpp_ok(is_file($file), 'html written');
    wpp_ok(is_file($file . '.gz'), 'gzip sibling written when gzip is on');
    wpp_same('<html>v1</html>', gzdecode((string) file_get_contents($file . '.gz')), 'gz holds the same content');

    // Re-saving with gzip off must remove the sibling, or the drop-in would keep
    // serving the old compressed copy in preference to the fresh html.
    $store->save($file, '<html>v2</html>', false);
    wpp_same('<html>v2</html>', (string) file_get_contents($file), 'html updated');
    wpp_ok(! is_file($file . '.gz'), 'stale gzip sibling removed when gzip is off');

    // Turning gzip back on recreates it with the current content.
    $store->save($file, '<html>v3</html>', true);
    wpp_same('<html>v3</html>', gzdecode((string) file_get_contents($file . '.gz')), 'gz regenerated with fresh content');

    // ---- keep_assets must not orphan locally hosted fonts ----
    @mkdir(WPP_CACHE_DIR . 'fonts', 0777, true);
    file_put_contents(WPP_CACHE_DIR . 'fonts/abc.woff2', 'FONT');
    file_put_contents(WPP_CACHE_DIR . 'fonts/abc.json', '{"fonts":[]}');
    file_put_contents(WPP_CACHE_DIR . 'abc.css', '@font-face{src:url(fonts/abc.woff2)}');
    file_put_contents(WPP_CACHE_DIR . 'example.test/serving/index.html', '<html>x</html>');

    $store->clear(true);
    wpp_ok(is_file(WPP_CACHE_DIR . 'abc.css'), 'keep_assets keeps generated css');
    wpp_ok(is_file(WPP_CACHE_DIR . 'fonts/abc.woff2'), 'keep_assets keeps hosted fonts the css points at');
    wpp_ok(! is_file(WPP_CACHE_DIR . 'example.test/serving/index.html'), 'cached pages are still cleared');

    // A full clear removes pages but keeps the drop-in config and log.
    file_put_contents(WPP_CACHE_DIR . 'example.test.json', '{"enabled":true}');
    file_put_contents(WPP_CACHE_DIR . 'wpp.log', 'entry');
    $store->save($file, '<html>y</html>', false);
    $store->clear(false);
    wpp_ok(! is_file($file), 'clear removes cached pages');
    wpp_ok(is_file(WPP_CACHE_DIR . 'example.test.json'), 'clear keeps the drop-in runtime config');
    wpp_ok(is_file(WPP_CACHE_DIR . 'wpp.log'), 'clear keeps the log');

    // Suites share the cache directory, so do not leave a log behind for the
    // logger suite to find.
    @unlink(WPP_CACHE_DIR . 'wpp.log');

    // ---- everything the drop-in reads must actually be written ----
    $settings->update('cache', [
        'enabled'             => true,
        'mobile'              => true,
        'exclude_search_bots' => true,
        'cache_query_strings' => false,
        'exclude_urls'        => ['/cart'],
        'exclude_user_agents' => ['BadBot'],
        'clear_time'          => 2,
        'clear_unit'          => 3600,
    ]);

    $runtime = new RuntimeSettings($settings);
    $runtime->write();
    $raw  = (string) file_get_contents($runtime->file());
    wpp_contains('<?php exit;', $raw, 'runtime file is guarded against direct HTTP access');
    $data = json_decode(WPP\Cache\RuntimeSettings::payload($raw), true);

    wpp_ok(is_array($data), 'runtime file is valid json');
    wpp_same(true, $data['enabled'], 'enabled written');
    wpp_same(true, $data['mobile'], 'mobile written');
    wpp_same(7200, $data['expire'], 'expiry written as seconds');
    wpp_same(['/cart'], $data['exclude'], 'url exclusions written');
    wpp_same(['BadBot'], $data['user_agents'], 'user agent exclusions written');
    wpp_same(false, $data['cache_query_strings'], 'query string flag written');

    // Regression: the drop-in had no way to know about this one, so crawlers
    // were still served cached HTML with the option switched on.
    wpp_same(true, $data['search_bots'], 'search bot exclusion reaches the drop-in');

    // The drop-in reads these exact keys.
    foreach (['version', 'enabled', 'disabled', 'mobile', 'expire', 'permalinks', 'exclude', 'user_agents', 'cache_query_strings', 'search_bots'] as $key) {
        wpp_ok(array_key_exists($key, $data), "runtime file exposes {$key}");
    }

    // A file written by an older release must be rewritten, or the drop-in
    // keeps reading a payload that is missing keys it now depends on.
    wpp_ok($runtime->written(), 'a freshly written runtime file is current');
    $stale = json_decode(WPP\Cache\RuntimeSettings::payload((string) file_get_contents($runtime->file())), true);
    $stale['version'] = '1.0.0';
    file_put_contents($runtime->file(), WPP\Cache\RuntimeSettings::GUARD . (string) json_encode($stale));
    wpp_ok(! $runtime->written(), 'a runtime file from an older version counts as needing a rewrite');
    $runtime->write();
    wpp_ok($runtime->written(), 'rewriting brings it up to date');

    // ---- mobile and desktop are distinct entries ----
    $mobile  = CacheStore::fileFor('example.test', '/x/', true, true);
    $desktop = CacheStore::fileFor('example.test', '/x/', true, false);
    wpp_ok($mobile !== $desktop, 'mobile and desktop resolve to different files');
    wpp_contains('-mobile', $mobile, 'mobile variant is suffixed');
    wpp_not_contains('-mobile', $desktop, 'desktop variant is not suffixed');
};
