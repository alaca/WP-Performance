<?php

declare(strict_types=1);

use WPP\Cache\FragmentCache;
use WPP\Cache\FragmentStore;
use WPP\Cache\RuntimeSettings;
use WPP\Settings\SettingsService;

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

if (! function_exists('wp_add_inline_style')) {
    function wp_add_inline_style($handle, $data): bool
    {
        $styles = $GLOBALS['wp_styles'] ?? null;
        if (! is_object($styles) || ! isset($styles->registered[$handle])) {
            return false;
        }
        $after = $styles->registered[$handle]->extra['after'] ?? [];
        $after[] = $data;
        $styles->registered[$handle]->extra['after'] = $after;
        return true;
    }
}

// Mirrors wp-includes/style-engine/*: the block-supports store that
// wp_render_layout_support_flag and friends push their generated CSS into.
if (! class_exists('WP_Style_Engine_CSS_Declarations')) {
    class WP_Style_Engine_CSS_Declarations
    {
        protected $declarations = [];

        public function __construct($declarations = [])
        {
            $this->add_declarations($declarations);
        }

        public function add_declarations($declarations)
        {
            foreach ((array) $declarations as $property => $value) {
                $this->declarations[$property] = $value;
            }
            return $this;
        }

        public function get_declarations()
        {
            return $this->declarations;
        }
    }
}

if (! class_exists('WP_Style_Engine_CSS_Rule')) {
    class WP_Style_Engine_CSS_Rule
    {
        protected $selector;
        protected $declarations;
        protected $rules_group;

        public function __construct($selector = '', $declarations = [], $rules_group = '')
        {
            $this->selector    = $selector;
            $this->declarations = new WP_Style_Engine_CSS_Declarations();
            $this->rules_group = (string) $rules_group;
            $this->add_declarations($declarations);
        }

        public function add_declarations($declarations)
        {
            $array = is_array($declarations) ? $declarations : $declarations->get_declarations();
            $this->declarations->add_declarations($array);
            return $this;
        }

        public function get_declarations()
        {
            return $this->declarations;
        }

        public function get_selector()
        {
            return $this->selector;
        }

        public function get_rules_group()
        {
            return $this->rules_group;
        }
    }
}

if (! class_exists('WP_Style_Engine_CSS_Rules_Store')) {
    class WP_Style_Engine_CSS_Rules_Store
    {
        protected static $stores = [];
        protected $name = '';
        protected $rules = [];

        public static function get_store($store_name = 'default')
        {
            if (! isset(static::$stores[$store_name])) {
                static::$stores[$store_name] = new static();
                static::$stores[$store_name]->name = $store_name;
            }
            return static::$stores[$store_name];
        }

        public static function remove_all_stores(): void
        {
            static::$stores = [];
        }

        public function get_all_rules()
        {
            return $this->rules;
        }

        public function add_rule($selector, $rules_group = '')
        {
            $key = $rules_group !== '' ? "$rules_group $selector" : $selector;
            if (empty($this->rules[$key])) {
                $this->rules[$key] = new WP_Style_Engine_CSS_Rule($selector, [], $rules_group);
            }
            return $this->rules[$key];
        }
    }
}

if (! function_exists('wp_style_engine_get_stylesheet_from_css_rules')) {
    function wp_style_engine_get_stylesheet_from_css_rules($css_rules, $options = [])
    {
        $context = $options['context'] ?? null;
        foreach ((array) $css_rules as $rule) {
            if (empty($rule['selector']) || empty($rule['declarations']) || ! is_array($rule['declarations'])) {
                continue;
            }
            if (! empty($context)) {
                WP_Style_Engine_CSS_Rules_Store::get_store($context)
                    ->add_rule($rule['selector'], (string) ($rule['rules_group'] ?? ''))
                    ->add_declarations($rule['declarations']);
            }
        }
        return '';
    }
}

return static function (): void {
    $settings = new SettingsService();
    $settings->update('cache', ['block_cache_enabled' => true, 'block_cache_ttl' => 3600]);

    // ---------------------------------------------------------------- finding 1
    // A network shares one fragments/ tree, so a sibling blog's entry must be
    // unreachable and must survive this blog's flush.

    WPP_Test_State::$multisite = true;
    $store    = new FragmentStore();
    $gen      = (int) get_option('wpp_fragment_gen', 0);
    $identity = 'k=sidebar|u=/|l=0';

    $unscoped     = md5($gen . '|' . $identity);
    $siblingFile  = WPP_CACHE_DIR . 'fragments/' . substr($unscoped, 0, 2) . '/' . $unscoped . '.html.php';
    wp_mkdir_p(dirname($siblingFile));
    file_put_contents($siblingFile, RuntimeSettings::GUARD . "0\n" . '<b>SITE A private sidebar</b>');

    wpp_same(null, $store->get($identity), 'network: a sibling blog fragment is not readable from this blog');

    $store->set($identity, '<b>SITE B sidebar</b>', 60);
    wpp_same('<b>SITE B sidebar</b>', $store->get($identity), 'network: this blog reads its own fragment');
    wpp_same(
        RuntimeSettings::GUARD . "0\n" . '<b>SITE A private sidebar</b>',
        (string) file_get_contents($siblingFile),
        'network: writing did not overwrite the sibling entry'
    );

    $scoped   = md5($gen . '|s' . get_current_blog_id() . '|' . $identity);
    $ownFile  = WPP_CACHE_DIR . 'fragments/' . substr($scoped, 0, 2)
        . '/s' . get_current_blog_id() . '-' . $scoped . '.html.php';
    wpp_ok(is_file($ownFile), 'network: the fragment key and file name carry the blog id');

    $foreignKey  = md5('another blog entry');
    $foreignFile = WPP_CACHE_DIR . 'fragments/' . substr($foreignKey, 0, 2) . '/s2-' . $foreignKey . '.html.php';
    wp_mkdir_p(dirname($foreignFile));
    file_put_contents($foreignFile, RuntimeSettings::GUARD . "0\n" . '<b>blog 2 fragment</b>');

    $store->flush();
    wpp_same(null, $store->get($identity), 'network: flush drops this blog fragments');
    wpp_ok(! is_file($ownFile), 'network: flush deletes this blog files');
    wpp_ok(is_file($foreignFile), 'network: flush leaves other blogs files in place');

    @unlink($foreignFile);
    @unlink($siblingFile);

    // Single site keeps the unscoped path the rest of the suite relies on.
    WPP_Test_State::$multisite = false;
    $single = new FragmentStore();
    $single->set('single-id', 'S', 60);
    $singleGen = (int) get_option('wpp_fragment_gen', 0);
    $singleKey = md5($singleGen . '|single-id');
    wpp_ok(
        is_file(WPP_CACHE_DIR . 'fragments/' . substr($singleKey, 0, 2) . '/' . $singleKey . '.html.php'),
        'single site: the fragment path is unscoped'
    );

    // ---------------------------------------------------------------- finding 2
    // Two members with the same role must not share one entry.

    WPP_Test_State::$loggedIn = true;
    WPP_Test_State::$role     = 'subscriber';
    $_SERVER['REQUEST_URI']   = '/members/dashboard/';

    $fc   = new FragmentCache($settings, new FragmentStore());
    $opts = ['ttl' => 3600, 'vary_url' => true, 'vary_role' => true, 'vary_device' => true];

    wpp_contains('uid=' . get_current_user_id(), $fc->identity('dash', $opts), 'a logged-in identity carries the user id');

    // The entry a role-only key produces: one member fills it, the next reads it.
    (new FragmentStore())->set(
        'k=dash|u=/members/dashboard/|l=1|r=subscriber|d=d',
        'Welcome back, Alice (nonce=abc123)',
        60
    );

    ob_start();
    $miss = $fc->start('dash', $opts);
    if ($miss) {
        echo 'Welcome back, Bob (nonce=def456)';
        $fc->end();
    }
    $served = (string) ob_get_clean();

    wpp_ok($miss, 'another member with the same role does not hit the first member entry');
    wpp_not_contains('Alice', $served, 'no other member markup is served');

    WPP_Test_State::$loggedIn = false;
    wpp_not_contains('uid=', $fc->identity('dash', $opts), 'an anonymous identity carries no user id');

    // Same run, two real user ids: get_current_user_id() is pinned in the shared
    // bootstrap, so the two-member case runs in a child process that defines it.
    $probe = WPP_TEST_TMP . 'two-members.php';
    file_put_contents($probe, <<<'PHP'
<?php
$GLOBALS['wpp_probe_uid'] = 0;
function get_current_user_id(): int
{
    return (int) $GLOBALS['wpp_probe_uid'];
}
require getenv('WPP_PROBE_BOOTSTRAP');

WPP_Test_State::$loggedIn = true;
WPP_Test_State::$role     = 'subscriber';
$_SERVER['REQUEST_URI']   = '/members/dashboard/';

$settings = new WPP\Settings\SettingsService();
$settings->update('cache', ['block_cache_enabled' => true, 'block_cache_ttl' => 3600]);
$fc   = new WPP\Cache\FragmentCache($settings, new WPP\Cache\FragmentStore());
$opts = ['ttl' => 3600, 'vary_url' => true, 'vary_role' => true, 'vary_device' => true];

$GLOBALS['wpp_probe_uid'] = 11;
$aliceIdentity = $fc->identity('dash', $opts);
ob_start();
if ($fc->start('dash', $opts)) {
    echo 'Welcome back, Alice (nonce=abc123)';
    $fc->end();
}
ob_end_clean();

$GLOBALS['wpp_probe_uid'] = 22;
$bobIdentity = $fc->identity('dash', $opts);
ob_start();
$bobMiss = $fc->start('dash', $opts);
if ($bobMiss) {
    echo 'Welcome back, Bob (nonce=def456)';
    $fc->end();
}
$bobSees = (string) ob_get_clean();

echo json_encode([
    'alice_identity' => $aliceIdentity,
    'bob_identity'   => $bobIdentity,
    'bob_miss'       => $bobMiss,
    'bob_sees'       => $bobSees,
]);
PHP);

    $command = 'WPP_PROBE_BOOTSTRAP=' . escapeshellarg(WPP_TEST_ROOT . '/tests/bootstrap.php')
        . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($probe) . ' 2>&1';
    $raw  = (string) shell_exec($command);
    $data = json_decode($raw, true);

    wpp_ok(is_array($data), 'two-member probe ran: ' . substr($raw, 0, 300));
    if (is_array($data)) {
        wpp_ok($data['alice_identity'] !== $data['bob_identity'], 'two members with one role get different identities');
        wpp_ok($data['bob_miss'] === true, 'the second member misses the cache');
        wpp_not_contains('Alice', (string) $data['bob_sees'], 'the second member is not served the first member markup');
        wpp_contains('Bob', (string) $data['bob_sees'], 'the second member gets his own render');
    }

    // ---------------------------------------------------------------- finding 3
    // Block-support CSS must survive a hit: preRenderBlock skips the subtree, so
    // nothing pushes layout/elements/duotone rules into the style engine again.

    $GLOBALS['wp_styles']  = (object) ['queue' => [], 'registered' => ['wp-block-group' => (object) ['extra' => []]]];
    $GLOBALS['wp_scripts'] = (object) ['queue' => []];
    WP_Style_Engine_CSS_Rules_Store::remove_all_stores();

    $blockCache = new FragmentCache($settings, new FragmentStore());
    $block      = [
        'blockName' => 'wpp/cache',
        'attrs'     => ['ttl' => 60],
        'innerHTML' => '<div class="wp-container-core-group-is-layout-abc"></div>',
    ];
    $layout = '.wp-container-core-group-is-layout-abc';
    $rendered = '<div class="wp-block-group ' . ltrim($layout, '.') . '">Row</div>';

    wpp_same(null, $blockCache->preRenderBlock(null, $block), 'block miss lets WordPress render the subtree');

    // What the Row block does while rendering.
    wp_enqueue_style('wp-block-group');
    wp_add_inline_style('wp-block-group', '.wp-block-group{color:red}');
    wp_style_engine_get_stylesheet_from_css_rules(
        [['selector' => $layout, 'declarations' => ['display' => 'flex', 'flex-wrap' => 'wrap']]],
        ['context' => 'block-supports']
    );
    $blockCache->renderBlock($rendered, $block);

    // A later request: nothing of the first render survives in memory.
    $GLOBALS['wp_styles']  = (object) ['queue' => [], 'registered' => ['wp-block-group' => (object) ['extra' => []]]];
    $GLOBALS['wp_scripts'] = (object) ['queue' => []];
    WP_Style_Engine_CSS_Rules_Store::remove_all_stores();

    wpp_same($rendered, $blockCache->preRenderBlock(null, $block), 'block hit replays the stored markup');

    $rules = WP_Style_Engine_CSS_Rules_Store::get_store('block-supports')->get_all_rules();
    wpp_ok(isset($rules[$layout]), 'hit: the layout rule is back in the block-supports store');
    if (isset($rules[$layout])) {
        wpp_same(
            ['display' => 'flex', 'flex-wrap' => 'wrap'],
            $rules[$layout]->get_declarations()->get_declarations(),
            'hit: the layout declarations are intact'
        );
    }

    wpp_ok(in_array('wp-block-group', $GLOBALS['wp_styles']->queue, true), 'hit: the style handle is enqueued again');
    wpp_same(
        ['.wp-block-group{color:red}'],
        $GLOBALS['wp_styles']->registered['wp-block-group']->extra['after'] ?? [],
        'hit: inline style data is added again'
    );

    unset($GLOBALS['wp_styles'], $GLOBALS['wp_scripts']);
    WP_Style_Engine_CSS_Rules_Store::remove_all_stores();
    (new FragmentStore())->flush();
};
