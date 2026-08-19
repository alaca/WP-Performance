<?php

declare(strict_types=1);

use WPP\Addons\Prefetch\PrefetchModule;

/**
 * The Speculation Rules document must stay valid JSON with the right exclusions:
 * a malformed or over-broad rule set makes browsers prerender admin or logout
 * URLs, which has real side effects.
 */
return static function (): void {
    $render = static function (array $config): array {
        $module = new PrefetchModule();
        $prop = new ReflectionProperty($module, 'config');
        $prop->setAccessible(true);
        $prop->setValue($module, $config);

        ob_start();
        $module->speculationRules();
        $out = ob_get_clean();

        wpp_contains('type="speculationrules"', $out, 'rules are emitted in a speculationrules script');
        $json = preg_replace('#^<script[^>]*>|</script>\s*$#', '', trim($out));
        $data = json_decode((string) $json, true);
        wpp_ok(is_array($data), 'rules are valid JSON');
        return is_array($data) ? $data : [];
    };

    $data = $render(['mode' => 'prerender', 'eagerness' => 'moderate', 'exclude' => ['/cart/*']]);
    wpp_ok(isset($data['prerender'][0]), 'prerender rule present');

    $rule = $data['prerender'][0];
    wpp_same('document', $rule['source'], 'rule sources links from the document');
    wpp_same('moderate', $rule['eagerness'], 'eagerness honored');

    $conditions = $rule['where']['and'] ?? [];
    wpp_ok($conditions !== [], 'conditions present');
    wpp_same('/*', $conditions[0]['href_matches'] ?? null, 'same-origin base matcher present');

    $excluded = [];
    foreach ($conditions as $c) {
        if (isset($c['not']['href_matches'])) {
            $excluded[] = $c['not']['href_matches'];
        }
    }
    wpp_ok(in_array('/wp-admin/*', $excluded, true), 'wp-admin excluded');
    wpp_ok(in_array('/wp-login.php*', $excluded, true), 'login excluded');
    wpp_ok(in_array('/*\\?*', $excluded, true), 'query-string action links excluded');
    wpp_ok(in_array('/cart/*', $excluded, true), 'user exclusion applied');

    $selectorExcluded = false;
    foreach ($conditions as $c) {
        if (($c['not']['selector_matches'] ?? '') === '[rel~="nofollow"]') {
            $selectorExcluded = true;
        }
    }
    wpp_ok($selectorExcluded, 'nofollow links excluded');

    // Prefetch mode and eager setting.
    $prefetch = $render(['mode' => 'prefetch', 'eagerness' => 'eager', 'exclude' => []]);
    wpp_ok(isset($prefetch['prefetch'][0]), 'prefetch mode emits a prefetch rule');
    wpp_ok(! isset($prefetch['prerender']), 'prefetch mode does not also prerender');
    wpp_same('eager', $prefetch['prefetch'][0]['eagerness'], 'eager honored');

    // Unknown values fall back to safe defaults rather than emitting junk.
    $bogus = $render(['mode' => 'nonsense', 'eagerness' => 'bogus', 'exclude' => []]);
    wpp_ok(isset($bogus['prerender'][0]), 'unknown mode falls back to prerender');
    wpp_same('moderate', $bogus['prerender'][0]['eagerness'], 'unknown eagerness falls back to moderate');

    // Empty and whitespace-only exclusions must not produce empty matchers,
    // which would exclude everything.
    $empty = $render(['mode' => 'prerender', 'eagerness' => 'moderate', 'exclude' => ['', '   ']]);
    foreach ($empty['prerender'][0]['where']['and'] as $c) {
        if (isset($c['not']['href_matches'])) {
            wpp_ok(trim($c['not']['href_matches']) !== '', 'no empty exclusion matcher emitted');
        }
    }

    // The fallback script must be gated on lack of Speculation Rules support.
    $module = new PrefetchModule();
    ob_start();
    $module->fallback();
    $fallback = ob_get_clean();
    wpp_contains('speculationrules', $fallback, 'fallback checks for speculation rules support');
    wpp_contains('HTMLScriptElement.supports', $fallback, 'fallback uses the supports check');
    wpp_contains('prefetch', $fallback, 'fallback still prefetches on unsupported browsers');
};
