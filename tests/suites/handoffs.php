<?php

declare(strict_types=1);

use WPP\Cache\FragmentStore;
use WPP\Settings\SettingsService;

/**
 * The last cross-file items from the review: each was raised by an agent that
 * did not own the file it had to change, so none had a home until now.
 */
return static function (): void {
    $settings = new SettingsService();

    // ---- numeric settings are stored as integers, whatever the form sent ----
    // An emptied number field posts '', and a string reaching RuntimeSettings
    // becomes a cache lifetime of zero.
    $settings->update('cache', ['clear_time' => '', 'clear_unit' => '86400']);
    $cache = $settings->get('cache');
    wpp_same(10, $cache['clear_time'], 'emptied number falls back to the default');
    wpp_same(86400, $cache['clear_unit'], 'numeric string is coerced to int');
    wpp_ok(is_int($cache['clear_time']), 'clear_time is an int');
    wpp_ok(is_int($cache['clear_unit']), 'clear_unit is an int');

    $settings->update('cache', ['clear_time' => '25']);
    wpp_same(25, $settings->get('cache')['clear_time'], 'numeric string keeps its value');

    $settings->update('cache', ['clear_time' => 0]);
    wpp_same(10, $settings->get('cache')['clear_time'], 'zero is rejected where the default is positive');

    $settings->update('cache', ['clear_time' => -5]);
    wpp_same(10, $settings->get('cache')['clear_time'], 'negative is rejected');

    $settings->update('cache', ['block_cache_ttl' => '']);
    wpp_same(3600, $settings->get('cache')['block_cache_ttl'], 'block cache ttl falls back');

    // A default of 0 means zero is legitimate for that setting.
    $settings->update('media', ['lcp_images' => 0]);
    wpp_same(0, $settings->get('media')['lcp_images'], 'zero is kept where the default is zero');
    $settings->update('media', ['lcp_images' => '3']);
    wpp_same(3, $settings->get('media')['lcp_images'], 'lcp count coerced to int');

    // The expiry the drop-in reads is now always a real number of seconds.
    $settings->update('cache', ['clear_time' => '', 'clear_unit' => 3600]);
    $c = $settings->get('cache');
    wpp_ok((int) $c['clear_time'] * (int) $c['clear_unit'] > 0, 'computed expiry is never zero');

    // Booleans and strings are untouched by the numeric path.
    $settings->update('cache', ['enabled' => true]);
    wpp_same(true, $settings->get('cache')['enabled'], 'bools unaffected');
    $settings->update('cdn', ['hostname' => 'https://cdn.example.com']);
    wpp_same('https://cdn.example.com', $settings->get('cdn')['hostname'], 'strings unaffected');

    // ---- fragment directory is not browsable ----
    $store = new FragmentStore();
    $store->set('protect-me', '<p>fragment</p>', 60);

    $dir = WPP_CACHE_DIR . 'fragments/';
    wpp_ok(is_file($dir . 'index.php'), 'fragments directory has an index.php');
    wpp_ok(is_file($dir . '.htaccess'), 'fragments directory has an .htaccess');
    wpp_contains('Require all denied', (string) file_get_contents($dir . '.htaccess'), 'htaccess denies direct access');
    wpp_contains('Options -Indexes', (string) file_get_contents($dir . '.htaccess'), 'directory listing disabled');

    // Protection must not break storage.
    wpp_same('<p>fragment</p>', $store->get('protect-me'), 'fragment still round trips');
};
