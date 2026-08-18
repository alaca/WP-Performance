<?php

declare(strict_types=1);

use WPP\Optimize\AssetParser;
use WPP\Optimize\Collection;
use WPP\Optimize\CssMinifier;
use WPP\Optimize\FontHost;
use WPP\Optimize\HtmlOptimizer;
use WPP\Optimize\Minifier;
use WPP\Settings\SettingsService;

/**
 * Markup-rewriting and CSS-URL-resolution defects that corrupted rendered
 * pages: wrong exclusion scope, host-less origins, poisoned bundles, invalid
 * @import order, unusable <picture>/preload pairs.
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
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_HOST']   = 'example.test';
    };

    $abs    = rtrim(ABSPATH, '/') . '/';
    $themes = $abs . 'wp-content/themes/px/';
    $upload = $abs . 'wp-content/uploads/px/';
    @mkdir($themes, 0777, true);
    @mkdir($upload, 0777, true);

    // =====================================================================
    // 1. images_exclude_urls is a page-URL control, not an image-src control
    // =====================================================================
    $reset();
    $settings->update('media', ['images_lazy' => true, 'images_exclude_urls' => ['/gallery/']]);

    $_SERVER['REQUEST_URI'] = '/gallery/';
    $out = $parser()->parse($page('<img src="/wp-content/uploads/px/hero.jpg">'));
    wpp_not_contains('loading="lazy"', $out, 'an excluded page skips image rewriting entirely');

    $_SERVER['REQUEST_URI'] = '/blog/';
    $out = $parser()->parse($page('<img src="/wp-content/uploads/px/hero.jpg">'));
    wpp_contains('loading="lazy"', $out, 'other pages are still optimized');

    // An image-path pattern in the page-URL field must not disable the feature.
    $reset();
    $settings->update('media', [
        'images_lazy'         => true,
        'images_exclude_urls' => ['https://example.test/wp-content/uploads/'],
    ]);
    $_SERVER['REQUEST_URI'] = '/blog/';
    $out = $parser()->parse($page('<img src="https://example.test/wp-content/uploads/px/hero.jpg">'));
    wpp_contains('loading="lazy"', $out, 'an image path in the page-URL field does not exclude the image');

    // Exclude by name still matches the src.
    $reset();
    $settings->update('media', ['images_lazy' => true, 'images_exclude' => ['hero.jpg']]);
    $out = $parser()->parse($page('<img src="/wp-content/uploads/px/hero.jpg">'));
    wpp_not_contains('loading="lazy"', $out, 'exclude-by-name still matches the image src');

    // =====================================================================
    // 2. CSS url()/@import resolution against a host-less stylesheet URL
    // =====================================================================
    $css   = new CssMinifier();
    $sheet = '@font-face{font-family:t;src:url(../fonts/theme.woff2) format("woff2")}'
        . '.hero{background:url(images/bg.jpg) no-repeat}'
        . '@import "more.css";';

    $resolved = $css->process($sheet, '/wp-content/themes/px/css/style.css');
    wpp_not_contains('http:///', $resolved, 'a host-less source url never yields an empty authority');
    wpp_contains('https://example.test/wp-content/themes/px/fonts/theme.woff2', $resolved, 'font url resolves against the site origin');
    wpp_contains('https://example.test/wp-content/themes/px/css/images/bg.jpg', $resolved, 'background url resolves against the site origin');
    wpp_contains('@import "https://example.test/wp-content/themes/px/css/more.css"', $resolved, '@import resolves against the site origin');

    // A protocol-relative source keeps the page's scheme.
    $relative = $css->process('.a{background:url(bg.png)}', '//example.test/wp-content/themes/px/style.css');
    wpp_contains('https://example.test/wp-content/themes/px/bg.png', $relative, 'protocol-relative source uses the https scheme');
    wpp_not_contains('http://example.test', $relative, 'protocol-relative source is not downgraded to http');

    // An absolute source url is still honored verbatim.
    $external = $css->process('.a{background:url(bg.png)}', 'https://cdn.example.com/t/style.css');
    wpp_contains('https://cdn.example.com/t/bg.png', $external, 'an absolute source url still wins');

    // The same defect end to end: a minified file must not carry http:/// urls.
    $reset();
    file_put_contents($themes . 'style.css', '.hero{background:url(images/bg.jpg)}');
    $settings->update('css', ['minify' => ['/wp-content/themes/px/style.css' => true]]);
    $out = $parser()->parse($page('<link rel="stylesheet" href="/wp-content/themes/px/style.css">'));
    preg_match('#/wpp-cache/([a-f0-9]{32})\.css#', $out, $m);
    $cached = $m[1] ?? '' ? (string) file_get_contents(WPP_CACHE_DIR . $m[1] . '.css') : '';
    wpp_ok($cached !== '', 'the minified artifact was written');
    wpp_not_contains('http:///', $cached, 'the cached artifact carries no empty-authority urls');

    // =====================================================================
    // 3. a bundle that could not be built must not swallow the source tags
    // =====================================================================
    $reset();
    file_put_contents($themes . 'a.css', '.a{color:red}');
    $settings->update('css', ['combine' => [
        '/wp-content/themes/px/a.css'       => true,
        '/wp-content/themes/px/missing.css' => true,
    ]]);

    $out = $parser()->parse($page(
        '<link rel="stylesheet" href="/wp-content/themes/px/a.css">'
        . '<link rel="stylesheet" href="/wp-content/themes/px/missing.css">'
    ));
    wpp_not_contains('/wpp-cache/', $out, 'an unreadable source aborts the bundle');
    wpp_contains('/wp-content/themes/px/a.css', $out, 'the readable source keeps its own link');
    wpp_contains('/wp-content/themes/px/missing.css', $out, 'the unreadable source keeps its own link');

    // Scripts get the same treatment.
    $reset();
    file_put_contents($themes . 'a.js', 'var a=1;');
    $settings->update('js', ['combine' => [
        '/wp-content/themes/px/a.js'       => true,
        '/wp-content/themes/px/missing.js' => true,
    ]]);
    $out = $parser()->parse($page(
        '<script src="/wp-content/themes/px/a.js"></script>'
        . '<script src="/wp-content/themes/px/missing.js"></script>'
    ));
    wpp_contains('/wp-content/themes/px/a.js', $out, 'the readable script keeps its own tag');
    wpp_contains('/wp-content/themes/px/missing.js', $out, 'the unreadable script keeps its own tag');

    // A zero-byte artifact left behind is rebuilt, not re-served.
    $reset();
    file_put_contents($themes . 'b.css', '.b{color:blue}');
    $settings->update('css', ['combine' => ['/wp-content/themes/px/b.css' => true]]);
    $link = $page('<link rel="stylesheet" href="/wp-content/themes/px/b.css">');

    preg_match('#/wpp-cache/([a-f0-9]{32})\.css#', $parser()->parse($link), $built);
    wpp_ok(isset($built[1]), 'the bundle was built');
    $bundle = WPP_CACHE_DIR . ($built[1] ?? 'x') . '.css';
    file_put_contents($bundle, '');
    $parser()->parse($link);
    wpp_contains('.b', (string) @file_get_contents($bundle), 'a zero-byte bundle is rebuilt on the next parse');

    // =====================================================================
    // 4. @import must lead the bundle, or the browser drops it
    // =====================================================================
    $reset();
    file_put_contents($themes . 'base.css', '.base{color:red}');
    file_put_contents($themes . 'child.css', '@import url("https://fonts.example.com/inter.css");.child{color:blue}');
    $settings->update('css', ['combine' => [
        '/wp-content/themes/px/base.css'  => true,
        '/wp-content/themes/px/child.css' => true,
    ]]);

    $out = $parser()->parse($page(
        '<link rel="stylesheet" href="/wp-content/themes/px/base.css">'
        . '<link rel="stylesheet" href="/wp-content/themes/px/child.css">'
    ));
    preg_match('#/wpp-cache/([a-f0-9]{32})\.css#', $out, $m);
    $bundled = isset($m[1]) ? (string) file_get_contents(WPP_CACHE_DIR . $m[1] . '.css') : '';
    wpp_ok($bundled !== '', 'the bundle was written');
    $importAt = strpos($bundled, '@import');
    $ruleAt   = strpos($bundled, '.base');
    wpp_ok($importAt !== false && $ruleAt !== false && $importAt < $ruleAt, '@import leads the combined bundle');

    // The inlined used-CSS has the same requirement.
    $reset();
    file_put_contents($themes . 'u1.css', '.one{color:red}');
    file_put_contents($themes . 'u2.css', '@import url("https://fonts.example.com/inter.css");.two{color:blue}');
    $settings->update('css', ['remove_unused' => true]);

    $out = $parser()->parse(
        '<html><head><link rel="stylesheet" href="/wp-content/themes/px/u1.css">'
        . '<link rel="stylesheet" href="/wp-content/themes/px/u2.css"></head>'
        . '<body><div class="one two"></div></body></html>'
    );
    preg_match('#<style id="wpp-used-css">(.*?)</style>#s', $out, $m);
    $used     = $m[1] ?? '';
    $importAt = strpos($used, '@import');
    $ruleAt   = strpos($used, '.one');
    wpp_ok($used !== '', 'used-CSS was inlined');
    wpp_ok($importAt !== false && $ruleAt !== false && $importAt < $ruleAt, '@import leads the inlined used-CSS');

    // =====================================================================
    // 5 + 10. next-gen <source> must carry sizes and cover the whole srcset
    // =====================================================================
    $reset();
    foreach (['hero-300.jpg', 'hero-1200.jpg'] as $name) {
        file_put_contents($upload . $name, 'jpg');
        file_put_contents($upload . $name . '.webp', 'webp');
    }
    $settings->update('media', ['webp' => true]);

    $img = '<img src="/wp-content/uploads/px/hero-1200.jpg"'
        . ' srcset="/wp-content/uploads/px/hero-300.jpg 300w, /wp-content/uploads/px/hero-1200.jpg 1200w"'
        . ' sizes="(max-width: 600px) 300px, 1200px">';
    $out = $parser()->parse($page($img));
    preg_match('#<source[^>]*>#', $out, $m);
    $source = $m[0] ?? '';
    wpp_contains('type="image/webp"', $source, 'a webp source is emitted');
    wpp_contains('sizes="(max-width: 600px) 300px, 1200px"', $source, 'the source carries the img sizes');

    // A partial next-gen set would let the browser pick a small file for a
    // slot the original srcset covered.
    @unlink($upload . 'hero-1200.jpg.webp');
    $out = $parser()->parse($page($img));
    wpp_not_contains('<source', $out, 'a partially converted srcset emits no source at all');
    wpp_not_contains('<picture>', $out, 'and therefore no picture wrapper');
    file_put_contents($upload . 'hero-1200.jpg.webp', 'webp');

    // =====================================================================
    // 6. an img already inside a <picture> is never wrapped again
    // =====================================================================
    $reset();
    file_put_contents($upload . 'art.jpg', 'jpg');
    file_put_contents($upload . 'art.jpg.webp', 'webp');
    file_put_contents($upload . 'art-mobile.jpg', 'jpg');
    $settings->update('media', ['webp' => true, 'images_lazy' => true]);

    $out = $parser()->parse($page(
        '<picture><source media="(max-width:600px)" srcset="/wp-content/uploads/px/art-mobile.jpg">'
        . '<img src="/wp-content/uploads/px/art.jpg"></picture>'
    ));
    wpp_same(1, substr_count($out, '<picture>'), 'no nested picture is emitted');
    wpp_contains('media="(max-width:600px)"', $out, 'the page\'s own art-direction source survives');
    wpp_not_contains('data-wpp-picture', $out, 'no marker attribute leaks into the page');
    wpp_contains('loading="lazy"', $out, 'the img inside a picture is still lazy-loaded');

    // =====================================================================
    // 7 + 11. the LCP preload must name the resource the picture will select
    // =====================================================================
    $reset();
    file_put_contents($upload . 'lcp.jpg', 'jpg');
    file_put_contents($upload . 'lcp.jpg.webp', 'webp');
    $settings->update('media', ['webp' => true, 'lcp_images' => 1]);

    $out = $parser()->parse($page('<img src="/wp-content/uploads/px/lcp.jpg">'));
    preg_match('#<link rel="preload"[^>]*>#', $out, $m);
    $preload = $m[0] ?? '';
    wpp_contains('href="/wp-content/uploads/px/lcp.jpg.webp"', $preload, 'the preload names the next-gen file');
    wpp_contains('type="image/webp"', $preload, 'the preload declares the next-gen type');

    // Without a next-gen sibling the origin file is still preloaded.
    $reset();
    file_put_contents($upload . 'plain.jpg', 'jpg');
    $settings->update('media', ['webp' => true, 'lcp_images' => 1]);
    $out = $parser()->parse($page('<img src="/wp-content/uploads/px/plain.jpg">'));
    wpp_contains('href="/wp-content/uploads/px/plain.jpg"', $out, 'an unconverted LCP image preloads its own url');

    // =====================================================================
    // 8. a query string must survive the next-gen extension swap
    // =====================================================================
    $reset();
    $settings->update('media', ['webp' => true]);
    $out = $parser()->parse($page('<img src="/wp-content/uploads/px/lcp.jpg?ver=4.2">'));
    wpp_contains('srcset="/wp-content/uploads/px/lcp.jpg.webp?ver=4.2"', $out, 'the extension is inserted before the query');
    wpp_not_contains('lcp.jpg?ver=4.2.webp', $out, 'the extension never lands inside the query string');

    // =====================================================================
    // 9. an individually deferred stylesheet keeps a noscript fallback
    // =====================================================================
    $reset();
    $settings->update('css', ['defer' => true]);

    $out = $parser()->parse($page('<link id="theme-css" rel="stylesheet" href="/p.css" media="print">'));
    wpp_contains('<noscript><link rel="stylesheet" href="/p.css" media="print">', $out, 'the deferred link keeps a noscript copy');
    wpp_contains('rel="preload"', $out, 'the deferred link is still preloaded');
    wpp_same(1, substr_count($out, 'id="theme-css"'), 'the noscript copy drops the duplicate id');

    $reset();
};
