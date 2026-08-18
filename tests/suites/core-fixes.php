<?php

declare(strict_types=1);

use WPP\Admin\AdminAssets;
use WPP\Admin\CompatibilityNotice;
use WPP\Admin\Metabox;
use WPP\Cache\CacheStore;
use WPP\Cache\DropinInstaller;
use WPP\Foundation\Plugin;
use WPP\Media\ImageConverter;
use WPP\Settings\Migration;
use WPP\Settings\Presets;
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

if (! function_exists('get_post_types')) {
    function get_post_types($args = [], $output = 'names')
    {
        return ['post' => 'post', 'page' => 'page'];
    }
}

if (! function_exists('add_meta_box')) {
    function add_meta_box($id, $title, $callback, $screen = null, $context = 'advanced', $priority = 'default'): void
    {
        $GLOBALS['wpp_meta_boxes'][] = ['id' => $id, 'screen' => $screen, 'context' => $context];
    }
}

if (! function_exists('wp_nonce_field')) {
    function wp_nonce_field($action = -1, $name = '_wpnonce', $referer = true, $display = true): string
    {
        return '';
    }
}

if (! function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1)
    {
        return $nonce === 'valid' ? 1 : false;
    }
}

if (! function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1): string
    {
        return 'nonce';
    }
}

if (! function_exists('rest_url')) {
    function rest_url($path = '', $scheme = 'rest'): string
    {
        return 'https://example.test/wp-json/' . ltrim((string) $path, '/');
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

if (! function_exists('wp_style_add_data')) {
    function wp_style_add_data($handle, $key, $value): bool
    {
        $GLOBALS['wpp_style_data'][] = [$handle, $key, $value];
        return true;
    }
}

if (! function_exists('wp_set_script_translations')) {
    function wp_set_script_translations($handle, $domain = 'default', $path = ''): bool
    {
        return true;
    }
}

if (! function_exists('wp_add_inline_script')) {
    function wp_add_inline_script($handle, $data, $position = 'after'): bool
    {
        return true;
    }
}

if (! class_exists('WP_Post')) {
    class WP_Post
    {
        public int $ID = 0;
        public string $post_type = 'post';
    }
}

/**
 * Regressions for the core review.
 *
 * Note on the sanitization cases: the bootstrap's sanitize_text_field() stub
 * reproduces WordPress's tag stripping and whitespace collapsing but not its
 * percent-octet removal loop, so the assertions that have to fail without the
 * fix lean on the first two. The percent-encoded values are asserted alongside
 * them because they are what the bug actually destroys in production.
 */
return static function (): void {
    $s = new SettingsService();

    // ------------------------------------------------- exclusion patterns

    $encoded = ['/%D0%BD%D0%BE%D0%B2%D0%BE%D1%81%D1%82%D0%B8/', 'https://site.test/my%20page/'];
    $s->update('cache', ['exclude_urls' => $encoded]);
    wpp_same($encoded, $s->get('cache')['exclude_urls'], 'percent-encoded exclusion patterns are stored verbatim');

    $hostile = ["/a\tb/", '/c  d/', '/e<1/'];
    $s->update('cache', ['exclude_urls' => $hostile]);
    wpp_same($hostile, $s->get('cache')['exclude_urls'], 'cache exclusions never reach the text sanitizer');

    $s->update('css', ['file_exclude' => $hostile, 'exclude_urls' => $hostile, 'used_safelist' => $hostile]);
    wpp_same($hostile, $s->get('css')['file_exclude'], 'css file exclusions are stored verbatim');
    wpp_same($hostile, $s->get('css')['used_safelist'], 'used-css safelist is stored verbatim');

    $s->update('js', ['delay_exclude' => $hostile]);
    wpp_same($hostile, $s->get('js')['delay_exclude'], 'js delay exclusions are stored verbatim');

    $s->update('media', ['images_exclude_urls' => $hostile, 'images_exclude_containers' => ['#main  .hero > img']]);
    wpp_same($hostile, $s->get('media')['images_exclude_urls'], 'image url exclusions are stored verbatim');
    wpp_same(['#main  .hero > img'], $s->get('media')['images_exclude_containers'], 'container selectors keep their spacing');

    $s->update('cdn', ['exclude' => $hostile]);
    wpp_same($hostile, $s->get('cdn')['exclude'], 'cdn exclusions are stored verbatim');

    // Plain string fields keep the tag-stripping sanitizer.
    $s->update('cdn', ['hostname' => '  https://cdn.example.com  <script>']);
    wpp_same('https://cdn.example.com', $s->get('cdn')['hostname'], 'plain string fields are still sanitized');

    // ------------------------------------------------- per-file map keys

    $s->update('css', ['minify' => [
        'https://site.test/wp-content/themes/my%20theme/style.css' => true,
        '/x<1.css' => true,
    ]]);
    $map = $s->get('css')['minify'];
    wpp_ok(
        array_key_exists('https://site.test/wp-content/themes/my%20theme/style.css', $map),
        'a percent-encoded asset url survives as a map key'
    );
    wpp_ok(array_key_exists('/x<1.css', $map), 'map keys never reach the text sanitizer');

    // ------------------------------------------------- critical css

    $svg = 'a{background:url("data:image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\'><path d=\'M0 0\'/></svg>")}';
    $s->update('css', ['critical_path' => $svg]);
    wpp_same($svg, $s->get('css')['critical_path'], 'inline svg in critical css survives the save');

    $s->update('css', ['critical_path' => "body{color:red}\n</style><script>x</script>"]);
    $css = $s->get('css')['critical_path'];
    wpp_not_contains('</style>', $css, 'a closing style tag cannot break out of the inlined block');
    wpp_contains("\n", $css, 'critical css keeps its newlines');
    wpp_contains('<script>', $css, 'nothing else in the css is stripped');

    // ------------------------------------------------- presets are declarative

    $presets = new Presets();
    wpp_ok($presets->apply('aggressive', $s), 'aggressive applies');

    $s->update('css', ['minify' => ['/a.css' => true], 'exclude_urls' => ['/skip/']]);
    $s->update('cdn', ['hostname' => 'https://cdn.example.com']);

    wpp_ok($presets->apply('safe', $s), 'safe applies over aggressive');

    wpp_same(false, $s->get('cache')['mobile'], 'safe resets cache.mobile');
    wpp_same(false, $s->get('css')['host_fonts'], 'safe resets css.host_fonts');
    wpp_same(false, $s->get('css')['combine_fonts'], 'safe resets css.combine_fonts');
    wpp_same('none', $s->get('css')['font_display'], 'safe resets css.font_display');
    wpp_same(false, $s->get('html')['minify_aggressive'], 'safe resets html.minify_aggressive');
    wpp_same(false, $s->get('html')['remove_quotes'], 'safe resets html.remove_quotes');
    wpp_same(false, $s->get('html')['remove_link_type'], 'safe resets html.remove_link_type');
    wpp_same(false, $s->get('html')['remove_script_type'], 'safe resets html.remove_script_type');
    wpp_same(false, $s->get('media')['webp'], 'safe resets media.webp');
    wpp_same(0, $s->get('media')['lcp_images'], 'safe resets media.lcp_images');
    wpp_same(false, $s->get('media')['images_responsive'], 'safe resets media.images_responsive');
    wpp_same(false, $s->get('media')['videos_lazy'], 'safe resets media.videos_lazy');

    wpp_same(true, $s->get('cache')['enabled'], 'safe still applies its own values');
    wpp_same(true, $s->get('media')['images_lazy'], 'safe still enables lazy loading');
    wpp_same(false, $s->get('js')['delay'], 'safe turns off the delay aggressive enabled');

    wpp_same(true, $s->get('css')['minify']['/a.css'] ?? null, 'a curated per-file map survives a preset');
    wpp_same(['/skip/'], $s->get('css')['exclude_urls'], 'a curated exclusion list survives a preset');
    wpp_same('https://cdn.example.com', $s->get('cdn')['hostname'], 'the cdn hostname survives a preset');

    // ------------------------------------------------- migration of unknown groups

    WPP_Test_State::reset();
    WPP_Test_State::$options = [
        'wpp_cache'               => '1',
        'wpp_cf_enabled'          => '1',
        'wpp_cf_email'            => 'me@test.com',
        'wpp_cf_api_key'          => 'secret-key',
        'wpp_cf_zone_id'          => 'zone123',
        'wpp_varnish_custom_host' => '127.0.0.1',
        'wpp_prefetch_pages'      => '1',
    ];

    // Activation runs before the add-on modules register their groups.
    $bare = new SettingsService();
    (new Migration($bare))->maybeRun();

    wpp_same('secret-key', get_option('wpp_cf_api_key'), 'cloudflare credentials survive a pass that cannot see the group');
    wpp_same('me@test.com', get_option('wpp_cf_email'), 'cloudflare email survives');
    wpp_same('127.0.0.1', get_option('wpp_varnish_custom_host'), 'varnish host survives');
    wpp_same('1', get_option('wpp_prefetch_pages'), 'prefetch flag survives');
    wpp_same(false, get_option('wpp_migrated'), 'the flag stays unset while a group is unknown');
    wpp_same(true, $bare->get('cache')['enabled'], 'known groups still migrate in the same pass');

    add_filter('wpp.settings.groups', static function (array $g): array {
        $g['cloudflare'] = ['option' => 'wpp_cloudflare', 'defaults' => [
            'enabled' => false, 'api_key' => '', 'email' => '', 'zone_id' => '',
        ]];
        $g['varnish'] = ['option' => 'wpp_varnish', 'defaults' => ['enabled' => false, 'custom_host' => '']];
        $g['prefetch'] = ['option' => 'wpp_prefetch', 'defaults' => ['enabled' => false, 'exclude' => []]];
        return $g;
    });

    $full = new SettingsService();
    (new Migration($full))->maybeRun();

    wpp_same('secret-key', $full->get('cloudflare')['api_key'], 'a later pass migrates what the first one could not');
    wpp_same('127.0.0.1', $full->get('varnish')['custom_host'], 'varnish is migrated by the later pass');
    wpp_same(true, $full->get('prefetch')['enabled'], 'prefetch is migrated by the later pass');
    wpp_same('2.0.0', get_option('wpp_migrated'), 'the flag is set once every group migrated');
    wpp_ok(! array_key_exists('wpp_cf_api_key', WPP_Test_State::$options), 'legacy keys go once they are migrated');

    // ------------------------------------------------- cache directory guards

    WPP_Test_State::reset();
    @unlink(WPP_CACHE_DIR . 'index.php');
    @unlink(WPP_CACHE_DIR . '.htaccess');

    Plugin::protectCacheDir();
    wpp_ok(is_file(WPP_CACHE_DIR . 'index.php'), 'the cache directory gets a silence index');

    $htaccess = (string) @file_get_contents(WPP_CACHE_DIR . '.htaccess');
    wpp_contains('Options -Indexes', $htaccess, 'directory listing is turned off');
    wpp_contains('Require all denied', $htaccess, 'the log and the runtime json are denied');
    wpp_contains('Deny from all', $htaccess, 'apache 2.2 fallback is present');

    $logSettings = new SettingsService();
    $logSettings->update('tools', ['enable_log' => true]);
    @unlink(WPP_CACHE_DIR . '.htaccess');
    $logger = new Logger($logSettings);
    $logger->log('guard check');
    wpp_ok(is_file(WPP_CACHE_DIR . '.htaccess'), 'writing the log restores the directory guard');
    $logger->clear();

    // ------------------------------------------------- metabox

    WPP_Test_State::reset();
    $box = new Metabox();

    $GLOBALS['wpp_meta_boxes'] = [];
    $box->addBox();
    wpp_same(1, count($GLOBALS['wpp_meta_boxes']), 'one box is registered');
    wpp_same(['post', 'page'], $GLOBALS['wpp_meta_boxes'][0]['screen'], 'the box names its screens instead of taking the current one');

    ob_start();
    $box->render((object) ['link_id' => 1]);
    wpp_same('', (string) ob_get_clean(), 'a non-post screen object renders nothing instead of raising a TypeError');

    $post = new WP_Post();
    $post->ID = 7;
    ob_start();
    $box->render($post);
    wpp_contains('wpp_exclude[cache]', (string) ob_get_clean(), 'the box still renders on a post');

    $cached = CacheStore::fileFor('example.test', '/contact/', true, false);
    $seed = static function () use ($cached): void {
        @mkdir(dirname($cached), 0777, true);
        file_put_contents($cached, 'cached html');
    };

    $seed();
    $_POST = ['wpp_metabox_nonce' => 'valid', 'wpp_exclude' => ['cache' => '1']];
    $box->save(7);
    wpp_same(1, get_post_meta(7, '_wpp_exclude_cache', true), 'the exclusion is stored');
    wpp_ok(! is_file($cached), 'ticking exclude-from-cache purges the copy already on disk');

    $seed();
    $box->save(7);
    wpp_ok(is_file($cached), 'saving again with nothing changed leaves the cache alone');

    $_POST = ['wpp_metabox_nonce' => 'valid'];
    $box->save(7);
    wpp_ok(! is_file($cached), 'clearing the exclusion purges too');
    wpp_same('', get_post_meta(7, '_wpp_exclude_cache', true), 'the exclusion is removed');

    // ------------------------------------------------- compatibility notice

    WPP_Test_State::reset();
    WPP_Test_State::$multisite = true;
    $GLOBALS['wpp_site_options'] = ['active_sitewide_plugins' => ['wp-rocket/wp-rocket.php' => 1234]];

    ob_start();
    (new CompatibilityNotice())->render();
    $notice = (string) ob_get_clean();
    wpp_contains('WP Rocket', $notice, 'a network-activated cache plugin is detected');

    $GLOBALS['wpp_site_options'] = [];
    ob_start();
    (new CompatibilityNotice())->render();
    wpp_same('', (string) ob_get_clean(), 'no notice without a conflicting plugin');

    // ------------------------------------------------- admin assets

    WPP_Test_State::reset();
    $_GET['page'] = WPP_SLUG;
    $GLOBALS['wpp_style_data'] = [];
    (new AdminAssets(new SettingsService()))->enqueue();
    wpp_ok(
        in_array(['wpp-admin', 'rtl', 'replace'], $GLOBALS['wpp_style_data'], true),
        'the admin stylesheet declares its rtl replacement'
    );

    // ------------------------------------------------- next-gen image encode

    if (function_exists('imagewebp') && function_exists('imagepng')) {
        $imgDir = WPP_TEST_TMP . 'img/';
        @mkdir($imgDir, 0777, true);
        $png = $imgDir . 'hero.png';

        $canvas = imagecreatetruecolor(8, 8);
        imagepng($canvas, $png);
        imagedestroy($canvas);

        // What a killed or out-of-memory encode leaves behind.
        file_put_contents($png . '.webp', '');

        $made = (new ImageConverter())->convert($png);
        clearstatcache();

        wpp_ok(in_array('webp', $made, true), 'a zero-byte next-gen file is encoded again instead of kept forever');
        wpp_ok((int) @filesize($png . '.webp') > 0, 'the replacement is a real image');
        wpp_ok(! is_file($png . '.webp.tmp'), 'no temp file is left behind');
    }

    // ------------------------------------------------- file modification guards

    WPP_Test_State::reset();
    $advanced = WP_CONTENT_DIR . '/advanced-cache.php';
    $config   = ABSPATH . 'wp-config.php';
    @mkdir(ABSPATH, 0777, true);
    @unlink($advanced);
    file_put_contents($config, "<?php\ndefine( 'DB_NAME', 'x' );\n");

    WPP_Test_State::$multisite = true;
    WPP_Test_State::$loggedIn = true;
    WPP_Test_State::$role = 'administrator';

    $dropin = new DropinInstaller();
    wpp_ok(! $dropin->install(), 'a subsite administrator cannot install the network drop-in');
    wpp_ok(! is_file($advanced), 'nothing was written to wp-content');
    wpp_not_contains('WP_CACHE', (string) file_get_contents($config), 'wp-config.php was not rewritten');

    WPP_Test_State::$role = 'superadmin';
    wpp_ok($dropin->install(), 'a super admin can install it');
    wpp_ok(is_file($advanced), 'the drop-in is written for a super admin');

    // ------------------------------------------------- activation

    WPP_Test_State::reset();
    @unlink($advanced);
    file_put_contents($config, "<?php\ndefine( 'DB_NAME', 'x' );\n");
    WPP_Test_State::$options = [
        'wpp_cf_enabled'          => '1',
        'wpp_cf_api_key'          => 'secret-key',
        'wpp_cf_email'            => 'me@test.com',
        'wpp_cf_zone_id'          => 'zone123',
        'wpp_varnish_custom_host' => '127.0.0.1',
        'wpp_prefetch_pages'      => '1',
    ];

    Plugin::onActivation();

    $activated = new SettingsService();
    wpp_same('secret-key', $activated->get('cloudflare')['api_key'] ?? null, 'activation migrates the cloudflare credentials');
    wpp_same('127.0.0.1', $activated->get('varnish')['custom_host'] ?? null, 'activation migrates the varnish host');
    wpp_same(true, $activated->get('prefetch')['enabled'] ?? null, 'activation migrates the prefetch flag');
    wpp_same('2.0.0', get_option('wpp_migrated'), 'activation completes the migration');
    wpp_ok(is_file(WPP_CACHE_DIR . 'index.php'), 'activation guards the cache directory');

    // ------------------------------------------------- multisite deactivation

    WPP_Test_State::reset();
    file_put_contents($config, "<?php\ndefine( 'DB_NAME', 'x' );\n");
    @unlink($advanced);
    wpp_ok((new DropinInstaller())->install(), 'drop-in installed for the deactivation case');

    WPP_Test_State::$multisite = true;
    $GLOBALS['wpp_sites'] = [1, 2];
    $GLOBALS['wpp_blog_options'] = [2 => ['active_plugins' => [plugin_basename(WPP_FILE)]]];

    Plugin::onDeactivation();
    wpp_ok(is_file($advanced), 'deactivating one blog leaves the network drop-in for the others');
    wpp_contains('WP_CACHE', (string) file_get_contents($config), 'wp-config keeps WP_CACHE while another blog needs it');

    $GLOBALS['wpp_blog_options'] = [2 => ['active_plugins' => []]];
    Plugin::onDeactivation();
    wpp_ok(! is_file($advanced), 'the drop-in goes once no other blog uses it');
};
