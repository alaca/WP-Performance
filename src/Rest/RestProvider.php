<?php

declare(strict_types=1);

namespace WPP\Rest;

use WPP\Foundation\Hooks\HookProvider;
use WPP\Rest\Admin\CacheController;
use WPP\Rest\Admin\DatabaseController;
use WPP\Rest\Admin\FilesController;
use WPP\Rest\Admin\ImageController;
use WPP\Rest\Admin\OverviewController;
use WPP\Rest\Admin\ServerController;
use WPP\Rest\Admin\SettingsController;
use WPP\Rest\Admin\ToolsController;

/**
 * Registers all REST routes on rest_api_init. Add-on modules add controllers
 * via the 'wpp.rest.register' action using ControllerRegistry.
 */
final class RestProvider extends HookProvider
{
    public function __construct(
        private SettingsController $settings,
        private DatabaseController $database,
        private CacheController $cache,
        private FilesController $files,
        private ToolsController $tools,
        private ImageController $images,
        private ServerController $server,
        private OverviewController $overview,
    ) {
    }

    protected function actions(): array
    {
        return ['rest_api_init' => 'registerRoutes'];
    }

    public function registerRoutes(): void
    {
        $this->settings->registerRoutes();
        $this->database->registerRoutes();
        $this->cache->registerRoutes();
        $this->files->registerRoutes();
        $this->tools->registerRoutes();
        $this->images->registerRoutes();
        $this->server->registerRoutes();
        $this->overview->registerRoutes();

        $registry = new ControllerRegistry();
        do_action('wpp.rest.register', $registry);
        foreach ($registry->all() as $controller) {
            if (method_exists($controller, 'registerRoutes')) {
                $controller->registerRoutes();
            }
        }
    }
}
