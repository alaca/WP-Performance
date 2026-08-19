<?php

/**
 * Standalone test bootstrap: enough of the WordPress API to exercise the
 * plugin's pure logic without a database or a WordPress install.
 *
 * Stubs mirror real WordPress behavior where a test depends on it (notably
 * sanitize_text_field collapsing whitespace and stripping tags).
 */

declare(strict_types=1);

define('WPP_TEST_ROOT', dirname(__DIR__));
define('WPP_TEST_TMP', sys_get_temp_dir() . '/wpp-tests-' . getmypid() . '/');

if (! defined('WPP_DIR')) {
    define('WPP_DIR', WPP_TEST_ROOT . '/');
}
if (! defined('WPP_VERSION')) {
    define('WPP_VERSION', '2.0.0');
}
if (! defined('WPP_SLUG')) {
    define('WPP_SLUG', 'wp-performance');
}
if (! defined('WPP_URL')) {
    define('WPP_URL', 'https://example.test/wp-content/plugins/wp-performance/');
}
if (! defined('WPP_CACHE_DIR')) {
    define('WPP_CACHE_DIR', WPP_TEST_TMP . 'cache/wpp-cache/');
}
if (! defined('WPP_CACHE_URL')) {
    define('WPP_CACHE_URL', 'https://example.test/wp-content/cache/wpp-cache/');
}
if (! defined('ABSPATH')) {
    define('ABSPATH', WPP_TEST_TMP . 'wp/');
}
if (! defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', WPP_TEST_TMP . 'wp/wp-content');
}

@mkdir(WPP_CACHE_DIR, 0777, true);
@mkdir(WP_CONTENT_DIR, 0777, true);

register_shutdown_function(static function (): void {
    $dir = WPP_TEST_TMP;
    if (! is_dir($dir)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($dir);
});

// ---------------------------------------------------------------- test state

final class WPP_Test_State
{
    /** @var array<string, mixed> */
    public static array $options = [];
    /** @var array<string, array<int, callable>> */
    public static array $hooks = [];
    /** @var array<int, array<string, mixed>> */
    public static array $postmeta = [];
    /** @var array<string, mixed> */
    public static array $objectCache = [];
    public static bool $extObjectCache = false;
    public static bool $loggedIn = false;
    public static bool $mobile = false;
    public static bool $multisite = false;
    public static string $role = 'subscriber';
    /** @var list<array{url:string, args:array}> */
    public static array $httpCalls = [];
    /** @var array<string, mixed> */
    public static array $httpResponses = [];

    public static function reset(): void
    {
        self::$options = [];
        self::$hooks = [];
        self::$postmeta = [];
        self::$objectCache = [];
        self::$extObjectCache = false;
        self::$loggedIn = false;
        self::$mobile = false;
        self::$multisite = false;
        self::$role = 'subscriber';
        self::$httpCalls = [];
        self::$httpResponses = [];
        $_POST = [];
        $_GET = [];
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_HOST'] = 'example.test';
    }
}

// ------------------------------------------------------------- options / meta

function get_option($key, $default = false)
{
    // Mirrors core: a key listed in 'notoptions' short-circuits to the default
    // without reading storage, which is how a value written by another process
    // stays invisible for the rest of the request.
    $notoptions = wp_cache_get('notoptions', 'options');
    if (is_array($notoptions) && isset($notoptions[$key])) {
        return $default;
    }

    return array_key_exists($key, WPP_Test_State::$options)
        ? WPP_Test_State::$options[$key]
        : $default;
}

function wpp_test_forget_notoption(string $key): void
{
    $notoptions = wp_cache_get('notoptions', 'options');
    if (is_array($notoptions) && isset($notoptions[$key])) {
        unset($notoptions[$key]);
        wp_cache_set('notoptions', $notoptions, 'options');
    }
}

function update_option($key, $value, $autoload = null): bool
{
    WPP_Test_State::$options[$key] = $value;
    wpp_test_forget_notoption($key);
    return true;
}

function add_option($key, $value = '', $deprecated = '', $autoload = 'yes'): bool
{
    if (array_key_exists($key, WPP_Test_State::$options)) {
        return false;
    }
    WPP_Test_State::$options[$key] = $value;
    wpp_test_forget_notoption($key);
    return true;
}

function delete_option($key): bool
{
    unset(WPP_Test_State::$options[$key]);

    // Mirrors core: the key is added to 'notoptions', so a later get_option in
    // the same request short-circuits to the default without a database read.
    $notoptions = wp_cache_get('notoptions', 'options');
    $notoptions = is_array($notoptions) ? $notoptions : [];
    $notoptions[$key] = true;
    wp_cache_set('notoptions', $notoptions, 'options');

    return true;
}

function update_post_meta($postId, $key, $value): bool
{
    WPP_Test_State::$postmeta[(int) $postId][$key] = $value;
    return true;
}

function get_post_meta($postId, $key = '', $single = false)
{
    $meta = WPP_Test_State::$postmeta[(int) $postId][$key] ?? '';
    return $single ? $meta : ($meta === '' ? [] : [$meta]);
}

function delete_post_meta($postId, $key, $value = ''): bool
{
    unset(WPP_Test_State::$postmeta[(int) $postId][$key]);
    return true;
}

function get_post_status($postId)
{
    return 'publish';
}

// ------------------------------------------------------------- hooks / events

function add_action($hook, $callback, $priority = 10, $args = 1): bool
{
    WPP_Test_State::$hooks[$hook][] = $callback;
    return true;
}

function add_filter($hook, $callback, $priority = 10, $args = 1): bool
{
    WPP_Test_State::$hooks[$hook][] = $callback;
    return true;
}

function do_action($hook, ...$args): void
{
    foreach (WPP_Test_State::$hooks[$hook] ?? [] as $cb) {
        $cb(...$args);
    }
}

function apply_filters($hook, $value, ...$args)
{
    foreach (WPP_Test_State::$hooks[$hook] ?? [] as $cb) {
        $value = $cb($value, ...$args);
    }
    return $value;
}

function remove_action($hook, $callback, $priority = 10): bool
{
    return true;
}

function remove_filter($hook, $callback, $priority = 10): bool
{
    return true;
}

function has_action($hook, $callback = false)
{
    return ! empty(WPP_Test_State::$hooks[$hook]);
}

function did_action($hook): int
{
    return 0;
}

function wp_clear_scheduled_hook($hook, $args = []): void
{
}

function wp_next_scheduled($hook, $args = [])
{
    return false;
}

function wp_schedule_event($timestamp, $recurrence, $hook, $args = [])
{
    return true;
}

// ------------------------------------------------------------- sanitization

function sanitize_text_field($str)
{
    if (! is_string($str)) {
        return $str;
    }
    $str = strip_tags($str);
    $str = preg_replace('/[\r\n\t ]+/', ' ', $str);
    return trim((string) $str);
}

function sanitize_textarea_field($str)
{
    if (! is_string($str)) {
        return $str;
    }
    return trim(strip_tags($str));
}

if (! function_exists('wp_check_invalid_utf8')) {
    function wp_check_invalid_utf8($str, $strip = false)
    {
        $str = (string) $str;
        if ($str === '') {
            return '';
        }
        return preg_match('//u', $str) === 1 ? $str : '';
    }
}

function sanitize_key($key)
{
    return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $key));
}

function sanitize_file_name($name)
{
    return preg_replace('/[^A-Za-z0-9_\.\-]/', '', (string) $name);
}

function esc_url($url)
{
    $url = trim((string) $url);
    if ($url !== '' && ! preg_match('#^(https?:|//|/|\#)#i', $url)) {
        return '';
    }
    return str_replace(['"', "'", '<', '>'], ['&quot;', '&#039;', '&lt;', '&gt;'], $url);
}

function esc_url_raw($url)
{
    return (string) $url;
}

function esc_attr($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function esc_html($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function wp_json_encode($data, $flags = 0)
{
    return json_encode($data, $flags);
}

function wp_unslash($value)
{
    return is_string($value) ? stripslashes($value) : $value;
}

function absint($n): int
{
    return abs((int) $n);
}

// ------------------------------------------------------------- environment

function is_admin(): bool
{
    return false;
}

function wp_doing_ajax(): bool
{
    return false;
}

function wp_doing_cron(): bool
{
    return false;
}

function is_user_logged_in(): bool
{
    return WPP_Test_State::$loggedIn;
}

function wp_is_mobile(): bool
{
    return WPP_Test_State::$mobile;
}

function is_multisite(): bool
{
    return WPP_Test_State::$multisite;
}

function get_current_blog_id(): int
{
    return 1;
}

function wp_get_current_user()
{
    return (object) ['roles' => [WPP_Test_State::$role], 'ID' => WPP_Test_State::$loggedIn ? 1 : 0];
}

if (! function_exists('get_current_user_id')) {
    function get_current_user_id(): int
    {
        return WPP_Test_State::$loggedIn ? 1 : 0;
    }
}

if (! function_exists('is_super_admin')) {
    function is_super_admin($userId = false): bool
    {
        return WPP_Test_State::$role === 'superadmin';
    }
}

function current_user_can($cap): bool
{
    return true;
}

function current_time($type, $gmt = 0)
{
    return $type === 'timestamp' ? time() : date('Y-m-d H:i:s');
}

function home_url($path = '')
{
    return 'https://example.test' . $path;
}

function site_url($path = '')
{
    return 'https://example.test' . $path;
}

function admin_url($path = '')
{
    return 'https://example.test/wp-admin/' . ltrim((string) $path, '/');
}

function wp_login_url($redirect = '')
{
    return 'https://example.test/wp-login.php';
}

function content_url($path = '')
{
    return 'https://example.test/wp-content/' . ltrim((string) $path, '/');
}

function plugin_dir_path($file)
{
    return dirname($file) . '/';
}

function plugin_dir_url($file)
{
    return WPP_URL;
}

function wp_mkdir_p($dir): bool
{
    return is_dir($dir) || mkdir($dir, 0777, true);
}

function wp_using_ext_object_cache($using = null): bool
{
    return WPP_Test_State::$extObjectCache;
}

function wp_cache_get($key, $group = '', $force = false, &$found = null)
{
    $k = $group . ':' . $key;
    $found = array_key_exists($k, WPP_Test_State::$objectCache);
    return $found ? WPP_Test_State::$objectCache[$k] : false;
}

function wp_cache_set($key, $value, $group = '', $expire = 0): bool
{
    WPP_Test_State::$objectCache[$group . ':' . $key] = $value;
    return true;
}

function wp_cache_delete($key, $group = ''): bool
{
    unset(WPP_Test_State::$objectCache[$group . ':' . $key]);
    return true;
}

function is_ssl(): bool
{
    return true;
}

function is_404(): bool
{
    return false;
}

function is_search(): bool
{
    return false;
}

function is_feed(): bool
{
    return false;
}

function is_preview(): bool
{
    return false;
}

function is_trackback(): bool
{
    return false;
}

function is_singular($types = ''): bool
{
    return false;
}

function get_queried_object_id(): int
{
    return 0;
}

function wp_is_post_revision($id)
{
    return false;
}

function wp_is_post_autosave($id)
{
    return false;
}

// ------------------------------------------------------------- shortcodes

function add_shortcode($tag, $callback): void
{
}

function do_shortcode($content)
{
    return $content;
}

function shortcode_atts($pairs, $atts, $shortcode = '')
{
    $atts = (array) $atts;
    $out = [];
    foreach ($pairs as $name => $default) {
        $out[$name] = array_key_exists($name, $atts) ? $atts[$name] : $default;
    }
    return $out;
}

function serialize_block($block)
{
    return json_encode($block);
}

// ------------------------------------------------------------- http

function wp_remote_get($url, $args = [])
{
    WPP_Test_State::$httpCalls[] = ['url' => $url, 'args' => $args];
    return WPP_Test_State::$httpResponses[$url]
        ?? ['response' => ['code' => 200], 'body' => '', 'headers' => []];
}

function wp_remote_post($url, $args = [])
{
    return wp_remote_get($url, $args);
}

function wp_remote_request($url, $args = [])
{
    return wp_remote_get($url, $args);
}

function wp_remote_retrieve_body($response)
{
    return is_array($response) ? ($response['body'] ?? '') : '';
}

function wp_remote_retrieve_response_code($response)
{
    return is_array($response) ? ($response['response']['code'] ?? 0) : 0;
}

function is_wp_error($thing): bool
{
    return $thing instanceof WP_Error;
}

class WP_Error
{
    public function __construct(public $code = '', public $message = '', public $data = null)
    {
    }

    public function get_error_message()
    {
        return $this->message;
    }
}

function __($text, $domain = null)
{
    return $text;
}

function _n($single, $plural, $number, $domain = null)
{
    return $number === 1 ? $single : $plural;
}

function _e($text, $domain = null): void
{
    echo $text;
}

function esc_html__($text, $domain = null)
{
    return $text;
}

function load_plugin_textdomain($domain, $abs = false, $path = ''): bool
{
    return true;
}

function plugin_basename($file)
{
    return basename(dirname($file)) . '/' . basename($file);
}

// ------------------------------------------------------------- REST

if (! class_exists('WP_REST_Response')) {
    class WP_REST_Response
    {
        public function __construct(public $data = null, public int $status = 200)
        {
        }

        public function get_data()
        {
            return $this->data;
        }

        public function get_status(): int
        {
            return $this->status;
        }
    }
}

if (! class_exists('WP_REST_Server')) {
    class WP_REST_Server
    {
        const READABLE = 'GET';
        const CREATABLE = 'POST';
        const EDITABLE = 'POST, PUT, PATCH';
        const DELETABLE = 'DELETE';
    }
}

if (! class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        /** @param array<string, mixed> $params */
        public function __construct(private array $params = [])
        {
        }

        public function get_param($key)
        {
            return $this->params[$key] ?? null;
        }

        public function set_param($key, $value): void
        {
            $this->params[$key] = $value;
        }

        public function get_json_params()
        {
            return $this->params;
        }

        public function get_body_params()
        {
            return $this->params;
        }

        public function offsetGet($key)
        {
            return $this->params[$key] ?? null;
        }

        public function __get($key)
        {
            return $this->params[$key] ?? null;
        }
    }
}

$GLOBALS['wpp_registered_routes'] = [];

function register_rest_route($namespace, $route, $args = [], $override = false): bool
{
    $GLOBALS['wpp_registered_routes'][$namespace . $route] = $args;
    return true;
}

// ------------------------------------------------------------- assertions

final class WPP_Assert
{
    public static int $passed = 0;
    /** @var list<string> */
    public static array $failures = [];

    public static function ok(bool $cond, string $message): void
    {
        if ($cond) {
            self::$passed++;
            return;
        }
        self::$failures[] = $message;
    }

    public static function same($expected, $actual, string $message): void
    {
        if ($expected === $actual) {
            self::$passed++;
            return;
        }
        self::$failures[] = sprintf(
            "%s\n      expected: %s\n      actual:   %s",
            $message,
            var_export($expected, true),
            var_export($actual, true)
        );
    }

    public static function contains(string $needle, string $haystack, string $message): void
    {
        self::ok(str_contains($haystack, $needle), $message . "\n      missing: " . $needle);
    }

    public static function notContains(string $needle, string $haystack, string $message): void
    {
        self::ok(! str_contains($haystack, $needle), $message . "\n      unexpected: " . $needle);
    }
}

function wpp_ok(bool $c, string $m): void
{
    WPP_Assert::ok($c, $m);
}

function wpp_same($e, $a, string $m): void
{
    WPP_Assert::same($e, $a, $m);
}

function wpp_contains(string $n, string $h, string $m): void
{
    WPP_Assert::contains($n, $h, $m);
}

function wpp_not_contains(string $n, string $h, string $m): void
{
    WPP_Assert::notContains($n, $h, $m);
}

require WPP_TEST_ROOT . '/vendor/autoload.php';

WPP_Test_State::reset();
