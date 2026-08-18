<?php

declare(strict_types=1);

namespace WPP\Cache;

use WPP\Settings\SettingsService;

/** Caches a region of rendered HTML, independent of the full-page cache. */
final class FragmentCache
{
    /** Prefix marking a stored block entry that carries an asset-handle header. */
    private const ASSETS_MARK = "\x00wppa";

    /** @var list<array{identity:string, ttl:int, level:int}> open buffer frames (supports nesting) */
    private array $stack = [];

    /** @var array<string, array{scripts:list<string>, styles:list<string>}> queue snapshots taken on a block miss */
    private array $pending = [];

    public function __construct(
        private SettingsService $settings,
        private FragmentStore $store
    ) {
    }

    public function register(): void
    {
        add_shortcode('wpp_cache', [$this, 'shortcode']);
        add_filter('pre_render_block', [$this, 'preRenderBlock'], 10, 2);
        add_filter('render_block', [$this, 'renderBlock'], 10, 2);
    }

    /**
     * Returning a string here stops WordPress from rendering the block and its
     * inner blocks at all.
     *
     * @param string|null          $pre
     * @param array<string, mixed> $block
     * @return string|null
     */
    public function preRenderBlock($pre, $block)
    {
        if (($block['blockName'] ?? '') !== 'wpp/cache' || $this->skip()) {
            return $pre;
        }

        $identity = $this->blockIdentity($block);
        $cached   = $this->store->get($identity);
        if ($cached === null) {
            $this->pending[$identity] = $this->assetQueue();
            return $pre;
        }

        // Short-circuiting here skips WP_Block::render(), which is where inner
        // blocks enqueue their view scripts and styles, so replay them by hand.
        [$html, $assets] = $this->decode($cached);
        $this->enqueueAssets($assets);

        return $html;
    }

    /**
     * Only runs on a miss: render_block does not fire when preRenderBlock returned content.
     *
     * @param string               $content
     * @param array<string, mixed> $block
     */
    public function renderBlock($content, $block): string
    {
        if (($block['blockName'] ?? '') !== 'wpp/cache' || $this->skip()) {
            return $content;
        }

        $identity = $this->blockIdentity($block);
        $before   = $this->pending[$identity] ?? null;
        unset($this->pending[$identity]);

        $assets = $before === null ? ['scripts' => [], 'styles' => []] : $this->assetsAdded($before);

        $this->store->set(
            $identity,
            $this->encode($content, $assets),
            $this->resolveTtl($this->blockOpts($block))
        );

        return $content;
    }

    public function enabled(): bool
    {
        return ! empty($this->settings->get('cache')['block_cache_enabled']);
    }

    /**
     * False on a hit (cached HTML is echoed, skip rendering); true otherwise (render, then call end()).
     *
     * @param array<string, mixed> $opts
     */
    public function start(string $key, array $opts = []): bool
    {
        if ($this->skip()) {
            return true;
        }

        $identity = $this->identity($key, $opts);
        $cached   = $this->store->get($identity);
        if ($cached !== null) {
            echo $cached;
            return false;
        }

        ob_start();
        $this->stack[] = [
            'identity' => $identity,
            'ttl'      => $this->resolveTtl($opts),
            'level'    => ob_get_level(),
        ];
        return true;
    }

    public function end(): void
    {
        if ($this->stack === []) {
            return;
        }
        $frame = array_pop($this->stack);

        // Code inside the region can close our buffer (ob_end_flush is common in
        // older plugins). Grabbing whatever is innermost would then steal the
        // page buffer and flush a truncated page into the page cache.
        if (ob_get_level() !== $frame['level']) {
            return;
        }

        $html = (string) ob_get_clean();
        $this->store->set($frame['identity'], $html, $frame['ttl']);
        echo $html;
    }

    /** @param array<string, mixed> $opts */
    public function wrap(string $key, array $opts, callable $callback): string
    {
        if ($this->skip()) {
            ob_start();
            $callback();
            return (string) ob_get_clean();
        }

        $identity = $this->identity($key, $opts);
        $cached   = $this->store->get($identity);
        if ($cached !== null) {
            return $cached;
        }

        ob_start();
        $callback();
        $html = (string) ob_get_clean();
        $this->store->set($identity, $html, $this->resolveTtl($opts));

        return $html;
    }

    public function shortcode(mixed $atts, ?string $content = null): string
    {
        $atts = shortcode_atts([
            'key'           => '',
            'ttl'           => 0,
            'vary_url'      => '1',
            'vary_role'     => '0',
            'vary_device'   => '0',
            'vary_loggedin' => '0',
        ], is_array($atts) ? $atts : [], 'wpp_cache');

        $content = (string) $content;
        $opts    = [
            'ttl'           => (int) $atts['ttl'],
            'vary_url'      => $this->boolAtt($atts['vary_url']),
            'vary_role'     => $this->boolAtt($atts['vary_role']),
            'vary_device'   => $this->boolAtt($atts['vary_device']),
            'vary_loggedin' => $this->boolAtt($atts['vary_loggedin']),
        ];
        $key = $atts['key'] !== '' ? (string) $atts['key'] : 'sc_' . md5($content);

        if ($this->skip()) {
            return do_shortcode($content);
        }

        $identity = $this->identity($key, $opts);
        $cached   = $this->store->get($identity);
        if ($cached !== null) {
            return $cached;
        }

        $rendered = do_shortcode($content);
        $this->store->set($identity, $rendered, $this->resolveTtl($opts));

        return $rendered;
    }

    /** @param array<string, mixed> $opts */
    public function identity(string $key, array $opts): string
    {
        $parts = ['k=' . $key];

        if ($opts['vary_url'] ?? true) {
            $parts[] = 'u=' . $this->requestPath();
        }

        // Not opt-in: a logged-in render carries nonces and account-specific
        // markup, and a shared entry leaks it in whichever direction fills first.
        $loggedIn = is_user_logged_in();
        $parts[]  = 'l=' . ($loggedIn ? '1' : '0');

        if ($loggedIn || ! empty($opts['vary_role'])) {
            $parts[] = 'r=' . $this->currentRole();
        }
        if (! empty($opts['vary_device'])) {
            $parts[] = 'd=' . (wp_is_mobile() ? 'm' : 'd');
        }

        return implode('|', $parts);
    }

    /** Tracking params are dropped so ?utm_* variants cannot mint endless entries. */
    private function requestPath(): string
    {
        $uri   = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path  = (string) (parse_url($uri, PHP_URL_PATH) ?: '/');
        $query = CacheStore::strippedQuery((string) parse_url($uri, PHP_URL_QUERY));

        return $query !== '' ? $path . '?' . $query : $path;
    }

    /** @param array<string, mixed> $block */
    private function blockIdentity(array $block): string
    {
        $key = 'block:' . md5(serialize_block($block));

        // The serialized source is identical for every iteration of a Query
        // Loop, so without the loop's post the first item is replayed for all.
        $postId = $this->contextPostId($block);
        if ($postId > 0) {
            $key .= ':p' . $postId;
        }

        return $this->identity($key, $this->blockOpts($block));
    }

    /** @param array<string, mixed> $block */
    private function contextPostId(array $block): int
    {
        $context = $block['context']['postId'] ?? null;
        if ($context !== null) {
            return (int) $context;
        }

        return function_exists('get_the_ID') ? (int) get_the_ID() : 0;
    }

    /**
     * @param array<string, mixed> $block
     * @return array<string, mixed>
     */
    private function blockOpts(array $block): array
    {
        $attrs = $block['attrs'] ?? [];
        return [
            'ttl'           => (int) ($attrs['ttl'] ?? 0),
            'vary_url'      => $attrs['varyUrl'] ?? true,
            'vary_loggedin' => ! empty($attrs['varyLoggedin']),
            'vary_role'     => ! empty($attrs['varyRole']),
            'vary_device'   => ! empty($attrs['varyDevice']),
        ];
    }

    private function skip(): bool
    {
        if (! $this->enabled()) {
            return true;
        }
        if (is_admin() || wp_doing_ajax() || (defined('DOING_CRON') && DOING_CRON)) {
            return true;
        }
        return ! empty($_POST);
    }

    /** @param array<string, mixed> $opts */
    private function resolveTtl(array $opts): int
    {
        $ttl = (int) ($opts['ttl'] ?? 0);
        if ($ttl > 0) {
            return $ttl;
        }
        $default = (int) ($this->settings->get('cache')['block_cache_ttl'] ?? 0);
        return $default > 0 ? $default : 3600;
    }

    private function currentRole(): string
    {
        if (! is_user_logged_in()) {
            return 'anon';
        }
        $user = wp_get_current_user();
        return $user->roles[0] ?? 'none';
    }

    private function boolAtt(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /** @return array{scripts:list<string>, styles:list<string>} */
    private function assetQueue(): array
    {
        return [
            'scripts' => isset($GLOBALS['wp_scripts']->queue) ? array_values((array) $GLOBALS['wp_scripts']->queue) : [],
            'styles'  => isset($GLOBALS['wp_styles']->queue) ? array_values((array) $GLOBALS['wp_styles']->queue) : [],
        ];
    }

    /**
     * @param array{scripts:list<string>, styles:list<string>} $before
     * @return array{scripts:list<string>, styles:list<string>}
     */
    private function assetsAdded(array $before): array
    {
        $now = $this->assetQueue();

        return [
            'scripts' => array_values(array_diff($now['scripts'], $before['scripts'])),
            'styles'  => array_values(array_diff($now['styles'], $before['styles'])),
        ];
    }

    /** @param array<string, mixed> $assets */
    private function enqueueAssets(array $assets): void
    {
        foreach ((array) ($assets['scripts'] ?? []) as $handle) {
            wp_enqueue_script((string) $handle);
        }
        foreach ((array) ($assets['styles'] ?? []) as $handle) {
            wp_enqueue_style((string) $handle);
        }
    }

    /** @param array{scripts:list<string>, styles:list<string>} $assets */
    private function encode(string $html, array $assets): string
    {
        if (($assets['scripts'] ?? []) === [] && ($assets['styles'] ?? []) === []) {
            return $html;
        }

        return self::ASSETS_MARK . (string) wp_json_encode($assets) . "\x00" . $html;
    }

    /** @return array{0:string, 1:array<string, mixed>} */
    private function decode(string $raw): array
    {
        if (! str_starts_with($raw, self::ASSETS_MARK)) {
            return [$raw, []];
        }

        $start = strlen(self::ASSETS_MARK);
        $end   = strpos($raw, "\x00", $start);
        if ($end === false) {
            return [$raw, []];
        }

        $assets = json_decode(substr($raw, $start, $end - $start), true);

        return [substr($raw, $end + 1), is_array($assets) ? $assets : []];
    }
}
