<?php

declare(strict_types=1);

use WPP\Cache\CacheStore;
use WPP\Cache\DropinInstaller;
use WPP\Cache\ObjectCacheInstaller;
use WPP\Cache\RuntimeSettings;
use WPP\Foundation\Plugin;
use WPP\Settings\SettingsService;
use WPP\Support\Logger;

if (! defined('WPP_FILE')) {
    define('WPP_FILE', WPP_TEST_ROOT . '/wp-performance.php');
}

if (! function_exists('get_site_option')) {
    function get_site_option($key, $default = false)
    {
        return $GLOBALS['wpp_site_options'][$key] ?? $default;
    }
}

if (! function_exists('get_sites')) {
    function get_sites($args = [])
    {
        return $GLOBALS['wpp_sites'] ?? [];
    }
}

if (! function_exists('switch_to_blog')) {
    function switch_to_blog($blogId): bool
    {
        $GLOBALS['wpp_blog_stack'][] = WPP_Test_State::$options;
        WPP_Test_State::$options = $GLOBALS['wpp_blog_options'][(int) $blogId] ?? [];
        return true;
    }
}

if (! function_exists('restore_current_blog')) {
    function restore_current_blog(): bool
    {
        WPP_Test_State::$options = array_pop($GLOBALS['wpp_blog_stack']) ?? [];
        return true;
    }
}

/**
 * Activation, update and multisite lifecycle: an existing 1.x install has to
 * end up with the 2.0 drop-in, an update has to refresh what activation would
 * have refreshed, and nothing a single blog does may reach across a network.
 */
return static function (): void {
    $advanced = WP_CONTENT_DIR . '/advanced-cache.php';
    $object   = WP_CONTENT_DIR . '/object-cache.php';
    $config   = ABSPATH . 'wp-config.php';
    @mkdir(ABSPATH, 0777, true);

    $seed = static function (string $relative, string $body = 'cached'): string {
        $path = WPP_CACHE_DIR . $relative;
        wp_mkdir_p(dirname($path));
        file_put_contents($path, $body);
        return $path;
    };

    $wipe = static function () use ($advanced, $object, $config): void {
        (new CacheStore())->clearAll();
        @unlink($advanced);
        @unlink($object);
        file_put_contents($config, "<?php\ndefine( 'DB_NAME', 'x' );\n");
    };

    // The 1.1.8.3 loader, the drop-in an updating user actually has on disk.
    $legacyDropin = <<<'PHP'
        <?php
        /**
        * WP Performance Optimizer - Cache loader
        *
        * @author Ante Laca <ante.laca@gmail.com>
        * @package WPP
        */

        if ( ! empty( $_POST ) ) {
            return false;
        }

        $settings = _wpp_get_site_settings();

        function _wpp_get_cache_file( $settings ) {
            return WP_CONTENT_DIR . '/cache/wpp-cache/' . $_SERVER[ 'HTTP_HOST' ];
        }

        function _wpp_get_site_settings() {
            $file = WP_CONTENT_DIR . '/cache/wpp-cache/' . $_SERVER[ 'HTTP_HOST' ] . '.settings.json';
            return file_exists( $file ) ? json_decode( file_get_contents( $file ), true ) : [];
        }
        PHP;

    // ---------------------------------------------- 1.x drop-in is ours (finding 3)

    WPP_Test_State::reset();
    $wipe();
    file_put_contents($advanced, $legacyDropin);

    $dropin = new DropinInstaller();
    wpp_ok(! $dropin->foreignDropin(), 'the 1.x loader is recognised as our own drop-in');
    wpp_ok(! $dropin->dropinInstalled(), 'the 1.x loader counts as needing an upgrade');
    wpp_ok($dropin->install(), 'updating from 1.x installs the 2.0 drop-in');

    $installed = (string) file_get_contents($advanced);
    wpp_contains('WPP_ADVANCED_CACHE', $installed, 'the 1.x loader is replaced by the stamped drop-in');
    wpp_contains(WPP_VERSION, $installed, 'the replacement carries the running version');
    wpp_not_contains('_wpp_get_site_settings', $installed, 'no 1.x code survives the upgrade');
    wpp_ok($dropin->dropinInstalled(), 'the upgraded drop-in reports as installed');
    wpp_contains("define( 'WP_CACHE', true );", (string) file_get_contents($config), 'the upgrade owns WP_CACHE again');

    // Each 1.x fingerprint on its own, since a site may hold a hand-edited copy.
    foreach ([
        'header'        => "<?php\n/**\n* WP Performance Optimizer - Cache loader\n*/\n",
        'cache file fn' => "<?php\nfunction _wpp_get_cache_file( \$settings ) { return ''; }\n",
        'settings fn'   => "<?php\nfunction _wpp_get_site_settings() { return []; }\n",
    ] as $label => $fixture) {
        file_put_contents($advanced, $fixture);
        wpp_ok(! $dropin->foreignDropin(), "a 1.x drop-in identified by its {$label} is not foreign");
        wpp_ok($dropin->install(), "a 1.x drop-in identified by its {$label} is upgraded");
    }

    // Widening ownership must not start claiming another plugin's drop-in.
    $foreign = "<?php\n// WP Rocket advanced-cache.php\ndefine('WP_ROCKET_ADVANCED_CACHE', true);\n";
    file_put_contents($advanced, $foreign);
    wpp_ok($dropin->foreignDropin(), 'a third-party drop-in is still foreign');
    wpp_ok(! $dropin->install(), 'install still refuses to overwrite a third-party drop-in');
    wpp_same($foreign, (string) file_get_contents($advanced), 'the third-party drop-in is untouched');

    // ---------------------------------------------- activation purges pre-guard files (finding 2)

    WPP_Test_State::reset();
    $wipe();

    $legacyLog      = $seed('wpp.log', "[2026-01-01 00:00:00] Cache saved: /a-visitor-path/\n");
    $legacyConfig   = $seed('example.test.json', '{"enabled":true,"exclude":["/staging-only/"]}');
    $legacySettings = $seed('example.test.settings.json', '{"cache":1,"exclude":["/staging-only/"]}');
    $legacyFragment = $seed('fragments/cd/' . str_repeat('c', 32) . '.html', "0\n<p>members only</p>");

    Plugin::onActivation();

    wpp_ok(! is_file($legacyLog), 'activation removes the pre-guard log');
    wpp_ok(! is_file($legacyConfig), 'activation removes the pre-guard runtime config');
    wpp_ok(! is_file($legacySettings), 'activation removes the 1.x settings file');
    wpp_ok(! is_file($legacyFragment), 'activation removes pre-guard fragments');
    wpp_ok(is_file((new RuntimeSettings(new SettingsService()))->file()), 'activation leaves a guarded runtime config behind');
    wpp_ok((new DropinInstaller())->dropinInstalled(), 'activation installed the drop-in that reads it');

    // A foreign drop-in blocks the install, and the purge must not run without
    // one of ours in place to read the new file name.
    WPP_Test_State::reset();
    $wipe();
    file_put_contents($advanced, $foreign);
    $blocked = $seed('example.test.json', '{"enabled":true}');

    Plugin::onActivation();
    wpp_ok(is_file($blocked), 'the purge is skipped while a foreign drop-in still reads the old name');

    // ---------------------------------------------- purge is per blog (finding 1)

    WPP_Test_State::reset();
    $wipe();
    WPP_Test_State::$multisite = true;

    $mine      = $seed('example.test.json', '{"enabled":true}');
    $myLog     = $seed('1_wpp.log', "[2026-01-01 00:00:00] mine\n");
    $subsite   = $seed('example.test~shop.json', '{"enabled":false}');
    $subLog    = $seed('2_wpp.log', "[2026-01-01 00:00:00] theirs\n");
    $legacy1x  = $seed('example.test.settings.json', '{"cache":1}');

    Plugin::purgeUnguarded();

    wpp_ok(! is_file($mine), 'the purge removes this blog pre-guard config');
    wpp_ok(! is_file($myLog), 'the purge removes this blog pre-guard log');
    wpp_ok(! is_file($legacy1x), 'the purge removes this host 1.x settings file');
    wpp_ok(is_file($subsite), 'another blog pre-guard config survives');
    wpp_ok(is_file($subLog), 'another blog pre-guard log survives');

    // Single site owns everything in the directory, so nothing is left behind.
    WPP_Test_State::reset();
    $stray = $seed('oldhost.json', '{"enabled":true}');
    Plugin::purgeUnguarded();
    wpp_ok(! is_file($stray), 'on a single site a config left by an old host name is still removed');

    // ---------------------------------------------- clear() is per blog (finding 7)

    WPP_Test_State::reset();
    $wipe();
    WPP_Test_State::$multisite = true;
    update_option('permalink_structure', '/%postname%/');

    $minePage  = $seed('example.test/about/index.html');
    $theirPage = $seed('other.test/about/index.html');
    $asset     = $seed('abc123.css', 'body{}');
    $font      = $seed('fonts/inter.woff2', 'font');

    (new CacheStore())->clear(false);

    wpp_ok(! is_file($minePage), 'clearing purges this blog cached pages');
    wpp_ok(is_file($theirPage), 'clearing on one blog leaves another blog cached pages alone');
    wpp_ok(is_file($asset), 'clearing on one blog keeps the shared generated assets');
    wpp_ok(is_file($font), 'clearing on one blog keeps the shared hosted fonts');

    // An explicit network flush still takes the whole tree.
    (new CacheStore())->clear(false, true);
    wpp_ok(! is_file($theirPage), 'a network flush reaches every blog');
    wpp_ok(! is_file($asset), 'a network flush drops the shared assets when assets are not kept');

    // Single site: one blog owns the tree, so a clear still purges all of it.
    WPP_Test_State::reset();
    $wipe();
    $single = $seed('example.test/about/index.html');
    $other  = $seed('other.test/about/index.html');
    $keptCss = $seed('abc123.css', 'body{}');
    (new CacheStore())->clear(true);
    wpp_ok(! is_file($single), 'a single site clear purges its pages');
    wpp_ok(! is_file($other), 'a single site clear purges a renamed host leftovers');
    wpp_ok(is_file($keptCss), 'keep_assets still preserves generated css');

    // ---------------------------------------------- network deactivation (finding 4)

    WPP_Test_State::reset();
    $wipe();
    wpp_ok((new DropinInstaller())->install(), 'drop-in installed for the network deactivation case');

    WPP_Test_State::$multisite = true;
    $GLOBALS['wpp_sites'] = [1, 2];
    $GLOBALS['wpp_blog_options'] = [2 => ['active_plugins' => []]];
    // WordPress writes active_sitewide_plugins after the deactivation hook runs.
    $GLOBALS['wpp_site_options'] = ['active_sitewide_plugins' => [plugin_basename(WPP_FILE) => 1234]];

    $networkPage = $seed('other.test/about/index.html');

    Plugin::onDeactivation(true);

    wpp_ok(! is_file($advanced), 'a network deactivation removes the drop-in');
    wpp_not_contains('WP_CACHE', (string) file_get_contents($config), 'a network deactivation removes our WP_CACHE define');
    wpp_ok(! is_file($networkPage), 'a network deactivation purges every blog cached pages');

    // A per-blog deactivation while the network activation stands still defers.
    $wipe();
    wpp_ok((new DropinInstaller())->install(), 'drop-in reinstalled');
    Plugin::onDeactivation(false);
    wpp_ok(is_file($advanced), 'a single blog deactivation leaves a network activated drop-in alone');

    $GLOBALS['wpp_site_options'] = [];

    // ---------------------------------------------- per blog deactivation (finding 5)

    WPP_Test_State::reset();
    $wipe();
    WPP_Test_State::$multisite = true;
    $GLOBALS['wpp_sites'] = [1, 2];
    $GLOBALS['wpp_blog_options'] = [2 => ['active_plugins' => [plugin_basename(WPP_FILE)]]];
    $GLOBALS['wpp_site_options'] = [];

    $settings = new SettingsService();
    $settings->update('cache', ['enabled' => true]);
    $runtime = new RuntimeSettings($settings);
    $runtime->write();

    wpp_ok((new DropinInstaller())->install(), 'drop-in installed for the per blog deactivation case');
    $myPage    = $seed('example.test/about/index.html');
    $otherPage = $seed('other.test/about/index.html');

    Plugin::onDeactivation(false);

    $payload = json_decode(RuntimeSettings::payload((string) file_get_contents($runtime->file())), true);
    wpp_ok(is_array($payload), 'the runtime config is still valid json after deactivation');
    wpp_same(false, (bool) ($payload['enabled'] ?? false), 'deactivating a blog stops the drop-in serving that blog');
    wpp_ok(! is_file($myPage), 'deactivating a blog purges that blog cached pages');
    wpp_ok(is_file($otherPage), 'deactivating a blog leaves the other blogs cached pages alone');
    wpp_ok(is_file($advanced), 'the shared drop-in stays for the blogs still using it');
    wpp_contains('WP_CACHE', (string) file_get_contents($config), 'wp-config keeps WP_CACHE for the other blogs');
    wpp_same(true, (bool) $settings->get('cache')['enabled'], 'the stored setting is untouched, so reactivation restores it');

    // ---------------------------------------------- object cache versioning (finding 6)

    WPP_Test_State::reset();
    $wipe();

    $objectCache = new ObjectCacheInstaller();
    wpp_ok($objectCache->install(), 'object cache drop-in installs');
    wpp_ok($objectCache->installed(), 'the copy is recognised as ours');
    wpp_ok($objectCache->objectCacheInstalled(), 'a fresh copy matches the running version');
    wpp_contains(WPP_VERSION, (string) file_get_contents($object), 'the copy carries a version stamp');

    // What an install updated from an earlier release has on disk.
    file_put_contents($object, "<?php\ndefine('WPP_OBJECT_CACHE', '1.0.0');\nclass WPP_Object_Cache {}\n");
    wpp_ok($objectCache->installed(), 'a stale copy is still ours');
    wpp_ok(! $objectCache->objectCacheInstalled(), 'a stale copy is detected as needing a refresh');
    wpp_ok($objectCache->install(), 'a stale copy is refreshed');
    wpp_contains('wp_cache_init', (string) file_get_contents($object), 'the refresh wrote the shipped drop-in');
    wpp_ok($objectCache->objectCacheInstalled(), 'the refresh brings it to the running version');

    if ($objectCache->available()) {
        // Self-heal: an update never runs the activation hook.
        file_put_contents($object, "<?php\ndefine('WPP_OBJECT_CACHE', '1.0.0');\nclass WPP_Object_Cache {}\n");
        $settings = new SettingsService();
        $settings->update('tools', ['object_cache' => true]);

        Plugin::syncObjectCache($settings);
        $healed = (string) file_get_contents($object);
        wpp_contains('wp_cache_init', $healed, 'an update refreshes a stale object cache drop-in');
        wpp_not_contains("'1.0.0'", $healed, 'the stale version stamp is gone');
        wpp_same(true, (bool) $settings->get('tools')['object_cache'], 'the toggle stays on after a refresh');

        // Drift: the file went away while the toggle still reads on.
        @unlink($object);
        Plugin::syncObjectCache($settings);
        wpp_contains('wp_cache_init', (string) @file_get_contents($object), 'a drop-in removed by something else is reinstalled');
        wpp_ok($objectCache->objectCacheInstalled(), 'the reinstalled drop-in is current');

        // A foreign drop-in cannot be replaced, so the toggle must not lie.
        file_put_contents($object, "<?php\n// Redis Object Cache by someone else\n");
        Plugin::syncObjectCache($settings);
        wpp_same(false, (bool) $settings->get('tools')['object_cache'], 'the toggle goes off when a foreign drop-in owns the file');
    }

    $wipe();
};
