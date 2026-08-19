<?php

declare(strict_types=1);

namespace WPP\Settings;

/**
 * Curated one-click configurations, applied through SettingsService so side
 * effects (server rules, drop-ins) fire normally. Presets only set global
 * toggles; per-file CSS/JS minify and combine are left to the Files panel.
 */
final class Presets
{
    /**
     * @var array<string, array<string, array<string, mixed>>>
     */
    private const PRESETS = [
        // Conservative. Safe on virtually any theme; no asset rewriting.
        'safe' => [
            'cache' => ['enabled' => true, 'browser_cache' => true, 'gzip' => true],
            'css'   => ['minify_inline' => true, 'defer' => false, 'remove_unused' => false],
            'js'    => ['minify_inline' => true, 'defer' => false, 'delay' => false],
            'html'  => ['enabled' => true, 'minify_normal' => true, 'remove_comments' => true],
            'media' => [
                'images_lazy'       => true,
                'images_dimensions' => true,
                'disable_emoji'     => true,
                'disable_embeds'    => true,
            ],
        ],

        // Recommended. Defers and minifies, hosts fonts, modern images.
        'balanced' => [
            'cache' => ['enabled' => true, 'browser_cache' => true, 'gzip' => true],
            'css'   => [
                'minify_inline' => true,
                'defer'         => true,
                'combine_fonts' => true,
                'host_fonts'    => true,
                'font_display'  => 'swap',
                'remove_unused' => false,
            ],
            'js'   => ['minify_inline' => true, 'defer' => true, 'delay' => false],
            'html' => [
                'enabled'            => true,
                'minify_normal'      => true,
                'remove_comments'    => true,
                'remove_link_type'   => true,
                'remove_script_type' => true,
            ],
            'media' => [
                'images_lazy'       => true,
                'images_responsive' => true,
                'images_dimensions' => true,
                'lcp_images'        => 1,
                'webp'              => true,
                'disable_emoji'     => true,
                'disable_embeds'    => true,
            ],
        ],

        // Maximum. Enables every optimization; test the site after applying.
        'aggressive' => [
            'cache' => ['enabled' => true, 'browser_cache' => true, 'gzip' => true, 'mobile' => true],
            'css'   => [
                'minify_inline' => true,
                'defer'         => true,
                'remove_unused' => true,
                'combine_fonts' => true,
                'host_fonts'    => true,
                'font_display'  => 'swap',
            ],
            'js'   => ['minify_inline' => true, 'defer' => true, 'delay' => true],
            'html' => [
                'enabled'            => true,
                'minify_normal'      => true,
                'minify_aggressive'  => true,
                'remove_comments'    => true,
                'remove_link_type'   => true,
                'remove_script_type' => true,
                'remove_quotes'      => true,
            ],
            'media' => [
                'images_lazy'       => true,
                'images_responsive' => true,
                'images_dimensions' => true,
                'lcp_images'        => 2,
                'webp'              => true,
                'videos_lazy'       => true,
                'disable_emoji'     => true,
                'disable_embeds'    => true,
            ],
        ],
    ];

    /** @return list<string> */
    public function names(): array
    {
        return array_keys(self::PRESETS);
    }

    public function knows(string $name): bool
    {
        return isset(self::PRESETS[$name]);
    }

    /** @return array<string, array<string, mixed>> */
    public function get(string $name): array
    {
        return self::PRESETS[$name] ?? [];
    }

    /**
     * Applying a preset is declarative over the keys presets manage: every one
     * of them goes back to its default first, so switching to a narrower preset
     * turns off what a broader one enabled instead of leaving it stuck on.
     * Keys no preset lists (per-file maps, exclusion lists, CDN, database,
     * tools) are user-curated and never touched.
     */
    public function apply(string $name, SettingsService $settings): bool
    {
        if (! $this->knows($name)) {
            return false;
        }

        $groups = $settings->groups();

        foreach (self::managedKeys() as $group => $keys) {
            if (! isset($groups[$group])) {
                continue;
            }

            $defaults = $groups[$group]['defaults'];

            $partial = [];
            foreach ($keys as $key) {
                if (array_key_exists($key, $defaults)) {
                    $partial[$key] = $defaults[$key];
                }
            }

            $settings->update($group, array_merge($partial, self::PRESETS[$name][$group] ?? []));
        }

        return true;
    }

    /**
     * Union of the keys every preset sets, per group.
     *
     * @return array<string, list<string>>
     */
    private static function managedKeys(): array
    {
        $keys = [];
        foreach (self::PRESETS as $groups) {
            foreach ($groups as $group => $values) {
                foreach (array_keys($values) as $key) {
                    $keys[$group][$key] = true;
                }
            }
        }

        return array_map('array_keys', $keys);
    }
}
