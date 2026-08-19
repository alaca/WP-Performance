<?php

declare(strict_types=1);

use WPP\Addons\Cloudflare\CloudflareApi;
use WPP\Media\ImageService;
use WPP\Optimize\FontHost;

if (! function_exists('add_image_size')) {
    function add_image_size($name, $width = 0, $height = 0, $crop = false): void
    {
        $GLOBALS['_wp_additional_image_sizes'][(string) $name] = [
            'width'  => (int) $width,
            'height' => (int) $height,
            'crop'   => $crop,
        ];
    }
}

if (! function_exists('remove_image_size')) {
    function remove_image_size($name): bool
    {
        if (! isset($GLOBALS['_wp_additional_image_sizes'][(string) $name])) {
            return false;
        }
        unset($GLOBALS['_wp_additional_image_sizes'][(string) $name]);
        return true;
    }
}

/**
 * Regressions for the add-on findings: locally hosted fonts, custom image
 * sizes, and the Cloudflare zone-settings push.
 */
return static function (): void {
    $fontsDir = WPP_CACHE_DIR . 'fonts/';
    $purge    = static function () use ($fontsDir): void {
        foreach (glob($fontsDir . '*') ?: [] as $file) {
            @unlink($file);
        }
    };
    $cached = static fn (string $cssUrl): string => (string) file_get_contents(
        str_replace(WPP_CACHE_URL, WPP_CACHE_DIR, $cssUrl)
    );

    // ---- A font that could not be stored locally is never cached as hosted ---

    $purge();
    $inter = 'https://fonts.googleapis.com/css2?family=Inter:wght@400;700';
    $stylesheet = '@font-face{font-family:Inter;font-weight:400;'
        . 'src:url(https://fonts.gstatic.com/s/inter/a.woff2) format("woff2");}'
        . '@font-face{font-family:Inter;font-weight:700;'
        . 'src:url(https://fonts.gstatic.com/s/inter/b.woff2) format("woff2");}';

    WPP_Test_State::$httpResponses = [
        $inter => ['response' => ['code' => 200], 'body' => $stylesheet],
        'https://fonts.gstatic.com/s/inter/a.woff2' => ['response' => ['code' => 200], 'body' => 'woff2-a'],
    ];

    $fontHost = new FontHost();
    wpp_same(null, $fontHost->localize($inter), 'a stylesheet whose fonts failed is not reported as hosted');
    wpp_same([], glob($fontsDir . '*.css') ?: [], 'a stylesheet still pointing at Google is not cached');

    // A purge clears the failure record, so the next render tries again.
    $purge();
    WPP_Test_State::$httpResponses['https://fonts.gstatic.com/s/inter/b.woff2']
        = ['response' => ['code' => 200], 'body' => 'woff2-b'];

    $hosted = $fontHost->localize($inter);
    wpp_ok(is_array($hosted), 'the stylesheet is localised once every font can be fetched');
    $css = $cached((string) ($hosted['css_url'] ?? ''));
    wpp_not_contains('fonts.gstatic.com', $css, 'the cached stylesheet serves every face locally');
    wpp_same(2, count((array) ($hosted['fonts'] ?? [])), 'both faces are preloaded from the local copy');

    WPP_Test_State::$httpCalls = [];
    $fontHost->localize($inter);
    wpp_same(0, count(WPP_Test_State::$httpCalls), 'a complete stylesheet is served from disk');

    // ---- A failed Google Fonts fetch is not repeated on every render --------

    $purge();
    WPP_Test_State::$httpCalls     = [];
    WPP_Test_State::$httpResponses = [];

    $lato = 'https://fonts.googleapis.com/css2?family=Lato';
    $fontHost->localize($lato);
    $fontHost->localize($lato);
    $fontHost->localize($lato);
    wpp_same(1, count(WPP_Test_State::$httpCalls), 'an unreachable font endpoint is not re-fetched on every render');

    // Purging the cache runs on every post update, so the record has to be
    // named like the other files a purge keeps, and stay unservable.
    $markers = glob($fontsDir . '*.php') ?: [];
    wpp_same(1, count($markers), 'the failure record is kept through a cache purge');
    wpp_contains('<?php', (string) file_get_contents((string) ($markers[0] ?? '')), 'the failure record is unservable');

    foreach (glob($fontsDir . '*') ?: [] as $file) {
        touch($file, time() - 3600);
    }
    clearstatcache();
    $fontHost->localize($lato);
    wpp_same(2, count(WPP_Test_State::$httpCalls), 'the fetch is retried once the failure record has aged out');
    $purge();

    // ---- Image sizes: the response reflects the change that just happened ---

    $GLOBALS['_wp_additional_image_sizes'] = [];
    WPP_Test_State::$options['wpp_image_sizes'] = ['hero' => ['width' => 1200, 'height' => 600, 'crop' => true]];

    $images = new ImageService();
    $images->register();

    $names = static fn (array $sizes): array => array_map(
        static fn (array $size): string => (string) $size['name'],
        $sizes
    );

    wpp_ok(in_array('hero', $names($images->definedSizes()), true), 'a stored custom size is listed');

    $images->add('promo', 300, 200, false);
    wpp_ok(
        in_array('promo', $names($images->definedSizes()), true),
        'a size added in this request is in the response the table renders'
    );

    $images->remove('hero');
    $afterRemove = $names($images->definedSizes());
    wpp_ok(! in_array('hero', $afterRemove, true), 'a removed custom size leaves the response without a reload');
    wpp_ok(in_array('promo', $afterRemove, true), 'removing one size keeps the others');

    $images->remove('thumbnail');
    wpp_ok(! in_array('thumbnail', $names($images->definedSizes()), true), 'a removed core size leaves the response');

    $images->restore();
    $restored = $names($images->definedSizes());
    wpp_ok(in_array('thumbnail', $restored, true), 'restoring defaults brings the core sizes back');
    wpp_ok(! in_array('promo', $restored, true), 'restoring defaults drops the custom sizes from the response');

    unset($GLOBALS['_wp_additional_image_sizes']);

    // ---- Cloudflare: a settings save cannot outlive the request ------------

    WPP_Test_State::$httpCalls     = [];
    WPP_Test_State::$httpResponses = [];

    $api    = new CloudflareApi('me@example.test', 'key', 'z1');
    $failed = $api->applyAll(['dev_mode' => true]);

    wpp_same(5, count(WPP_Test_State::$httpCalls), 'every zone setting is pushed when there is time for it');
    $slow = array_filter(
        WPP_Test_State::$httpCalls,
        static fn (array $call): bool => (float) ($call['args']['timeout'] ?? 0) > 5
    );
    wpp_same([], $slow, 'no zone setting push can block the save request for 15 seconds');

    add_filter('wpp.cloudflare.push_budget', static fn (): int => 0);
    WPP_Test_State::$httpCalls = [];
    $failed = $api->applyAll(['dev_mode' => true]);
    wpp_same(0, count(WPP_Test_State::$httpCalls), 'no zone setting is pushed once the time budget is spent');
    wpp_same(5, count($failed), 'settings the budget skipped are reported instead of silently dropped');
    wpp_contains('in time', (string) ($failed['brotli'] ?? ''), 'a skipped setting says why it was not applied');
    wpp_ok(isset($failed['development_mode']), 'a skipped development mode is not recorded as pushed');
    unset(WPP_Test_State::$hooks['wpp.cloudflare.push_budget']);

    WPP_Test_State::$httpCalls = [];
    $api->purgeAll();
    wpp_same(15, (int) (WPP_Test_State::$httpCalls[0]['args']['timeout'] ?? 0), 'a purge keeps the full timeout');
};
