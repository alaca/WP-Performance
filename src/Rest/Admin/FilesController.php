<?php

declare(strict_types=1);

namespace WPP\Rest\Admin;

use WP_REST_Response;
use WP_REST_Server;
use WPP\Cache\CacheStore;
use WPP\Optimize\Collection;

/**
 * GET  /wpp/v1/files          -> discovered CSS/JS grouped by origin
 * POST /wpp/v1/files/rescan   -> clear the list + cache, re-warm the homepage, return fresh list
 */
final class FilesController
{
    private const NS = 'wpp/v1';

    public function __construct(
        private Collection $collection,
        private CacheStore $store
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NS, '/files', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'index'],
                'permission_callback' => [$this, 'canAccess'],
            ],
        ]);

        register_rest_route(self::NS, '/files/rescan', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'rescan'],
                'permission_callback' => [$this, 'canAccess'],
            ],
        ]);
    }

    public function canAccess(): bool
    {
        return current_user_can('manage_options');
    }

    public function index(): WP_REST_Response
    {
        return new WP_REST_Response($this->shape(), 200);
    }

    public function rescan(): WP_REST_Response
    {
        $this->collection->clear();
        $this->store->clear(false);

        // Render the homepage through the optimizer so files get re-collected.
        $response = wp_remote_get(home_url('/'), [
            'timeout'   => 15,
            'sslverify' => false,
            'headers'   => ['X-WPP-Rescan' => '1'],
        ]);

        // The loopback request collected into a different process, so drop the
        // cached option before reading or this request still sees the old value.
        $this->collection->refresh();

        $shape = $this->shape();

        $reachable = ! is_wp_error($response)
            && (int) wp_remote_retrieve_response_code($response) === 200;

        if (! $reachable) {
            $shape['warning'] = __(
                'Could not load the homepage automatically, so the list may be incomplete. Visit your site once, then reload this page.',
                'wpp'
            );
        } elseif ($shape['css'] === [] && $shape['js'] === []) {
            $shape['warning'] = __(
                'No files were found. Turn on a CSS or JavaScript optimization first, then update the list.',
                'wpp'
            );
        }

        return new WP_REST_Response($shape, 200);
    }

    /** @return array{css: array<string,string[]>, js: array<string,string[]>} */
    private function shape(): array
    {
        $all = $this->collection->all();
        return [
            'css' => $all['css'] ?? [],
            'js'  => $all['js'] ?? [],
        ];
    }
}
