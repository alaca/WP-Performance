<?php

declare(strict_types=1);

namespace WPP\Admin;

use WP_Post;
use WPP\Cache\CacheStore;
use WPP\Foundation\Hooks\HookProvider;
use WPP\Settings\SettingsService;

/**
 * Edit-screen metabox to exclude a single post/page from each optimization type.
 * Stored as _wpp_exclude_{type} post meta and honored by the cache + parser.
 */
final class Metabox extends HookProvider
{
    public const TYPES = ['cache', 'css', 'js', 'html', 'image', 'video'];

    protected function actions(): array
    {
        return [
            'add_meta_boxes' => 'addBox',
            'save_post'      => ['save', 10, 1],
        ];
    }

    /**
     * Named screens only. add_meta_boxes also fires on screens whose object is
     * not a WP_Post (the link editor passes a bookmark), and a null screen would
     * register the box there too.
     */
    public function addBox(): void
    {
        add_meta_box(
            'wpp-exclude',
            __('WP Performance', 'wpp'),
            [$this, 'render'],
            array_values(get_post_types(['public' => true])),
            'side',
            'default'
        );
    }

    public function render(mixed $post): void
    {
        if (! $post instanceof WP_Post) {
            return;
        }

        wp_nonce_field('wpp_metabox', 'wpp_metabox_nonce');

        $labels = [
            'cache' => __('Exclude from cache', 'wpp'),
            'css'   => __('Exclude from CSS optimization', 'wpp'),
            'js'    => __('Exclude from JavaScript optimization', 'wpp'),
            'html'  => __('Exclude from HTML optimization', 'wpp'),
            'image' => __('Exclude from image optimization', 'wpp'),
            'video' => __('Exclude from video lazy load', 'wpp'),
        ];

        echo '<div class="wpp-metabox">';
        foreach ($labels as $key => $label) {
            $checked = get_post_meta($post->ID, '_wpp_exclude_' . $key, true) ? ' checked' : '';
            printf(
                '<p style="margin:.4em 0"><label><input type="checkbox" name="wpp_exclude[%s]" value="1"%s> %s</label></p>',
                esc_attr($key),
                $checked,
                esc_html($label)
            );
        }
        echo '</div>';
    }

    public function save(int $postId): void
    {
        $nonce = isset($_POST['wpp_metabox_nonce']) ? sanitize_text_field(wp_unslash($_POST['wpp_metabox_nonce'])) : '';
        if ($nonce === '' || ! wp_verify_nonce($nonce, 'wpp_metabox')) {
            return;
        }
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || ! current_user_can('edit_post', $postId)) {
            return;
        }

        $selected = [];
        if (isset($_POST['wpp_exclude']) && is_array($_POST['wpp_exclude'])) {
            $selected = array_map('sanitize_key', array_keys(wp_unslash($_POST['wpp_exclude'])));
        }

        $changed = false;

        foreach (self::TYPES as $type) {
            $key = '_wpp_exclude_' . $type;
            $was = (bool) get_post_meta($postId, $key, true);
            $now = in_array($type, $selected, true);

            if ($was === $now) {
                continue;
            }

            $changed = true;

            if ($now) {
                update_post_meta($postId, $key, 1);
            } else {
                delete_post_meta($postId, $key);
            }
        }

        // These exclusions are only read while a page is being written.
        // advanced-cache.php runs before WordPress and has no post meta, so an
        // already cached copy would keep being served until it expired.
        if ($changed) {
            $settings = new SettingsService();
            (new CacheStore())->clear(! empty($settings->get('cache')['keep_assets']));
        }
    }
}
