<?php

declare(strict_types=1);

use WPP\Optimize\CssMinifier;
use WPP\Optimize\FontHost;
use WPP\Optimize\HtmlMinifier;
use WPP\Optimize\JsMinifier;
use WPP\Optimize\UsedCss;

/**
 * The minifiers rewrite real site CSS/JS/HTML, so every bug here is a visibly
 * broken page. Each block pins one corruption found by review, using the input
 * that reproduced it.
 */
return static function (): void {
    $js   = new JsMinifier();
    $css  = new CssMinifier();
    $html = new HtmlMinifier();

    // ---- HTML: nested placeholders must all be restored ----
    // pre/textarea are stashed before script, so their key ends up inside a
    // script body; strtr() never rescans its own output.
    $nested = $html->minify('<script>var s = "<pre>hi</pre>";</script><p>after</p>', ['minify_normal' => 1]);
    wpp_not_contains("\x02", $nested, 'html: no control characters leak into the page');
    wpp_contains('<script>var s = "<pre>hi</pre>";</script>', $nested, 'html: pre inside a script string is restored');
    wpp_contains('<p>after</p>', $nested, 'html: markup after the script survives');

    $nestedTa = $html->minify("<script>t.innerHTML='<textarea>x</textarea>';</script>", ['minify_normal' => 1]);
    wpp_not_contains("\x02", $nestedTa, 'html: no control characters leak for textarea');
    wpp_contains('<textarea>x</textarea>', $nestedTa, 'html: textarea inside a script string is restored');

    // ---- CSS: an apostrophe in a comment must not protect the rules after it ----
    $apostrophe = $css->minify("/* Don't edit */\n.a { color: red; }\n/* It's fine */\n.b { color: blue; }");
    wpp_contains('.a{color:red}', $apostrophe, 'css: rule between two apostrophe comments survives');
    wpp_contains('.b{color:blue}', $apostrophe, 'css: rule after an apostrophe comment survives');
    wpp_not_contains('edit', $apostrophe, 'css: comments are still removed');

    // A real string still wins over a comment sequence inside it, and vice versa.
    $mixed = $css->minify('.a::after{content:"it\'s"}/* gone */.b{color:red}');
    wpp_contains('content:"it\'s"', $mixed, 'css: apostrophe inside a string literal preserved');
    wpp_contains('.b{color:red}', $mixed, 'css: rule after the string survives');
    wpp_not_contains('gone', $mixed, 'css: comment after a string is removed');

    // ---- HTML remove_quotes: never absorb the slash of a self-closing tag ----
    $link = $html->minify('<link rel="stylesheet" href="https://site.test/a.css"/>', ['remove_quotes' => 1]);
    wpp_contains('href="https://site.test/a.css"', $link, 'html: value before /> keeps its quotes');
    wpp_contains('rel=stylesheet', $link, 'html: quotes still dropped where it is safe');

    $img = $html->minify('<img src="/a/b.png"/>', ['remove_quotes' => 1]);
    wpp_contains('src="/a/b.png"', $img, 'html: img src before /> keeps its quotes');

    $spaced = $html->minify('<img src="/a/b.png" />', ['remove_quotes' => 1]);
    wpp_contains('src=/a/b.png', $spaced, 'html: whitespace-terminated value still unquoted');

    // ---- HTML remove_quotes: body text is not markup ----
    $prose = $html->minify('<p>Add <code>type="text/css"</code> to the tag.</p>', ['remove_quotes' => 1]);
    wpp_contains('<code>type="text/css"</code>', $prose, 'html: inline code in body text is untouched');

    // ---- JS: a regex literal after a keyword ----
    $afterKeyword = $js->minify('function f(s){return /["\']/.test(s)}var b="http://a.com/x";');
    wpp_contains('http://a.com/x', $afterKeyword, 'js: code after a regex containing quotes survives');
    wpp_contains('/["\']/', $afterKeyword, 'js: the regex literal itself is intact');

    wpp_contains('/a  b/', $js->minify('function g(s){return /a  b/.test(s)}'), 'js: whitespace inside a regex is not collapsed');

    // A property named like a keyword is an operand, so '/' is division.
    $property = $js->minify('var n = a.case / 2, s = "http://x/y";');
    wpp_contains('"http://x/y"', $property, 'js: division after a keyword-named property');

    // ---- JS: division after a postfix ++/-- ----
    $postfix = $js->minify('var x = i++ / 2, s = "a/b"; var t = "c"; var u = "http://x/y/z";');
    wpp_contains('"http://x/y/z"', $postfix, 'js: code after i++ / 2 survives');
    wpp_contains('"a/b"', $postfix, 'js: string containing a slash is intact');

    $postfixDec = $js->minify('var x = i-- / 2, s = "a/b"; var t = "c"; var u = "http://x/y/z";');
    wpp_contains('"http://x/y/z"', $postfixDec, 'js: code after i-- / 2 survives');

    // ---- HTML: whitespace between inline elements is significant ----
    $inline = $html->minify('<p><strong>Hello</strong> <em>world</em></p>', ['minify_aggressive' => 1]);
    wpp_contains('</strong> <em>', $inline, 'html: space between inline elements kept');
    wpp_contains(
        '</a> <a',
        $html->minify('<p><a href="/a">first</a> <a href="/b">second</a></p>', ['minify_aggressive' => 1]),
        'html: space between adjacent links kept'
    );
    wpp_contains(
        '<div><p>hi</p></div>',
        $html->minify("<div>\n    <p>hi</p>\n</div>", ['minify_aggressive' => 1]),
        'html: whitespace around block elements still collapsed'
    );

    // Attribute values decide what a form submits.
    wpp_contains('value="a  b"', $html->minify('<input value="a  b">', ['minify_normal' => 1]), 'html: attribute whitespace preserved');
    wpp_contains('<p>a b</p>', $html->minify('<p>a    b</p>', ['minify_normal' => 1]), 'html: text whitespace still collapsed');

    // ---- CSS url(): quotes are re-emitted, parens inside quotes match ----
    $src = 'https://site.test/wp-content/themes/t/style.css';
    wpp_contains(
        'url("https://site.test/wp-content/themes/t/img/my logo.png")',
        $css->process('.a{background:url("img/my logo.png")}', $src),
        'css: a url with a space stays quoted'
    );
    wpp_contains(
        'url("https://site.test/wp-content/themes/t/img/logo(2).png")',
        $css->process('.a{background:url("img/logo(2).png")}', $src),
        'css: a quoted url with parens is absolutized'
    );
    wpp_contains(
        "url('https://site.test/wp-content/themes/t/img/a.png')",
        $css->process(".a{background:url('img/a.png')}", $src),
        'css: single quotes are preserved'
    );

    // ---- UsedCss ----
    $abs = rtrim(ABSPATH, '/') . '/';
    @mkdir($abs . 'wp-content/themes/u', 0777, true);
    $used = new UsedCss();

    file_put_contents($abs . 'wp-content/themes/u/braces.css', '.icon::after{content:"}"}.btn{color:red}.card{color:blue}');
    $braces = $used->build('<body class="icon btn card"></body>', ['/wp-content/themes/u/braces.css'], []);
    wpp_contains('content:"}"}', $braces, 'usedcss: the rule holding a brace in a string is closed');
    wpp_not_contains('content:"}.btn', $braces, 'usedcss: the brace in a string does not swallow the next rule');
    wpp_contains('.btn{color:red}', $braces, 'usedcss: rule after a brace in a string survives');
    wpp_contains('.card{color:blue}', $braces, 'usedcss: last rule survives a brace in a string');

    file_put_contents($abs . 'wp-content/themes/u/attrs.css', 'a[href="#top"]{color:red}.x:not(.js-hidden){color:blue}');
    $attrs = $used->build('<body class="x"><a href="#top">t</a></body>', ['/wp-content/themes/u/attrs.css'], []);
    wpp_contains('a[href="#top"]', $attrs, 'usedcss: attribute-selector value is not read as an id token');
    wpp_contains('.x:not(.js-hidden)', $attrs, 'usedcss: :not() argument is not read as a class token');

    file_put_contents($abs . 'wp-content/themes/u/unused.css', '.present{color:red}.absent{color:blue}');
    $unused = $used->build('<body class="present"></body>', ['/wp-content/themes/u/unused.css'], []);
    wpp_contains('.present', $unused, 'usedcss: a used rule is kept');
    wpp_not_contains('.absent', $unused, 'usedcss: an unused rule is still dropped');

    // ---- FontHost ----
    $fontHost = new FontHost();
    wpp_same(null, $fontHost->localize('https://evil.test/css?family=X'), 'fonthost: a non-google host is refused');
    wpp_same([], WPP_Test_State::$httpCalls, 'fonthost: a refused url is never fetched');

    $googleUrl = 'https://fonts.googleapis.com/css?family=Test';
    $fontUrl   = 'https://fonts.gstatic.com/s/test/v1/abc.woff2';
    WPP_Test_State::$httpResponses[$googleUrl] = ['body' => '@font-face{src:url(' . $fontUrl . ') format("woff2")}'];
    WPP_Test_State::$httpResponses[$fontUrl]   = ['body' => 'woff2-bytes'];

    // Occupying the target path with a directory makes the font write fail.
    @mkdir(WPP_CACHE_DIR . 'fonts/', 0777, true);
    @mkdir(WPP_CACHE_DIR . 'fonts/' . md5($fontUrl) . '.woff2', 0777, true);

    set_error_handler(static fn (): bool => true);
    $hosted = $fontHost->localize($googleUrl);
    restore_error_handler();

    // Hosting a stylesheet whose faces are still remote is worse than not
    // hosting it: the short-circuit makes it permanent, and the admin reports
    // the fonts as local while every visitor still hits fonts.gstatic.com.
    wpp_same(null, $hosted, 'fonthost: a partly localised stylesheet is not hosted');
    wpp_ok(
        ! is_file(WPP_CACHE_DIR . 'fonts/' . md5($googleUrl) . '.css'),
        'fonthost: nothing is cached when a font write fails, so the next request retries'
    );
};
