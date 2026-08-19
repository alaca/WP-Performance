<?php

declare(strict_types=1);

namespace WPP\Cron;

use WPP\Settings\SettingsService;

/**
 * Warms the page cache by fetching URLs listed in the configured XML sitemaps.
 */
final class Preloader
{
    private const MAX_URLS = 50;
    private const MAX_INDEXED = 10;

    public function __construct(private SettingsService $settings)
    {
    }

    public function run(): void
    {
        $sitemaps = array_filter((array) $this->settings->get('cache')['sitemaps']);
        if ($sitemaps === []) {
            return;
        }

        $pages    = [];
        $children = [];

        foreach ($sitemaps as $sitemap) {
            $body = $this->fetch((string) $sitemap);
            if ($body === '') {
                continue;
            }
            // The URLs admins configure are usually indexes (wp-sitemap.xml,
            // sitemap_index.xml), whose <loc> entries are further sitemaps.
            if (stripos($body, '<sitemapindex') !== false) {
                $children = array_merge($children, $this->locations($body));
            } else {
                $pages = array_merge($pages, $this->locations($body));
            }
        }

        $children = array_slice(array_values(array_unique($children)), 0, self::MAX_INDEXED);
        foreach ($children as $child) {
            if (count($pages) >= self::MAX_URLS) {
                break;
            }
            $body = $this->fetch($child);
            if ($body !== '') {
                $pages = array_merge($pages, $this->locations($body));
            }
        }

        $pages = array_slice(array_values(array_unique($pages)), 0, self::MAX_URLS);
        foreach ($pages as $url) {
            wp_remote_get($url, ['timeout' => 5, 'blocking' => false, 'reject_unsafe_urls' => true]);
        }
    }

    private function fetch(string $url): string
    {
        $response = wp_remote_get($url, ['timeout' => 10, 'reject_unsafe_urls' => true]);
        return is_wp_error($response) ? '' : (string) wp_remote_retrieve_body($response);
    }

    /**
     * Sitemap bodies are remote input, so only locations on this site are kept:
     * anything else would let a third-party sitemap aim requests at hosts the
     * site can reach but the internet cannot.
     *
     * @return list<string>
     */
    private function locations(string $body): array
    {
        if (! preg_match_all('#<loc>\s*(.*?)\s*</loc>#i', $body, $matches)) {
            return [];
        }

        $host = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));
        $out  = [];
        foreach ($matches[1] as $loc) {
            $loc = trim((string) $loc);
            if ($loc !== '' && strtolower((string) wp_parse_url($loc, PHP_URL_HOST)) === $host) {
                $out[] = $loc;
            }
        }

        return $out;
    }
}
