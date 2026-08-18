<?php

declare(strict_types=1);

use WPP\Cache\CacheStore;
use WPP\Optimize\Minifier;
use WPP\Rest\Admin\ToolsController;
use WPP\Settings\Presets;
use WPP\Support\Logger;
use WPP\Settings\SettingsHistory;
use WPP\Settings\SettingsService;

/**
 * The fixes for the restore/import and multisite-clear findings added new
 * entry points. These pin the callers to them: without this the new code is
 * unreachable and the findings are only half fixed.
 */
return static function (): void {
    $settings = new SettingsService();

    // ---- restore and import must be able to REMOVE a per-file map entry ----
    $history = new SettingsHistory($settings);
    $history->clear();

    $settings->update('css', ['combine' => ['/a.css' => true]]);

    // A fresh instance captures once per request, as the real hook does.
    $history = new SettingsHistory($settings);
    $history->register();

    $settings->update('css', ['combine' => ['/a.css' => true, '/b.css' => true]]);
    wpp_same(2, count($settings->get('css')['combine']), 'a second entry was added');

    $snaps = $history->all();
    wpp_ok($snaps !== [], 'the prior state was captured as a restore point');
    wpp_same(
        ['/a.css' => true],
        $snaps[0]['groups']['css']['combine'] ?? null,
        'the snapshot holds the state before the second entry'
    );

    wpp_ok($history->restore(0), 'restore reports success');
    wpp_same(
        ['/a.css' => true],
        $settings->get('css')['combine'],
        'restore removes the entry added after the snapshot'
    );

    // ---- importing a configuration must clear rules the file does not carry ----
    $settings->update('css', ['combine' => ['/a.css' => true, '/b.css' => true]]);

    $tools = new ToolsController(
        $settings,
        new Presets(),
        new SettingsHistory($settings),
        new Logger($settings)
    );
    $tools->import(new WP_REST_Request(['css' => ['combine' => ['/a.css' => true]]]));

    wpp_same(
        ['/a.css' => true],
        $settings->get('css')['combine'],
        'import drops a rule the imported file does not carry'
    );

    // ---- a non-200 body must never be baked into a bundle ----
    // Assets::contents() only resolves local files, so an off-site or missing
    // URL falls through to the loopback fetch, where a 404 still has a body.
    $missing = 'https://cdn.example.test/gone.css';
    WPP_Test_State::$httpResponses[$missing] = [
        'response' => ['code' => 404],
        'body'     => '<!doctype html><html><body>Not found</body></html>',
        'headers'  => [],
    ];

    $minifier = new Minifier();
    wpp_same('', $minifier->cssForCombine($missing), 'a 404 body is not returned as stylesheet source');
    wpp_not_contains('doctype', $minifier->cssForCombine($missing), 'the error page never reaches the bundle');

    WPP_Test_State::$httpResponses['https://cdn.example.test/ok.css'] = [
        'response' => ['code' => 200],
        'body'     => '.ok{color:red}',
        'headers'  => [],
    ];
    wpp_contains('.ok', $minifier->cssForCombine('https://cdn.example.test/ok.css'), 'a 200 body is still used');

    // ---- a network flush has to reach every blog ----
    $store = new CacheStore();
    WPP_Test_State::$multisite = true;

    foreach (['example.test/about/', 'other.test/about/'] as $rel) {
        $dir = WPP_CACHE_DIR . $rel;
        wp_mkdir_p($dir);
        file_put_contents($dir . 'index.html', 'page');
    }

    $store->clear(false, true);
    wpp_ok(! is_file(WPP_CACHE_DIR . 'other.test/about/index.html'), 'an explicit network flush reaches another blog');

    WPP_Test_State::$multisite = false;
};
