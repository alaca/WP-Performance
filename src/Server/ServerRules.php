<?php

declare(strict_types=1);

namespace WPP\Server;

use WPP\Cache\DropinInstaller;

/**
 * Generates browser-cache (expires) and gzip rules. On Apache they are written
 * into .htaccess; on nginx they are returned as text for manual paste.
 * Page-cache serving itself is handled by the advanced-cache.php drop-in.
 */
final class ServerRules
{
    private const TYPES_LONG = [
        'text/css', 'application/javascript', 'text/javascript',
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        'image/x-icon', 'font/woff', 'font/woff2', 'application/font-woff',
    ];

    public function serverType(): string
    {
        $software = strtolower((string) ($_SERVER['SERVER_SOFTWARE'] ?? ''));
        if (str_contains($software, 'nginx') || str_contains($software, 'flywheel')) {
            return 'nginx';
        }
        if (str_contains($software, 'apache') || str_contains($software, 'litespeed')) {
            return 'apache';
        }
        return 'unknown';
    }

    /** @param array<string,mixed> $cache write/refresh .htaccess blocks (Apache only). */
    public function apply(array $cache): void
    {
        // ABSPATH/.htaccess is a network-wide root file, so it sits behind the
        // same boundary as the drop-ins: not a subsite administrator's to write,
        // and off limits entirely under DISALLOW_FILE_MODS.
        if (! DropinInstaller::fileModsAllowed() || $this->serverType() !== 'apache') {
            return;
        }
        $file = $this->htaccessPath();
        if ($file === null) {
            return;
        }
        $this->writeBlock($file, 'expire', ! empty($cache['browser_cache']) ? $this->expiresBlock() : '');
        $this->writeBlock($file, 'gzip', ! empty($cache['gzip']) ? $this->gzipBlock() : '');
    }

    public function removeAll(): void
    {
        if (! DropinInstaller::fileModsAllowed()) {
            return;
        }
        $file = $this->htaccessPath();
        if ($file === null) {
            return;
        }
        $this->writeBlock($file, 'expire', '');
        $this->writeBlock($file, 'gzip', '');
    }

    /** @param array<string,mixed> $cache nginx config text for manual paste. */
    public function nginxRules(array $cache): string
    {
        $lines = [];

        if (! empty($cache['gzip'])) {
            $lines[] = 'gzip on;';
            $lines[] = 'gzip_comp_level 5;';
            $lines[] = 'gzip_types text/css application/javascript text/javascript image/svg+xml application/json;';
            $lines[] = '';
        }

        if (! empty($cache['browser_cache'])) {
            $lines[] = 'location ~* \.(css|js|jpg|jpeg|png|gif|webp|svg|ico|woff2?)$ {';
            $lines[] = '    expires 30d;';
            $lines[] = '    add_header Cache-Control "public, max-age=2592000";';
            $lines[] = '}';
            $lines[] = '';
        }

        if (! empty($cache['enabled'])) {
            foreach ($this->pageCacheLines($cache) as $line) {
                $lines[] = $line;
            }
        }

        return trim(implode("\n", $lines));
    }

    /**
     * A rewrite here answers before PHP runs, so it is only offered where the
     * static file is the same answer the drop-in would have given.
     *
     * @param array<string,mixed> $cache
     * @return list<string>
     */
    private function pageCacheLines(array $cache): array
    {
        if (! empty($cache['mobile'])) {
            return [
                '# Page cache serving is left to PHP while the mobile cache is on: nginx',
                '# would answer phones with the desktop copy, and the mobile variant is only',
                '# ever written by a request that reaches WordPress.',
            ];
        }

        if (! get_option('permalink_structure')) {
            return [
                '# Page cache serving is left to PHP: with plain permalinks the cached file',
                '# is named after a hash of the URL, which nginx cannot build.',
            ];
        }

        return [
            '# Serve the WP Performance page cache (optional, bypasses PHP).',
            '# nginx cannot compare file age, so a page matched here is served whatever its',
            '# age: the configured expiry only applies to requests that reach PHP.',
            'set $wpp_cache "";',
            'if ($request_method = GET) { set $wpp_cache "C"; }',
            'if ($query_string != "") { set $wpp_cache ""; }',
            'if ($http_cookie ~* "wordpress_logged_in_|wp-postpass_|comment_author_") { set $wpp_cache ""; }',
            'set $wpp_file "/wp-content/cache/wpp-cache/$host$request_uri/index.html";',
            'if (-f $document_root$wpp_file) { set $wpp_cache "${wpp_cache}F"; }',
            'if ($wpp_cache = "CF") { rewrite ^ $wpp_file last; }',
        ];
    }

    private function expiresBlock(): string
    {
        $out = ["<IfModule mod_expires.c>", "ExpiresActive On"];
        foreach (self::TYPES_LONG as $type) {
            $duration = str_starts_with($type, 'image/') || str_starts_with($type, 'font/')
                ? 'access plus 1 month'
                : 'access plus 1 year';
            if (str_starts_with($type, 'text/css') || str_contains($type, 'javascript')) {
                $duration = 'access plus 1 year';
            }
            $out[] = "ExpiresByType {$type} \"{$duration}\"";
        }
        $out[] = "</IfModule>";
        $out[] = '<IfModule mod_headers.c>';
        $out[] = '  Header set Cache-Control "public" env=long_cache';
        $out[] = '</IfModule>';
        return implode("\n", $out);
    }

    private function gzipBlock(): string
    {
        return "<IfModule mod_deflate.c>\n"
            . "AddOutputFilterByType DEFLATE text/html text/plain text/css text/xml "
            . "application/javascript application/json image/svg+xml font/woff font/woff2\n"
            . "</IfModule>";
    }

    private function writeBlock(string $file, string $name, string $content): void
    {
        $marker   = 'WPP ' . $name;
        $existing = file_exists($file) ? (string) file_get_contents($file) : '';
        $existing = (string) preg_replace('/# BEGIN ' . $marker . '.*?# END ' . $marker . '\s*/s', '', $existing);

        if ($content !== '') {
            $block    = "# BEGIN {$marker}\n" . $content . "\n# END {$marker}\n";
            $existing = $block . ltrim($existing);
        }

        file_put_contents($file, $existing, LOCK_EX);
    }

    private function htaccessPath(): ?string
    {
        $file = ABSPATH . '.htaccess';
        if (file_exists($file) && is_writable($file)) {
            return $file;
        }
        if (! file_exists($file) && is_writable(ABSPATH)) {
            return $file;
        }
        return null;
    }
}
