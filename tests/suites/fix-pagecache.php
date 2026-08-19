<?php

declare(strict_types=1);

use WPP\Cache\CacheStore;
use WPP\Frontend\PageCache;
use WPP\Server\ServerRules;
use WPP\Settings\SettingsService;

if (! function_exists('is_embed')) {
    function is_embed(): bool
    {
        return false;
    }
}

/**
 * The write path and the generated server rules: what may reach the shared
 * anonymous cache file, and what nginx may be told to hand back without PHP.
 */
return static function (): void {
    // ------------------------------------------------ write path vs serve path

    $applies = static function (): bool {
        $page   = new PageCache(new SettingsService(), new CacheStore());
        $method = new ReflectionMethod($page, 'cacheApplies');
        $method->setAccessible(true);

        return (bool) $method->invoke($page);
    };

    $_SERVER['REQUEST_URI'] = '/hello-world/';
    $_SERVER['HTTP_HOST']   = 'example.test';
    $_COOKIE = [];
    update_option('wpp_cache', ['enabled' => true]);

    wpp_ok($applies(), 'caching applies on a plain anonymous request');

    // A commenter's render carries the name, email and URL core prefills the
    // comment form with, plus their own "awaiting moderation" notices.
    $_COOKIE = ['comment_author_77b70d4d' => 'Mallory Evil'];
    wpp_ok(! $applies(), 'a comment_author_ cookie keeps the render out of the shared cache');

    $_COOKIE = ['comment_author_email_77b70d4d' => 'mallory@secret.example'];
    wpp_ok(! $applies(), 'a comment_author_email_ cookie keeps the render out of the shared cache');

    $_COOKIE = ['comment_author_url_77b70d4d' => 'https://mallory.example'];
    wpp_ok(! $applies(), 'a comment_author_url_ cookie keeps the render out of the shared cache');

    // Contract: every cookie prefix the drop-in refuses to serve is a cookie
    // prefix the writer must refuse to write, or the render belonging to that
    // session becomes the copy handed to everyone else.
    $dropin = (string) file_get_contents(WPP_TEST_ROOT . '/advanced-cache.php');
    $found  = preg_match('/preg_match\(\'\/\^\(([^)]+)\)\//', $dropin, $m);
    wpp_same(1, $found, 'the drop-in cookie bypass regex is readable from the drop-in');

    foreach (explode('|', $m[1] ?? '') as $prefix) {
        $prefix = str_replace('\\', '', $prefix);
        if ($prefix === '') {
            continue;
        }

        // The writer covers this one through is_user_logged_in(), which is the
        // condition the cookie stands for.
        WPP_Test_State::$loggedIn = $prefix === 'wordpress_logged_in_';

        $_COOKIE = [$prefix . 'abc123' => 'value'];
        wpp_ok(! $applies(), 'writer refuses a session the drop-in refuses to serve: ' . $prefix);
    }

    WPP_Test_State::$loggedIn = false;
    $_COOKIE = [];
    wpp_ok($applies(), 'an ordinary visitor still populates the cache');

    // ------------------------------------------------ generated nginx rules

    $rules = new ServerRules();
    update_option('permalink_structure', '/%postname%/');

    $desktop = $rules->nginxRules(['enabled' => true]);
    wpp_contains('rewrite ^ $wpp_file last;', $desktop, 'the page cache rewrite is offered when it can be correct');
    wpp_contains('cannot compare file age', $desktop, 'the block says the expiry does not apply to it');

    // nginx would answer a phone with the desktop file and, because PHP never
    // runs, the mobile variant would never be written either.
    $mobile = $rules->nginxRules(['enabled' => true, 'mobile' => true]);
    wpp_not_contains('rewrite ^ $wpp_file last;', $mobile, 'no rewrite is generated while the mobile cache is on');
    wpp_not_contains('index.html', $mobile, 'no index.html path is generated while the mobile cache is on');
    wpp_contains('mobile cache', $mobile, 'the output says why the rewrite is missing');

    // With plain permalinks the cached file is a hash of the URL, so the
    // $request_uri path can never match anything on disk.
    update_option('permalink_structure', '');
    $plain = $rules->nginxRules(['enabled' => true]);
    wpp_not_contains('rewrite ^ $wpp_file last;', $plain, 'no rewrite is generated for plain permalinks');
    wpp_contains('plain permalinks', $plain, 'the output says why the rewrite is missing');

    update_option('permalink_structure', '/%postname%/');
    $gzip = $rules->nginxRules(['gzip' => true, 'browser_cache' => true, 'enabled' => true, 'mobile' => true]);
    wpp_contains('gzip on;', $gzip, 'gzip rules are unaffected');
    wpp_contains('expires 30d;', $gzip, 'browser cache rules are unaffected');

    // ------------------------------------------------ root .htaccess boundary

    $_SERVER['SERVER_SOFTWARE'] = 'Apache/2.4.57';
    $htaccess = ABSPATH . '.htaccess';
    @unlink($htaccess);

    $rules->apply(['browser_cache' => true]);
    wpp_ok(is_file($htaccess), 'a single site install writes the expires block');
    wpp_contains('# BEGIN WPP expire', (string) file_get_contents($htaccess), 'the block is written');

    @unlink($htaccess);
    WPP_Test_State::$multisite = true;
    WPP_Test_State::$loggedIn  = true;
    WPP_Test_State::$role      = 'administrator';

    $rules->apply(['browser_cache' => true]);
    wpp_ok(! is_file($htaccess), 'a subsite administrator does not write the network root .htaccess');

    // Deactivating on a subsite must not strip the blocks the network relies on.
    file_put_contents($htaccess, "# BEGIN WPP expire\nExpiresActive On\n# END WPP expire\n");
    $rules->removeAll();
    wpp_contains('# BEGIN WPP expire', (string) file_get_contents($htaccess), 'a subsite administrator does not strip the network root .htaccess');

    WPP_Test_State::$role = 'superadmin';
    $rules->removeAll();
    wpp_not_contains('# BEGIN WPP expire', (string) file_get_contents($htaccess), 'a super admin still clears the blocks');

    @unlink($htaccess);
    WPP_Test_State::$multisite = false;
    WPP_Test_State::$loggedIn  = false;
    WPP_Test_State::$role      = 'subscriber';
    unset($_SERVER['SERVER_SOFTWARE']);
    delete_option('permalink_structure');
};
