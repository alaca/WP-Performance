<?php

declare(strict_types=1);

namespace WPP\Rest\Admin;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WPP\Database\DatabaseService;

/**
 * GET  /wpp/v1/database/counts  -> per-type counts + next scheduled run
 * POST /wpp/v1/database/clean   -> run a cleanup type, return fresh counts
 */
final class DatabaseController
{
    private const NS = 'wpp/v1';

    public function __construct(private DatabaseService $db)
    {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NS, '/database/counts', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'counts'],
                'permission_callback' => [$this, 'canAccess'],
            ],
        ]);

        register_rest_route(self::NS, '/database/clean', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'clean'],
                'permission_callback' => [$this, 'canAccess'],
                'args'                => [
                    'type' => [
                        'required' => true,
                        'type'     => 'string',
                        'enum'     => ['trash', 'spam', 'revisions', 'transients', 'autodrafts', 'cron', 'all'],
                    ],
                ],
            ],
        ]);
    }

    public function canAccess(): bool
    {
        return current_user_can('manage_options');
    }

    public function counts(): WP_REST_Response
    {
        return new WP_REST_Response($this->payload(), 200);
    }

    public function clean(WP_REST_Request $request): WP_REST_Response
    {
        $this->db->clear((string) $request['type']);
        return new WP_REST_Response($this->payload(), 200);
    }

    /** @return array{counts: array<string,int>, next_run: int|null} */
    private function payload(): array
    {
        $ts = (int) get_option('wpp_db_cleanup_next', 0);
        return [
            'counts'   => $this->db->counts(),
            'next_run' => $ts > 0 ? $ts : null,
        ];
    }
}
