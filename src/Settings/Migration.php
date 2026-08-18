<?php

declare(strict_types=1);

namespace WPP\Settings;

/**
 * One-time migration from the original plugin's individual wpp_* options to the
 * grouped settings store. Idempotent and flag-guarded, so it is safe to call on
 * activation and on admin boot (the latter catches in-place updates).
 */
final class Migration
{
    private const FLAG    = 'wpp_migrated';
    private const PREFIX  = 'wpp_';
    private const UNSET   = "\0__wpp_unset__\0";

    /**
     * Legacy option keys per destination group. Keys of a group that is not
     * registered must survive, or an activation that runs before the add-on
     * modules boot would delete configuration nothing has read yet.
     *
     * @var array<string, list<string>>
     */
    private const LEGACY_KEYS = [
        'cache' => [
            'cache', 'mobile_cache', 'cache_time', 'cache_length', 'update_clear',
            'save_clear', 'delete_clear', 'clear_assets', 'cache_url_exclude',
            'browser_cache', 'gzip_compression', 'sitemaps_list', 'user_agents_exclude',
            'search_bots_exclude',
        ],
        'html' => [
            'html_optimization', 'html_minify_normal', 'html_minify_aggressive',
            'html_remove_comments', 'html_remove_link_type', 'html_remove_script_type',
            'html_remove_qoutes', 'html_url_exclude',
        ],
        'css' => [
            'css_minify', 'css_minify_inline', 'css_combine', 'css_inline', 'css_disable',
            'css_disable_position', 'css_disable_selected', 'css_disable_except', 'css_defer',
            'css_prefetch', 'css_preconnect', 'css_combine_fonts', 'css_font_display',
            'css_url_exclude', 'css_file_exclude', 'css_custom_path_def', 'css_disable_loggedin',
            // The css group absorbs the old per-type resource hints, js ones included.
            'js_prefetch', 'js_preconnect',
        ],
        'js' => [
            'js_minify', 'js_minify_inline', 'js_combine', 'js_inline', 'js_defer',
            'js_url_exclude', 'js_disable', 'js_disable_position', 'js_disable_selected',
            'js_disable_except', 'js_file_exclude', 'js_disable_loggedin',
        ],
        'media' => [
            'images_resp', 'images_force', 'images_lazy', 'disable_lazy_mobile',
            'images_containers_ids', 'images_exclude', 'image_url_exclude', 'videos_lazy',
            'video_url_exclude', 'disable_emoji', 'disable_embeds',
        ],
        'cdn' => ['cdn', 'cdn_hostname', 'cdn_exclude'],
        'database' => [
            'db_cleanup_transients', 'db_cleanup_revisions', 'db_cleanup_spam', 'db_cleanup_trash',
            'db_cleanup_cron', 'db_cleanup_autodrafts', 'db_cleanup_frequency', 'db_cleanup_next',
        ],
        'tools' => ['enable_log'],
        'cloudflare' => [
            'cf_enabled', 'cf_email', 'cf_api_key', 'cf_zone_id', 'cf_dev_mode', 'cf_cache_level',
            'cf_browser_expire', 'cf_minify_css', 'cf_minify_js', 'cf_minify_html',
            'cf_rocket_loader', 'cf_brotli', 'cf_custom_purge_urls',
        ],
        'varnish' => ['varnish_auto_purge', 'varnish_custom_host'],
        'prefetch' => ['prefetch_pages'],
    ];

    /** Legacy keys that belong to no group: consumed elsewhere or regenerated state. */
    private const LEGACY_UNGROUPED = [
        // per-post excludes (now post meta)
        'cache_post_exclude', 'css_post_exclude', 'js_post_exclude', 'html_post_exclude',
        'image_post_exclude', 'video_post_exclude',
        // image sizes (transformed in place)
        'image_sizes', 'image_sizes_remove',
        // internal / regenerated state
        'current_settings', 'local_css', 'wpp_disable',
        'plugin_css_list', 'theme_css_list', 'plugin_js_list', 'theme_js_list',
        'external_css_list', 'external_js_list', 'prefetch_css_list', 'prefetch_js_list',
    ];

    /** @var list<string> */
    private array $skipped = [];

    public function __construct(private SettingsService $settings)
    {
    }

    public function maybeRun(): void
    {
        if (get_option(self::FLAG)) {
            return;
        }

        // A group whose module has not registered its settings yet keeps its
        // legacy options, and the flag stays unset so a later pass can finish it.
        if ($this->run() !== []) {
            return;
        }

        // Autoloaded so the per-request guard check above costs no extra query.
        update_option(self::FLAG, defined('WPP_VERSION') ? WPP_VERSION : '2.0.0', true);
    }

    /** @return list<string> groups that could not be migrated because they are unknown */
    public function run(): array
    {
        $this->skipped = [];

        $this->migrateGroup('cache', [
            'enabled'             => ['cache', 'bool'],
            'mobile'              => ['mobile_cache', 'bool'],
            'clear_time'          => ['cache_time', 'int'],
            'clear_unit'          => ['cache_length', 'int'],
            'clear_on_publish'    => ['update_clear', 'bool'],
            'clear_on_delete'     => ['delete_clear', 'bool'],
            'clear_on_save'       => ['save_clear', 'bool'],
            'keep_assets'         => ['clear_assets', 'bool'],
            'browser_cache'       => ['browser_cache', 'bool'],
            'gzip'                => ['gzip_compression', 'bool'],
            'exclude_search_bots' => ['search_bots_exclude', 'bool'],
            'exclude_urls'        => ['cache_url_exclude', 'list'],
            'exclude_user_agents' => ['user_agents_exclude', 'list'],
            'sitemaps'            => ['sitemaps_list', 'list'],
        ]);

        $this->migrateGroup('css', [
            'minify_inline'    => ['css_minify_inline', 'bool'],
            'defer'            => ['css_defer', 'bool'],
            'combine_fonts'    => ['css_combine_fonts', 'bool'],
            'font_display'     => ['css_font_display', 'string'],
            'disable_loggedin' => ['css_disable_loggedin', 'bool'],
            'file_exclude'     => ['css_file_exclude', 'list'],
            'exclude_urls'     => ['css_url_exclude', 'list'],
            'minify'           => ['css_minify', 'map'],
            'inline'           => ['css_inline', 'map'],
            'combine'          => ['css_combine', 'map'],
            'disable'          => ['css_disable', 'map'],
            'disable_position' => ['css_disable_position', 'map'],
            'disable_selected' => ['css_disable_selected', 'map'],
            'disable_except'   => ['css_disable_except', 'map'],
        ], [
            // Old kept separate CSS/JS resource hints; the new store merges them.
            'dns_prefetch' => ['css_prefetch', 'js_prefetch'],
            'preconnect'   => ['css_preconnect', 'js_preconnect'],
        ]);

        $this->migrateGroup('js', [
            'minify_inline'    => ['js_minify_inline', 'bool'],
            'defer'            => ['js_defer', 'bool'],
            'disable_loggedin' => ['js_disable_loggedin', 'bool'],
            'file_exclude'     => ['js_file_exclude', 'list'],
            'exclude_urls'     => ['js_url_exclude', 'list'],
            'minify'           => ['js_minify', 'map'],
            'inline'           => ['js_inline', 'map'],
            'combine'          => ['js_combine', 'map'],
            'disable'          => ['js_disable', 'map'],
            'disable_position' => ['js_disable_position', 'map'],
            'disable_selected' => ['js_disable_selected', 'map'],
            'disable_except'   => ['js_disable_except', 'map'],
        ]);

        $this->migrateGroup('html', [
            'enabled'            => ['html_optimization', 'bool'],
            'minify_normal'      => ['html_minify_normal', 'bool'],
            'minify_aggressive'  => ['html_minify_aggressive', 'bool'],
            'remove_comments'    => ['html_remove_comments', 'bool'],
            'remove_link_type'   => ['html_remove_link_type', 'bool'],
            'remove_script_type' => ['html_remove_script_type', 'bool'],
            'remove_quotes'      => ['html_remove_qoutes', 'bool'], // original key is misspelled
            'exclude_urls'       => ['html_url_exclude', 'list'],
        ]);

        $this->migrateGroup('media', [
            'images_lazy'                => ['images_lazy', 'bool'],
            'images_lazy_disable_mobile' => ['disable_lazy_mobile', 'bool'],
            'images_responsive'          => ['images_resp', 'bool'],
            'images_exclude'             => ['images_exclude', 'list'],
            'images_exclude_containers'  => ['images_containers_ids', 'list'],
            'images_exclude_urls'        => ['image_url_exclude', 'list'],
            'videos_lazy'                => ['videos_lazy', 'bool'],
            'videos_exclude_urls'        => ['video_url_exclude', 'list'],
            'disable_emoji'              => ['disable_emoji', 'bool'],
            'disable_embeds'             => ['disable_embeds', 'bool'],
        ]);

        $this->migrateGroup('cdn', [
            'enabled'  => ['cdn', 'bool'],
            'hostname' => ['cdn_hostname', 'string'],
            'exclude'  => ['cdn_exclude', 'list'],
        ]);

        $this->migrateGroup('database', [
            'cleanup_trash'      => ['db_cleanup_trash', 'bool'],
            'cleanup_spam'       => ['db_cleanup_spam', 'bool'],
            'cleanup_revisions'  => ['db_cleanup_revisions', 'bool'],
            'cleanup_transients' => ['db_cleanup_transients', 'bool'],
            'cleanup_autodrafts' => ['db_cleanup_autodrafts', 'bool'],
            'cleanup_cron'       => ['db_cleanup_cron', 'bool'],
            'frequency'          => ['db_cleanup_frequency', 'string'],
        ]);

        $this->migrateGroup('tools', [
            'enable_log' => ['enable_log', 'bool'],
        ]);

        $this->migrateGroup('cloudflare', [
            'enabled'           => ['cf_enabled', 'bool'],
            'api_key'           => ['cf_api_key', 'string'],
            'email'             => ['cf_email', 'string'],
            'zone_id'           => ['cf_zone_id', 'string'],
            'dev_mode'          => ['cf_dev_mode', 'bool'],
            'cache_level'       => ['cf_cache_level', 'string'],
            'browser_expire'    => ['cf_browser_expire', 'int'],
            'rocket_loader'     => ['cf_rocket_loader', 'bool'],
            'brotli'            => ['cf_brotli', 'bool'],
            'custom_purge_urls' => ['cf_custom_purge_urls', 'list'],
        ]);

        $this->migrateGroup('varnish', [
            'enabled'     => ['varnish_auto_purge', 'bool'],
            'custom_host' => ['varnish_custom_host', 'string'],
        ]);

        $this->migrateGroup('prefetch', [
            'enabled' => ['prefetch_pages', 'bool'],
        ]);

        $this->migratePostExcludes();
        $this->migrateImageSizes();
        $this->deleteOldOptions();

        return $this->skipped;
    }

    /**
     * @param array<string, array{0:string,1:string}> $map  newField => [oldKey, type]
     * @param array<string, list<string>>             $merge newField => [oldKey, oldKey] merged lists
     */
    private function migrateGroup(string $group, array $map, array $merge = []): void
    {
        if (! $this->settings->knows($group)) {
            $this->skipped[] = $group;
            return;
        }

        $partial = [];

        foreach ($map as $field => [$oldKey, $type]) {
            if (! $this->has($oldKey)) {
                continue;
            }
            $partial[$field] = $this->cast($this->raw($oldKey), $type);
        }

        foreach ($merge as $field => $oldKeys) {
            $values = [];
            $present = false;
            foreach ($oldKeys as $oldKey) {
                if ($this->has($oldKey)) {
                    $present = true;
                    $values = array_merge($values, $this->cast($this->raw($oldKey), 'list'));
                }
            }
            if ($present) {
                $partial[$field] = array_values(array_unique($values));
            }
        }

        if ($partial !== []) {
            $this->settings->update($group, $partial);
        }
    }

    private function migratePostExcludes(): void
    {
        $types = ['cache', 'css', 'js', 'html', 'image', 'video'];
        foreach ($types as $type) {
            $ids = $this->raw($type . '_post_exclude');
            if (! is_array($ids)) {
                continue;
            }
            foreach ($ids as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    update_post_meta($id, '_wpp_exclude_' . $type, 1);
                }
            }
        }
    }

    /** Old custom sizes were [name => [w, h, crop]]; the new shape is keyed. */
    private function migrateImageSizes(): void
    {
        $sizes = get_option(self::PREFIX . 'image_sizes', self::UNSET);
        if ($sizes !== self::UNSET && is_array($sizes)) {
            $out = [];
            foreach ($sizes as $name => $def) {
                if (! is_array($def)) {
                    continue;
                }
                if (array_key_exists('width', $def)) {
                    $out[$name] = $def; // already new shape
                } else {
                    $out[$name] = [
                        'width'  => (int) ($def[0] ?? 0),
                        'height' => (int) ($def[1] ?? 0),
                        'crop'   => ! empty($def[2]),
                    ];
                }
            }
            update_option(self::PREFIX . 'image_sizes', $out, false);
        }

        $removed = get_option(self::PREFIX . 'image_sizes_remove', self::UNSET);
        if ($removed !== self::UNSET && is_array($removed)) {
            update_option(self::PREFIX . 'image_sizes_remove', array_values($removed), false);
        }
    }

    private function deleteOldOptions(): void
    {
        // Keys whose option name collides with a new group/feature option and now
        // holds migrated data, so they must NOT be deleted.
        $keep = ['cache', 'cdn', 'image_sizes', 'image_sizes_remove'];

        $old = self::LEGACY_UNGROUPED;
        foreach (self::LEGACY_KEYS as $group => $keys) {
            if (in_array($group, $this->skipped, true)) {
                continue;
            }
            $old = array_merge($old, $keys);
        }

        foreach ($old as $key) {
            if (in_array($key, $keep, true)) {
                continue;
            }
            delete_option(self::PREFIX . $key);
        }
    }

    private function has(string $oldKey): bool
    {
        return get_option(self::PREFIX . $oldKey, self::UNSET) !== self::UNSET;
    }

    private function raw(string $oldKey): mixed
    {
        $value = get_option(self::PREFIX . $oldKey, self::UNSET);
        return $value === self::UNSET ? null : $value;
    }

    private function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'bool'   => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'int'    => (int) $value,
            'string' => is_scalar($value) ? (string) $value : '',
            'list'   => is_array($value)
                ? array_values(array_filter(array_map(static fn ($v): string => (string) $v, $value), static fn (string $v): bool => $v !== ''))
                : [],
            'map'    => is_array($value) ? $value : [],
            default  => $value,
        };
    }
}
