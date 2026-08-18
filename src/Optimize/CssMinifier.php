<?php

declare(strict_types=1);

namespace WPP\Optimize;

/**
 * Conservative CSS minifier with no external dependencies.
 *
 * minify(): strip comments + collapse insignificant whitespace, preserving
 * string literals verbatim. Does not touch spaces around + or - (calc-safe) or
 * around : (descendant-pseudo-safe); gzip recovers those bytes anyway.
 *
 * process(): additionally rewrite relative url()/@import to absolute, needed
 * when a stylesheet is minified into / combined under the cache directory.
 */
final class CssMinifier
{
    public function minify(string $css): string
    {
        // Comments and strings are recognised in one pass: matched separately, an
        // apostrophe inside a comment pairs with one in a later comment and the
        // rules between them become a protected "string" the comment stripper
        // then deletes.
        $strings = [];
        $css = (string) preg_replace_callback(
            '#/\*.*?\*/|"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'#s',
            static function (array $m) use (&$strings): string {
                if (str_starts_with($m[0], '/*')) {
                    return '';
                }
                $key = "\x01" . count($strings) . "\x01";
                $strings[$key] = $m[0];
                return $key;
            },
            $css
        );

        $css = (string) preg_replace('/\s+/', ' ', $css);          // collapse whitespace
        $css = (string) preg_replace('/\s*([{};,>])\s*/', '$1', $css); // around structural chars
        // Collapse colon spacing only when whitespace follows (declarations like
        // "color: red"); selectors like ".x :hover" have no space after the colon.
        $css = (string) preg_replace('/\s*:\s+/', ':', $css);
        $css = str_replace(';}', '}', $css);                       // trailing semicolons
        $css = trim($css);

        return strtr($css, $strings);
    }

    public function process(string $css, string $sourceUrl): string
    {
        return $this->minify($this->absolutize($css, $sourceUrl));
    }

    private function absolutize(string $css, string $sourceUrl): string
    {
        // Quotes are re-emitted: an unquoted url() token may not contain
        // whitespace, quotes or parentheses.
        $count = 0;
        $css = (string) preg_replace_callback(
            '/url\(\s*(?:"([^"]*)"|\'([^\']*)\'|([^"\'()\s]*))\s*\)/i',
            function (array $m) use ($sourceUrl): string {
                $quote = $m[1] !== null ? '"' : ($m[2] !== null ? '\'' : '');
                $value = $m[1] ?? $m[2] ?? $m[3] ?? '';
                return 'url(' . $quote . $this->resolve($sourceUrl, trim($value)) . $quote . ')';
            },
            $css,
            -1,
            $count,
            PREG_UNMATCHED_AS_NULL
        );

        return (string) preg_replace_callback(
            '/@import\s+(["\'])([^"\']+)\1/i',
            fn (array $m): string => '@import "' . $this->resolve($sourceUrl, $m[2]) . '"',
            $css
        );
    }

    private function resolve(string $sourceUrl, string $rel): string
    {
        // Anything already absolute, a data/blob payload, or a bare fragment is
        // returned untouched. The delimiter must not be '#', which would end the
        // pattern at the fragment alternative and silently match nothing.
        if ($rel === '' || preg_match('~^(?:[a-z][a-z0-9+.-]*:|//|#)~i', $rel)) {
            return $rel;
        }

        $scheme = parse_url($sourceUrl, PHP_URL_SCHEME) ?: 'http';
        $host   = (string) parse_url($sourceUrl, PHP_URL_HOST);
        $port   = parse_url($sourceUrl, PHP_URL_PORT);
        $origin = $scheme . '://' . $host . ($port !== null ? ':' . $port : '');
        $srcPath = parse_url($sourceUrl, PHP_URL_PATH) ?: '/';

        if (str_starts_with($rel, '/')) {
            $path = $rel;
        } else {
            $dir  = substr($srcPath, 0, (int) strrpos($srcPath, '/') + 1);
            $path = $dir . $rel;
        }

        return $origin . $this->normalizePath($path);
    }

    private function normalizePath(string $path): string
    {
        $out = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                array_pop($out);
            } elseif ($segment !== '.' && $segment !== '') {
                $out[] = $segment;
            }
        }
        return '/' . implode('/', $out);
    }
}
