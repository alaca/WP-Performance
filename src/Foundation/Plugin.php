<?php

declare(strict_types=1);

namespace WPP\Foundation;

use WPP\Addons\Cloudflare\CloudflareModule;
use WPP\Addons\Prefetch\PrefetchModule;
use WPP\Addons\Varnish\VarnishModule;
use WPP\Cache\CacheStore;
use WPP\Cache\DropinInstaller;
use WPP\Cache\ObjectCacheInstaller;
use WPP\Cache\RuntimeSettings;
use WPP\Settings\Migration;
use WPP\Core\CoreModule;
use WPP\Foundation\Container\Container;
use WPP\Foundation\Modules\ModuleManager;
use WPP\Server\ServerRules;
use WPP\Settings\SettingsService;
use WPP\Support\Logger;

/**
 * Plugin singleton. Owns the Container and ModuleManager and runs the boot pipeline.
 */
final class Plugin
{
    private const HTACCESS = <<<'HTACCESS'
        Options -Indexes
        <FilesMatch "\.(log|json)(\.php)?$">
        <IfModule mod_authz_core.c>
        Require all denied
        </IfModule>
        <IfModule !mod_authz_core.c>
        Order allow,deny
        Deny from all
        </IfModule>
        </FilesMatch>

        HTACCESS;

    private static ?self $instance = null;
    private static bool $booted = false;

    public readonly Container $container;
    public readonly ModuleManager $modules;

    private function __construct()
    {
        $this->container = new Container();
        $this->modules   = new ModuleManager($this->container);

        $this->container->instance(Container::class, $this->container);
        $this->container->instance(ModuleManager::class, $this->modules);
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    /** Load text domain, register and boot all modules. Idempotent. */
    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        $self = self::instance();

        load_plugin_textdomain('wpp', false, dirname(plugin_basename(WPP_FILE)) . '/languages');

        $self->modules->register(new CoreModule());
        $self->modules->register(new CloudflareModule());
        $self->modules->register(new VarnishModule());
        $self->modules->register(new PrefetchModule());

        // Allow third-party add-on modules to register here.
        do_action('wpp.modules.register', $self->modules);

        $self->modules->bootAll();

        // The activation hook does not fire on a plugin update, so a copy of
        // object-cache.php left by an earlier release would otherwise stay in
        // wp-content untouched for good.
        add_action('admin_init', static function (): void {
            self::syncObjectCache(new SettingsService());
        });

        do_action('wpp.booted', $self);
    }

    public static function onActivation(): void
    {
        self::protectCacheDir();

        $settings = new SettingsService();

        self::bootAddons();
        (new Migration($settings))->maybeRun();

        $dropin = new DropinInstaller();
        $dropin->install();
        self::syncObjectCache($settings);
        (new RuntimeSettings($settings))->write();
        (new ServerRules())->apply($settings->get('cache'));

        // Deleting the pre-guard files would strand a drop-in that still reads
        // them, so this waits for confirmation that ours is the one installed.
        if ($dropin->dropinInstalled()) {
            self::purgeUnguarded();
        }

        do_action('wpp.activated');
    }

    /** @param bool $networkWide as register_deactivation_hook passes it */
    public static function onDeactivation(bool $networkWide = false): void
    {
        // Whatever happens to the shared files, this blog has to stop being
        // served from cache. The stored settings are left alone so that
        // reactivating restores them.
        self::disableRuntime(new RuntimeSettings(new SettingsService()));

        // advanced-cache.php, object-cache.php, wp-config.php, the cache
        // directory and the root .htaccess are all network-wide. Tearing them
        // down would stop caching for every other blog still using the plugin.
        //
        // A network deactivation leaves no blog with the plugin active, and
        // WordPress writes active_sitewide_plugins only after this hook has
        // run, so the sitewide check cannot answer for it.
        if ($networkWide || ! self::activeOnAnotherBlog()) {
            (new DropinInstaller())->uninstall();
            (new ObjectCacheInstaller())->uninstall();
            (new CacheStore())->clear(false, true);
            (new ServerRules())->removeAll();
        } else {
            // Only this blog's pages: the assets and the other blogs' pages
            // belong to the sites still running the plugin.
            (new CacheStore())->clear(true);
        }

        wp_clear_scheduled_hook('wpp_preload');
        wp_clear_scheduled_hook('wpp_db_cleanup');

        do_action('wpp.deactivated');
    }

    /** Flip the file the drop-in reads without touching the stored settings. */
    private static function disableRuntime(RuntimeSettings $runtime): void
    {
        $file = $runtime->file();
        if (is_file($file)) {
            @file_put_contents($file, RuntimeSettings::GUARD . (string) wp_json_encode(['enabled' => false]), LOCK_EX);
        }
    }

    /**
     * The cache directory sits in the web root. The troubleshooting log and the
     * drop-in's runtime JSON hold request paths and the cache exclusion list,
     * so neither may be fetchable over HTTP.
     */
    public static function protectCacheDir(): void
    {
        if (! is_dir(WPP_CACHE_DIR) && ! wp_mkdir_p(WPP_CACHE_DIR)) {
            return;
        }

        $index = WPP_CACHE_DIR . 'index.php';
        if (! is_file($index)) {
            @file_put_contents($index, "<?php\n// Silence is golden.\n", LOCK_EX);
        }

        // A cache clear deletes this file, so it is rewritten after every purge.
        $htaccess = WPP_CACHE_DIR . '.htaccess';
        if (! is_file($htaccess)) {
            @file_put_contents($htaccess, self::HTACCESS, LOCK_EX);
        }
    }

    /**
     * Earlier releases wrote the runtime config, the log and cached fragments
     * without a .php extension or an exit guard, so on nginx they were readable
     * over HTTP. A cache clear preserves json/log, so nothing else removes them.
     */
    public static function purgeUnguarded(): void
    {
        if (! is_dir(WPP_CACHE_DIR)) {
            return;
        }

        $stale = array_merge(
            self::unguardedFiles(),
            glob(WPP_CACHE_DIR . 'fragments/*/*.html') ?: []
        );

        foreach ($stale as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * Every blog on a network writes its config and its log into one directory,
     * so a wildcard would delete files belonging to sites whose own drop-in has
     * not been refreshed yet, leaving them reading another site's config or
     * nothing at all. A single site owns everything in there.
     *
     * @return list<string>
     */
    private static function unguardedFiles(): array
    {
        if (! is_multisite()) {
            return array_merge(
                glob(WPP_CACHE_DIR . '*wpp.log') ?: [],
                glob(WPP_CACHE_DIR . '*.json') ?: []
            );
        }

        $settings = new SettingsService();
        $host     = CacheStore::sanitizeHost((string) parse_url((string) home_url(), PHP_URL_HOST));

        return [
            substr((new RuntimeSettings($settings))->file(), 0, -4),
            substr((new Logger($settings))->file(), 0, -4),
            WPP_CACHE_DIR . $host . '.settings.json',
        ];
    }

    /**
     * Activation runs after plugins_loaded, so boot() never fired and the add-on
     * modules have not registered their settings groups. Migrating without them
     * would treat the legacy Cloudflare/Varnish/Prefetch options as orphans.
     */
    private static function bootAddons(): void
    {
        if (self::$booted) {
            return;
        }

        $self = self::instance();

        if (! $self->container->has(SettingsService::class)) {
            $self->container->bind(SettingsService::class, static fn (): SettingsService => new SettingsService());
        }

        foreach ([new CloudflareModule(), new VarnishModule(), new PrefetchModule()] as $module) {
            if ($self->modules->get($module->id()) === null) {
                $self->modules->register($module);
            }
        }

        $self->modules->bootAll();
    }

    /**
     * Put the object-cache drop-in back after a deactivation removed it, and
     * refresh a copy an earlier release installed.
     */
    public static function syncObjectCache(SettingsService $settings): void
    {
        $objectCache = new ObjectCacheInstaller();

        if (empty($settings->get('tools')['object_cache']) || ! $objectCache->available()) {
            return;
        }

        if ($objectCache->objectCacheInstalled() || $objectCache->install()) {
            return;
        }

        // A failed refresh of a copy that still works must not switch the
        // toggle off; only nothing of ours on disk means the toggle is lying.
        if (! $objectCache->installed()) {
            $settings->update('tools', ['object_cache' => false]);
        }
    }

    /** True when another blog on the network still has the plugin active. */
    private static function activeOnAnotherBlog(): bool
    {
        if (! is_multisite()) {
            return false;
        }

        $basename = plugin_basename(WPP_FILE);
        $current  = get_current_blog_id();

        if (array_key_exists($basename, (array) get_site_option('active_sitewide_plugins', []))) {
            return true;
        }

        foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $blogId) {
            if ((int) $blogId === $current) {
                continue;
            }

            switch_to_blog((int) $blogId);
            $active = in_array($basename, (array) get_option('active_plugins', []), true);
            restore_current_blog();

            if ($active) {
                return true;
            }
        }

        return false;
    }
}
