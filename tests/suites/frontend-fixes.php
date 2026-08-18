<?php

declare(strict_types=1);

use WPP\Cache\CacheInvalidation;
use WPP\Cache\CacheStore;
use WPP\Cache\FragmentCache;
use WPP\Cache\FragmentStore;
use WPP\Cache\RuntimeSettings;
use WPP\Frontend\PageCache;
use WPP\Settings\SettingsService;

if (! function_exists('is_embed')) {
    function is_embed(): bool
    {
        return false;
    }
}

if (! function_exists('get_the_ID')) {
    function get_the_ID()
    {
        return $GLOBALS['wpp_test_post_id'] ?? false;
    }
}

if (! function_exists('wp_enqueue_script')) {
    function wp_enqueue_script($handle, ...$rest): void
    {
        if (isset($GLOBALS['wp_scripts']) && ! in_array($handle, $GLOBALS['wp_scripts']->queue, true)) {
            $GLOBALS['wp_scripts']->queue[] = $handle;
        }
    }
}

if (! function_exists('wp_enqueue_style')) {
    function wp_enqueue_style($handle, ...$rest): void
    {
        if (isset($GLOBALS['wp_styles']) && ! in_array($handle, $GLOBALS['wp_styles']->queue, true)) {
            $GLOBALS['wp_styles']->queue[] = $handle;
        }
    }
}

/**
 * Regressions for the frontend review: what has to be true so an enabled option
 * actually reaches the page, and so a cached fragment belongs to the request
 * that asked for it.
 */
return static function (): void {
    // -------------------------------------------------- buffer gate

    // Writes group options directly so each case starts from the defaults.
    $buffers = static function (array $groups): bool {
        foreach (['cache', 'css', 'js', 'html', 'media', 'cdn'] as $group) {
            update_option('wpp_' . $group, $groups[$group] ?? []);
        }

        $page = new PageCache(new SettingsService(), new CacheStore());
        $base = ob_get_level();
        $page->startBuffer();
        $started = ob_get_level() > $base;
        if ($started) {
            ob_end_clean();
        }

        return $started;
    };

    wpp_ok(! $buffers([]), 'nothing enabled: no buffer');
    wpp_ok($buffers(['css' => ['defer' => true]]), 'css defer still buffers');

    // Every one of these is consumed by AssetParser, which only ever runs
    // through the filter this buffer applies.
    wpp_ok($buffers(['js' => ['delay' => true]]), 'js delay buffers');
    wpp_ok($buffers(['css' => ['remove_unused' => true]]), 'remove unused css buffers');
    wpp_ok($buffers(['css' => ['combine_fonts' => true]]), 'combine google fonts buffers');
    wpp_ok($buffers(['css' => ['host_fonts' => true]]), 'host google fonts buffers');
    wpp_ok($buffers(['css' => ['font_display' => 'swap']]), 'font-display buffers');
    wpp_ok(! $buffers(['css' => ['font_display' => 'none']]), 'font-display none does not buffer');
    wpp_ok($buffers(['css' => ['dns_prefetch' => ['https://fonts.gstatic.com']]]), 'dns-prefetch buffers');
    wpp_ok($buffers(['css' => ['preconnect' => ['https://fonts.gstatic.com']]]), 'preconnect buffers');
    wpp_ok($buffers(['css' => ['disable' => ['theme/style.css' => true]]]), 'css per-file disable buffers');
    wpp_ok($buffers(['js' => ['disable' => ['theme/app.js' => true]]]), 'js per-file disable buffers');
    wpp_ok(! $buffers(['js' => ['disable' => ['theme/app.js' => false]]]), 'an all-off disable map does not buffer');
    wpp_ok($buffers(['media' => ['images_dimensions' => true]]), 'image dimensions buffers');
    wpp_ok($buffers(['media' => ['webp' => true]]), 'webp buffers');
    wpp_ok($buffers(['media' => ['lcp_images' => 2]]), 'lcp preload buffers');
    wpp_ok(! $buffers(['media' => ['lcp_images' => 0]]), 'lcp preload of 0 does not buffer');

    // -------------------------------------------------- kill switch

    $applies = static function (): bool {
        $page   = new PageCache(new SettingsService(), new CacheStore());
        $method = new ReflectionMethod($page, 'cacheApplies');
        $method->setAccessible(true);

        return (bool) $method->invoke($page);
    };

    $_SERVER['REQUEST_URI'] = '/';
    $_COOKIE = [];
    update_option('wpp_cache', ['enabled' => true]);

    wpp_ok($applies(), 'caching applies on a plain anonymous request');
    update_option('wpp_disabled', true);
    wpp_ok(! $applies(), 'wpp_disabled stops the write path, not just the drop-in');
    delete_option('wpp_disabled');

    // -------------------------------------------------- invalidation

    $settings  = new SettingsService();
    $store     = new CacheStore();
    $fragments = new FragmentStore();
    update_option('wpp_cache', ['enabled' => true, 'clear_on_save' => true, 'clear_on_publish' => true]);
    (new CacheInvalidation($settings, $store, new RuntimeSettings($settings), $fragments))->register();

    $page = CacheStore::fileFor('example.test', '/hello/', true, false);

    $store->save($page, str_repeat('x', 300), false);
    do_action('wpp.settings.updated', 'css', []);
    wpp_ok(! is_file($page), 'saving css settings clears the page cache');

    $store->save($page, str_repeat('x', 300), false);
    do_action('wpp.settings.updated', 'media', []);
    wpp_ok(! is_file($page), 'saving media settings clears the page cache');

    $store->save($page, str_repeat('x', 300), false);
    do_action('wpp.settings.updated', 'database', []);
    wpp_ok(is_file($page), 'a group that cannot change rendered output leaves the cache alone');

    $store->save($page, str_repeat('x', 300), false);
    do_action('comment_post', 7, 0);
    wpp_ok(is_file($page), 'a comment held for moderation does not clear the cache');

    do_action('comment_post', 7, 1);
    wpp_ok(! is_file($page), 'an approved comment clears the cache');

    $store->save($page, str_repeat('x', 300), false);
    do_action('transition_comment_status', 'approved', 'unapproved', null);
    wpp_ok(! is_file($page), 'approving a held comment clears the cache');

    $store->save($page, str_repeat('x', 300), false);
    do_action('trashed_comment', 7);
    wpp_ok(! is_file($page), 'trashing a comment clears the cache');

    $store->clear(false);

    // -------------------------------------------------- fragment identity

    update_option('wpp_cache', ['block_cache_enabled' => true, 'block_cache_ttl' => 3600]);
    $fc = new FragmentCache(new SettingsService(), new FragmentStore());
    $_SERVER['REQUEST_URI'] = '/blog/';

    $block = ['blockName' => 'wpp/cache', 'attrs' => ['ttl' => 60], 'innerHTML' => '<p>loop</p>'];

    $GLOBALS['wpp_test_post_id'] = 11;
    wpp_same(null, $fc->preRenderBlock(null, $block), 'loop item 1 misses');
    $fc->renderBlock('POST-A', $block);

    $GLOBALS['wpp_test_post_id'] = 12;
    wpp_same(null, $fc->preRenderBlock(null, $block), 'loop item 2 does not replay item 1');
    $fc->renderBlock('POST-B', $block);

    $GLOBALS['wpp_test_post_id'] = 11;
    wpp_same('POST-A', $fc->preRenderBlock(null, $block), 'loop item 1 gets its own entry back');
    $GLOBALS['wpp_test_post_id'] = 12;
    wpp_same('POST-B', $fc->preRenderBlock(null, $block), 'loop item 2 gets its own entry back');

    unset($GLOBALS['wpp_test_post_id']);

    // A logged-in render must never populate the entry anonymous visitors read.
    $adminBlock = ['blockName' => 'wpp/cache', 'attrs' => ['ttl' => 60], 'innerHTML' => '<p>greeting</p>'];
    WPP_Test_State::$loggedIn = true;
    WPP_Test_State::$role = 'administrator';
    wpp_same(null, $fc->preRenderBlock(null, $adminBlock), 'admin render misses');
    $fc->renderBlock('HELLO ADMIN', $adminBlock);
    wpp_same('HELLO ADMIN', $fc->preRenderBlock(null, $adminBlock), 'admin gets the admin render back');

    WPP_Test_State::$loggedIn = false;
    wpp_same(null, $fc->preRenderBlock(null, $adminBlock), 'anonymous visitor does not get the admin render');

    wpp_contains('l=0', $fc->identity('x', []), 'login state is always part of the identity');
    WPP_Test_State::$loggedIn = true;
    wpp_contains('r=administrator', $fc->identity('x', []), 'a logged-in identity carries the role');
    WPP_Test_State::$loggedIn = false;

    // Tracking params must not mint an entry each.
    $_SERVER['REQUEST_URI'] = '/p/?utm_source=a';
    $clean = $fc->identity('x', []);
    $_SERVER['REQUEST_URI'] = '/p/?utm_source=b&fbclid=zz';
    wpp_same($clean, $fc->identity('x', []), 'tracking params collapse to one identity');
    $_SERVER['REQUEST_URI'] = '/p/';
    wpp_same($clean, $fc->identity('x', []), 'a tracked URL shares the clean URL identity');
    $_SERVER['REQUEST_URI'] = '/p/?id=2';
    wpp_ok($clean !== $fc->identity('x', []), 'a real query param still varies the identity');
    $_SERVER['REQUEST_URI'] = '/blog/';

    // -------------------------------------------------- fragment assets

    $GLOBALS['wp_scripts'] = (object) ['queue' => ['jquery']];
    $GLOBALS['wp_styles']  = (object) ['queue' => ['theme']];

    $navBlock = ['blockName' => 'wpp/cache', 'attrs' => ['ttl' => 60], 'innerHTML' => '<nav></nav>'];
    wpp_same(null, $fc->preRenderBlock(null, $navBlock), 'nav block misses');

    // What WP_Block::render() would have enqueued while rendering the subtree.
    $GLOBALS['wp_scripts']->queue[] = 'wp-block-navigation-view';
    $GLOBALS['wp_styles']->queue[]  = 'wp-block-navigation';
    $fc->renderBlock('<nav>menu</nav>', $navBlock);

    $GLOBALS['wp_scripts'] = (object) ['queue' => ['jquery']];
    $GLOBALS['wp_styles']  = (object) ['queue' => ['theme']];

    wpp_same('<nav>menu</nav>', $fc->preRenderBlock(null, $navBlock), 'nav block hit returns clean html');
    wpp_ok(
        in_array('wp-block-navigation-view', $GLOBALS['wp_scripts']->queue, true),
        'a hit re-enqueues the view script the cached markup needs'
    );
    wpp_ok(
        in_array('wp-block-navigation', $GLOBALS['wp_styles']->queue, true),
        'a hit re-enqueues the block style'
    );
    wpp_same(2, count($GLOBALS['wp_scripts']->queue), 'a hit enqueues only what the block added');

    unset($GLOBALS['wp_scripts'], $GLOBALS['wp_styles']);

    // -------------------------------------------------- buffer ownership

    $base = ob_get_level();
    ob_start();
    $outer = ob_get_level();

    wpp_ok($fc->start('guard', ['ttl' => 60]), 'guard fragment misses');
    echo 'PARTIAL';
    ob_end_flush();

    $fc->end();
    $levelAfterEnd = ob_get_level();
    $leaked = (string) ob_get_clean();

    wpp_same($outer, $levelAfterEnd, 'end() leaves a foreign buffer alone');
    wpp_same('PARTIAL', $leaked, 'the surrounding buffer keeps its own content');
    wpp_same(null, (new FragmentStore())->get($fc->identity('guard', [])), 'nothing is stored when the buffer was stolen');

    while (ob_get_level() > $base) {
        ob_end_clean();
    }

    // -------------------------------------------------- fragment store limits

    $store = new FragmentStore();

    $store->set('atomic', 'A', 60);
    $gen  = (int) get_option('wpp_fragment_gen', 0);
    $key  = md5($gen . '|atomic');
    $file = WPP_CACHE_DIR . 'fragments/' . substr($key, 0, 2) . '/' . $key . '.html';
    clearstatcache();
    $first = fileinode($file);
    $store->set('atomic', 'BBBBBBBB', 60);
    clearstatcache();
    wpp_ok($first !== fileinode($file), 'a rewrite swaps a complete file in, never truncates in place');
    wpp_same('BBBBBBBB', $store->get('atomic'), 'the swapped in value reads back');
    wpp_same([], glob(dirname($file) . '/*.tmp'), 'no temp file is left behind');

    $max      = (int) (new ReflectionClass(FragmentStore::class))->getConstant('MAX_PER_SHARD');
    $fullKey  = md5($gen . '|shard-probe');
    $fullDir  = WPP_CACHE_DIR . 'fragments/' . substr($fullKey, 0, 2);
    wp_mkdir_p($fullDir);
    for ($i = 0; $i < $max; $i++) {
        file_put_contents($fullDir . '/filler' . $i . '.html', "0\nX");
    }

    $store->set('shard-probe', 'OVERFLOW', 60);
    wpp_same(null, $store->get('shard-probe'), 'a full shard refuses new fragments');

    foreach ((array) glob($fullDir . '/filler*.html') as $filler) {
        file_put_contents((string) $filler, (time() - 10) . "\nX");
    }
    $store->set('shard-probe', 'ROOM', 60);
    wpp_same('ROOM', $store->get('shard-probe'), 'expired entries are swept so the shard accepts writes again');
};
