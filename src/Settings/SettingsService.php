<?php

declare(strict_types=1);

namespace WPP\Settings;

/**
 * Grouped settings store. Each logical group maps to a single wp_option holding
 * a defaults-merged associative array. Add-on modules inject groups via the
 * 'wpp.settings.groups' filter.
 *
 * Replaces the original plugin's ~110 individual wpp_* options.
 */
final class SettingsService
{
    /**
     * Core groups: id => ['option' => wp_option name, 'defaults' => tree].
     *
     * @var array<string, array{option: string, defaults: array<string, mixed>}>
     */
    private const GROUPS = [
        'cache' => [
            'option' => 'wpp_cache',
            'defaults' => [
                'enabled'              => false,
                'mobile'               => false,
                'clear_time'           => 10,
                'clear_unit'           => 3600,   // 60 | 3600 | 86400
                'clear_on_publish'     => false,
                'clear_on_delete'      => false,
                'clear_on_save'        => false,
                'keep_assets'          => false,
                'sitemaps'             => [],
                'browser_cache'        => false,
                'gzip'                 => false,
                'exclude_urls'         => [],
                'exclude_user_agents'  => [],
                'exclude_search_bots'  => false,
                'cache_query_strings'  => false,
                'block_cache_enabled'        => false,
                'block_cache_ttl'            => 3600,
                'block_cache_flush_on_clear' => true,
            ],
        ],
        'css' => [
            'option' => 'wpp_css',
            'defaults' => [
                'minify_inline'    => false,
                'defer'            => false,
                'critical_path'    => '',
                'remove_unused'    => false,
                'used_safelist'    => [],
                'combine_fonts'    => false,
                'host_fonts'       => false,
                'font_display'     => 'none', // none|auto|block|swap|fallback|optional
                'disable_loggedin' => false,
                'file_exclude'     => [],
                'exclude_urls'     => [],
                // path => bool / string maps
                'minify'           => [],
                'inline'           => [],
                'combine'          => [],
                'disable'          => [],
                'disable_position' => [],
                'disable_selected' => [],
                'disable_except'   => [],
                // resource hints (lists of origins)
                'dns_prefetch'     => [],
                'preconnect'       => [],
            ],
        ],
        'js' => [
            'option' => 'wpp_js',
            'defaults' => [
                'minify_inline'    => false,
                'defer'            => false,
                'delay'            => false,
                'delay_exclude'    => [],
                'disable_loggedin' => false,
                'file_exclude'     => [],
                'exclude_urls'     => [],
                'minify'           => [],
                'inline'           => [],
                'combine'          => [],
                'disable'          => [],
                'disable_position' => [],
                'disable_selected' => [],
                'disable_except'   => [],
            ],
        ],
        'html' => [
            'option' => 'wpp_html',
            'defaults' => [
                'enabled'            => false,
                'minify_normal'      => false,
                'minify_aggressive'  => false,
                'remove_comments'    => false,
                'remove_link_type'   => false,
                'remove_script_type' => false,
                'remove_quotes'      => false,
                'exclude_urls'       => [],
            ],
        ],
        'media' => [
            'option' => 'wpp_media',
            'defaults' => [
                'images_lazy'                => false,
                'images_lazy_disable_mobile' => false,
                'images_responsive'          => false,
                'images_dimensions'          => false,
                'lcp_images'                 => 0,
                'webp'                       => false,
                'images_exclude'             => [],
                'images_exclude_containers'  => [],
                'images_exclude_urls'        => [],
                'videos_lazy'                => false,
                'videos_exclude_urls'        => [],
                'disable_emoji'              => false,
                'disable_embeds'             => false,
            ],
        ],
        'cdn' => [
            'option' => 'wpp_cdn',
            'defaults' => [
                'enabled'  => false,
                'hostname' => '',
                'exclude'  => [],
            ],
        ],
        'database' => [
            'option' => 'wpp_database',
            'defaults' => [
                'cleanup_trash'      => false,
                'cleanup_spam'       => false,
                'cleanup_revisions'  => false,
                'cleanup_transients' => false,
                'cleanup_autodrafts' => false,
                'cleanup_cron'       => false,
                'frequency'          => 'none', // none|daily|weekly|monthly
            ],
        ],
        'tools' => [
            'option' => 'wpp_tools',
            'defaults' => [
                'enable_log'   => false,
                'object_cache' => false,
            ],
        ],
    ];

    /** Fields holding raw CSS. Emitted inside a <style> element, never as markup. */
    private const CSS_KEYS = ['critical_path'];

    /**
     * URL, pattern and selector values. sanitize_text_field() deletes every
     * percent-encoded octet, which rewrites '/%D0%BD%D0%BE/' to '//' and turns a
     * single exclusion into one that matches every URL on the site.
     */
    private const RAW_KEYS = [
        'exclude',
        'exclude_urls',
        'exclude_user_agents',
        'file_exclude',
        'delay_exclude',
        'images_exclude',
        'images_exclude_containers',
        'images_exclude_urls',
        'videos_exclude_urls',
        'custom_purge_urls',
        'sitemaps',
        'used_safelist',
        'dns_prefetch',
        'preconnect',
    ];

    /**
     * All groups: core + add-on (via filter).
     *
     * @return array<string, array{option: string, defaults: array<string, mixed>}>
     */
    public function groups(): array
    {
        /** @var array<string, array{option: string, defaults: array<string, mixed>}> $groups */
        $groups = apply_filters('wpp.settings.groups', self::GROUPS);
        return $groups;
    }

    public function knows(string $group): bool
    {
        return isset($this->groups()[$group]);
    }

    /** @return array<string, mixed> merged over the group defaults */
    public function get(string $group): array
    {
        $groups = $this->groups();
        if (! isset($groups[$group])) {
            return [];
        }

        $cfg = $groups[$group];
        $stored = get_option($cfg['option'], []);
        $stored = is_array($stored) ? $stored : [];

        return $this->mergeDeep($cfg['defaults'], $stored);
    }

    public function all(): array
    {
        $out = [];
        foreach (array_keys($this->groups()) as $group) {
            $out[$group] = $this->get($group);
        }
        return $out;
    }

    /**
     * Merge a partial update into a group and persist. Unknown top-level keys are dropped.
     *
     * @param array<string, mixed> $partial
     * @return array<string, mixed> the new merged value
     */
    public function update(string $group, array $partial): array
    {
        $groups = $this->groups();
        if (! isset($groups[$group])) {
            return [];
        }

        $cfg = $groups[$group];

        // Whitelist to known top-level keys, then sanitize.
        $clean = [];
        foreach ($partial as $key => $value) {
            if (! array_key_exists($key, $cfg['defaults'])) {
                continue;
            }
            $clean[$key] = is_int($cfg['defaults'][$key])
                ? $this->numeric($value, $cfg['defaults'][$key])
                : $this->sanitize($key, $value);
        }

        $current = $this->get($group);
        $next = $this->mergeDeep($current, $clean);

        // Fired before the write so listeners (e.g. settings history) can capture
        // the full prior state as a restore point.
        do_action('wpp.settings.updating', $group);

        update_option($cfg['option'], $next, false);

        do_action('wpp.settings.updated', $group, $next);

        return $next;
    }

    /**
     * Deep merge: scalars overwrite, associative arrays recurse, lists replace wholesale.
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> $over
     * @return array<string, mixed>
     */
    private function mergeDeep(array $base, array $over): array
    {
        foreach ($over as $key => $value) {
            if (
                is_array($value)
                && isset($base[$key])
                && is_array($base[$key])
                && $this->isAssoc($value)
                && $this->isAssoc($base[$key])
            ) {
                $base[$key] = $this->mergeDeep($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }

    private function isAssoc(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * Numeric settings are stored as integers whatever the form sent. An emptied
     * number field arrives as '', and a string there reaches the drop-in as a
     * cache lifetime of zero.
     */
    private function numeric(mixed $value, int $default): int
    {
        if (is_bool($value) || is_array($value) || $value === null || $value === '') {
            return $default;
        }

        $number = (int) $value;

        // A positive default means zero is not a legal value for that setting.
        return ($number <= 0 && $default > 0) ? $default : max(0, $number);
    }

    /**
     * Type-preserving sanitization. Plain strings are stripped of tags; URL and
     * pattern values and CSS are stored verbatim, because the text sanitizers
     * silently rewrite them into something that no longer means what was typed.
     */
    private function sanitize(string $key, mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                // Per-file map keys are asset URLs the parser looks up verbatim.
                $ck = is_string($k) ? $this->raw($k) : $k;
                $out[$ck] = $this->sanitize($key, $v);
            }
            return $out;
        }
        if (is_string($value)) {
            if (in_array($key, self::CSS_KEYS, true)) {
                return $this->css($value);
            }
            return in_array($key, self::RAW_KEYS, true)
                ? $this->raw($value)
                : sanitize_text_field($value);
        }
        return $value;
    }

    private function raw(string $value): string
    {
        return trim(str_replace("\0", '', wp_check_invalid_utf8($value)));
    }

    /** Only a closing style tag can break out of the element the CSS is printed in. */
    private function css(string $value): string
    {
        $value = str_replace("\0", '', wp_check_invalid_utf8($value));

        return trim(str_ireplace('</style', '<\\/style', $value));
    }
}
