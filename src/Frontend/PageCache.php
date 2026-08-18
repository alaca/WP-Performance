<?php

declare(strict_types=1);

namespace WPP\Frontend;

use WPP\Cache\CacheStore;
use WPP\Foundation\Hooks\HookProvider;
use WPP\Settings\SettingsService;
use WPP\Support\Url;

/**
 * Buffers the rendered front-end HTML, runs it through the optimization filter
 * ('wpp.frontend.html'), and writes it to the page cache when caching applies.
 * Buffering starts when caching OR any asset optimization is active.
 */
final class PageCache extends HookProvider
{
    private float $started = 0.0;

    public function __construct(
        private SettingsService $settings,
        private CacheStore $store
    ) {
    }

    protected function actions(): array
    {
        return ['template_redirect' => ['startBuffer', 0]];
    }

    public function startBuffer(): void
    {
        if (! $this->shouldBuffer()) {
            return;
        }
        $this->started = microtime(true);
        ob_start([$this, 'finish']);
    }

    public function finish(string $html): string
    {
        if (strlen($html) < 255 || http_response_code() !== 200) {
            return $html;
        }

        $html = (string) apply_filters('wpp.frontend.html', $html);

        $seconds = $this->started > 0 ? round(microtime(true) - $this->started, 3) : 0;
        $cached  = $this->cacheApplies();

        $html .= $cached
            ? sprintf("\n<!-- Cached by WP Performance in %ss on %s UTC -->", $seconds, gmdate('Y-m-d H:i:s'))
            : sprintf("\n<!-- Optimized by WP Performance in %ss -->", $seconds);

        // Re-checked after rendering: a template or plugin can set DONOTCACHEPAGE
        // while the page is being built, long after startBuffer() ran.
        if ($cached && defined('DONOTCACHEPAGE') && DONOTCACHEPAGE) {
            $cached = false;
        }

        if ($cached) {
            $cache      = $this->settings->get('cache');
            $host       = CacheStore::sanitizeHost((string) parse_url(home_url(), PHP_URL_HOST));
            $permalinks = (bool) get_option('permalink_structure');
            $mobile     = ! empty($cache['mobile']) && wp_is_mobile();
            $file       = CacheStore::fileFor($host, $_SERVER['REQUEST_URI'] ?? '/', $permalinks, $mobile);
            $this->store->save($file, $html, ! empty($cache['gzip']));
            do_action('wpp.cache.saved', $_SERVER['REQUEST_URI'] ?? '/');
        }

        return $html;
    }

    private function shouldBuffer(): bool
    {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return false;
        }
        if (defined('DONOTCACHEPAGE') && DONOTCACHEPAGE) {
            return false;
        }
        if (! empty($_POST)) {
            return false;
        }
        if (is_404() || is_search() || is_feed() || is_preview() || is_trackback()) {
            return false;
        }

        return $this->cacheApplies() || $this->optimizationActive();
    }

    private function cacheApplies(): bool
    {
        $cache = $this->settings->get('cache');
        if (empty($cache['enabled']) || is_user_logged_in()) {
            return false;
        }

        // The drop-in stops serving while this is set, so writes must stop too:
        // otherwise `wp wpp disable` fills the cache with debug-state renders
        // that get fresh mtimes and are served the moment it is lifted.
        if (get_option('wpp_disabled', false)) {
            return false;
        }

        // Password-protected content must never reach the shared anonymous
        // cache: the visitor who entered the password renders the unlocked page,
        // and everyone else would then be served it straight from disk.
        if ($this->passwordSession() || (is_singular() && post_password_required())) {
            return false;
        }

        // Core prefills the comment form from these cookies, so a commenter's
        // render carries their name, email and URL, plus the moderation notices
        // on their own pending comments. The drop-in refuses to serve cache to
        // that session; without the same check here it is that session's render
        // the drop-in then hands to everyone else.
        if ($this->commenterSession()) {
            return false;
        }

        // Only cache real HTML. Sitemaps and other XML/JSON responses would be
        // replayed by the drop-in with a text/html content type.
        if (! $this->isHtmlResponse()) {
            return false;
        }

        if (empty($cache['cache_query_strings'])
            && CacheStore::hasRealQuery((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY))
        ) {
            return false;
        }

        if ($this->isEcommercePage() || $this->hasCartCookie()) {
            return false;
        }

        $current = (is_ssl() ? 'https' : 'http') . '://'
            . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');

        if (Url::anyMatch((array) ($cache['exclude_urls'] ?? []), $current)) {
            return false;
        }

        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

        if (! empty($cache['exclude_search_bots'])
            && preg_match('/bot|crawl|slurp|spider|bing|google|yandex|duckduck/i', $ua)
        ) {
            return false;
        }

        // Also enforced on the write path. The drop-in refuses to serve an
        // excluded agent, but without this its render would still be the copy
        // written to disk and handed to everyone else.
        foreach ((array) ($cache['exclude_user_agents'] ?? []) as $pattern) {
            $pattern = trim((string) $pattern);
            if ($pattern !== '' && stripos($ua, $pattern) !== false) {
                return false;
            }
        }

        if (is_singular()) {
            $id = get_queried_object_id();
            if ($id && get_post_meta($id, '_wpp_exclude_cache', true)) {
                return false;
            }
        }

        return true;
    }

    /** A visitor holding a post-password cookie renders unlocked content. */
    private function passwordSession(): bool
    {
        foreach (array_keys($_COOKIE) as $cookie) {
            if (str_starts_with((string) $cookie, 'wp-postpass_')) {
                return true;
            }
        }
        return false;
    }

    /** A visitor holding comment-author cookies renders a personalised page. */
    private function commenterSession(): bool
    {
        foreach (array_keys($_COOKIE) as $cookie) {
            if (str_starts_with((string) $cookie, 'comment_author_')) {
                return true;
            }
        }
        return false;
    }

    private function isHtmlResponse(): bool
    {
        if (is_feed() || is_embed()) {
            return false;
        }

        foreach (headers_list() as $header) {
            if (stripos($header, 'content-type:') !== 0) {
                continue;
            }
            return stripos($header, 'text/html') !== false;
        }

        return true;
    }

    private function isEcommercePage(): bool
    {
        if (function_exists('is_woocommerce')) {
            if ((function_exists('is_cart') && is_cart())
                || (function_exists('is_checkout') && is_checkout())
                || (function_exists('is_account_page') && is_account_page())
            ) {
                return true;
            }
        }
        return function_exists('edd_is_checkout') && edd_is_checkout();
    }

    private function hasCartCookie(): bool
    {
        foreach (array_keys($_COOKIE) as $cookie) {
            if (preg_match('/^(woocommerce_items_in_cart|woocommerce_cart_hash|wp_woocommerce_session_|edd_items_in_cart|edd_cart_token)/', (string) $cookie)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Must list every option AssetParser acts on: the parser only ever runs
     * through the filter this buffer applies, so an option missing here is an
     * option that silently does nothing whenever page caching does not apply.
     */
    private function optimizationActive(): bool
    {
        $css   = $this->settings->get('css');
        $js    = $this->settings->get('js');
        $html  = $this->settings->get('html');
        $media = $this->settings->get('media');
        $cdn   = $this->settings->get('cdn');

        if (! empty($html['enabled']) || ! empty($cdn['enabled'])) {
            return true;
        }

        foreach (['defer', 'minify_inline', 'remove_unused', 'combine_fonts', 'host_fonts'] as $flag) {
            if (! empty($css[$flag])) {
                return true;
            }
        }
        if ((string) ($css['font_display'] ?? 'none') !== 'none') {
            return true;
        }
        foreach (['dns_prefetch', 'preconnect'] as $hints) {
            if (array_filter((array) ($css[$hints] ?? [])) !== []) {
                return true;
            }
        }

        if (! empty($js['defer']) || ! empty($js['delay']) || ! empty($js['minify_inline'])) {
            return true;
        }

        if (! empty($media['images_lazy']) || ! empty($media['images_responsive'])
            || ! empty($media['videos_lazy']) || ! empty($media['images_dimensions'])
            || ! empty($media['webp']) || (int) ($media['lcp_images'] ?? 0) > 0
        ) {
            return true;
        }

        foreach (['minify', 'combine', 'inline', 'disable'] as $map) {
            if (array_filter((array) ($css[$map] ?? [])) !== []
                || array_filter((array) ($js[$map] ?? [])) !== []
            ) {
                return true;
            }
        }

        return false;
    }
}
