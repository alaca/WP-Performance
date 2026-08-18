<?php

declare(strict_types=1);

namespace WPP\Addons\Cloudflare;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WPP\Settings\SettingsService;

/**
 * POST /wpp/v1/cloudflare/purge         -> purge everything
 * POST /wpp/v1/cloudflare/purge-custom  -> purge the posted URLs, or the saved ones
 * GET  /wpp/v1/cloudflare/status        -> zone settings the last save could not apply
 */
final class CloudflareController
{
    private const NS = 'wpp/v1';

    public function __construct(private SettingsService $settings)
    {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NS, '/cloudflare/purge', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'purge'],
                'permission_callback' => [$this, 'canAccess'],
            ],
        ]);
        register_rest_route(self::NS, '/cloudflare/purge-custom', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'purgeCustom'],
                'permission_callback' => [$this, 'canAccess'],
                'args'                => [
                    'urls' => [
                        'type'  => 'array',
                        'items' => ['type' => 'string'],
                    ],
                ],
            ],
        ]);
        register_rest_route(self::NS, '/cloudflare/status', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'status'],
                'permission_callback' => [$this, 'canAccess'],
            ],
        ]);
    }

    public function canAccess(): bool
    {
        return current_user_can('manage_options');
    }

    public function purge(): WP_REST_Response
    {
        $api = $this->api();
        if ($api === null) {
            return $this->error();
        }
        return $this->result($api->purgeAll());
    }

    public function purgeCustom(WP_REST_Request $request): WP_REST_Response
    {
        $api = $this->api();
        if ($api === null) {
            return $this->error();
        }

        $posted = $request->get_param('urls');
        $urls   = is_array($posted) && $posted !== []
            ? array_map('esc_url_raw', array_map('strval', $posted))
            : (array) ($this->settings->get('cloudflare')['custom_purge_urls'] ?? []);

        return $this->result($api->purgeUrls(array_values(array_filter($urls))));
    }

    public function status(): WP_REST_Response
    {
        return new WP_REST_Response([
            'errors' => (array) get_option(CloudflareModule::ERRORS_OPTION, []),
        ], 200);
    }

    private function api(): ?CloudflareApi
    {
        $cf = $this->settings->get('cloudflare');
        if (empty($cf['enabled']) || empty($cf['email']) || empty($cf['api_key']) || empty($cf['zone_id'])) {
            return null;
        }
        return new CloudflareApi((string) $cf['email'], (string) $cf['api_key'], (string) $cf['zone_id']);
    }

    /**
     * A non-2xx body is what api-fetch throws to the client, and it reads
     * 'message', so the reason has to travel in that key.
     */
    private function result(array $response): WP_REST_Response
    {
        $ok     = ! empty($response['success']);
        $errors = CloudflareApi::errorMessages((array) ($response['errors'] ?? []));

        return new WP_REST_Response([
            'success' => $ok,
            'code'    => $ok ? 'wpp_cloudflare_ok' : 'wpp_cloudflare_error',
            'message' => $ok ? '' : ($errors[0] ?? __('Cloudflare rejected the request.', 'wpp')),
            'errors'  => $errors,
        ], $ok ? 200 : 400);
    }

    private function error(): WP_REST_Response
    {
        $message = __('Cloudflare is not configured.', 'wpp');

        return new WP_REST_Response([
            'success' => false,
            'code'    => 'wpp_cloudflare_not_configured',
            'message' => $message,
            'errors'  => [$message],
        ], 400);
    }
}
