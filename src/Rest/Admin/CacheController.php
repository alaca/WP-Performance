<?php

declare(strict_types=1);

namespace WPP\Rest\Admin;

use WP_REST_Response;
use WP_REST_Server;
use WPP\Cache\CacheStore;
use WPP\Cache\FragmentStore;
use WPP\Settings\SettingsService;

/**
 * GET  /wpp/v1/cache/stats           -> cached bytes by type
 * POST /wpp/v1/cache/clear           -> clear the page cache, return fresh stats
 * POST /wpp/v1/cache/fragments/clear -> flush the block (fragment) cache
 */
final class CacheController
{
    private const NS = 'wpp/v1';

    public function __construct(
        private CacheStore $store,
        private SettingsService $settings,
        private FragmentStore $fragments
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NS, '/cache/stats', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'stats'],
                'permission_callback' => [$this, 'canAccess'],
            ],
        ]);

        register_rest_route(self::NS, '/cache/clear', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'clear'],
                'permission_callback' => [$this, 'canAccess'],
            ],
        ]);

        register_rest_route(self::NS, '/cache/fragments/clear', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'clearFragments'],
                'permission_callback' => [$this, 'canAccess'],
            ],
        ]);
    }

    public function canAccess(): bool
    {
        return current_user_can('manage_options');
    }

    public function stats(): WP_REST_Response
    {
        return new WP_REST_Response($this->store->stats(), 200);
    }

    public function clear(): WP_REST_Response
    {
        $keep = ! empty($this->settings->get('cache')['keep_assets']);
        $this->store->clear($keep);
        return new WP_REST_Response($this->store->stats(), 200);
    }

    public function clearFragments(): WP_REST_Response
    {
        $this->fragments->flush();
        return new WP_REST_Response(['ok' => true], 200);
    }
}
