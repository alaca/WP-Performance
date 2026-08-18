<?php

declare(strict_types=1);

namespace WPP\Addons\Varnish;

use WPP\Foundation\Container\Container;
use WPP\Foundation\Modules\Module;
use WPP\Settings\SettingsService;

/**
 * Purges Varnish whenever the page cache is cleared.
 */
final class VarnishModule implements Module
{
    public function id(): string
    {
        return 'varnish';
    }

    public function boot(Container $container): void
    {
        add_filter('wpp.settings.groups', static function (array $groups): array {
            $groups['varnish'] = [
                'option'   => 'wpp_varnish',
                'defaults' => ['enabled' => false, 'custom_host' => ''],
            ];
            return $groups;
        });

        $settings = $container->get(SettingsService::class);
        if (empty($settings->get('varnish')['enabled'])) {
            return;
        }

        add_action('wpp.cache.after_clear', static function () use ($settings): void {
            (new VarnishClient())->purge($settings->get('varnish'));
        });
    }
}
