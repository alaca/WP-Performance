<?php

declare(strict_types=1);

namespace WPP\Cache;

/**
 * Installs/removes the object-cache.php drop-in. Only ever removes our own
 * drop-in (identified by a marker), never another plugin's.
 */
final class ObjectCacheInstaller
{
    private const MARKER = 'WPP_Object_Cache';
    private const STAMP  = 'WPP_OBJECT_CACHE';

    public function available(): bool
    {
        return extension_loaded('redis');
    }

    public function install(): bool
    {
        // Never replace another plugin's object cache: overwriting it would take
        // its caching offline and the original file is not recoverable.
        if ($this->foreignDropin() || ! DropinInstaller::fileModsAllowed()) {
            return false;
        }

        $src = WPP_DIR . 'object-cache.php';
        if (! file_exists($src)) {
            return false;
        }

        $contents = @file_get_contents($src);
        if ($contents === false) {
            return false;
        }

        return @file_put_contents($this->dest(), $this->stamp($contents), LOCK_EX) !== false;
    }

    public function uninstall(): void
    {
        if ($this->installed()) {
            @unlink($this->dest());
        }
    }

    public function installed(): bool
    {
        return is_file($this->dest()) && str_contains((string) @file_get_contents($this->dest()), self::MARKER);
    }

    /** Installed, ours, and matching the running plugin version. */
    public function objectCacheInstalled(): bool
    {
        return $this->installed() && $this->installedVersion() === $this->version();
    }

    /** True when an object-cache.php from another source is present. */
    public function foreignDropin(): bool
    {
        return is_file($this->dest()) && ! str_contains((string) @file_get_contents($this->dest()), self::MARKER);
    }

    private function dest(): string
    {
        return WP_CONTENT_DIR . '/object-cache.php';
    }

    private function version(): string
    {
        return defined('WPP_VERSION') ? (string) WPP_VERSION : '0';
    }

    private function installedVersion(): ?string
    {
        $contents = is_file($this->dest()) ? (string) @file_get_contents($this->dest()) : '';
        return preg_match("/'" . self::STAMP . "',\s*'([^']*)'/", $contents, $m) ? $m[1] : null;
    }

    /** The stamp is what tells a copy left by an earlier release from a current one. */
    private function stamp(string $contents): string
    {
        $define = "<?php\ndefine('" . self::STAMP . "', '" . $this->version() . "');\n";

        return preg_replace('/^<\?php\s*\n/', $define, $contents, 1) ?? $contents;
    }
}
