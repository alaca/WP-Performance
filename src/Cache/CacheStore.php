<?php

declare(strict_types=1);

namespace WPP\Cache;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Reads and writes the static page cache on disk.
 *
 * IMPORTANT: fileFor() must stay byte-for-byte in sync with the standalone
 * wpp_dropin_file() in advanced-cache.php, which serves cached files.
 */
final class CacheStore
{
    /** Tracking params stripped from the cache key (so ?utm_* etc. share the clean cache). */
    public const TRACKING_PARAMS = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'gclid', 'fbclid', 'gad_source', 'gbraid', 'wbraid', 'msclkid',
        'mc_cid', 'mc_eid', '_ga', '_gl', 'usqp', 'age-verified',
    ];

    /** Query string with tracking params removed and keys sorted; '' if nothing meaningful remains. */
    public static function strippedQuery(string $query): string
    {
        if ($query === '') {
            return '';
        }
        parse_str($query, $params);
        foreach (self::TRACKING_PARAMS as $param) {
            unset($params[$param]);
        }
        if ($params === []) {
            return '';
        }
        ksort($params);
        return http_build_query($params);
    }

    /** Whether a request carries real (non-tracking) query params. */
    public static function hasRealQuery(string $query): bool
    {
        return self::strippedQuery($query) !== '';
    }

    /**
     * Lowercased so a mixed-case or malformed Host header still resolves to the
     * directory the writer used. Mirrored by wpp_dropin_host() in advanced-cache.php.
     */
    public static function sanitizeHost(string $host): string
    {
        $host  = strtolower(explode(':', $host, 2)[0]);
        $clean = (string) preg_replace('/[^a-z0-9\.\-_]/', '', $host);

        return $clean !== '' ? $clean : 'site';
    }

    /**
     * Cache file path for a request.
     *
     * permalinks on  + no query : {host}{/path/}index.html
     * permalinks on  + query    : {host}{/path/}{md5(query)}.html
     * permalinks off            : {host}/{md5(host+uri)}.html
     * mobile variant adds "-mobile" before ".html"; gzip sibling adds ".gz".
     */
    public static function fileFor(string $host, string $requestUri, bool $permalinks, bool $mobile): string
    {
        $host  = self::sanitizeHost($host);
        $parts = explode('?', $requestUri, 2);
        $path  = '/' . trim(str_replace('..', '', $parts[0]), '/');
        if ($path !== '/') {
            $path .= '/';
        }
        $query = self::strippedQuery($parts[1] ?? '');

        if ($permalinks) {
            $dir = WPP_CACHE_DIR . $host . $path;
            $key = $query !== '' ? md5($query) : 'index';
        } else {
            $dir = WPP_CACHE_DIR . $host . '/';
            $key = md5($host . $path . ($query !== '' ? '?' . $query : ''));
        }

        return $dir . $key . ($mobile ? '-mobile' : '') . '.html';
    }

    public function exists(string $file): bool
    {
        return is_file($file);
    }

    public function save(string $file, string $html, bool $gzip): bool
    {
        $dir = dirname($file);
        if (! is_dir($dir) && ! wp_mkdir_p($dir)) {
            return false;
        }

        $ok = $this->writeAtomic($file, $html);

        // A stale .gz would keep being served in preference to the html, so it
        // is removed whenever gzip is off or the encode fails.
        $wroteGz = false;
        if ($ok && $gzip && function_exists('gzencode')) {
            $encoded = gzencode($html, 6);
            if ($encoded !== false) {
                $wroteGz = $this->writeAtomic($file . '.gz', $encoded);
            }
        }
        if (! $wroteGz && is_file($file . '.gz')) {
            @unlink($file . '.gz');
        }

        return $ok;
    }

    /**
     * The drop-in readfile()s these with no lock, so an in-place write would let
     * a concurrent request receive a half-written (or zero-byte) page. Rename is
     * atomic within a filesystem, so a reader sees either the old file or the new.
     */
    private function writeAtomic(string $file, string $contents): bool
    {
        $tmp = $file . '.' . getmypid() . '-' . uniqid() . '.tmp';
        if (file_put_contents($tmp, $contents, LOCK_EX) === false) {
            @unlink($tmp);
            return false;
        }
        if (! @rename($tmp, $file)) {
            @unlink($tmp);
            return false;
        }

        return true;
    }

    /**
     * Delete cached pages. Keeps json/log (drop-in config + log) and, optionally, css/js.
     *
     * On a network every blog writes into one cache directory, so a purge
     * started on one site is limited to that site's pages: the generated assets
     * at the root are shared, and the other blogs' pages are not this blog's to
     * throw away. $network is the network-wide flush a super admin asks for.
     */
    public function clear(bool $keepAssets = false, bool $network = false): void
    {
        if (! is_dir(WPP_CACHE_DIR)) {
            return;
        }
        do_action('wpp.cache.before_clear');

        $keep = ['json', 'log', 'php'];
        if ($keepAssets) {
            // Locally hosted fonts are referenced by the kept CSS, so dropping
            // them would leave every @font-face pointing at a 404.
            array_push($keep, 'css', 'js', 'woff2', 'woff', 'ttf', 'eot', 'otf');
        }
        // Block-cache fragments belong to the after_clear listener, which honours
        // block_cache_flush_on_clear; deleting them here ignores that setting.
        $this->deleteContents(self::scope($network), $keep, self::fragmentsDir());

        do_action('wpp.cache.after_clear');
    }

    /** Cache subtree a purge may delete from. */
    private static function scope(bool $network): string
    {
        if ($network || ! is_multisite()) {
            return WPP_CACHE_DIR;
        }

        $home = (string) home_url();
        $path = '/' . trim((string) parse_url($home, PHP_URL_PATH), '/');
        if ($path !== '/') {
            $path .= '/';
        }

        // Without permalinks every page is a flat md5 file in the host
        // directory, so the site path is not part of the layout to scope by.
        if (! get_option('permalink_structure', '')) {
            $path = '/';
        }

        return WPP_CACHE_DIR . self::sanitizeHost((string) parse_url($home, PHP_URL_HOST)) . $path;
    }

    public function clearAll(): void
    {
        if (! is_dir(WPP_CACHE_DIR)) {
            return;
        }
        $this->deleteContents(WPP_CACHE_DIR, []);
    }

    private static function fragmentsDir(): string
    {
        return rtrim(WPP_CACHE_DIR, '/\\') . '/fragments';
    }

    /** Whether a path sits inside the block cache's fragment subtree. */
    private static function isFragment(string $path): bool
    {
        $dir = self::fragmentsDir();

        return str_starts_with($path, $dir . '/') || str_starts_with($path, $dir . DIRECTORY_SEPARATOR);
    }

    /**
     * @param string[] $keepExt extensions to preserve
     */
    private function deleteContents(string $dir, array $keepExt, string $skipDir = ''): void
    {
        if (! is_dir($dir)) {
            return;
        }

        // glob() would treat a cached path containing [ ] * ? as a pattern and
        // return nothing, silently leaving that directory unpurged forever.
        foreach (iterator_to_array(new FilesystemIterator($dir, FilesystemIterator::SKIP_DOTS)) as $item) {
            $path = $item->getPathname();
            if ($item->isDir()) {
                if ($skipDir !== '' && rtrim($path, '/\\') === $skipDir) {
                    continue;
                }
                $this->deleteContents($path, $keepExt, $skipDir);
                @rmdir($path);
                continue;
            }
            $name = $item->getFilename();
            if ($name === 'index.php' || $name === '.htaccess') {
                continue;
            }
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (in_array($ext, $keepExt, true)) {
                continue;
            }
            @unlink($path);
        }
    }

    /** Number of cached HTML pages on disk (gzip siblings, assets and block fragments excluded). */
    public function pages(): int
    {
        if (! is_dir(WPP_CACHE_DIR)) {
            return 0;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(WPP_CACHE_DIR, FilesystemIterator::SKIP_DOTS)
        );

        $count = 0;
        foreach ($iterator as $file) {
            if (self::isFragment($file->getPathname())) {
                continue;
            }
            if ($file->isFile() && strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION)) === 'html') {
                $count++;
            }
        }

        return $count;
    }

    /** @return array{html:int, css:int, js:int, total:int} byte sizes, block fragments excluded */
    public function stats(): array
    {
        $out = ['html' => 0, 'css' => 0, 'js' => 0, 'total' => 0];
        if (! is_dir(WPP_CACHE_DIR)) {
            return $out;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(WPP_CACHE_DIR, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || self::isFragment($file->getPathname())) {
                continue;
            }
            $name = $file->getFilename();
            $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (in_array($ext, ['json', 'log', 'php'], true)) {
                continue;
            }
            if ($ext === 'gz') {
                $ext = strtolower(pathinfo(substr($name, 0, -3), PATHINFO_EXTENSION));
            }

            $size = (int) $file->getSize();
            $out['total'] += $size;
            if (isset($out[$ext])) {
                $out[$ext] += $size;
            }
        }

        return $out;
    }
}
