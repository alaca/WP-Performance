<?php

declare(strict_types=1);

namespace WPP\Core;

use WPP\Admin\AdminAssets;
use WPP\Admin\AdminMenu;
use WPP\Admin\CompatibilityNotice;
use WPP\Admin\Metabox;
use WPP\Cache\CacheInvalidation;
use WPP\Cache\CacheStore;
use WPP\Cache\DropinInstaller;
use WPP\Cache\FragmentCache;
use WPP\Cache\FragmentStore;
use WPP\Cache\ObjectCacheInstaller;
use WPP\Cache\RuntimeSettings;
use WPP\Cron\CronHooks;
use WPP\Cron\Preloader;
use WPP\Database\DatabaseService;
use WPP\Foundation\Container\Container;
use WPP\Foundation\Modules\Module;
use WPP\Foundation\Plugin;
use WPP\Frontend\Optimizer;
use WPP\Frontend\PageCache;
use WPP\Media\ImageConverter;
use WPP\Media\ImageService;
use WPP\Media\MediaHooks;
use WPP\Optimize\AssetParser;
use WPP\Optimize\Collection;
use WPP\Optimize\FontHost;
use WPP\Optimize\HtmlOptimizer;
use WPP\Optimize\Minifier;
use WPP\Rest\Admin\CacheController;
use WPP\Rest\Admin\DatabaseController;
use WPP\Rest\Admin\FilesController;
use WPP\Rest\Admin\ImageController;
use WPP\Rest\Admin\OverviewController;
use WPP\Rest\Admin\ServerController;
use WPP\Rest\Admin\SettingsController;
use WPP\Rest\Admin\ToolsController;
use WPP\Rest\RestProvider;
use WPP\Server\ServerRules;
use WPP\Settings\Migration;
use WPP\Settings\Presets;
use WPP\Settings\SettingsHistory;
use WPP\Settings\SettingsService;
use WPP\Support\Logger;

/**
 * The one concrete module that wires the whole plugin: services, admin, REST,
 * frontend optimization, cron, CLI. Add-ons register as separate modules.
 */
final class CoreModule implements Module
{
    public function id(): string
    {
        return 'core';
    }

    public function boot(Container $container): void
    {
        $container->bind(SettingsService::class, static fn (): SettingsService => new SettingsService());
        $container->bind(DatabaseService::class, static fn (): DatabaseService => new DatabaseService());
        $container->bind(CacheStore::class, static fn (): CacheStore => new CacheStore());
        $container->bind(FragmentStore::class, static fn (): FragmentStore => new FragmentStore());
        $container->bind(
            FragmentCache::class,
            static fn (Container $c): FragmentCache => new FragmentCache(
                $c->get(SettingsService::class),
                $c->get(FragmentStore::class)
            )
        );
        $container->bind(
            RuntimeSettings::class,
            static fn (Container $c): RuntimeSettings => new RuntimeSettings($c->get(SettingsService::class))
        );
        $container->bind(Minifier::class, static fn (): Minifier => new Minifier());
        $container->bind(HtmlOptimizer::class, static fn (): HtmlOptimizer => new HtmlOptimizer());
        $container->bind(Collection::class, static fn (): Collection => new Collection());
        $container->bind(ImageService::class, static fn (): ImageService => new ImageService());
        $container->bind(ImageConverter::class, static fn (): ImageConverter => new ImageConverter());
        $container->bind(ServerRules::class, static fn (): ServerRules => new ServerRules());
        $container->bind(
            Preloader::class,
            static fn (Container $c): Preloader => new Preloader($c->get(SettingsService::class))
        );
        $container->bind(FontHost::class, static fn (): FontHost => new FontHost());
        $container->bind(AssetParser::class, static fn (Container $c): AssetParser => new AssetParser(
            $c->get(SettingsService::class),
            $c->get(Minifier::class),
            $c->get(HtmlOptimizer::class),
            $c->get(Collection::class),
            $c->get(FontHost::class)
        ));

        $settings = $container->get(SettingsService::class);
        $store    = $container->get(CacheStore::class);

        $history = new SettingsHistory($settings);
        $history->register();

        $logger = new Logger($settings);
        $logger->register();

        $fragmentStore = $container->get(FragmentStore::class);
        $container->get(FragmentCache::class)->register();
        add_action('wpp.cache.after_clear', static function () use ($settings, $fragmentStore): void {
            // The purge deletes the directory's .htaccess along with the cache.
            Plugin::protectCacheDir();

            if (! empty($settings->get('cache')['block_cache_flush_on_clear'])) {
                $fragmentStore->flush();
            }
        });

        add_action('init', static function (): void {
            $asset = WPP_DIR . 'build/blocks/cache/index.asset.php';
            if (! file_exists($asset)) {
                return;
            }
            $data = require $asset;
            wp_register_script(
                'wpp-cache-block',
                WPP_URL . 'build/blocks/cache/index.js',
                $data['dependencies'],
                $data['version'],
                true
            );
            wp_set_script_translations('wpp-cache-block', 'wpp', WPP_DIR . 'languages');
            register_block_type('wpp/cache', ['editor_script' => 'wpp-cache-block']);
        });

        (new RestProvider(
            new SettingsController($settings),
            new DatabaseController($container->get(DatabaseService::class)),
            new CacheController($store, $settings, $fragmentStore),
            new FilesController($container->get(Collection::class), $store),
            new ToolsController($settings, new Presets(), $history, $logger),
            new ImageController($container->get(ImageService::class), $container->get(ImageConverter::class)),
            new ServerController($container->get(ServerRules::class), $settings),
            new OverviewController($store, $settings, $container->get(ServerRules::class), $fragmentStore),
        ))->register();

        (new CacheInvalidation($settings, $store, $container->get(RuntimeSettings::class), $fragmentStore))->register();
        (new MediaHooks($settings, $container->get(ImageService::class), $container->get(ImageConverter::class)))->register();
        (new CronHooks($settings, $container->get(DatabaseService::class), $container->get(Preloader::class)))->register();

        $serverRules = $container->get(ServerRules::class);
        add_action('wpp.settings.updated', static function (string $group) use ($serverRules, $settings): void {
            if ($group === 'cache') {
                $serverRules->apply($settings->get('cache'));
            }
            if ($group === 'tools') {
                $objectCache = new ObjectCacheInstaller();
                $want = ! empty($settings->get('tools')['object_cache']) && $objectCache->available();
                if ($want && ! $objectCache->installed()) {
                    // Turn the setting back off if another drop-in owns the file,
                    // so the toggle never reads ON while nothing was installed.
                    if (! $objectCache->install()) {
                        $settings->update('tools', ['object_cache' => false]);
                    }
                } elseif (! $want && $objectCache->installed()) {
                    $objectCache->uninstall();
                }
            }
        }, 10, 1);

        if (is_admin()) {
            (new AdminMenu())->register();
            (new AdminAssets($settings))->register();
            (new Metabox())->register();
            (new CompatibilityNotice())->register();

            // Migrate legacy options. The activation hook does not fire on a plugin
            // update (WordPress reactivates silently), so an existing user who simply
            // updates would never be migrated by activation alone. Run it here too,
            // flag-gated so it is a no-op after the first pass.
            add_action('admin_init', static function () use ($settings): void {
                (new Migration($settings))->maybeRun();
            });

            // Self-heal: activation only runs once, so restore anything the
            // drop-in needs if it went missing. Without the runtime file the
            // drop-in bails and page caching stops without any visible sign.
            Plugin::protectCacheDir();

            $dropin  = new DropinInstaller();
            $runtime = $container->get(RuntimeSettings::class);
            if (! defined('WP_CACHE') || ! $dropin->dropinInstalled()) {
                $dropin->install();
                $runtime->write();
            } elseif (! $runtime->written()) {
                $runtime->write();
            }
        } else {
            (new PageCache($settings, $store))->register();
            (new Optimizer($container->get(AssetParser::class)))->register();
        }
    }
}
