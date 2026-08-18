<?php

declare(strict_types=1);

use WPP\Optimize\Collection;

/**
 * Regression: "Update files list" returned an empty table.
 *
 * clear() calls delete_option(), which adds the key to the 'notoptions' cache.
 * The rescan's loopback request then collects assets in a SEPARATE process, so
 * the option row exists again, but the admin request keeps short-circuiting to
 * the default for the rest of its life and reports an empty list.
 */
return static function (): void {
    $collection = new Collection();

    // Normal buffering and merge behavior.
    $collection->record('css', 'theme', '/theme/style.css');
    $collection->record('js', 'theme', '/theme/app.js');
    $collection->flush();

    $all = $collection->all();
    wpp_same(['/theme/style.css'], $all['css']['theme'] ?? [], 'css url recorded');
    wpp_same(['/theme/app.js'], $all['js']['theme'] ?? [], 'js url recorded');

    // Repeated urls are de-duplicated across requests.
    $collection->record('css', 'theme', '/theme/style.css');
    $collection->record('css', 'theme', '/theme/extra.css');
    $collection->flush();
    wpp_same(
        ['/theme/style.css', '/theme/extra.css'],
        $collection->all()['css']['theme'] ?? [],
        'urls merge without duplicates'
    );

    // Groups stay separate.
    $collection->record('css', 'plugin', '/plugins/a/a.css');
    $collection->record('css', 'external', 'https://cdn.example.com/x.css');
    $collection->flush();
    $all = $collection->all();
    wpp_same(['/plugins/a/a.css'], $all['css']['plugin'] ?? [], 'plugin group kept separate');
    wpp_same(['https://cdn.example.com/x.css'], $all['css']['external'] ?? [], 'external group kept separate');

    // Flushing an empty buffer must not clobber stored data.
    $collection->flush();
    wpp_ok($collection->all() !== [], 'empty flush does not wipe the stored list');

    // ---- the regression itself ----
    $collection->clear();
    wpp_same([], $collection->all(), 'clear empties the list');

    // Another process (the loopback request) collects and writes the option.
    WPP_Test_State::$options[Collection::OPTION] = ['css' => ['theme' => ['/theme/style.css']]];

    // Without busting the cache the current request still sees nothing, which
    // is exactly what made the admin table come back empty.
    wpp_same([], $collection->all(), 'stale notoptions cache hides the new value');

    $collection->refresh();
    wpp_same(
        ['/theme/style.css'],
        $collection->all()['css']['theme'] ?? [],
        'refresh() exposes the value written by the other process'
    );

    // refresh() is safe to call when nothing is cached.
    $collection->refresh();
    wpp_same(
        ['/theme/style.css'],
        $collection->all()['css']['theme'] ?? [],
        'refresh() is idempotent'
    );
};
