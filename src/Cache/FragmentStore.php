<?php

declare(strict_types=1);

namespace WPP\Cache;

/**
 * Fragment storage: persistent object cache when available, else disk files.
 * Invalidation is generation-based, so flush() just bumps a namespacing counter.
 */
final class FragmentStore
{
    private const GROUP      = 'wpp_fragments';
    private const GEN_OPTION = 'wpp_fragment_gen';
    private const SUBDIR     = 'fragments/';

    /** Per-shard file ceiling; keys are md5-spread over 256 shards. */
    private const MAX_PER_SHARD = 200;

    public function usesObjectCache(): bool
    {
        return function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache();
    }

    public function get(string $identity): ?string
    {
        $key = $this->key($identity);

        if ($this->usesObjectCache()) {
            $value = wp_cache_get($key, self::GROUP);
            return is_string($value) ? $value : null;
        }

        return $this->diskGet($key);
    }

    public function set(string $identity, string $html, int $ttl): void
    {
        $key = $this->key($identity);
        $ttl = max(0, $ttl);

        if ($this->usesObjectCache()) {
            wp_cache_set($key, $html, self::GROUP, $ttl);
            return;
        }

        $this->diskSet($key, $html, $ttl);
    }

    public function flush(): void
    {
        update_option(self::GEN_OPTION, $this->generation() + 1, true);

        $scope = $this->siteScope();
        if ($scope === '') {
            $this->deleteDir($this->dir());
            return;
        }

        // Sibling blogs keep their own generation counter, so wiping the shared
        // tree would leave them serving entries they can no longer invalidate.
        foreach (glob($this->dir() . '*/' . $scope . '-*.html.php') ?: [] as $file) {
            @unlink($file);
        }
    }

    /** @return array{count:int, bytes:int} disk fragments only */
    public function stats(): array
    {
        $out = ['count' => 0, 'bytes' => 0];
        $dir = $this->dir();
        if (! is_dir($dir)) {
            return $out;
        }

        $scope    = $this->siteScope();
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            $name = $file->getFilename();
            if (! $file->isFile() || ! str_ends_with($name, '.html.php')) {
                continue;
            }
            if ($scope !== '' && ! str_starts_with($name, $scope . '-')) {
                continue;
            }
            $out['count']++;
            $out['bytes'] += (int) $file->getSize();
        }

        return $out;
    }

    private function generation(): int
    {
        return (int) get_option(self::GEN_OPTION, 0);
    }

    /**
     * Namespace for the current blog, empty off a network.
     *
     * Every blog shares one fragments/ tree and one identity format, so without
     * this two sites running the same theme resolve to the same file.
     */
    private function siteScope(): string
    {
        if (! function_exists('is_multisite') || ! is_multisite()) {
            return '';
        }

        return 's' . get_current_blog_id();
    }

    private function key(string $identity): string
    {
        $scope = $this->siteScope();

        return md5($this->generation() . '|' . ($scope !== '' ? $scope . '|' : '') . $identity);
    }

    /** Deny direct HTTP access to stored fragments. */
    private function protect(): void
    {
        $dir = $this->dir();

        $index = $dir . 'index.php';
        if (! is_file($index)) {
            @file_put_contents($index, "<?php\n// Silence is golden.\n", LOCK_EX);
        }

        $htaccess = $dir . '.htaccess';
        if (! is_file($htaccess)) {
            @file_put_contents($htaccess, "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n", LOCK_EX);
        }
    }

    private function dir(): string
    {
        return WPP_CACHE_DIR . self::SUBDIR;
    }

    /** The blog prefix keeps flush() and stats() able to tell one blog's files from another's. */
    private function fileFor(string $key): string
    {
        $scope = $this->siteScope();

        return $this->dir() . substr($key, 0, 2) . '/' . ($scope !== '' ? $scope . '-' : '') . $key . '.html.php';
    }

    private function diskGet(string $key): ?string
    {
        $file = $this->fileFor($key);
        if (! is_file($file)) {
            return null;
        }

        $raw = file_get_contents($file);
        if ($raw === false) {
            return null;
        }

        $raw = RuntimeSettings::payload($raw);
        $raw = ltrim($raw, "\n");

        $nl = strpos($raw, "\n");
        if ($nl === false) {
            return null;
        }

        $expiry = (int) substr($raw, 0, $nl);
        if ($expiry !== 0 && $expiry < time()) {
            @unlink($file);
            return null;
        }

        return substr($raw, $nl + 1);
    }

    private function diskSet(string $key, string $html, int $ttl): void
    {
        $file = $this->fileFor($key);
        $dir  = dirname($file);
        if (! is_dir($dir) && ! wp_mkdir_p($dir)) {
            return;
        }

        // Fragments are rendered page sections and may hold content the visitor
        // was not meant to see, so the directory must not be browsable.
        $this->protect();

        // Identities carry the request URL, so an anonymous visitor can mint
        // unlimited distinct entries. Refuse rather than exhaust inodes.
        if (! is_file($file) && $this->shardFull($dir)) {
            return;
        }

        $expiry = $ttl > 0 ? time() + $ttl : 0;
        $body   = RuntimeSettings::GUARD . $expiry . "\n" . $html;

        // Readers take no lock, so the entry has to appear whole or not at all.
        $tmp = $file . '.' . getmypid() . uniqid('', false) . '.tmp';
        if (file_put_contents($tmp, $body) === false || ! @rename($tmp, $file)) {
            @unlink($tmp);
        }
    }

    /** Sweeps expired entries before reporting a shard as full. */
    private function shardFull(string $dir): bool
    {
        $files = glob(rtrim($dir, '/') . '/*.html.php');
        if (! is_array($files) || count($files) < self::MAX_PER_SHARD) {
            return false;
        }

        $now  = time();
        $live = 0;
        foreach ($files as $file) {
            $handle = @fopen($file, 'r');
            if ($handle === false) {
                $live++;
                continue;
            }
            // First line is the exit guard, so the expiry is on the second.
            $first  = (string) fgets($handle);
            $expiry = str_starts_with($first, '<?php') ? (int) fgets($handle) : (int) $first;
            fclose($handle);
            if ($expiry !== 0 && $expiry < $now) {
                @unlink($file);
                continue;
            }
            $live++;
        }

        return $live >= self::MAX_PER_SHARD;
    }

    private function deleteDir(string $dir): void
    {
        $items = glob(rtrim($dir, '/') . '/*');
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if (is_dir($item)) {
                $this->deleteDir($item);
                @rmdir($item);
            } else {
                @unlink($item);
            }
        }
    }
}
