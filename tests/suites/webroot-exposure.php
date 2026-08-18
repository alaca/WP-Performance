<?php

declare(strict_types=1);

use WPP\Cache\FragmentStore;
use WPP\Cache\RuntimeSettings;
use WPP\Settings\SettingsService;
use WPP\Support\Logger;

/**
 * The cache directory lives inside the web root, and .htaccess is ignored by
 * nginx, so every file holding non-public data has to be unservable on its own:
 * a .php extension plus a leading exit guard, which returns nothing whatever the
 * server config says.
 *
 * Verified live before this was fixed: /wp-content/cache/wpp-cache/<host>.json
 * and wpp.log both returned 200 with their real contents on nginx.
 */
return static function (): void {
    $settings = new SettingsService();
    $guarded  = static fn (string $file): bool => is_file($file)
        && str_starts_with((string) file_get_contents($file), '<?php');

    // ---- runtime config the drop-in reads ----
    $settings->update('cache', ['enabled' => true, 'exclude_urls' => ['/secret-admin-path/']]);
    $runtime = new RuntimeSettings($settings);
    $runtime->write();

    $file = $runtime->file();
    wpp_contains('.php', $file, 'runtime config is named so a web server will not serve it raw');
    wpp_ok($guarded($file), 'runtime config starts with a php exit guard');
    wpp_ok($runtime->written(), 'a guarded runtime config still reads back as current');

    $data = json_decode(RuntimeSettings::payload((string) file_get_contents($file)), true);
    wpp_ok(is_array($data), 'the payload behind the guard is still valid json');
    wpp_same(['/secret-admin-path/'], $data['exclude'], 'exclusion list survives the guard');

    // Removing the pre-guard config is the purge's job, not the writer's: a
    // drop-in that could not be refreshed still needs something to read.
    $legacy = substr($file, 0, -4);
    file_put_contents($legacy, '{"enabled":true}');
    $runtime->write();
    wpp_ok(is_file($legacy), 'writing the config does not orphan a drop-in that still reads the old name');

    // ---- troubleshooting log ----
    $settings->update('tools', ['enable_log' => true]);
    $logger = new Logger($settings);
    $logger->clear();
    $logger->log('Cache saved: /a-visitor-path/');

    wpp_contains('.php', $logger->file(), 'log is named so a web server will not serve it raw');
    wpp_ok($guarded($logger->file()), 'log starts with a php exit guard');
    wpp_contains('Cache saved: /a-visitor-path/', $logger->read(), 'log reads back without the guard');
    wpp_not_contains('<?php', $logger->read(), 'the guard is not shown in the admin log viewer');

    // Appending must not add a second guard.
    $logger->log('Second line');
    wpp_same(1, substr_count((string) file_get_contents($logger->file()), '<?php'), 'appending keeps exactly one guard');
    wpp_contains('Second line', $logger->read(), 'appended line is readable');

    // Rotation trims from the front, which would take the guard with it.
    $big = str_repeat("[2026-01-01 00:00:00] filler line to force rotation\n", 24000);
    wpp_ok(strlen($big) > 1048576, 'the appended filler pushes the log over the cap');

    file_put_contents($logger->file(), $big, FILE_APPEND);
    $logger->log('After rotation');
    clearstatcache(true, $logger->file());
    wpp_ok(filesize($logger->file()) <= 1048576 + 512, 'rotation brings the log back under the cap');
    wpp_ok($guarded($logger->file()), 'rotation re-applies the guard instead of stripping it');
    wpp_contains('After rotation', $logger->read(), 'rotation keeps the newest entries');

    // ---- cached fragments, which can hold logged-in markup ----
    $store = new FragmentStore();
    $store->set('exposure', '<p>members only</p>', 60);

    $found = glob(WPP_CACHE_DIR . 'fragments/*/*') ?: [];
    wpp_ok($found !== [], 'a fragment was written to disk');
    foreach ($found as $fragment) {
        wpp_contains('.php', $fragment, 'fragment is named so a web server will not serve it raw');
        wpp_ok($guarded($fragment), 'fragment starts with a php exit guard');
    }
    wpp_same('<p>members only</p>', $store->get('exposure'), 'fragment still round trips behind the guard');

    // Guarding must not break expiry or the stats the dashboard reports.
    $store->set('expiring', '<p>x</p>', 60);
    wpp_ok($store->stats()['count'] > 0, 'guarded fragments are still counted');
    $store->flush();
    wpp_same(null, $store->get('exposure'), 'flush still invalidates');

    // Expiry lives on the second line now, and a reader that took the guard for
    // the expiry would read 0 (never expires) and stop sweeping stale entries.
    // The sweep only runs for the shard being written to, so the stale entries
    // have to share a shard with the fragment that triggers it.
    $store->set('sweeper', '<p>fresh</p>', 60);
    $probe = glob(WPP_CACHE_DIR . 'fragments/*/*.html.php') ?: [];
    $shard = dirname($probe[0]) . '/';
    unlink($probe[0]);

    for ($i = 0; $i < 205; $i++) {
        file_put_contents($shard . str_pad((string) $i, 32, 'a') . '.html.php', RuntimeSettings::GUARD . (time() - 60) . "\nstale");
    }
    $store->set('sweeper', '<p>fresh</p>', 60);
    wpp_ok(count(glob($shard . '*.html.php') ?: []) < 205, 'expired fragments are still swept behind the guard');
    wpp_same('<p>fresh</p>', $store->get('sweeper'), 'the fragment that triggered the sweep was stored');

    // ---- files an older release left unguarded ----
    $legacyLog = WPP_CACHE_DIR . 'wpp.log';
    $legacyCfg = WPP_CACHE_DIR . 'oldhost.json';
    $legacyFrg = WPP_CACHE_DIR . 'fragments/cd/' . str_repeat('c', 32) . '.html';
    wp_mkdir_p(dirname($legacyFrg));
    file_put_contents($legacyLog, "[2026-01-01 00:00:00] Cache saved: /a-visitor-path/\n");
    file_put_contents($legacyCfg, '{"exclude":["/secret-admin-path/"]}');
    file_put_contents($legacyFrg, "0\n<p>members only</p>");

    WPP\Foundation\Plugin::purgeUnguarded();

    wpp_ok(! is_file($legacyLog), 'an unguarded log from an older release is removed');
    wpp_ok(! is_file($legacyCfg), 'an unguarded runtime config from an older release is removed');
    wpp_ok(! is_file($legacyFrg), 'an unguarded fragment from an older release is removed');
    wpp_ok(is_file($logger->file()), 'the purge leaves the current guarded log alone');

    $settings->update('tools', ['enable_log' => false]);
};
