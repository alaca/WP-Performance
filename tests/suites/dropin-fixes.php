<?php

declare(strict_types=1);

use WPP\Cache\CacheStore;
use WPP\Cache\RuntimeSettings;
use WPP\Settings\SettingsService;

/**
 * Regressions for the drop-in and the store that feeds it. The reader runs
 * before WordPress and the writer runs inside it, so every fix here has to hold
 * on both sides at once.
 */
return static function (): void {
    // Defining functions only: force the drop-in's main body to return early.
    $_SERVER['REQUEST_METHOD'] = 'POST';
    require_once WPP_TEST_ROOT . '/advanced-cache.php';

    $dir      = WPP_CACHE_DIR;
    $store    = new CacheStore();
    $runtime  = new RuntimeSettings(new SettingsService());

    // ---- host normalization: reader and writer must agree byte-for-byte ----
    // A Host header the client controls decided whether the cache worked at all.
    $hosts = ['example.test', 'EXAMPLE.test', 'Example.Test:8080', 'example.test:80x', 'ex ample.test', '../etc', ''];
    foreach ($hosts as $raw) {
        wpp_same(CacheStore::sanitizeHost($raw), wpp_dropin_host($raw), "host parity: '{$raw}'");
        wpp_same(
            CacheStore::fileFor($raw, '/about/', true, false),
            wpp_dropin_file($dir, wpp_dropin_host($raw), '/about/', true, false),
            "cache path parity from a raw Host header: '{$raw}'"
        );
    }
    wpp_same('example.test', CacheStore::sanitizeHost('EXAMPLE.test'), 'a mixed-case host resolves to the same directory');
    wpp_same('example.test', CacheStore::sanitizeHost('example.test:80x'), 'a malformed port is dropped, not glued on');

    // ---- one runtime config per site, not per host ----
    // Sites in a subdirectory network share a host and would otherwise overwrite
    // each other's cache settings.
    file_put_contents($dir . 'example.test.json.php', '{"site":"main"}');
    file_put_contents($dir . 'example.test~shop.json.php', '{"site":"shop"}');

    wpp_same($dir . 'example.test.json.php', wpp_dropin_conf($dir, 'example.test', '/'), 'the front page uses the main site config');
    wpp_same($dir . 'example.test.json.php', wpp_dropin_conf($dir, 'example.test', '/about/'), 'a main-site path uses the main site config');
    wpp_same($dir . 'example.test~shop.json.php', wpp_dropin_conf($dir, 'example.test', '/shop/'), 'a subsite uses its own config');
    wpp_same($dir . 'example.test~shop.json.php', wpp_dropin_conf($dir, 'example.test', '/shop/cart/?x=1'), 'a path inside a subsite uses the subsite config');
    wpp_same($dir . 'example.test.json.php', wpp_dropin_conf($dir, 'example.test', '/shopping/'), 'a path that only shares a prefix is not the subsite');
    wpp_same('', wpp_dropin_conf($dir, 'other.test', '/'), 'an unknown host resolves to no config');

    wpp_same('', RuntimeSettings::pathKey('/'), 'the main site keeps the host-only filename');
    wpp_same('~shop', RuntimeSettings::pathKey('/shop/'), 'a subsite is keyed by its path');
    wpp_ok(RuntimeSettings::pathKey('/shop/') !== RuntimeSettings::pathKey('/blog/'), 'two subsites on one host get different files');

    foreach (['/shop/', '/a/b/', '/Shop/'] as $sitePath) {
        $file = $dir . 'example.test' . RuntimeSettings::pathKey($sitePath) . '.json.php';
        file_put_contents($file, '{}');
        wpp_same($file, wpp_dropin_conf($dir, 'example.test', $sitePath . 'page/'), "the reader finds the file the writer named for {$sitePath}");
        @unlink($file);
    }
    wpp_same($dir . 'example.test.json.php', $runtime->file(), 'a single-site install keeps the host-only config path');

    // ---- mobile bucket must match wp_is_mobile(), which picks what gets written ----
    $mobileCases = [
        ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148', null, true, 'iPhone'],
        ['Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Safari/604.1', null, false, 'iPad without a Mobile token is desktop to wp_is_mobile()'],
        ['Mozilla/5.0 (Linux; Android 13; Tablet) AppleWebKit/537.36 Chrome/120 Safari/537.36', '?0', false, 'the client hint wins over the UA string'],
        ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/120 Safari/537.36', '?1', true, 'the client hint alone marks a request mobile'],
        ['Mozilla/5.0 (Linux; Android 13; SM-A536B) Chrome/120 Mobile Safari/537.36', null, true, 'Android phone'],
        ['Mozilla/5.0 (Linux; U; en-us) AppleWebKit/533.16 Silk/3.68 like Chrome', null, true, 'Silk'],
        ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/120 Safari/537.36', null, false, 'desktop Chrome'],
        ['', null, false, 'no user agent'],
    ];
    foreach ($mobileCases as [$ua, $hint, $expected, $label]) {
        wpp_same($expected, wpp_dropin_is_mobile($ua, $hint), "mobile bucket: {$label}");
    }

    // ---- content negotiation ----
    wpp_ok(wpp_dropin_accepts_gzip('gzip, deflate, br'), 'a plain gzip offer is accepted');
    wpp_ok(wpp_dropin_accepts_gzip('GZIP'), 'the token match is case-insensitive');
    wpp_ok(wpp_dropin_accepts_gzip('gzip;q=0.5'), 'a weighted gzip offer is accepted');
    wpp_ok(! wpp_dropin_accepts_gzip('gzip;q=0'), 'q=0 is an explicit refusal, not an offer');
    wpp_ok(! wpp_dropin_accepts_gzip('deflate, gzip;q=0.0'), 'q=0.0 is a refusal too');
    wpp_ok(! wpp_dropin_accepts_gzip('deflate'), 'another encoding is not gzip');
    wpp_ok(! wpp_dropin_accepts_gzip(''), 'a client that says nothing gets the identity body');
    wpp_ok(wpp_dropin_accepts_gzip('*'), 'a wildcard accepts gzip');
    wpp_ok(! wpp_dropin_accepts_gzip('*;q=0'), 'a wildcard refusal is honoured');

    // The body changes with these headers, so a shared proxy needs to key on them.
    wpp_same('Accept-Encoding', wpp_dropin_vary(false), 'cached responses vary on the encoding');
    wpp_same('Accept-Encoding, User-Agent', wpp_dropin_vary(true), 'the mobile variant also varies on the user agent');

    // ---- non-canonical URLs belong to WordPress, not to the cache ----
    wpp_ok(wpp_dropin_canonical('/about/', true), 'the canonical path is served');
    wpp_ok(wpp_dropin_canonical('/', true), 'the front page is canonical');
    wpp_ok(wpp_dropin_canonical('/shop/?page=2', true), 'the query string is not part of the path');
    wpp_ok(! wpp_dropin_canonical('/about', true), 'a missing trailing slash is left for redirect_canonical');
    wpp_ok(! wpp_dropin_canonical('//about/', true), 'a doubled leading slash is not canonical');
    wpp_ok(! wpp_dropin_canonical('/about//', true), 'a doubled trailing slash is not canonical');
    wpp_ok(! wpp_dropin_canonical('/a//b/', true), 'a doubled inner slash is not canonical');
    wpp_ok(! wpp_dropin_canonical('/a/../b/', true), 'a traversal variant is not canonical');
    wpp_ok(wpp_dropin_canonical('/about', false), 'a site without trailing slashes serves the bare path');
    wpp_ok(! wpp_dropin_canonical('/about/', false), 'and hands the slashed variant back to WordPress');

    // ---- runtime payload ----
    update_option('permalink_structure', '/%postname%/');
    update_option('wpp_cache', ['enabled' => true, 'clear_time' => 10, 'clear_unit' => 60]);
    $runtime->write();
    $data = json_decode(WPP\Cache\RuntimeSettings::payload((string) file_get_contents($runtime->file())), true);
    wpp_same(true, $data['trailing_slash'], 'the drop-in learns the site uses trailing slashes');
    wpp_same(600, $data['expire'], 'a configured expiry is written through untouched');

    update_option('permalink_structure', '/%postname%');
    $runtime->write();
    $data = json_decode(WPP\Cache\RuntimeSettings::payload((string) file_get_contents($runtime->file())), true);
    wpp_same(false, $data['trailing_slash'], 'and when it does not');

    // An emptied "Clear cache after" box stores '', and expire 0 means the
    // drop-in never considers a page stale again.
    update_option('wpp_cache', ['enabled' => true, 'clear_time' => '', 'clear_unit' => 3600]);
    $runtime->write();
    $data = json_decode(WPP\Cache\RuntimeSettings::payload((string) file_get_contents($runtime->file())), true);
    wpp_ok($data['expire'] > 0, 'an emptied expiry field cannot switch expiry off');

    // ---- the cache directory is inside the web root ----
    @unlink($dir . 'index.php');
    @unlink($dir . '.htaccess');
    $runtime->write();
    wpp_ok(is_file($dir . 'index.php'), 'writing the runtime config drops a directory index');
    $guard = (string) file_get_contents($dir . '.htaccess');
    wpp_contains('json', $guard, 'the guard covers the runtime config');
    wpp_contains('log', $guard, 'the guard covers the log');
    wpp_contains('denied', $guard, 'the guard actually denies');

    // ---- writes are atomic ----
    // A reader holding the old file must keep seeing a whole page: file_put_contents
    // truncates in place, so the drop-in could readfile() a half-written response.
    $page = $dir . 'example.test/atomic/index.html';
    $store->save($page, str_repeat('a', 4096), false);
    $reader = fopen($page, 'rb');
    $store->save($page, str_repeat('b', 4096), false);
    $seen = (string) stream_get_contents($reader);
    fclose($reader);
    wpp_same(str_repeat('a', 4096), $seen, 'a request already reading the page still gets a complete one');
    wpp_same(str_repeat('b', 4096), (string) file_get_contents($page), 'the new page is published');
    wpp_same([], glob($dir . 'example.test/atomic/*.tmp') ?: [], 'no temporary file is left behind');

    // ---- clearing pages must not take the block cache with it ----
    $store->clear(false);

    $fragment = $dir . 'fragments/ab/abcdef.html';
    @mkdir(dirname($fragment), 0777, true);
    file_put_contents($fragment, "0\n<span>fragment</span>");
    $store->save($dir . 'example.test/kept/index.html', '<html>page</html>', false);

    wpp_same(1, $store->pages(), 'block fragments are not counted as cached pages');
    $stats = $store->stats();
    wpp_same(strlen('<html>page</html>'), $stats['html'], 'block fragments are not counted as cached HTML bytes');

    $store->clear(false);
    wpp_ok(is_file($fragment), 'clearing the page cache leaves block fragments to their own setting');
    wpp_ok(! is_file($dir . 'example.test/kept/index.html'), 'cached pages are still cleared');
    wpp_ok(is_file($dir . '.htaccess'), 'clearing the cache keeps the directory guard');
    wpp_ok(is_file($dir . 'index.php'), 'clearing the cache keeps the directory index');

    // ---- purge must reach every directory it created ----
    // glob() treats [ ] * ? as pattern syntax and never returns dot entries, so
    // those directories survived every clear.
    $bracket = $dir . 'example.test/item[42]/index.html';
    $dotted  = $dir . 'example.test/.well-known/index.html';
    @mkdir(dirname($bracket), 0777, true);
    @mkdir(dirname($dotted), 0777, true);
    file_put_contents($bracket, '<html>bracket</html>');
    file_put_contents($dotted, '<html>dotted</html>');

    $store->clear(false);
    wpp_ok(! is_file($bracket), 'a path with glob metacharacters is purged');
    wpp_ok(! is_file($dotted), 'a dot-prefixed path is purged');

    @unlink($fragment);
    @rmdir($dir . 'fragments/ab');
    @rmdir($dir . 'fragments');
    @unlink($dir . 'example.test~shop.json.php');
};
