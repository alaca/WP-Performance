<?php
/**
 * WP Performance advanced-cache.php drop-in.
 *
 * Auto-installed into wp-content/. Serves a static cached page before
 * WordPress fully loads. Standalone by necessity (no plugin classes available).
 * Cache path logic mirrors WPP\Cache\CacheStore::fileFor().
 */

if (! defined('ABSPATH')) {
    return;
}

// Ownership + version stamp. DropinInstaller rewrites the placeholder on copy,
// so it can tell our drop-in from another plugin's and re-copy a stale one.
define('WPP_ADVANCED_CACHE', '@wpp-version@');

if (! function_exists('wpp_dropin_wildcard')) {
    function wpp_dropin_wildcard(string $pattern): string
    {
        $p = preg_quote($pattern, '#');
        $p = str_replace(
            ['\{any\}', '\{numbers\}', '\{letters\}', '\{all\}'],
            ['[^/]+', '[0-9]+', '[A-Za-z]+', '.*'],
            $p
        );
        return '#^' . $p . '$#';
    }
}

if (! function_exists('wpp_dropin_host')) {
    // Mirrors WPP\Cache\CacheStore::sanitizeHost(). The writer derives its host
    // from home_url(); a reader that normalizes differently looks for files that
    // are never written, so a mixed-case Host header would disable the cache.
    function wpp_dropin_host(string $host): string
    {
        $host  = strtolower(explode(':', $host, 2)[0]);
        $clean = (string) preg_replace('/[^a-z0-9\.\-_]/', '', $host);

        return $clean !== '' ? $clean : 'site';
    }
}

if (! function_exists('wpp_dropin_path_key')) {
    // Mirrors WPP\Cache\RuntimeSettings::pathKey().
    /** @param string[] $segments */
    function wpp_dropin_path_key(array $segments): string
    {
        $key = '';
        foreach ($segments as $segment) {
            $key .= '~' . preg_replace('/[^a-z0-9\-_]/', '-', strtolower($segment));
        }

        return $key;
    }
}

if (! function_exists('wpp_dropin_conf')) {
    // Sites in a subdirectory network share a host, so each writes its own
    // config keyed by site path. Longest matching prefix wins; the host-only
    // file is both the single-site install and the network's main site.
    function wpp_dropin_conf(string $cacheDir, string $host, string $requestUri): string
    {
        $path     = explode('?', $requestUri, 2)[0];
        $segments = array_slice(array_values(array_filter(explode('/', $path), 'strlen')), 0, 3);

        for ($i = count($segments); $i > 0; $i--) {
            $file = $cacheDir . $host . wpp_dropin_path_key(array_slice($segments, 0, $i)) . '.json';
            if (is_file($file)) {
                return $file;
            }
        }

        $file = $cacheDir . $host . '.json';

        return is_file($file) ? $file : '';
    }
}

if (! function_exists('wpp_dropin_stripped_query')) {
    function wpp_dropin_stripped_query(string $query): string
    {
        if ($query === '') {
            return '';
        }
        $tracking = [
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
            'gclid', 'fbclid', 'gad_source', 'gbraid', 'wbraid', 'msclkid',
            'mc_cid', 'mc_eid', '_ga', '_gl', 'usqp', 'age-verified',
        ];
        parse_str($query, $params);
        foreach ($tracking as $param) {
            unset($params[$param]);
        }
        if ($params === []) {
            return '';
        }
        ksort($params);
        return http_build_query($params);
    }
}

if (! function_exists('wpp_dropin_is_mobile')) {
    // Mirrors wp_is_mobile(), which is what PageCache uses to pick the variant
    // it writes. Any divergence hands phone HTML to desktops and back.
    function wpp_dropin_is_mobile(string $ua, ?string $hint): bool
    {
        if ($hint !== null) {
            return $hint === '?1';
        }
        if ($ua === '') {
            return false;
        }
        foreach (['Mobile', 'Android', 'Silk/', 'Kindle', 'BlackBerry', 'Opera Mini', 'Opera Mobi'] as $token) {
            if (strpos($ua, $token) !== false) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('wpp_dropin_canonical')) {
    function wpp_dropin_canonical(string $requestUri, bool $trailingSlash): bool
    {
        $path = explode('?', $requestUri, 2)[0];
        $norm = '/' . trim(str_replace('..', '', $path), '/');
        if ($norm !== '/' && $trailingSlash) {
            $norm .= '/';
        }

        return $path === $norm && strpos($path, '//') === false;
    }
}

if (! function_exists('wpp_dropin_accepts_gzip')) {
    function wpp_dropin_accepts_gzip(string $header): bool
    {
        $accepted = false;
        foreach (explode(',', strtolower($header)) as $part) {
            $bits  = explode(';', $part);
            $token = trim($bits[0]);
            if ($token !== 'gzip' && $token !== '*') {
                continue;
            }
            $q = 1.0;
            foreach (array_slice($bits, 1) as $param) {
                $param = str_replace(' ', '', $param);
                if (strpos($param, 'q=') === 0) {
                    $q = (float) substr($param, 2);
                }
            }
            if ($token === 'gzip') {
                return $q > 0;
            }
            $accepted = $q > 0;
        }

        return $accepted;
    }
}

if (! function_exists('wpp_dropin_vary')) {
    function wpp_dropin_vary(bool $mobile): string
    {
        return 'Accept-Encoding' . ($mobile ? ', User-Agent' : '');
    }
}

if (! function_exists('wpp_dropin_file')) {
    function wpp_dropin_file(string $cacheDir, string $host, string $requestUri, bool $permalinks, bool $mobile): string
    {
        $parts = explode('?', $requestUri, 2);
        $path  = '/' . trim(str_replace('..', '', $parts[0]), '/');
        if ($path !== '/') {
            $path .= '/';
        }
        $query = wpp_dropin_stripped_query($parts[1] ?? '');

        if ($permalinks) {
            $dir = $cacheDir . $host . $path;
            $key = $query !== '' ? md5($query) : 'index';
        } else {
            $dir = $cacheDir . $host . '/';
            $key = md5($host . $path . ($query !== '' ? '?' . $query : ''));
        }

        return $dir . $key . ($mobile ? '-mobile' : '') . '.html';
    }
}

// Only cache plain GET requests with no POST payload.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    return;
}
if (! empty($_POST)) {
    return;
}

$wpp_host = wpp_dropin_host((string) ($_SERVER['HTTP_HOST'] ?? ''));
$wpp_dir  = WP_CONTENT_DIR . '/cache/wpp-cache/';
$wpp_uri  = $_SERVER['REQUEST_URI'] ?? '/';

$wpp_conf = wpp_dropin_conf($wpp_dir, $wpp_host, $wpp_uri);
if ($wpp_conf === '') {
    return;
}

$wpp_settings = json_decode((string) file_get_contents($wpp_conf), true);
if (! is_array($wpp_settings) || empty($wpp_settings['enabled']) || ! empty($wpp_settings['disabled'])) {
    return;
}

// Skip real (non-tracking) query strings unless explicitly allowed.
if (empty($wpp_settings['cache_query_strings'])
    && wpp_dropin_stripped_query((string) parse_url($wpp_uri, PHP_URL_QUERY)) !== ''
) {
    return;
}

$wpp_url = (($_SERVER['HTTPS'] ?? '') === 'on' ? 'https' : 'http') . '://' . $wpp_host . $wpp_uri;

// URL exclusions.
foreach ((array) ($wpp_settings['exclude'] ?? []) as $wpp_pattern) {
    if ($wpp_pattern === '') {
        continue;
    }
    if (strpos($wpp_url, $wpp_pattern) !== false || @preg_match(wpp_dropin_wildcard($wpp_pattern), $wpp_url)) {
        return;
    }
}

// Never serve cache to logged-in / commenter / password-protected sessions.
foreach (array_keys($_COOKIE) as $wpp_cookie) {
    if (preg_match('/^(wordpress_logged_in_|wp-postpass_|comment_author_|woocommerce_items_in_cart|woocommerce_cart_hash|wp_woocommerce_session_|edd_items_in_cart|edd_cart_token)/', (string) $wpp_cookie)) {
        return;
    }
}

$wpp_ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
foreach ((array) ($wpp_settings['user_agents'] ?? []) as $wpp_bad) {
    if ($wpp_bad !== '' && stripos($wpp_ua, (string) $wpp_bad) !== false) {
        return;
    }
}

$wpp_mobile = ! empty($wpp_settings['mobile']) && wpp_dropin_is_mobile(
    $wpp_ua,
    isset($_SERVER['HTTP_SEC_CH_UA_MOBILE']) ? (string) $_SERVER['HTTP_SEC_CH_UA_MOBILE'] : null
);

if (! empty($wpp_settings['search_bots'])
    && preg_match('/bot|crawl|slurp|spider|bing|google|yandex|duckduck/i', $wpp_ua)
) {
    return;
}

// Variants of one path (/about, //about//) all normalize to the same file, so
// serving them would answer 200 where WordPress would have issued its canonical
// redirect. A config from an older release has no flag and keeps the old behavior.
if (isset($wpp_settings['trailing_slash'])
    && ! wpp_dropin_canonical($wpp_uri, (bool) $wpp_settings['trailing_slash'])
) {
    return;
}

// No desktop fallback for mobile: serving one here exits before WordPress runs,
// so the mobile variant would never be generated.
$wpp_file = wpp_dropin_file($wpp_dir, $wpp_host, $wpp_uri, ! empty($wpp_settings['permalinks']), $wpp_mobile);
if (! is_file($wpp_file)) {
    return;
}

// Freshness: regenerate if older than the configured expiry.
$wpp_expire = (int) ($wpp_settings['expire'] ?? 0);
$wpp_mtime  = filemtime($wpp_file);
if ($wpp_expire > 0 && (time() - $wpp_mtime) > $wpp_expire) {
    return;
}

// The body varies on Accept-Encoding, and on User-Agent when the mobile variant
// is in play. Without this a shared proxy replays one variant to every client.
header('Vary: ' . wpp_dropin_vary(! empty($wpp_settings['mobile'])));

if (! empty($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $wpp_mtime) {
    header('HTTP/1.1 304 Not Modified');
    exit;
}

header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $wpp_mtime) . ' GMT');
header('Content-Type: text/html; charset=UTF-8');
header('X-WPP-Cache: HIT');

if (wpp_dropin_accepts_gzip((string) ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''))
    && is_file($wpp_file . '.gz')
    && filemtime($wpp_file . '.gz') >= $wpp_mtime
) {
    header('Content-Encoding: gzip');
    readfile($wpp_file . '.gz');
    exit;
}

readfile($wpp_file);
exit;
