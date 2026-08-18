<?php

declare(strict_types=1);

namespace WPP\Rest\Admin;

use WP_REST_Response;
use WP_REST_Server;
use WPP\Server\ServerRules;
use WPP\Settings\SettingsService;

/**
 * GET /wpp/v1/server -> server type + generated nginx rules (for manual paste).
 */
final class ServerController
{
    private const NS = 'wpp/v1';

    public function __construct(
        private ServerRules $rules,
        private SettingsService $settings
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NS, '/server', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'show'],
                'permission_callback' => [$this, 'canAccess'],
            ],
        ]);
    }

    public function canAccess(): bool
    {
        return current_user_can('manage_options');
    }

    public function show(): WP_REST_Response
    {
        return new WP_REST_Response([
            'type'  => $this->rules->serverType(),
            'nginx' => $this->rules->nginxRules($this->settings->get('cache')),
        ], 200);
    }
}
