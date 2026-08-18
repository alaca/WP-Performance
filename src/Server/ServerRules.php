<?php

declare(strict_types=1);

namespace WPP\Server;

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
        if ($this->serverType() !== 'apache') {
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
            $lines[] = '# Serve the WP Performance page cache (optional, bypasses PHP):';
            $lines[] = 'set $wpp_cache "";';
            $lines[] = 'if ($request_method = GET) { set $wpp_cache "C"; }';
            $lines[] = 'if ($query_string != "") { set $wpp_cache ""; }';
            $lines[] = 'if ($http_cookie ~* "wordpress_logged_in_|wp-postpass_|comment_author_") { set $wpp_cache ""; }';
            $lines[] = 'set $wpp_file "/wp-content/cache/wpp-cache/$host$request_uri/index.html";';
            $lines[] = 'if (-f $document_root$wpp_file) { set $wpp_cache "${wpp_cache}F"; }';
            $lines[] = 'if ($wpp_cache = "CF") { rewrite ^ $wpp_file last; }';
        }

        return trim(implode("\n", $lines));
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
