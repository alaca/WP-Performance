<?php

declare(strict_types=1);

use WPP\Settings\SettingsService;

return static function (): void {
    $s = new SettingsService();

    // Defaults are readable for every core group.
    foreach (['cache', 'css', 'js', 'html', 'media', 'cdn', 'database', 'tools'] as $group) {
        wpp_ok($s->knows($group), "knows group {$group}");
        wpp_ok($s->get($group) !== [], "group {$group} has defaults");
    }
    wpp_same([], $s->get('nope'), 'unknown group returns []');
    wpp_ok(! $s->knows('nope'), 'unknown group is not known');

    // Unknown keys are dropped, known keys persist.
    $s->update('cache', ['enabled' => true, 'not_a_real_key' => 'x']);
    $cache = $s->get('cache');
    wpp_same(true, $cache['enabled'], 'known key persists');
    wpp_ok(! array_key_exists('not_a_real_key', $cache), 'unknown key is dropped');

    // Types survive the round trip (REST sends real JSON types).
    $s->update('cache', ['clear_time' => 25, 'clear_unit' => 86400, 'gzip' => false]);
    $cache = $s->get('cache');
    wpp_same(25, $cache['clear_time'], 'int stays int');
    wpp_same(86400, $cache['clear_unit'], 'int unit stays int');
    wpp_same(false, $cache['gzip'], 'false stays false');

    // Lists replace wholesale rather than merging.
    $s->update('cache', ['exclude_urls' => ['/a', '/b', '/c']]);
    wpp_same(['/a', '/b', '/c'], $s->get('cache')['exclude_urls'], 'list is stored');
    $s->update('cache', ['exclude_urls' => ['/a']]);
    wpp_same(['/a'], $s->get('cache')['exclude_urls'], 'removing list items persists');

    // Emptying a list must persist (defaults must not resurrect old values).
    $s->update('cache', ['exclude_urls' => []]);
    wpp_same([], $s->get('cache')['exclude_urls'], 'emptying a list persists');

    // Values that must survive sanitization unmangled.
    $s->update('css', ['critical_path' => "body{color:red}\n.h{margin:0}"]);
    wpp_contains("\n", $s->get('css')['critical_path'], 'critical_path keeps newlines (multiline key)');

    $s->update('cdn', ['hostname' => 'https://cdn.example.com']);
    wpp_same('https://cdn.example.com', $s->get('cdn')['hostname'], 'cdn hostname survives');

    $s->update('css', ['dns_prefetch' => ['//fonts.gstatic.com'], 'preconnect' => ['https://fonts.gstatic.com']]);
    wpp_same(['//fonts.gstatic.com'], $s->get('css')['dns_prefetch'], 'protocol-relative origin survives');

    $s->update('cache', ['exclude_urls' => ['/cart/*', '/shop?a=1&b=2']]);
    wpp_same(['/cart/*', '/shop?a=1&b=2'], $s->get('cache')['exclude_urls'], 'wildcards and query strings survive');

    $s->update('media', ['images_exclude_containers' => ['#main .hero > img']]);
    wpp_same(['#main .hero > img'], $s->get('media')['images_exclude_containers'], 'css-ish selector survives');

    // Per-file maps: setting a key false must disable it (the UI writes false
    // rather than removing the key, because mergeDeep cannot remove map keys).
    $s->update('css', ['minify' => ['/a.css' => true, '/b.css' => true]]);
    $s->update('css', ['minify' => ['/b.css' => false]]);
    $minify = $s->get('css')['minify'];
    wpp_same(true, $minify['/a.css'], 'untouched map entry kept');
    wpp_same(false, $minify['/b.css'], 'map entry can be turned off');

    // all() returns every group.
    $all = $s->all();
    foreach (['cache', 'css', 'js', 'html', 'media', 'cdn', 'database', 'tools'] as $group) {
        wpp_ok(isset($all[$group]), "all() includes {$group}");
    }

    // Add-on groups injected through the filter are respected.
    add_filter('wpp.settings.groups', static function (array $groups): array {
        $groups['testaddon'] = ['option' => 'wpp_testaddon', 'defaults' => ['enabled' => false, 'n' => 5]];
        return $groups;
    });
    $s2 = new SettingsService();
    wpp_ok($s2->knows('testaddon'), 'filter-injected group is known');
    $s2->update('testaddon', ['enabled' => true]);
    wpp_same(true, $s2->get('testaddon')['enabled'], 'filter-injected group persists');
    wpp_same(5, $s2->get('testaddon')['n'], 'filter-injected default retained');

    // The update event fires with the group id.
    $seen = [];
    add_action('wpp.settings.updated', static function ($group) use (&$seen): void {
        $seen[] = $group;
    });
    $s->update('html', ['enabled' => true]);
    wpp_ok(in_array('html', $seen, true), 'wpp.settings.updated fires with group');
};
