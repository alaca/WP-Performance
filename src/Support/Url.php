<?php

declare(strict_types=1);

namespace WPP\Support;

/**
 * URL pattern matching with the plugin's wildcard tokens.
 * Keep in sync with the standalone copy in advanced-cache.php.
 */
final class Url
{
    public static function matches(string $pattern, string $url): bool
    {
        if ($pattern === '') {
            return false;
        }
        if (str_contains($url, $pattern)) {
            return true;
        }
        return (bool) @preg_match(self::toRegex($pattern), $url);
    }

    /** @param array<int|string, mixed> $patterns */
    public static function anyMatch(array $patterns, string $url): bool
    {
        foreach ($patterns as $pattern) {
            if (self::matches((string) $pattern, $url)) {
                return true;
            }
        }
        return false;
    }

    public static function toRegex(string $pattern): string
    {
        $p = preg_quote($pattern, '#');
        $p = str_replace(
            ['\{any\}', '\{numbers\}', '\{letters\}', '\{all\}'],
            ['[^/]+', '[0-9]+', '[A-Za-z]+', '.*'],
            $p
        );
        return '#^' . $p . '$#';
    }
}
