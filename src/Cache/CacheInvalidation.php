<?php

declare(strict_types=1);

namespace WPP\Cache;

use WPP\Foundation\Hooks\HookProvider;
use WPP\Settings\SettingsService;

/**
 * Clears the page cache on the configured events and keeps the drop-in's
 * runtime settings file in sync whenever cache settings change.
 */
final class CacheInvalidation extends HookProvider
{
    /** Settings groups whose values end up baked into the cached HTML. */
    private const RENDER_GROUPS = ['cache', 'css', 'js', 'html', 'media', 'cdn'];

    public function __construct(
        private SettingsService $settings,
        private CacheStore $store,
        private RuntimeSettings $runtime,
        private FragmentStore $fragments
    ) {
    }

    protected function actions(): array
    {
        return [
            'save_post'            => ['onSavePost', 10, 1],
            'deleted_post'         => ['onDeletePost', 10, 1],
            'switch_theme'         => 'onSwitchTheme',
            'wpp.settings.updated' => ['onSettingsUpdated', 10, 2],
            'comment_post'              => ['onCommentPosted', 10, 2],
            'edit_comment'              => ['onCommentChanged', 10, 1],
            'transition_comment_status' => ['onCommentTransition', 10, 2],
            'trashed_comment'           => ['onCommentChanged', 10, 1],
            // The drop-in picks its path scheme from a snapshot of this option,
            // so a change must refresh the snapshot and drop paths built the old way.
            'update_option_permalink_structure' => 'onPermalinksChanged',
            'permalink_structure_changed'       => 'onPermalinksChanged',
        ];
    }

    public function onSavePost(int $postId): void
    {
        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }
        if (! empty($this->settings->get('cache')['clear_on_publish'])) {
            $this->store->clear($this->keepAssets());
        }
        // Block cache may wrap dynamic content (recent posts, counts) that a new
        // or edited published post affects, so refresh it on content changes.
        if (get_post_status($postId) === 'publish') {
            $this->flushBlocks();
        }
    }

    /** @param int|string $approved 1, 'approve' or 'spam', as wp_allow_comment() returns it */
    public function onCommentPosted(int $commentId, $approved = 0): void
    {
        // A comment held for moderation is not in anyone's rendered output yet.
        if ($approved === 1 || $approved === '1' || $approved === 'approve') {
            $this->onCommentChanged();
        }
    }

    public function onCommentTransition(string $new, string $old): void
    {
        if ($new !== $old && ($new === 'approved' || $old === 'approved')) {
            $this->onCommentChanged();
        }
    }

    public function onCommentChanged(): void
    {
        if (! empty($this->settings->get('cache')['clear_on_publish'])) {
            $this->store->clear($this->keepAssets());
        }
        $this->flushBlocks();
    }

    public function onDeletePost(int $postId): void
    {
        if (! empty($this->settings->get('cache')['clear_on_delete'])) {
            $this->store->clear($this->keepAssets());
        }
        $this->flushBlocks();
    }

    public function onPermalinksChanged(): void
    {
        $this->runtime->write();
        $this->store->clear($this->keepAssets());
        $this->flushBlocks();
    }

    public function onSwitchTheme(): void
    {
        $this->store->clear($this->keepAssets());
        $this->flushBlocks();
    }

    /** @param array<string,mixed> $next */
    public function onSettingsUpdated(string $group, array $next): void
    {
        // Keep the drop-in's runtime file current for any cache-affecting change.
        $this->runtime->write();

        // Cached HTML is the post-optimization output, so a css/js/html/media/cdn
        // save is stale the moment it lands, not just a cache-group save.
        if (in_array($group, self::RENDER_GROUPS, true)
            && ! empty($this->settings->get('cache')['clear_on_save'])
        ) {
            $this->store->clear($this->keepAssets());
        }
    }

    private function keepAssets(): bool
    {
        return ! empty($this->settings->get('cache')['keep_assets']);
    }

    private function flushBlocks(): void
    {
        if (! empty($this->settings->get('cache')['block_cache_enabled'])) {
            $this->fragments->flush();
        }
    }
}
