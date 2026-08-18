<?php

declare(strict_types=1);

use WPP\Settings\Presets;
use WPP\Settings\SettingsHistory;
use WPP\Settings\SettingsService;
use WPP\Support\Logger;

return static function (): void {
    $settings = new SettingsService();

    // ---------------------------------------------------------- presets
    $presets = new Presets();
    wpp_same(['safe', 'balanced', 'aggressive'], $presets->names(), 'three presets exist');
    wpp_ok($presets->knows('balanced'), 'known preset');
    wpp_ok(! $presets->knows('bogus'), 'unknown preset rejected');
    wpp_ok(! $presets->apply('bogus', $settings), 'applying an unknown preset fails');

    wpp_ok($presets->apply('balanced', $settings), 'balanced applies');
    wpp_same(true, $settings->get('cache')['enabled'], 'balanced enables the page cache');
    wpp_same(true, $settings->get('css')['defer'], 'balanced defers css');
    wpp_same(false, $settings->get('css')['remove_unused'], 'balanced leaves unused-css off');
    wpp_same(false, $settings->get('js')['delay'], 'balanced leaves delay-js off');
    wpp_same(true, $settings->get('media')['webp'], 'balanced enables webp');

    wpp_ok($presets->apply('aggressive', $settings), 'aggressive applies');
    wpp_same(true, $settings->get('css')['remove_unused'], 'aggressive removes unused css');
    wpp_same(true, $settings->get('js')['delay'], 'aggressive delays js');
    wpp_same(true, $settings->get('html')['minify_aggressive'], 'aggressive minifies html hard');

    // Presets must not touch the per-file maps, which are user-curated.
    wpp_same([], $settings->get('css')['minify'], 'presets leave the per-file minify map alone');

    // ---------------------------------------------------------- history
    // Each request captures at most one snapshot of the prior state.
    $freshRequest = static function () use ($settings): SettingsHistory {
        WPP_Test_State::$hooks = [];
        $h = new SettingsHistory($settings);
        $h->register();
        return $h;
    };

    WPP_Test_State::$options['wpp_history'] = [];
    $settings->update('cache', ['enabled' => false]);

    $h = $freshRequest();
    $settings->update('cache', ['enabled' => true]);
    $list = $h->all();
    wpp_same(1, count($list), 'one snapshot after one request');
    wpp_same(false, $list[0]['groups']['cache']['enabled'], 'snapshot holds the PRIOR state');
    wpp_same('cache', $list[0]['trigger'], 'snapshot records the trigger group');

    $h = $freshRequest();
    $settings->update('css', ['defer' => true]);
    $settings->update('js', ['defer' => true]);
    wpp_same(2, count($h->all()), 'multiple groups in one request capture one snapshot');

    $entries = $h->entries();
    wpp_ok(! isset($entries[0]['groups']), 'entries() strips the heavy payload');
    wpp_same(0, $entries[0]['index'], 'entries() exposes an index');
    wpp_ok(is_int($entries[0]['time']), 'entries() exposes a timestamp');

    // Restore reverts to a snapshot.
    $h = $freshRequest();
    wpp_ok($h->restore(1), 'restore of a valid index succeeds');
    wpp_same(false, $settings->get('cache')['enabled'], 'restore reverted the cache setting');
    wpp_ok(! $freshRequest()->restore(99), 'restore of an unknown index fails');

    // The list is capped at five.
    for ($i = 0; $i < 8; $i++) {
        $r = $freshRequest();
        $settings->update('cache', ['clear_time' => 10 + $i]);
    }
    wpp_same(5, count($freshRequest()->all()), 'history is capped at five snapshots');

    // ---------------------------------------------------------- logger
    $settings->update('tools', ['enable_log' => false]);
    $logger = new Logger($settings);
    WPP_Test_State::$hooks = [];
    $logger->register();

    do_action('wpp.cache.after_clear');
    wpp_same('', $logger->read(), 'nothing is logged while logging is off');
    wpp_ok(! is_file($logger->file()), 'no log file is created while logging is off');

    $settings->update('tools', ['enable_log' => true]);
    wpp_ok($logger->enabled(), 'logger reports enabled');

    do_action('wpp.cache.after_clear');
    do_action('wpp.cache.saved', '/about/');
    do_action('wpp.settings.updated', 'media');
    do_action('wpp.db.cleaned', 'revisions');

    $log = $logger->read();
    wpp_contains('Cache cleared', $log, 'cache clear is logged');
    wpp_contains('Cache saved: /about/', $log, 'cache save is logged');
    wpp_contains('Settings saved: media', $log, 'settings save is logged');
    wpp_contains('Database cleanup: revisions', $log, 'database cleanup is logged');
    wpp_ok(
        preg_match('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] /m', $log) === 1,
        'log lines carry a timestamp'
    );

    $logger->clear();
    wpp_same('', $logger->read(), 'clearing empties the log');
    wpp_ok(! is_file($logger->file()), 'clearing removes the log file');
};
