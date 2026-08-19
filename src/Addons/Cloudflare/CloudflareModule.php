<?php

declare(strict_types=1);

namespace WPP\Addons\Cloudflare;

use WPP\Foundation\Container\Container;
use WPP\Foundation\Modules\Module;
use WPP\Settings\SettingsService;
use WPP\Support\Logger;

/**
 * Cloudflare integration: settings group, REST purge endpoints, and pushing
 * zone settings to Cloudflare when the settings are saved.
 */
final class CloudflareModule implements Module
{
    public const ERRORS_OPTION = 'wpp_cloudflare_push_errors';

    private const DEV_MODE_OPTION = 'wpp_cloudflare_dev_mode_pushed';

    public function id(): string
    {
        return 'cloudflare';
    }

    public function boot(Container $container): void
    {
        add_filter('wpp.settings.groups', static function (array $groups): array {
            $groups['cloudflare'] = [
                'option'   => 'wpp_cloudflare',
                'defaults' => [
                    'enabled'           => false,
                    'api_key'           => '',
                    'email'             => '',
                    'zone_id'           => '',
                    'dev_mode'          => false,
                    'cache_level'       => 'aggressive',
                    'browser_expire'    => 14400,
                    'rocket_loader'     => false,
                    'brotli'            => false,
                    'custom_purge_urls' => [],
                ],
            ];
            return $groups;
        });

        $settings = $container->get(SettingsService::class);

        add_action('wpp.settings.updated', static function (string $group) use ($settings): void {
            if ($group !== 'cloudflare') {
                return;
            }
            $cf = $settings->get('cloudflare');
            if (empty($cf['enabled']) || empty($cf['email']) || empty($cf['api_key']) || empty($cf['zone_id'])) {
                return;
            }

            // Cloudflare turns development mode off by itself after three hours,
            // so re-asserting a stale 'on' would bypass the whole cache again.
            $dev     = ! empty($cf['dev_mode']);
            $pushed  = get_option(self::DEV_MODE_OPTION, null);
            $withDev = $pushed === null || (bool) $pushed !== $dev;

            $api    = new CloudflareApi((string) $cf['email'], (string) $cf['api_key'], (string) $cf['zone_id']);
            $failed = $api->applyAll($cf, $withDev);

            if ($withDev && ! isset($failed['development_mode'])) {
                update_option(self::DEV_MODE_OPTION, $dev, false);
            }

            $logger = new Logger($settings);
            foreach ($failed as $id => $message) {
                $logger->log(sprintf('Cloudflare: %s was not applied (%s)', $id, $message));
            }

            if ($failed === []) {
                delete_option(self::ERRORS_OPTION);
                return;
            }
            update_option(self::ERRORS_OPTION, $failed, false);
        }, 10, 2);

        add_action('wpp.rest.register', static function ($registry) use ($settings): void {
            $registry->add(new CloudflareController($settings));
        });
    }
}
