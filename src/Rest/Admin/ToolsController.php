<?php

declare(strict_types=1);

namespace WPP\Rest\Admin;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WPP\Settings\Presets;
use WPP\Settings\SettingsHistory;
use WPP\Settings\SettingsService;
use WPP\Support\Logger;

/**
 * GET  /wpp/v1/tools/export          -> all settings groups (client downloads as JSON)
 * POST /wpp/v1/tools/import          -> apply an exported settings object
 * POST /wpp/v1/tools/preset          -> apply a curated configuration preset
 * GET  /wpp/v1/tools/history         -> recent settings restore points
 * POST /wpp/v1/tools/history/restore -> restore a snapshot by index
 * GET  /wpp/v1/tools/log             -> troubleshooting log content
 * POST /wpp/v1/tools/log/clear       -> empty the log
 */
final class ToolsController
{
    private const NS = 'wpp/v1';

    public function __construct(
        private SettingsService $settings,
        private Presets $presets,
        private SettingsHistory $history,
        private Logger $logger
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NS, '/tools/export', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'export'],
                'permission_callback' => [$this, 'canAccess'],
            ],
        ]);

        register_rest_route(self::NS, '/tools/import', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'import'],
                'permission_callback' => [$this, 'canAccess'],
            ],
        ]);

        register_rest_route(self::NS, '/tools/preset', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'preset'],
                'permission_callback' => [$this, 'canAccess'],
            ],
        ]);

        register_rest_route(self::NS, '/tools/history', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'history'],
                'permission_callback' => [$this, 'canAccess'],
            ],
        ]);

        register_rest_route(self::NS, '/tools/history/restore', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'restore'],
                'permission_callback' => [$this, 'canAccess'],
            ],
        ]);

        register_rest_route(self::NS, '/tools/log', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'log'],
                'permission_callback' => [$this, 'canAccess'],
            ],
        ]);

        register_rest_route(self::NS, '/tools/log/clear', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'clearLog'],
                'permission_callback' => [$this, 'canAccess'],
            ],
        ]);
    }

    public function canAccess(): bool
    {
        return current_user_can('manage_options');
    }

    public function export(): WP_REST_Response
    {
        return new WP_REST_Response($this->settings->all(), 200);
    }

    public function import(WP_REST_Request $request): WP_REST_Response
    {
        $data = $request->get_json_params();
        if (is_array($data)) {
            foreach ($data as $group => $values) {
                // An import is the whole configuration, so rules the target site
                // has and the file does not have to be cleared, not merged.
                if (is_string($group) && is_array($values) && $this->settings->knows($group)) {
                    $this->settings->replace($group, $values);
                }
            }
        }
        return new WP_REST_Response($this->settings->all(), 200);
    }

    public function preset(WP_REST_Request $request): WP_REST_Response
    {
        $name = sanitize_key((string) $request->get_param('name'));
        if (! $this->presets->apply($name, $this->settings)) {
            return new WP_REST_Response(['error' => 'unknown_preset'], 400);
        }
        return new WP_REST_Response($this->settings->all(), 200);
    }

    public function history(): WP_REST_Response
    {
        return new WP_REST_Response($this->history->entries(), 200);
    }

    public function restore(WP_REST_Request $request): WP_REST_Response
    {
        $index = (int) $request->get_param('index');
        if (! $this->history->restore($index)) {
            return new WP_REST_Response(['error' => 'unknown_snapshot'], 400);
        }
        return new WP_REST_Response($this->settings->all(), 200);
    }

    public function log(): WP_REST_Response
    {
        return new WP_REST_Response([
            'enabled' => $this->logger->enabled(),
            'content' => $this->logger->read(),
        ], 200);
    }

    public function clearLog(): WP_REST_Response
    {
        $this->logger->clear();
        return new WP_REST_Response([
            'enabled' => $this->logger->enabled(),
            'content' => '',
        ], 200);
    }
}
