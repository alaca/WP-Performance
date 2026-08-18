<?php

declare(strict_types=1);

use WPP\Cache\CacheStore;

/**
 * The load-bearing invariant of the whole page cache: CacheStore (the writer,
 * running inside WordPress) and advanced-cache.php (the reader, running before
 * WordPress) must derive the same path for the same request. Any divergence
 * means the cache silently never hits, or serves the wrong page.
 */
return static function (): void {
    // Defining functions only: force the drop-in's main body to return early.
    $_SERVER['REQUEST_METHOD'] = 'POST';
    require_once WPP_TEST_ROOT . '/advanced-cache.php';

    wpp_ok(function_exists('wpp_dropin_file'), 'drop-in exposes wpp_dropin_file');
    wpp_ok(function_exists('wpp_dropin_stripped_query'), 'drop-in exposes wpp_dropin_stripped_query');

    $dir = WPP_CACHE_DIR;
    $host = 'example.test';

    $cases = [
        ['/', true, false, 'root, permalinks'],
        ['/', false, false, 'root, no permalinks'],
        ['/about/', true, false, 'simple path'],
        ['/about', true, false, 'path without trailing slash'],
        ['/a/b/c/', true, false, 'nested path'],
        ['/about/', true, true, 'mobile variant'],
        ['/about/', false, true, 'mobile without permalinks'],
        ['/shop/?utm_source=x&utm_medium=y', true, false, 'tracking params only'],
        ['/shop/?utm_source=x', false, false, 'tracking params, no permalinks'],
        ['/shop/?page=2', true, false, 'real query string'],
        ['/shop/?page=2&color=red', true, false, 'multi-param query'],
        ['/shop/?color=red&page=2', true, false, 'query param order'],
        ['/shop/?page=2&utm_source=x', true, false, 'mixed real and tracking'],
        ['/p/?a=1', false, false, 'query, no permalinks'],
        ['/unicode-café/', true, false, 'non-ascii path'],
        ['/double//slash/', true, false, 'double slash'],
        ['/tráv/../ersal/', true, false, 'traversal-ish path'],
    ];

    foreach ($cases as [$uri, $permalinks, $mobile, $label]) {
        $writer = CacheStore::fileFor($host, $uri, $permalinks, $mobile);
        $reader = wpp_dropin_file($dir, $host, $uri, $permalinks, $mobile);
        wpp_same($reader, $writer, "path parity: {$label} ({$uri})");
    }

    // Query normalization parity.
    foreach (['', 'utm_source=a', 'page=2', 'b=2&a=1', 'a=1&utm_medium=x', 'fbclid=z&q=1'] as $q) {
        wpp_same(
            wpp_dropin_stripped_query($q),
            CacheStore::strippedQuery($q),
            "query normalization parity: '{$q}'"
        );
    }

    // Tracking-only query strings must normalize away entirely, so a link with
    // utm tags shares the cache entry of the clean URL.
    wpp_same('', CacheStore::strippedQuery('utm_source=nl&utm_campaign=x'), 'tracking-only query normalizes to empty');
    wpp_ok(! CacheStore::hasRealQuery('utm_source=nl'), 'tracking-only query is not a real query');
    wpp_ok(CacheStore::hasRealQuery('page=2'), 'real query detected');
    wpp_same(
        CacheStore::fileFor($host, '/x/', true, false),
        CacheStore::fileFor($host, '/x/?utm_source=nl', true, false),
        'utm-tagged URL maps to the clean cache entry'
    );

    // Host sanitization: port stripped, path separators removed. A malicious
    // Host header must not escape the cache directory.
    wpp_same('example.test', CacheStore::sanitizeHost('example.test:10092'), 'port stripped from host');
    wpp_same('example.test', CacheStore::sanitizeHost('example.test'), 'plain host unchanged');

    foreach (['../../etc', 'a/../../b', 'evil.com/../../', "ex\0ample", 'ex ample'] as $evil) {
        $clean = CacheStore::sanitizeHost($evil);
        wpp_not_contains('/', $clean, "sanitizeHost strips separators: {$evil}");
        wpp_not_contains("\0", $clean, "sanitizeHost strips null byte: {$evil}");
    }

    // Paths must stay inside the cache directory even for hostile input.
    foreach (['/../../../etc/passwd', '/..%2f..%2fetc', "/a\0b/", '/' . str_repeat('../', 10) . 'x'] as $uri) {
        $file = CacheStore::fileFor($host, $uri, true, false);
        $real = str_replace('\\', '/', $file);
        wpp_not_contains('/../', $real, "no traversal segment for {$uri}");
        wpp_ok(str_starts_with($real, rtrim(WPP_CACHE_DIR, '/')), "path stays under cache dir for {$uri}");
    }

    // Mobile and desktop never collide.
    wpp_ok(
        CacheStore::fileFor($host, '/x/', true, true) !== CacheStore::fileFor($host, '/x/', true, false),
        'mobile and desktop paths differ'
    );

    // Different hosts never collide (multisite / multi-domain safety).
    wpp_ok(
        CacheStore::fileFor('a.test', '/x/', true, false) !== CacheStore::fileFor('b.test', '/x/', true, false),
        'different hosts get different paths'
    );

    // The drop-in refuses to serve anything without its runtime file, so the
    // plugin must be able to notice it is missing and write it again.
    $runtime = new WPP\Cache\RuntimeSettings(new WPP\Settings\SettingsService());
    @unlink($runtime->file());
    wpp_ok(! $runtime->written(), 'a missing runtime file is detected');
    $runtime->write();
    wpp_ok($runtime->written(), 'runtime file is rewritten');

    $data = json_decode((string) file_get_contents($runtime->file()), true);
    wpp_ok(is_array($data), 'runtime file is valid json');
    foreach (['enabled', 'disabled', 'mobile', 'expire', 'permalinks', 'exclude', 'user_agents', 'cache_query_strings'] as $key) {
        wpp_ok(array_key_exists($key, $data), "runtime file exposes {$key} to the drop-in");
    }
    wpp_contains(rtrim(WPP_CACHE_DIR, '/'), $runtime->file(), 'runtime file lives in the cache directory');
};
