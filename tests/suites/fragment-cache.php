<?php

declare(strict_types=1);

use WPP\Cache\FragmentCache;
use WPP\Cache\FragmentStore;
use WPP\Settings\SettingsService;

return static function (): void {
    $settings = new SettingsService();
    $settings->update('cache', ['block_cache_enabled' => true, 'block_cache_ttl' => 3600]);

    // ---- store: disk backend ----
    $store = new FragmentStore();
    wpp_ok(! $store->usesObjectCache(), 'store starts on the disk backend');

    $store->set('id1', 'HELLO', 60);
    wpp_same('HELLO', $store->get('id1'), 'disk: value round trips');
    wpp_same(null, $store->get('nope'), 'disk: miss returns null');

    $store->set('id2', 'FOREVER', 0);
    wpp_same('FOREVER', $store->get('id2'), 'disk: ttl 0 stores without expiry');

    // Expiry is honored.
    $store->set('id3', 'TEMP', 100);
    $gen = (int) get_option('wpp_fragment_gen', 0);
    $key = md5($gen . '|id3');
    $file = WPP_CACHE_DIR . 'fragments/' . substr($key, 0, 2) . '/' . $key . '.html';
    wpp_ok(is_file($file), 'disk: fragment file written');
    file_put_contents($file, (time() - 10) . "\nTEMP");
    wpp_same(null, $store->get('id3'), 'disk: expired fragment is a miss');
    wpp_ok(! is_file($file), 'disk: expired fragment file removed');

    // Generation flush invalidates everything.
    $store->set('id4', 'KEEP', 60);
    $store->flush();
    wpp_same(null, $store->get('id4'), 'flush invalidates existing fragments');

    // ---- store: object cache backend ----
    WPP_Test_State::$extObjectCache = true;
    $oc = new FragmentStore();
    wpp_ok($oc->usesObjectCache(), 'store detects an external object cache');
    $oc->set('oid', 'OC', 60);
    wpp_same('OC', $oc->get('oid'), 'object cache: value round trips');
    $oc->flush();
    wpp_same(null, $oc->get('oid'), 'object cache: flush invalidates');
    WPP_Test_State::$extObjectCache = false;

    // ---- engine ----
    $fc = new FragmentCache($settings, new FragmentStore());
    wpp_ok($fc->enabled(), 'engine enabled when the setting is on');

    $_SERVER['REQUEST_URI'] = '/page-a';
    wpp_contains('u=/page-a', $fc->identity('x', []), 'identity varies by URL by default');
    wpp_not_contains('u=', $fc->identity('x', ['vary_url' => false]), 'vary_url false drops the URL');
    wpp_contains('l=', $fc->identity('x', ['vary_loggedin' => true]), 'vary_loggedin adds login state');
    wpp_contains('d=', $fc->identity('x', ['vary_device' => true]), 'vary_device adds device');
    wpp_contains('r=', $fc->identity('x', ['vary_role' => true]), 'vary_role adds role');

    // Miss renders and stores; hit replays and skips rendering.
    ob_start();
    $first = $fc->start('blk', ['ttl' => 60]);
    echo 'RENDERED';
    $fc->end();
    $out1 = ob_get_clean();
    wpp_ok($first, 'first call is a miss');
    wpp_same('RENDERED', $out1, 'miss emits the rendered output');

    ob_start();
    $second = $fc->start('blk', ['ttl' => 60]);
    if ($second) {
        echo 'SHOULD_NOT_RENDER';
        $fc->end();
    }
    $out2 = ob_get_clean();
    wpp_ok(! $second, 'second call is a hit');
    wpp_same('RENDERED', $out2, 'hit replays the cached output');

    // A different URL is a different fragment.
    $_SERVER['REQUEST_URI'] = '/page-b';
    ob_start();
    $third = $fc->start('blk', ['ttl' => 60]);
    if ($third) {
        echo 'PAGEB';
        $fc->end();
    }
    $out3 = ob_get_clean();
    wpp_ok($third, 'different URL misses');
    wpp_same('PAGEB', $out3, 'different URL renders its own content');
    $_SERVER['REQUEST_URI'] = '/page-a';

    // wrap(): callback runs once across two calls.
    $calls = 0;
    $cb = static function () use (&$calls): void {
        $calls++;
        echo 'WRAPPED';
    };
    wpp_same('WRAPPED', $fc->wrap('wkey', ['ttl' => 60], $cb), 'wrap returns output');
    wpp_same('WRAPPED', $fc->wrap('wkey', ['ttl' => 60], $cb), 'wrap returns cached output');
    wpp_same(1, $calls, 'wrap callback runs only once');

    // Nesting: an inner fragment inside an outer one.
    ob_start();
    if ($fc->start('outer', ['ttl' => 60])) {
        echo '[outer';
        if ($fc->start('inner', ['ttl' => 60])) {
            echo '[inner]';
            $fc->end();
        }
        echo ']';
        $fc->end();
    }
    $nested = ob_get_clean();
    wpp_same('[outer[inner]]', $nested, 'nested fragments compose correctly');

    // Block filters: hit short-circuits, miss stores.
    $block = ['blockName' => 'wpp/cache', 'attrs' => ['ttl' => 60], 'innerHTML' => '<p>hi</p>'];
    wpp_same(null, $fc->preRenderBlock(null, ['blockName' => 'core/paragraph']), 'other blocks are ignored');
    wpp_same(null, $fc->preRenderBlock(null, $block), 'block miss lets WordPress render');
    wpp_same('<p>R</p>', $fc->renderBlock('<p>R</p>', $block), 'renderBlock returns content');
    wpp_same('<p>R</p>', $fc->preRenderBlock(null, $block), 'block hit short-circuits rendering');

    $changed = $block;
    $changed['innerHTML'] = '<p>edited</p>';
    wpp_same(null, $fc->preRenderBlock(null, $changed), 'editing block content busts its cache');

    // Disabled: nothing is cached and rendering is untouched.
    $settings->update('cache', ['block_cache_enabled' => false]);
    $off = new FragmentCache($settings, new FragmentStore());
    ob_start();
    $r = $off->start('zz');
    echo 'Z';
    $off->end();
    $outOff = ob_get_clean();
    wpp_ok($r, 'disabled engine always reports a miss');
    wpp_same('Z', $outOff, 'disabled engine still emits output');
    wpp_same(null, (new FragmentStore())->get($off->identity('zz', [])), 'disabled engine stores nothing');
};
