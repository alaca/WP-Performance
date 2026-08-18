<?php

declare(strict_types=1);

namespace WPP\Rest\Admin;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WPP\Media\ImageConverter;
use WPP\Media\ImageService;

/**
 * Image-size management + thumbnail regeneration.
 *
 * GET  /wpp/v1/images/sizes        -> defined sizes
 * POST /wpp/v1/images/sizes        -> add a size
 * POST /wpp/v1/images/sizes/remove -> remove a size
 * POST /wpp/v1/images/sizes/restore-> restore defaults
 * POST /wpp/v1/images/regenerate   -> regenerate a batch {offset,limit}
 */
final class ImageController
{
    private const NS = 'wpp/v1';

    public function __construct(
        private ImageService $images,
        private ImageConverter $converter
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NS, '/images/sizes', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'sizes'],
                'permission_callback' => [$this, 'canAccess'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'add'],
                'permission_callback' => [$this, 'canAccess'],
            ],
        ]);

        register_rest_route(self::NS, '/images/sizes/remove', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'remove'],
                'permission_callback' => [$this, 'canAccess'],
            ],
        ]);

        register_rest_route(self::NS, '/images/sizes/restore', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'restore'],
                'permission_callback' => [$this, 'canAccess'],
            ],
        ]);

        register_rest_route(self::NS, '/images/regenerate', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'regenerate'],
                'permission_callback' => [$this, 'canAccess'],
            ],
        ]);

        register_rest_route(self::NS, '/images/convert', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'convert'],
                'permission_callback' => [$this, 'canAccess'],
            ],
        ]);
    }

    public function canAccess(): bool
    {
        return current_user_can('manage_options');
    }

    public function sizes(): WP_REST_Response
    {
        return new WP_REST_Response($this->images->definedSizes(), 200);
    }

    public function add(WP_REST_Request $request): WP_REST_Response
    {
        $this->images->add(
            (string) $request->get_param('name'),
            (int) $request->get_param('width'),
            (int) $request->get_param('height'),
            (bool) $request->get_param('crop')
        );
        return new WP_REST_Response($this->images->definedSizes(), 200);
    }

    public function remove(WP_REST_Request $request): WP_REST_Response
    {
        $this->images->remove((string) $request->get_param('name'));
        return new WP_REST_Response($this->images->definedSizes(), 200);
    }

    public function restore(): WP_REST_Response
    {
        $this->images->restore();
        return new WP_REST_Response($this->images->definedSizes(), 200);
    }

    public function regenerate(WP_REST_Request $request): WP_REST_Response
    {
        $offset = max(0, (int) $request->get_param('offset'));
        $limit  = min(20, max(1, (int) ($request->get_param('limit') ?: 5)));
        return new WP_REST_Response($this->images->regenerate($offset, $limit), 200);
    }

    public function convert(WP_REST_Request $request): WP_REST_Response
    {
        $offset = max(0, (int) $request->get_param('offset'));
        $limit  = min(20, max(1, (int) ($request->get_param('limit') ?: 5)));
        return new WP_REST_Response($this->converter->bulk($offset, $limit), 200);
    }
}
