<?php

declare(strict_types=1);

namespace WPP\Optimize;

/**
 * Records discovered front-end CSS/JS URLs grouped by origin (theme/plugin/
 * external) so the admin CSS/JS tabs can render per-file controls. Buffered
 * during a request and merged into a single option on flush().
 */
final class Collection
{
    public const OPTION = 'wpp_collected_assets';

    /** @var array<string, array<string, string[]>> type => group => urls */
    private array $buffer = [];

    public function record(string $type, string $group, string $url): void
    {
        $this->buffer[$type][$group][] = $url;
    }

    public function flush(): void
    {
        if ($this->buffer === []) {
            return;
        }

        $stored  = $this->all();
        $changed = false;

        foreach ($this->buffer as $type => $groups) {
            foreach ($groups as $group => $urls) {
                $existing = $stored[$type][$group] ?? [];
                $merged   = array_values(array_unique(array_merge($existing, $urls)));
                if ($merged !== $existing) {
                    $stored[$type][$group] = $merged;
                    $changed = true;
                }
            }
        }

        if ($changed) {
            update_option(self::OPTION, $stored, false);
        }

        $this->buffer = [];
    }

    /** @return array<string, array<string, string[]>> */
    public function all(): array
    {
        $value = get_option(self::OPTION, []);
        return is_array($value) ? $value : [];
    }

    public function clear(): void
    {
        delete_option(self::OPTION);
    }

    /**
     * Drop the cached option so a value written by another process becomes
     * visible. delete_option() adds the key to the 'notoptions' cache, so after
     * a rescan clears the list the same request would otherwise keep reading the
     * default and never see what the loopback request just collected.
     */
    public function refresh(): void
    {
        wp_cache_delete(self::OPTION, 'options');

        $notoptions = wp_cache_get('notoptions', 'options');
        if (is_array($notoptions) && isset($notoptions[self::OPTION])) {
            unset($notoptions[self::OPTION]);
            wp_cache_set('notoptions', $notoptions, 'options');
        }
    }
}
