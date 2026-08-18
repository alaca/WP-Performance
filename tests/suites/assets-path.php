<?php

declare(strict_types=1);

use WPP\Support\Assets;

/**
 * Regression: Assets::toPath() resolved any URL to a path with no containment
 * or type check. Remove Unused CSS feeds it hrefs straight from page markup, so
 * a crafted <link href="/wp-config.php"> was read off disk and its contents
 * republished into a world-readable file in the cache directory.
 */
return static function (): void {
    $abs = rtrim(ABSPATH, '/') . '/';
    @mkdir($abs . 'wp-content/themes/x', 0777, true);
    @mkdir(dirname(rtrim($abs, '/')) . '/outside', 0777, true);

    file_put_contents($abs . 'wp-config.php', "<?php define('DB_PASSWORD','hunter2');");
    file_put_contents($abs . '.htaccess', 'secret');
    file_put_contents($abs . 'wp-content/themes/x/style.css', '.a{color:red}');
    file_put_contents($abs . 'wp-content/themes/x/app.js', 'var a=1;');
    file_put_contents($abs . 'wp-content/themes/x/notes.txt', 'private');
    file_put_contents(dirname(rtrim($abs, '/')) . '/outside/evil.css', '.evil{}');

    // Legitimate assets still resolve and can be read.
    $cssPath = Assets::toPath('/wp-content/themes/x/style.css');
    wpp_ok($cssPath !== null, 'stylesheet resolves to a path');
    wpp_same('.a{color:red}', Assets::contents('/wp-content/themes/x/style.css'), 'stylesheet contents readable');
    wpp_ok(Assets::toPath('/wp-content/themes/x/app.js') !== null, 'script resolves to a path');
    wpp_ok(Assets::toPath('/wp-content/themes/x/style.css?ver=1.2') !== null, 'query string is ignored');

    // Sensitive files are refused even though they exist and are inside the install.
    wpp_same(null, Assets::toPath('/wp-config.php'), 'wp-config.php is refused');
    wpp_same(null, Assets::contents('/wp-config.php'), 'wp-config.php contents unreachable');
    wpp_same(null, Assets::toPath('/.htaccess'), '.htaccess is refused');
    wpp_same(null, Assets::toPath('/wp-content/themes/x/notes.txt'), 'non-asset extension refused');

    // Traversal cannot escape the install, even to a file with an allowed extension.
    wpp_same(null, Assets::toPath('/../outside/evil.css'), 'traversal outside the install is refused');
    wpp_same(null, Assets::toPath('/wp-content/../../outside/evil.css'), 'nested traversal refused');

    // Missing files stay null.
    wpp_same(null, Assets::toPath('/wp-content/themes/x/nope.css'), 'missing file returns null');

    // Remote and unrelated URLs are not treated as local paths.
    wpp_same(null, Assets::toPath('https://cdn.example.com/x.css'), 'remote url is not a local path');
    wpp_ok(Assets::isLocal('/wp-content/x.css'), 'root-relative url is local');
    wpp_ok(! Assets::isLocal('https://cdn.example.com/x.css'), 'remote url is not local');
};
