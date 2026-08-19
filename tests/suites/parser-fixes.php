<?php

declare(strict_types=1);

use WPP\Optimize\AssetParser;
use WPP\Optimize\Collection;
use WPP\Optimize\FontHost;
use WPP\Optimize\HtmlOptimizer;
use WPP\Optimize\Minifier;
use WPP\Settings\SettingsService;

/**
 * Each block pins one reviewed AssetParser defect: the optimization either
 * dropped markup the browser needed or served a stale cached artifact.
 */
return static function (): void {
    $settings = new SettingsService();

    $parser = static function () use ($settings): AssetParser {
        return new AssetParser(
            $settings,
            new Minifier(),
            new HtmlOptimizer(),
            new Collection(),
            new FontHost()
        );
    };

    $page = static fn (string $body): string => "<html><head></head><body>{$body}</body></html>";

    $reset = static function (): void {
        foreach (['wpp_css', 'wpp_js', 'wpp_media', 'wpp_cdn', 'wpp_html'] as $option) {
            update_option($option, []);
        }
    };

    $abs = rtrim(ABSPATH, '/') . '/';
    @mkdir($abs . 'wp-content/themes/fx', 0777, true);
    @mkdir($abs . 'wp-content/uploads', 0777, true);

    // ---- Google Fonts: css2 endpoint and every family survive combining ----
    $reset();
    $settings->update('css', ['combine_fonts' => true]);

    $out = $parser()->parse($page(
        '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&amp;family=Lora:wght@400&amp;display=swap">'
    ));
    wpp_contains('fonts.googleapis.com/css2?family=', $out, 'css2 endpoint is preserved');
    wpp_contains('family=Inter:wght@400;700', $out, 'first css2 family is kept');
    wpp_contains('family=Lora:wght@400', $out, 'second css2 family is kept');

    // A v1 link still rebuilds against v1 syntax.
    $reset();
    $settings->update('css', ['combine_fonts' => true]);
    $out = $parser()->parse($page(
        '<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans|Roboto">'
    ));
    wpp_contains('/css?family=Open+Sans|Roboto', $out, 'v1 families keep the pipe-joined syntax');

    // ---- media attribute survives combining and used-CSS ----
    $reset();
    file_put_contents($abs . 'screen.css', '.screen{color:red}');
    file_put_contents($abs . 'print.css', '.print{color:black}');
    $settings->update('css', ['combine' => ['/screen.css' => true, '/print.css' => true]]);

    $out = $parser()->parse($page(
        '<link rel="stylesheet" href="/screen.css"><link rel="stylesheet" href="/print.css" media="print">'
    ));
    wpp_contains('media="print"', $out, 'print sheets are bundled separately and keep media');
    wpp_same(2, substr_count($out, '/wpp-cache/'), 'one bundle per media value');

    $reset();
    $settings->update('css', ['remove_unused' => true]);
    $out = $parser()->parse($page('<link rel="stylesheet" href="/wp-content/themes/fx/print.css" media="print">'));
    wpp_contains('href="/wp-content/themes/fx/print.css" media="print"', $out, 'media-scoped sheet is not swept into used-CSS');

    // ---- combined JS lands where the first combined tag stood ----
    $reset();
    file_put_contents($abs . 'jquery.js', 'var jq=1;');
    $settings->update('js', ['combine' => ['/jquery.js' => true]]);

    $out    = $parser()->parse($page('<script src="/jquery.js"></script><script>jQuery(1);</script>'));
    $bundle = strpos($out, '/wpp-cache/');
    $inline = strpos($out, 'jQuery(1);');
    wpp_ok($bundle !== false, 'js bundle was emitted');
    wpp_ok($bundle !== false && $inline !== false && $bundle < $inline, 'bundle runs before the inline code that depends on it');

    // ---- used-CSS cache is keyed by the safelist ----
    $reset();
    file_put_contents($abs . 'wp-content/themes/fx/used.css', '.always{color:red}.menu-open{display:block}');
    $usedPage = '<html><head><link rel="stylesheet" href="/wp-content/themes/fx/used.css"></head><body><div class="always"></div></body></html>';

    $settings->update('css', ['remove_unused' => true, 'used_safelist' => []]);
    $out = $parser()->parse($usedPage);
    wpp_contains('wpp-used-css', $out, 'used-CSS is inlined');
    wpp_not_contains('menu-open', $out, 'a class absent from the HTML is stripped');

    $settings->update('css', ['used_safelist' => ['.menu-open']]);
    $out = $parser()->parse($usedPage);
    wpp_contains('menu-open', $out, 'safelist change invalidates the cached used-CSS');

    // ---- videos_exclude_urls is honored ----
    $reset();
    $settings->update('media', ['videos_lazy' => true, 'videos_exclude_urls' => ['https://www.youtube.com/embed/LIVE']]);

    $out = $parser()->parse($page('<iframe src="https://www.youtube.com/embed/LIVE"></iframe>'));
    wpp_not_contains('loading="lazy"', $out, 'excluded video iframe is not lazy-loaded');

    $out = $parser()->parse($page('<iframe src="https://www.youtube.com/embed/OTHER"></iframe>'));
    wpp_contains('loading="lazy"', $out, 'other iframes are still lazy-loaded');

    // ---- next-gen sources are probed against the origin URL, not the CDN one ----
    $reset();
    file_put_contents($abs . 'wp-content/uploads/logo.png', 'png');
    file_put_contents($abs . 'wp-content/uploads/logo.png.webp', 'webp');
    $settings->update('media', ['webp' => true]);

    $out = $parser()->parse($page('<img src="/wp-content/uploads/logo.png">'));
    wpp_contains('type="image/webp"', $out, 'webp source is emitted for a srcset-less image');

    $settings->update('cdn', ['enabled' => true, 'hostname' => 'https://cdn.example.com']);
    $out = $parser()->parse($page('<img src="/wp-content/uploads/logo.png">'));
    wpp_contains('type="image/webp"', $out, 'webp source survives the CDN rewrite');
    wpp_contains('cdn.example.com/wp-content/uploads/logo.png.webp', $out, 'webp source is served from the CDN');

    // ---- cdn.exclude understands the wildcard tokens the UI documents ----
    $reset();
    $settings->update('cdn', [
        'enabled'  => true,
        'hostname' => 'https://cdn.example.com',
        'exclude'  => ['https://example.test/wp-content/uploads/{numbers}/{numbers}/{any}'],
    ]);

    $out = $parser()->parse($page('<img src="https://example.test/wp-content/uploads/2026/01/photo.jpg">'));
    wpp_contains('example.test/wp-content/uploads/2026/01/photo.jpg', $out, 'wildcard exclusion keeps the origin URL');
    wpp_not_contains('cdn.example.com', $out, 'excluded image never reaches the CDN');

    // ---- LCP preload resolves to the same candidate as the <img> ----
    $reset();
    $settings->update('media', ['lcp_images' => 1]);

    $out = $parser()->parse($page(
        '<img src="/hero.jpg" srcset="/hero-768.jpg 768w, /hero.jpg 2000w" sizes="(max-width: 768px) 100vw, 1024px">'
    ));
    wpp_contains('imagesrcset="/hero-768.jpg 768w, /hero.jpg 2000w"', $out, 'preload carries the srcset');
    wpp_contains('imagesizes="(max-width: 768px) 100vw, 1024px"', $out, 'preload carries the sizes');

    // ---- stylesheets are re-inserted in place, not at the end of <head> ----
    $reset();
    file_put_contents($abs . 'theme.css', '.theme{color:green}');
    $settings->update('css', ['combine' => ['/theme.css' => true]]);

    $out    = $parser()->parse('<html><head><link rel="stylesheet" href="/theme.css"><style id="custom">.a{color:red}</style></head><body></body></html>');
    $bundle = strpos($out, '/wpp-cache/');
    $custom = strpos($out, 'id="custom"');
    wpp_ok($bundle !== false, 'css bundle was emitted');
    wpp_ok($bundle !== false && $custom !== false && $bundle < $custom, 'bundle stays ahead of the inline style that overrides it');

    // ---- deferring moves media onto the onload handler ----
    $reset();
    $settings->update('css', ['defer' => true]);

    $out = $parser()->parse($page('<link rel="stylesheet" href="/p.css" media="print">'));
    preg_match('#<link[^>]*rel="preload"[^>]*>#', $out, $m);
    wpp_not_contains('media="print"', $m[0] ?? '', 'preload link carries no media attribute');
    wpp_contains('<noscript>', $out, 'a deferred sheet keeps a noscript fallback');
    wpp_contains('this.media=', $out, 'media is restored when the preload loads');

    $out = $parser()->parse($page('<link rel="alternate stylesheet" href="/alt.css" title="Alt">'));
    wpp_contains('rel="alternate stylesheet"', $out, 'alternate stylesheet is left disabled');
    wpp_not_contains('preload', $out, 'alternate stylesheet is not force-enabled');

    // ---- CDN rewrites root-relative and mixed-scheme asset URLs ----
    $reset();
    $settings->update('cdn', ['enabled' => true, 'hostname' => 'https://cdn.example.com']);

    $out = $parser()->parse($page('<img src="/wp-content/uploads/hero.jpg">'));
    wpp_contains('src="https://cdn.example.com/wp-content/uploads/hero.jpg"', $out, 'root-relative image is rewritten to the CDN');

    $out = $parser()->parse($page('<img src="http://example.test/a.png">'));
    wpp_contains('src="https://cdn.example.com/a.png"', $out, 'http asset on an https home is rewritten');

    // ---- a scheme-less CDN hostname is refused instead of breaking every URL ----
    $reset();
    $settings->update('cdn', ['enabled' => true, 'hostname' => 'cdn.example.com']);

    $out = $parser()->parse($page('<img src="https://example.test/a.png">'));
    wpp_contains('src="https://example.test/a.png"', $out, 'scheme-less hostname leaves the URL untouched');
    wpp_not_contains('src="cdn.example.com', $out, 'no document-relative CDN path is emitted');

    // ---- editing a source file busts the combined bundle ----
    $reset();
    $file = $abs . 'wp-content/themes/fx/v.css';
    file_put_contents($file, '.v1{color:red}');
    touch($file, time() - 120);
    $settings->update('css', ['combine' => ['/wp-content/themes/fx/v.css' => true]]);
    $link = $page('<link rel="stylesheet" href="/wp-content/themes/fx/v.css">');

    preg_match('#/wpp-cache/([a-f0-9]{32})\.css#', $parser()->parse($link), $first);
    file_put_contents($file, '.v2{color:blue}');
    touch($file, time());
    preg_match('#/wpp-cache/([a-f0-9]{32})\.css#', $parser()->parse($link), $second);

    wpp_ok(isset($first[1], $second[1]), 'both parses produced a bundle URL');
    wpp_ok(($first[1] ?? '') !== ($second[1] ?? ''), 'editing the source changes the bundle URL');
    wpp_contains('.v2', (string) @file_get_contents(WPP_CACHE_DIR . ($second[1] ?? '') . '.css'), 'the new bundle holds the edited CSS');

    $reset();
};
