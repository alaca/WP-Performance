<?php

declare(strict_types=1);

use WPP\Optimize\AssetParser;
use WPP\Optimize\Collection;
use WPP\Optimize\FontHost;
use WPP\Optimize\HtmlOptimizer;
use WPP\Optimize\Minifier;
use WPP\Settings\SettingsService;

/**
 * The parser rewrites a live page with regex, so the failure mode is a visibly
 * broken site. These pin the cases that corrupted real markup.
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

    // ---- non-JavaScript script types must survive untouched ----
    $settings->update('js', ['delay' => true, 'minify_inline' => true]);

    $blocks = [
        'application/ld+json'      => '{"@context":"https://schema.org"}',
        'importmap'                => '{"imports":{"a":"/a.js"}}',
        'speculationrules'         => '{"prerender":[{"where":{"href_matches":"/*"}}]}',
        'text/template'            => '<div>{{ name }}</div>',
        'text/x-handlebars-template' => '<p>{{x}}</p>',
        'application/json'         => '{"a":1}',
    ];

    foreach ($blocks as $type => $body) {
        $tag  = '<script type="' . $type . '">' . $body . '</script>';
        $out  = $parser()->parse($page($tag));
        wpp_contains($tag, $out, "script type {$type} passed through untouched");
        wpp_not_contains('wpp/lazy', str_replace('<script type="' . $type . '">', '', $out), "script type {$type} not delayed");
    }

    // Real JavaScript is still processed.
    $js = $parser()->parse($page('<script>var a = 1;</script>'));
    wpp_contains('wpp/lazy', $js, 'plain inline javascript is still delayed');

    // ---- an attribute containing '>' must not corrupt the tag ----
    // Previously the [^>]* patterns stopped at the '>' inside the attribute, so
    // the tag was cut in half and the remainder ('b" title="x">') leaked into
    // the page as visible text. The value may come back HTML-escaped, which is
    // equivalent; what matters is that nothing escapes the tag.
    $settings->update('media', ['images_lazy' => true]);

    $out  = $parser()->parse($page('<img src="/a.png" alt="a > b" title="x">'));
    $body = (string) preg_replace('#.*<body>(.*)</body>.*#s', '$1', $out);
    wpp_same(1, substr_count($body, '<img'), 'exactly one img tag remains');
    wpp_ok(
        str_contains($body, 'a &gt; b') || str_contains($body, 'a > b'),
        'attribute value containing > is preserved'
    );
    wpp_same('', trim((string) preg_replace('#<img\b(?:"[^"]*"|\'[^\']*\'|[^"\'>])*>#i', '', $body)), 'no text leaked outside the tag');
    wpp_contains('title="x"', $body, 'attribute after the > is still part of the tag');

    $out  = $parser()->parse($page('<link rel="stylesheet" href="/s.css" title="a > b">'));
    $body = (string) preg_replace('#.*<body>(.*)</body>.*#s', '$1', $out);
    wpp_ok(
        str_contains($body, 'a &gt; b') || str_contains($body, 'a > b'),
        'link attribute containing > is preserved'
    );
    wpp_same('', trim((string) preg_replace('#<link\b(?:"[^"]*"|\'[^\']*\'|[^"\'>])*>#i', '', $body)), 'no text leaked from the link tag');

    // ---- delay_exclude wins over the combine map ----
    $settings->update('js', [
        'delay'         => true,
        'delay_exclude' => ['keepme'],
        'combine'       => ['/keepme.js' => true],
        'minify_inline' => false,
    ]);
    $out = $parser()->parse($page('<script src="/keepme.js"></script>'));
    wpp_contains('/keepme.js', $out, 'delay-excluded script is not swallowed by combine');
    wpp_not_contains('wpp/lazy', $out, 'delay-excluded script is not delayed');

    // ---- CDN rewriting covers srcset ----
    $settings->update('js', ['delay' => false, 'combine' => [], 'delay_exclude' => []]);
    $settings->update('media', ['images_lazy' => false]);
    $settings->update('cdn', ['enabled' => true, 'hostname' => 'https://cdn.example.com', 'exclude' => []]);

    $img = '<img src="https://example.test/a.png" srcset="https://example.test/a-300.png 300w, https://example.test/a-600.png 600w" sizes="100vw">';
    $out = $parser()->parse($page($img));
    wpp_contains('cdn.example.com/a-300.png 300w', $out, 'srcset candidate rewritten to the CDN');
    wpp_contains('cdn.example.com/a-600.png 600w', $out, 'second srcset candidate rewritten');
    wpp_contains('600w', $out, 'srcset descriptors preserved');

    // Excluded URLs stay off the CDN even inside srcset.
    $settings->update('cdn', ['enabled' => true, 'hostname' => 'https://cdn.example.com', 'exclude' => ['no-cdn']]);
    $out = $parser()->parse($page('<img src="https://example.test/a.png" srcset="https://example.test/no-cdn.png 300w">'));
    wpp_contains('example.test/no-cdn.png 300w', $out, 'cdn exclusion honored inside srcset');

    $settings->update('cdn', ['enabled' => false, 'hostname' => '', 'exclude' => []]);
};
