<?php

declare(strict_types=1);

namespace WPP\Optimize;

/**
 * Downloads a Google Fonts stylesheet + its woff2 files into the cache dir and
 * rewrites the CSS to serve them locally. Result is cached on disk.
 */
final class FontHost
{
    /**
     * @return array{css_url: string, fonts: string[]}|null
     */
    public function localize(string $googleUrl): ?array
    {
        if (strtolower((string) parse_url($googleUrl, PHP_URL_HOST)) !== 'fonts.googleapis.com') {
            return null;
        }

        $dir      = WPP_CACHE_DIR . 'fonts/';
        $hash     = md5($googleUrl);
        $cssFile  = $dir . $hash . '.css';
        $cssUrl   = WPP_CACHE_URL . 'fonts/' . $hash . '.css';
        $metaFile = $dir . $hash . '.json';

        if (is_file($cssFile) && is_file($metaFile)) {
            $fonts = json_decode((string) file_get_contents($metaFile), true);
            return ['css_url' => $cssUrl, 'fonts' => is_array($fonts) ? $fonts : []];
        }

        if (! is_dir($dir) && ! wp_mkdir_p($dir)) {
            return null;
        }

        // A modern UA makes Google serve woff2 @font-face rules.
        $response = wp_remote_get($googleUrl, [
            'timeout' => 10,
            'headers' => [
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
            ],
        ]);
        if (is_wp_error($response)) {
            return null;
        }
        $css = (string) wp_remote_retrieve_body($response);
        if ($css === '') {
            return null;
        }

        $fonts = [];
        if (preg_match_all('#https://fonts\.gstatic\.com/[^)\'"]+\.woff2#i', $css, $matches)) {
            foreach (array_unique($matches[0]) as $fontUrl) {
                $localName = md5($fontUrl) . '.woff2';
                $localPath = $dir . $localName;
                $localUrl  = WPP_CACHE_URL . 'fonts/' . $localName;

                if (! is_file($localPath)) {
                    $fontResponse = wp_remote_get($fontUrl, ['timeout' => 10]);
                    if (is_wp_error($fontResponse)) {
                        continue;
                    }
                    $body = (string) wp_remote_retrieve_body($fontResponse);
                    if ($body === '') {
                        continue;
                    }
                    // Rewriting the CSS after a failed write caches @font-face
                    // rules pointing at files that do not exist.
                    if (file_put_contents($localPath, $body, LOCK_EX) === false) {
                        continue;
                    }
                }

                $css     = str_replace($fontUrl, $localUrl, $css);
                $fonts[] = $localUrl;
            }
        }

        if (file_put_contents($cssFile, $css, LOCK_EX) === false
            || file_put_contents($metaFile, (string) wp_json_encode($fonts), LOCK_EX) === false) {
            return null;
        }

        return ['css_url' => $cssUrl, 'fonts' => $fonts];
    }
}
