<?php

declare(strict_types=1);

use WPP\Cache\DropinInstaller;
use WPP\Cache\ObjectCacheInstaller;

/**
 * Drop-ins are shared files: wp-content/advanced-cache.php and object-cache.php
 * can belong to another caching plugin. Installing must never clobber a file we
 * do not own, uninstalling must never delete one, and a plugin update must
 * refresh our own stale copy.
 */
return static function (): void {
    $advanced = WP_CONTENT_DIR . '/advanced-cache.php';
    $object   = WP_CONTENT_DIR . '/object-cache.php';
    @unlink($advanced);
    @unlink($object);

    // wp-config the installer can edit.
    $config = ABSPATH . 'wp-config.php';
    @mkdir(ABSPATH, 0777, true);
    file_put_contents($config, "<?php\ndefine( 'DB_NAME', 'x' );\n");

    $dropin = new DropinInstaller();

    // ---- fresh install ----
    wpp_ok(! $dropin->dropinInstalled(), 'nothing installed to begin with');
    wpp_ok($dropin->install(), 'install succeeds on a clean install');
    wpp_ok(is_file($advanced), 'advanced-cache.php written');
    wpp_ok($dropin->dropinInstalled(), 'our drop-in is detected as installed');
    wpp_ok(! $dropin->foreignDropin(), 'our own drop-in is not foreign');

    $written = (string) file_get_contents($advanced);
    wpp_contains('WPP_ADVANCED_CACHE', $written, 'ownership stamp present');
    wpp_contains(WPP_VERSION, $written, 'version stamped into the copy');
    wpp_not_contains('@wpp-version@', $written, 'placeholder was replaced');
    wpp_contains("define( 'WP_CACHE', true );", (string) file_get_contents($config), 'WP_CACHE enabled');

    // ---- a stale copy from an older plugin version is refreshed ----
    file_put_contents($advanced, "<?php\ndefine('WPP_ADVANCED_CACHE', '1.0.0');\n");
    wpp_ok(! $dropin->dropinInstalled(), 'a version mismatch counts as not installed');
    wpp_ok($dropin->install(), 'stale copy is refreshed');
    wpp_contains(WPP_VERSION, (string) file_get_contents($advanced), 'refreshed to the current version');

    // ---- the 1.x loader is still recognised as ours, not as another plugin's ----
    file_put_contents($advanced, "<?php\n/**\n * WP Performance Optimizer - Cache loader\n */\nfunction _wpp_get_cache_file() {}\n");
    wpp_ok(! $dropin->foreignDropin(), 'a pre-stamp copy of ours is not treated as foreign');
    wpp_ok(! $dropin->dropinInstalled(), 'a pre-stamp copy counts as needing a refresh');
    wpp_ok($dropin->install(), 'a pre-stamp copy is upgraded in place');
    wpp_contains('WPP_ADVANCED_CACHE', (string) file_get_contents($advanced), 'upgrade adds the stamp');

    // ---- a foreign drop-in is never touched ----
    $foreign = "<?php\n// Some Other Cache Plugin\n";
    file_put_contents($advanced, $foreign);
    wpp_ok($dropin->foreignDropin(), 'a third-party drop-in is detected as foreign');
    wpp_ok(! $dropin->dropinInstalled(), 'a foreign drop-in does not count as ours');
    wpp_ok(! $dropin->install(), 'install refuses to overwrite a foreign drop-in');
    wpp_same($foreign, (string) file_get_contents($advanced), 'foreign drop-in left byte-for-byte intact');

    $dropin->uninstall();
    wpp_ok(is_file($advanced), 'uninstall does not delete a foreign drop-in');
    wpp_same($foreign, (string) file_get_contents($advanced), 'foreign drop-in still intact after uninstall');

    // A foreign drop-in owning WP_CACHE keeps it: we only strip our own line.
    file_put_contents($config, "<?php\ndefine( 'WP_CACHE', true ); // Some Other Plugin\n");
    $dropin->uninstall();
    wpp_contains('WP_CACHE', (string) file_get_contents($config), "another plugin's WP_CACHE define is preserved");

    // ---- our own drop-in is removed cleanly ----
    @unlink($advanced);
    file_put_contents($config, "<?php\ndefine( 'DB_NAME', 'x' );\n");
    $dropin->install();
    wpp_ok(is_file($advanced), 'reinstalled');
    $dropin->uninstall();
    wpp_ok(! is_file($advanced), 'our drop-in is deleted on uninstall');
    wpp_not_contains('WP_CACHE', (string) file_get_contents($config), 'our WP_CACHE define is removed');

    // ---- object cache drop-in ----
    $objectCache = new ObjectCacheInstaller();
    wpp_ok(! $objectCache->installed(), 'object cache not installed initially');

    $foreignObject = "<?php\n// Redis Object Cache by someone else\n";
    file_put_contents($object, $foreignObject);
    wpp_ok($objectCache->foreignDropin(), 'foreign object-cache.php detected');
    wpp_ok(! $objectCache->installed(), 'foreign object cache is not counted as ours');
    wpp_ok(! $objectCache->install(), 'install refuses to overwrite a foreign object cache');
    wpp_same($foreignObject, (string) file_get_contents($object), 'foreign object cache left intact');

    $objectCache->uninstall();
    wpp_ok(is_file($object), 'uninstall does not delete a foreign object cache');

    @unlink($object);
    wpp_ok($objectCache->install(), 'object cache installs on a clean install');
    wpp_ok($objectCache->installed(), 'our object cache is detected');
    $objectCache->uninstall();
    wpp_ok(! is_file($object), 'our object cache is removed on uninstall');
};
