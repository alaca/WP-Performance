<?php

declare(strict_types=1);

namespace WPP\Optimize;

/**
 * Downloads a Google Fonts stylesheet + its woff2 files into the cache dir and
 * rewrites the CSS to serve them locally. Result is cached on disk.
 */
final class FontHost
{
    /** Seconds a failed download suppresses the next attempt for. */
    private const RETRY_AFTER = 300;

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
        // .php so a cache purge, which runs on every post update, does not drop
        // the failure record and reopen the stall it exists to prevent.
        $failFile = $dir . $hash . '.fail.php';

        if (is_file($cssFile) && is_file($metaFile)) {
            $fonts = json_decode((string) file_get_contents($metaFile), true);
            return ['css_url' => $cssUrl, 'fonts' => is_array($fonts) ? $fonts : []];
        }

        // localize() runs inside the output buffer of every uncached front-end
        // request, so an unreachable endpoint must not be retried each time.
        if (is_file($failFile) && (time() - (int) @filemtime($failFile)) < self::RETRY_AFTER) {
            return null;
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
            return $this->fail($failFile);
        }
        $css = (string) wp_remote_retrieve_body($response);
        if ($css === '') {
            return $this->fail($failFile);
        }

        $fonts    = [];
        $complete = true;
        if (preg_match_all('#https://fonts\.gstatic\.com/[^)\'"]+\.woff2#i', $css, $matches)) {
            foreach (array_unique($matches[0]) as $fontUrl) {
                $localName = md5($fontUrl) . '.woff2';
                $localPath = $dir . $localName;
                $localUrl  = WPP_CACHE_URL . 'fonts/' . $localName;

                if (! is_file($localPath)) {
                    $fontResponse = wp_remote_get($fontUrl, ['timeout' => 10]);
                    if (is_wp_error($fontResponse)) {
                        $complete = false;
                        continue;
                    }
                    $body = (string) wp_remote_retrieve_body($fontResponse);
                    if ($body === '') {
                        $complete = false;
                        continue;
                    }
                    // Rewriting the CSS after a failed write caches @font-face
                    // rules pointing at files that do not exist.
                    if (file_put_contents($localPath, $body, LOCK_EX) === false) {
                        $complete = false;
                        continue;
                    }
                }

                $css     = str_replace($fontUrl, $localUrl, $css);
                $fonts[] = $localUrl;
            }
        }

        // Caching a partial rewrite is permanent: the short-circuit above would
        // keep serving faces straight from fonts.gstatic.com for good.
        if (! $complete) {
            return $this->fail($failFile);
        }

        if (file_put_contents($cssFile, $css, LOCK_EX) === false
            || file_put_contents($metaFile, (string) wp_json_encode($fonts), LOCK_EX) === false) {
            return $this->fail($failFile);
        }

        @unlink($failFile);

        return ['css_url' => $cssUrl, 'fonts' => $fonts];
    }

    /** Records the failure so the next requests fall back without a round trip. */
    private function fail(string $failFile): ?array
    {
        if (! is_file($failFile)) {
            @file_put_contents($failFile, "<?php exit; ?>\n", LOCK_EX);
            return null;
        }
        @touch($failFile);

        return null;
    }
}
