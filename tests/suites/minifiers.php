<?php

declare(strict_types=1);

use WPP\Optimize\CssMinifier;
use WPP\Optimize\HtmlMinifier;
use WPP\Optimize\JsMinifier;

return static function (): void {
    $js = new JsMinifier();
    $css = new CssMinifier();
    $html = new HtmlMinifier();

    // ---- JavaScript: the state machine must never corrupt code ----
    wpp_contains('/a\/b[/]/g', $js->minify('var re = /a\/b[/]/g; var x = 1/2;'), 'js: regex literal with escaped slash');
    wpp_contains('/x/g', $js->minify('var a=b/c; var d=/x/g.test(s);'), 'js: regex vs division');
    wpp_contains('/z/', $js->minify('var x = (a+b)/2; var r=/z/;'), 'js: division after paren then regex');
    wpp_contains('${', $js->minify('var s = `a ${ b } c`;'), 'js: template literal interpolation kept');
    wpp_contains('http://x.com/*not a comment*/', $js->minify('var s = "http://x.com/*not a comment*/";'), 'js: block-comment sequence inside string');
    wpp_contains('a // b', $js->minify('var s = "a // b"; var y=1;'), 'js: line-comment sequence inside string');
    wpp_contains("'", $js->minify("var s = 'it\\'s';"), 'js: escaped quote inside string');

    // Real comments are removed.
    wpp_not_contains('kill me', $js->minify("var a=1; // kill me\nvar b=2;"), 'js: line comment removed');
    wpp_not_contains('kill me', $js->minify("var a=1; /* kill me */ var b=2;"), 'js: block comment removed');

    // ASI safety: statements separated only by newlines must not be joined.
    $asi = $js->minify("var a = 1\nvar b = 2\nconsole.log(a+b)");
    wpp_same(2, substr_count($asi, 'var'), 'js: both statements survive ASI');
    wpp_ok(
        str_contains($asi, "\n") || str_contains($asi, ';'),
        'js: statements stay separated (newline or semicolon)'
    );

    // Minification actually shrinks typical code.
    $long = "function add ( a , b ) {\n    // sum\n    return a + b ;\n}\n";
    wpp_ok(strlen($js->minify($long)) < strlen($long), 'js: output is smaller');

    // ---- CSS ----
    wpp_contains('base64,PHN2Zz48L3N2Zz4=', $css->minify('.a{background:url(data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=)}'), 'css: base64 data uri intact');
    wpp_contains('a(1).png', $css->minify('.a{background:url("a(1).png")}'), 'css: parens inside quoted url');
    wpp_contains('--x', $css->minify(':root{--x: 10px;--y:calc(var(--x) * 2)}'), 'css: custom properties kept');
    wpp_contains('@media', $css->minify('@media (min-width: 700px){.a{color:red}}'), 'css: media query kept');
    wpp_contains('700px', $css->minify('@media (min-width: 700px){.a{color:red}}'), 'css: media query value kept');
    wpp_contains('a;b}c', $css->minify('.a::after{content:"a;b}c"}'), 'css: braces and semicolons inside string');
    wpp_contains('/* x */', $css->minify('.a::after{content:"/* x */"}'), 'css: comment sequence inside string');
    wpp_contains('!important', $css->minify('.a{color:red !important}'), 'css: important kept');
    wpp_contains('http://x', $css->minify('a[href^="http://x"]{color:red}'), 'css: double slash in attribute selector');
    wpp_not_contains('kill me', $css->minify('/* kill me */ .a{color:red}'), 'css: real comment removed');
    wpp_ok(strlen($css->minify(".a {\n  color : red ;\n}\n")) < 20, 'css: whitespace collapsed');

    // ---- CSS url() rewriting when a stylesheet moves into the cache dir ----
    // Regression: a '#' delimiter in the "already absolute" guard ended the
    // pattern at the fragment alternative, so the guard matched nothing and
    // every url() was rewritten, including base64 fonts. That broke Dashicons.
    $src = 'http://localhost:10092/wp-includes/css/dashicons.min.css';

    $dataUri = $css->process('.a{background:url(data:image/png;base64,AAAA)}', $src);
    wpp_contains('url(data:image/png;base64,AAAA)', $dataUri, 'css: data uri left untouched');
    wpp_not_contains('/data:', $dataUri, 'css: data uri is never prefixed with a base path');

    wpp_contains(
        'url(https://cdn.example.com/x.png)',
        $css->process('.a{background:url(https://cdn.example.com/x.png)}', $src),
        'css: absolute url left untouched'
    );
    wpp_contains(
        'url(//cdn.example.com/x.png)',
        $css->process('.a{background:url(//cdn.example.com/x.png)}', $src),
        'css: protocol-relative url left untouched'
    );
    wpp_contains(
        'url(#grad)',
        $css->process('.a{fill:url(#grad)}', $src),
        'css: svg fragment reference left untouched'
    );
    wpp_contains(
        'url(about:blank)',
        $css->process('.a{background:url(about:blank)}', $src),
        'css: non-http scheme left untouched'
    );

    // Relative urls resolve against the stylesheet, keeping a non-default port.
    wpp_contains(
        'url(http://localhost:10092/wp-includes/img/x.png)',
        $css->process('.a{background:url(../img/x.png)}', $src),
        'css: relative url resolves and keeps the port'
    );
    wpp_contains(
        'url(http://localhost:10092/img/x.png)',
        $css->process('.a{background:url(/img/x.png)}', $src),
        'css: root-relative url keeps the port'
    );
    wpp_contains(
        'url(https://example.test/wp-includes/img/x.png)',
        $css->process('.a{background:url(../img/x.png)}', 'https://example.test/wp-includes/css/a.css'),
        'css: default port is not appended'
    );

    // @import is resolved the same way.
    wpp_contains(
        '@import "http://localhost:10092/wp-includes/css/other.css"',
        $css->process('@import "other.css";', $src),
        'css: relative @import resolved'
    );

    // ---- HTML ----
    $opts = ['minify_normal' => true, 'remove_comments' => true];
    $out = $html->minify("<div>   <p>hi</p>   </div>\n\n<!-- gone -->", $opts);
    wpp_not_contains('gone', $out, 'html: comment removed');
    wpp_contains('<p>hi</p>', $out, 'html: markup preserved');

    // Conditional comments must survive comment removal.
    $cond = $html->minify('<!--[if IE]><p>ie</p><![endif]--><div>x</div>', $opts);
    wpp_contains('[if IE]', $cond, 'html: conditional comment preserved');

    // Content of pre/textarea/script must not be whitespace-collapsed.
    $pre = $html->minify("<pre>a   b\n  c</pre>", $opts);
    wpp_contains("a   b", $pre, 'html: pre content whitespace preserved');
    $ta = $html->minify("<textarea>a   b</textarea>", $opts);
    wpp_contains('a   b', $ta, 'html: textarea content preserved');
};
