<?php

declare(strict_types=1);

namespace WPP\Addons\Varnish;

/**
 * Sends a non-blocking PURGE request to a Varnish cache.
 */
final class VarnishClient
{
    /** @param array<string,mixed> $cfg */
    public function purge(array $cfg): void
    {
        $custom = trim((string) ($cfg['custom_host'] ?? ''));
        $target = $custom !== '' ? $custom : home_url('/');

        $parsed = wp_parse_url($target);
        if (! is_array($parsed) || empty($parsed['host'])) {
            return;
        }

        $scheme   = $parsed['scheme'] ?? 'http';
        $port     = isset($parsed['port']) ? ':' . (int) $parsed['port'] : '';
        $purgeUrl = $scheme . '://' . $parsed['host'] . $port . '/.*';

        wp_remote_request($purgeUrl, [
            'method'   => 'PURGE',
            'blocking' => false,
            'timeout'  => 2,
            'headers'  => [
                'host'           => (string) wp_parse_url(home_url(), PHP_URL_HOST),
                'X-Purge-Method' => 'regex',
            ],
        ]);
    }
}
