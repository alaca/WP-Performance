<?php

declare(strict_types=1);

use WPP\Settings\Migration;
use WPP\Settings\SettingsService;

/**
 * Settings sanitization and v1 -> v2 migration regressions: values an admin can
 * legitimately choose must survive a save, and an upgrade must not throw away
 * configuration the new UI still reads.
 */
return static function (): void {
    $cloudflareGroup = static function (): void {
        add_filter('wpp.settings.groups', static function (array $g): array {
            $g['cloudflare'] = ['option' => 'wpp_cloudflare', 'defaults' => [
                'enabled' => false, 'api_key' => '', 'email' => '', 'zone_id' => '',
                'dev_mode' => false, 'cache_level' => 'aggressive', 'browser_expire' => 14400,
                'rocket_loader' => false, 'brotli' => false, 'custom_purge_urls' => [],
            ]];
            return $g;
        });
    };

    // ------------------------------------------------ zero is a real TTL choice

    $cloudflareGroup();
    $s = new SettingsService();

    $s->update('cloudflare', ['browser_expire' => 0]);
    wpp_same(0, $s->get('cloudflare')['browser_expire'], 'browser_expire 0 (respect origin headers) is saved');

    $s->update('cloudflare', ['browser_expire' => 14400]);
    $s->update('cloudflare', ['browser_expire' => '0']);
    wpp_same(0, $s->get('cloudflare')['browser_expire'], 'browser_expire "0" from a form select is saved');

    $s->update('cloudflare', ['browser_expire' => 7200]);
    wpp_same(7200, $s->get('cloudflare')['browser_expire'], 'a positive browser_expire still round-trips');

    $s->update('cloudflare', ['browser_expire' => '']);
    wpp_same(14400, $s->get('cloudflare')['browser_expire'], 'an emptied browser_expire falls back to the default');

    // Numeric settings where zero has no meaning keep their positive-only guard.
    $s->update('cache', ['clear_time' => 0, 'clear_unit' => 0, 'block_cache_ttl' => 0]);
    $cache = $s->get('cache');
    wpp_same(10, $cache['clear_time'], 'clear_time 0 still falls back to the default');
    wpp_same(3600, $cache['clear_unit'], 'clear_unit 0 still falls back to the default');
    wpp_same(3600, $cache['block_cache_ttl'], 'block_cache_ttl 0 still falls back to the default');

    // ------------------------------------ restoring a snapshot removes entries

    $s->update('css', ['minify' => ['https://x/a.css' => true]]);
    $snapshot = $s->get('css');
    $s->update('css', ['minify' => ['https://x/a.css' => true, 'https://x/b.css' => true]]);
    $s->update('css', ['exclude_urls' => ['/cart/'], 'defer' => true]);

    if (! method_exists($s, 'replace')) {
        wpp_ok(false, 'SettingsService::replace() is missing, so a restore point cannot remove settings');
    } else {
        $restored = $s->replace('css', $snapshot);
        wpp_same(['https://x/a.css' => true], $s->get('css')['minify'], 'restore drops the per-file entry added after the snapshot');
        wpp_same(['https://x/a.css' => true], $restored['minify'], 'replace() returns the written state');
        wpp_same([], $s->get('css')['exclude_urls'], 'restore drops a list added after the snapshot');
        wpp_same(false, $s->get('css')['defer'], 'restore reverts a scalar changed after the snapshot');

        // Keys the payload omits fall back to the group defaults, not to the
        // value that happened to be stored.
        $s->update('css', ['host_fonts' => true]);
        $s->replace('css', ['defer' => true]);
        wpp_same(false, $s->get('css')['host_fonts'], 'a key absent from the payload resets to its default');
        wpp_same(true, $s->get('css')['defer'], 'a key present in the payload is written');

        // Same whitelisting and sanitization as update().
        $written = $s->replace('css', ['defer' => true, 'not_a_real_key' => 'x']);
        wpp_ok(! array_key_exists('not_a_real_key', $written), 'replace() drops unknown keys');
        wpp_same([], $s->replace('nope', ['a' => 1]), 'replace() ignores an unknown group');

        // History capture and cache invalidation both hang off these events.
        $seen = [];
        add_action('wpp.settings.updating', static function ($group) use (&$seen): void {
            $seen[] = 'updating:' . $group;
        });
        add_action('wpp.settings.updated', static function ($group) use (&$seen): void {
            $seen[] = 'updated:' . $group;
        });
        $s->replace('html', ['enabled' => true]);
        wpp_ok(in_array('updating:html', $seen, true), 'replace() fires wpp.settings.updating');
        wpp_ok(in_array('updated:html', $seen, true), 'replace() fires wpp.settings.updated');
    }

    // update() still merges, so a partial PUT from a tab cannot wipe the group.
    $s->update('js', ['minify' => ['https://x/a.js' => true]]);
    $s->update('js', ['minify' => ['https://x/b.js' => true]]);
    wpp_same(
        ['https://x/a.js' => true, 'https://x/b.js' => true],
        $s->get('js')['minify'],
        'update() still merges per-file maps'
    );

    // ------------------------------- percent-encoded URL patterns are preserved

    // The shared bootstrap stub omits WordPress core's percent-octet stripping,
    // so the real behaviour is supplied to a child process that loads the class
    // fresh (PHP caches the namespaced-function fallback per call site).
    $probe = sys_get_temp_dir() . '/wpp-fix-settings-probe-' . getmypid() . '.php';
    file_put_contents($probe, <<<'PHP'
<?php

declare(strict_types=1);

namespace WPP\Settings {
    function sanitize_text_field($str)
    {
        $str = \sanitize_text_field($str);
        if (! is_string($str)) {
            return $str;
        }
        $found = false;
        while (preg_match('/%[a-f0-9]{2}/i', $str, $m)) {
            $str  = str_replace($m[0], '', $str);
            $found = true;
        }
        return $found ? trim((string) preg_replace('/ +/', ' ', $str)) : $str;
    }
}

namespace {
    require $argv[1];

    $s = new WPP\Settings\SettingsService();
    $s->update('css', [
        'disable_selected' => ['https://x/a.css' => ['/%D0%BD%D0%BE%D0%B2%D0%BE%D1%81%D1%82%D0%B8/']],
        'disable_except'   => ['https://x/a.css' => ['/my%20page/']],
        'exclude_urls'     => ['/caf%C3%A9/'],
    ]);
    $s->update('cdn', ['hostname' => 'https://cdn.test/%20x']);

    $css = $s->get('css');
    echo json_encode([
        'selected' => $css['disable_selected']['https://x/a.css'][0] ?? null,
        'except'   => $css['disable_except']['https://x/a.css'][0] ?? null,
        'exclude'  => $css['exclude_urls'][0] ?? null,
        'hostname' => $s->get('cdn')['hostname'],
    ]);
}
PHP);

    $out = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($probe) . ' '
        . escapeshellarg(WPP_TEST_ROOT . '/tests/bootstrap.php') . ' 2>/dev/null');
    @unlink($probe);
    $probed = json_decode((string) $out, true);

    if (! is_array($probed)) {
        wpp_ok(false, 'percent-encoding probe did not run: ' . var_export($out, true));
    } else {
        // Proves the probe really is running core's sanitizer.
        wpp_same('https://cdn.test/x', $probed['hostname'], 'probe applies core percent stripping to plain text fields');

        wpp_same('/%D0%BD%D0%BE%D0%B2%D0%BE%D1%81%D1%82%D0%B8/', $probed['selected'], 'disable_selected keeps percent-encoded octets');
        wpp_same('/my%20page/', $probed['except'], 'disable_except keeps percent-encoded octets');
        wpp_same('/caf%C3%A9/', $probed['exclude'], 'exclude_urls keeps percent-encoded octets');
    }

    // ------------------------------------------------------- v1 -> v2 migration

    WPP_Test_State::reset();
    $cloudflareGroup();

    WPP_Test_State::$options = [
        'wpp_css_custom_path_def' => "body{margin:0}\n.hero{opacity:1}",
        'wpp_db_cleanup_frequency' => 'daily',
        'wpp_db_cleanup_next'      => 2000000000,
        'wpp_cf_enabled'           => '1',
        'wpp_cf_browser_expire'    => '0',
    ];

    $settings = new SettingsService();
    (new Migration($settings))->run();

    wpp_same(
        "body{margin:0}\n.hero{opacity:1}",
        $settings->get('css')['critical_path'],
        'v1 critical CSS is moved to css.critical_path'
    );
    wpp_ok(
        ! array_key_exists('wpp_css_custom_path_def', WPP_Test_State::$options),
        'the legacy critical CSS option is cleaned up once migrated'
    );

    wpp_same(2000000000, get_option('wpp_db_cleanup_next'), 'the cleanup next-run timestamp survives the migration');

    wpp_same(0, $settings->get('cloudflare')['browser_expire'], 'a legacy browser_expire of 0 migrates as 0');
};
