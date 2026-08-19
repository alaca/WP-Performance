<?php

declare(strict_types=1);

namespace WPP\Rest\Admin;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WPP\Settings\SettingsService;

/**
 * GET    /wpp/v1/settings           -> all groups
 * GET    /wpp/v1/settings/{group}   -> one group (defaults-merged)
 * PUT    /wpp/v1/settings/{group}   -> merge partial, persist, return merged
 */
final class SettingsController
{
    private const NS = 'wpp/v1';

    public function __construct(private SettingsService $settings)
    {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NS, '/settings', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'index'],
                'permission_callback' => [$this, 'canAccess'],
            ],
        ]);

        register_rest_route(self::NS, '/settings/(?P<group>[a-z0-9_-]+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'show'],
                'permission_callback' => [$this, 'canAccess'],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'update'],
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
        return new WP_REST_Response($this->settings->all(), 200);
    }

    public function show(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $group = (string) $request['group'];
        if (! $this->settings->knows($group)) {
            return new WP_Error('wpp_unknown_group', __('Unknown settings group.', 'wpp'), ['status' => 404]);
        }
        return new WP_REST_Response($this->settings->get($group), 200);
    }

    public function update(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $group = (string) $request['group'];
        if (! $this->settings->knows($group)) {
            return new WP_Error('wpp_unknown_group', __('Unknown settings group.', 'wpp'), ['status' => 404]);
        }

        $body = $request->get_json_params();
        if (! is_array($body)) {
            $body = $request->get_body_params();
        }
        unset($body['group']);

        $next = $this->settings->update($group, is_array($body) ? $body : []);

        return new WP_REST_Response($next, 200);
    }
}
