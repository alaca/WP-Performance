<?php

declare(strict_types=1);

namespace WPP\Media;

use WPP\Foundation\Hooks\HookProvider;
use WPP\Settings\SettingsService;

/**
 * Registers custom image sizes and, per the media settings, disables WordPress
 * emoji and oEmbed output.
 */
final class MediaHooks extends HookProvider
{
    public function __construct(
        private SettingsService $settings,
        private ImageService $images,
        private ImageConverter $converter
    ) {
    }

    protected function actions(): array
    {
        return ['init' => 'onInit'];
    }

    protected function filters(): array
    {
        return [
            'intermediate_image_sizes_advanced'  => 'filterImageSizes',
            'wp_generate_attachment_metadata'    => ['onMetadata', 10, 2],
        ];
    }

    /**
     * @param array<string,mixed> $metadata
     * @return array<string,mixed>
     */
    public function onMetadata(array $metadata, int $attachmentId): array
    {
        if (! empty($this->settings->get('media')['webp'])) {
            $this->converter->convertAttachment($attachmentId);
        }
        return $metadata;
    }

    public function onInit(): void
    {
        $this->images->register();

        $media = $this->settings->get('media');
        if (! empty($media['disable_emoji'])) {
            $this->disableEmoji();
        }
        if (! empty($media['disable_embeds'])) {
            $this->disableEmbeds();
        }
    }

    /**
     * @param array<string, mixed> $sizes
     * @return array<string, mixed>
     */
    public function filterImageSizes(array $sizes): array
    {
        return $this->images->filterSizes($sizes);
    }

    private function disableEmoji(): void
    {
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

        add_filter('tiny_mce_plugins', static function ($plugins) {
            return is_array($plugins) ? array_diff($plugins, ['wpemoji']) : $plugins;
        });
        add_filter('wp_resource_hints', static function ($urls, $relation) {
            if ($relation === 'dns-prefetch') {
                $urls = array_filter((array) $urls, static fn ($u): bool => ! str_contains((string) $u, 's.w.org'));
            }
            return $urls;
        }, 10, 2);
    }

    private function disableEmbeds(): void
    {
        remove_action('rest_api_init', 'wp_oembed_register_route');
        remove_filter('oembed_dataparse', 'wp_filter_oembed_result', 10);
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'wp_oembed_add_host_js');
        add_filter('embed_oembed_discover', '__return_false');
    }
}
