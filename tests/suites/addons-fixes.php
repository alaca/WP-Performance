<?php

declare(strict_types=1);

use WPP\Addons\Cloudflare\CloudflareApi;
use WPP\Addons\Cloudflare\CloudflareController;
use WPP\Addons\Cloudflare\CloudflareModule;
use WPP\Addons\Prefetch\PrefetchModule;
use WPP\Addons\Varnish\VarnishClient;
use WPP\Cli\CliCommands;
use WPP\Cron\Preloader;
use WPP\Database\DatabaseService;
use WPP\Foundation\Container\Container;
use WPP\Settings\SettingsService;

if (! function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1)
    {
        return parse_url((string) $url, $component);
    }
}

if (! class_exists('WP_CLI')) {
    class WP_CLI
    {
        public static function success($message): void
        {
        }

        public static function error($message): void
        {
        }
    }
}

/** Records every query and hands back canned rows: no database, no schema. */
final class WPP_Fake_Wpdb
{
    public string $options = 'wp_options';
    public string $posts = 'wp_posts';
    public string $comments = 'wp_comments';

    /** @var list<string> */
    public array $queries = [];
    /** @var list<string> */
    public array $col = [];
    public int $var = 0;

    public function esc_like($text)
    {
        return addcslashes((string) $text, '_%\\');
    }

    public function prepare($query, ...$args)
    {
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }

        $parts = preg_split('/(%[sdf])/', (string) $query, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $out   = '';
        $i     = 0;
        foreach ($parts as $part) {
            $out .= match ($part) {
                '%s'    => "'" . $args[$i++] . "'",
                '%d'    => (string) (int) $args[$i++],
                '%f'    => (string) (float) $args[$i++],
                default => $part,
            };
        }

        return $out;
    }

    public function get_var($query)
    {
        $this->queries[] = (string) $query;
        return $this->var;
    }

    public function get_col($query)
    {
        $this->queries[] = (string) $query;
        return $this->col;
    }

    public function query($query)
    {
        $this->queries[] = (string) $query;
        return 1;
    }

    public function last(string $needle): string
    {
        foreach (array_reverse($this->queries) as $query) {
            if (str_contains($query, $needle)) {
                return $query;
            }
        }
        return '';
    }
}

/**
 * Regressions for the add-on, cron, database and CLI review findings. Each block
 * fails against the code as it was before the corresponding fix.
 */
return static function (): void {
    $calledUrls = static fn (): array => array_map(
        static fn (array $call): string => (string) $call['url'],
        WPP_Test_State::$httpCalls
    );

    // ---- Preloader follows sitemap indexes ----------------------------------

    WPP_Test_State::$options['wpp_cache'] = ['sitemaps' => ['https://example.test/wp-sitemap.xml']];
    WPP_Test_State::$httpResponses = [
        'https://example.test/wp-sitemap.xml' => [
            'response' => ['code' => 200],
            'body'     => '<?xml version="1.0"?><sitemapindex><sitemap>'
                . '<loc>https://example.test/wp-sitemap-posts-post-1.xml</loc>'
                . '</sitemap></sitemapindex>',
        ],
        'https://example.test/wp-sitemap-posts-post-1.xml' => [
            'response' => ['code' => 200],
            'body'     => '<?xml version="1.0"?><urlset>'
                . '<url><loc>https://example.test/hello/</loc></url>'
                . '<url><loc>http://169.254.169.254/latest/meta-data/</loc></url>'
                . '</urlset>',
        ],
    ];

    (new Preloader(new SettingsService()))->run();

    $urls = $calledUrls();
    wpp_ok(in_array('https://example.test/wp-sitemap-posts-post-1.xml', $urls, true), 'child sitemap fetched');
    wpp_ok(in_array('https://example.test/hello/', $urls, true), 'page listed in the child sitemap is warmed');
    wpp_ok(
        ! in_array('http://169.254.169.254/latest/meta-data/', $urls, true),
        'off-site sitemap location is not requested'
    );

    foreach (WPP_Test_State::$httpCalls as $call) {
        wpp_ok(! empty($call['args']['reject_unsafe_urls']), 'preload requests reject unsafe URLs');
    }

    // A plain urlset still warms its own URLs.
    WPP_Test_State::$httpCalls = [];
    WPP_Test_State::$httpResponses = [
        'https://example.test/sitemap.xml' => [
            'response' => ['code' => 200],
            'body'     => '<urlset><url><loc>https://example.test/about/</loc></url></urlset>',
        ],
    ];
    WPP_Test_State::$options['wpp_cache'] = ['sitemaps' => ['https://example.test/sitemap.xml']];
    (new Preloader(new SettingsService()))->run();
    wpp_ok(in_array('https://example.test/about/', $calledUrls(), true), 'flat sitemap URLs are warmed');

    // ---- Varnish keeps the port --------------------------------------------

    WPP_Test_State::$httpCalls = [];
    (new VarnishClient())->purge(['custom_host' => 'http://127.0.0.1:6081']);
    wpp_same('http://127.0.0.1:6081/.*', $calledUrls()[0] ?? '', 'purge reaches the configured Varnish port');

    WPP_Test_State::$httpCalls = [];
    (new VarnishClient())->purge(['custom_host' => '']);
    wpp_same('https://example.test/.*', $calledUrls()[0] ?? '', 'default host still purges the site itself');

    // ---- wp wpp flush honours keep_assets ----------------------------------

    WPP_Test_State::$options['wpp_cache'] = ['keep_assets' => true];
    file_put_contents(WPP_CACHE_DIR . 'page.html', 'x');
    file_put_contents(WPP_CACHE_DIR . 'bundle.css', 'x');

    (new CliCommands())->flush([], []);

    wpp_ok(! is_file(WPP_CACHE_DIR . 'page.html'), 'cli flush clears cached pages');
    wpp_ok(is_file(WPP_CACHE_DIR . 'bundle.css'), 'cli flush keeps assets when keep_assets is on');
    @unlink(WPP_CACHE_DIR . 'bundle.css');

    // ---- Transients: count value rows, delete expired pairs -----------------

    $wpdb = new WPP_Fake_Wpdb();
    $GLOBALS['wpdb'] = $wpdb;
    $wpdb->col = ['_transient_timeout_expired', '_site_transient_timeout_old'];

    $database = new DatabaseService();
    $database->clear('transients');

    $select = $wpdb->last('SELECT option_name');
    wpp_contains("option_value < ", $select, 'only expired timeout rows are selected');
    wpp_contains("'\\_transient\\_timeout\\_%'", $select, 'timeout pattern is anchored to the prefix');

    $delete = $wpdb->last('DELETE');
    wpp_contains("'_transient_expired'", $delete, 'expired transient value row deleted');
    wpp_contains("'_transient_timeout_expired'", $delete, 'expired transient timeout row deleted');
    wpp_contains("'_site_transient_old'", $delete, 'expired site transient value row deleted');
    wpp_not_contains('LIKE', $delete, 'delete never matches transients by pattern');

    $wpdb->queries = [];
    $transientsCount = new ReflectionMethod($database, 'transientsCount');
    $transientsCount->setAccessible(true);
    $transientsCount->invoke($database);
    $count = $wpdb->last('SELECT COUNT(*)');
    wpp_contains('NOT LIKE', $count, 'timeout rows are excluded from the transient count');
    wpp_not_contains("'%\\_transient", $count, 'count is not matched with a leading wildcard');

    // ---- Cloudflare: retired settings, reported failures --------------------

    WPP_Test_State::$httpCalls = [];
    WPP_Test_State::$httpResponses = [
        'https://api.cloudflare.com/client/v4/zones/z1/settings/brotli' => [
            'response' => ['code' => 200],
            'body'     => '{"success":true,"result":{}}',
        ],
    ];

    $api    = new CloudflareApi('me@example.test', 'key', 'z1');
    $failed = $api->applyAll(['dev_mode' => true, 'cache_level' => 'simplified']);

    $settingUrls = $calledUrls();
    wpp_ok(
        ! in_array('https://api.cloudflare.com/client/v4/zones/z1/settings/minify', $settingUrls, true),
        'the retired auto minify setting is not pushed'
    );
    wpp_ok(isset($failed['cache_level']), 'a rejected zone setting is reported back');
    wpp_ok(! isset($failed['brotli']), 'an accepted zone setting is not reported as failed');

    WPP_Test_State::$httpCalls = [];
    $api->applyAll(['dev_mode' => true], false);
    wpp_ok(
        ! in_array('https://api.cloudflare.com/client/v4/zones/z1/settings/development_mode', $calledUrls(), true),
        'development mode is skipped when it has not changed'
    );

    wpp_same(
        'Could not route (7003)',
        CloudflareApi::errorMessages([['code' => 7003, 'message' => 'Could not route']])[0] ?? '',
        'cloudflare error objects are flattened to readable strings'
    );

    // ---- Cloudflare module: dev mode pushed once, failures stored -----------

    WPP_Test_State::$httpCalls = [];
    WPP_Test_State::$httpResponses = [
        'https://api.cloudflare.com/client/v4/zones/z1/settings/development_mode' => [
            'response' => ['code' => 200],
            'body'     => '{"success":true,"result":{}}',
        ],
    ];
    WPP_Test_State::$options['wpp_cloudflare'] = [
        'enabled'  => true,
        'email'    => 'me@example.test',
        'api_key'  => 'key',
        'zone_id'  => 'z1',
        'dev_mode' => true,
    ];

    $container = new Container();
    $container->instance(SettingsService::class, new SettingsService());
    (new CloudflareModule())->boot($container);

    do_action('wpp.settings.updated', 'cloudflare', []);
    do_action('wpp.settings.updated', 'cloudflare', []);

    $devCalls = count(array_filter(
        $calledUrls(),
        static fn (string $url): bool => str_ends_with($url, '/settings/development_mode')
    ));
    wpp_same(1, $devCalls, 'development mode is not re-asserted on every save');
    wpp_ok(
        get_option(CloudflareModule::ERRORS_OPTION, []) !== [],
        'zone settings the API rejected are recorded for the UI'
    );

    // ---- Cloudflare REST: failures carry a message, purge takes posted URLs --

    $settings   = new SettingsService();
    $controller = new CloudflareController($settings);

    $result = new ReflectionMethod($controller, 'result');
    $result->setAccessible(true);
    $response = $result->invoke($controller, [
        'success' => false,
        'errors'  => [['code' => 7003, 'message' => 'Could not route to /zones/xxx']],
    ]);
    wpp_same(400, $response->get_status(), 'a rejected purge is a 400');
    wpp_contains('Could not route', (string) $response->get_data()['message'], 'the reason travels in message');

    WPP_Test_State::$httpCalls = [];
    $controller->purgeCustom(new WP_REST_Request(['urls' => ['https://example.test/style.css']]));
    $body = json_decode((string) (WPP_Test_State::$httpCalls[0]['args']['body'] ?? ''), true);
    wpp_ok(
        in_array('https://example.test/style.css', (array) ($body['files'] ?? []), true),
        'the URLs shown in the field are the ones purged'
    );

    // ---- Prefetch fallback obeys the configured exclusions ------------------

    $module = new PrefetchModule();
    $config = new ReflectionProperty($module, 'config');
    $config->setAccessible(true);
    $config->setValue($module, ['mode' => 'prefetch', 'eagerness' => 'conservative', 'exclude' => ['/cart/*']]);

    ob_start();
    $module->fallback();
    $out = ob_get_clean();

    preg_match('/var c=(\{.*?\});/', $out, $m);
    $fallbackConfig = json_decode($m[1] ?? '', true);
    wpp_ok(is_array($fallbackConfig), 'the fallback is handed its configuration');
    wpp_same('pointerdown', $fallbackConfig['event'] ?? '', 'eagerness maps to the fallback trigger');
    wpp_ok(in_array('^\\/cart\\/.*$', $fallbackConfig['exclude'] ?? [], true), 'user exclusions reach the fallback');
    wpp_ok(in_array('^\\/wp-admin\\/.*$', $fallbackConfig['exclude'] ?? [], true), 'admin URLs excluded in the fallback');

    unset($GLOBALS['wpdb']);
};
