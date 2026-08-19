<?php

declare(strict_types=1);

namespace WPP\Optimize;

use WPP\Support\Assets;

/**
 * Heuristic used-CSS extractor. For a given page, keeps only the rules whose
 * class/id tokens appear in the HTML (plus tag/at-rules and a safelist). It is
 * deliberately conservative: rules are kept on any doubt, never wrongly dropped
 * for a token that is present. The full stylesheet is still loaded async as a
 * fallback (handled by the caller) so JS-added classes remain styled.
 */
final class UsedCss
{
    private CssMinifier $css;

    public function __construct()
    {
        $this->css = new CssMinifier();
    }

    /**
     * @param string[] $urls local CSS file URLs
     * @param string[] $safelist selector substrings to always keep
     */
    public function build(string $html, array $urls, array $safelist): string
    {
        $tokens = $this->htmlTokens($html);
        $out    = '';

        foreach ($urls as $url) {
            $code = Assets::contents($url);
            if ($code === null) {
                continue;
            }
            // Absolutize url()/@import and minify before inlining.
            $code = $this->css->process($code, $url);
            foreach ($this->statements($code) as $statement) {
                $out .= $this->filterStatement($statement, $tokens, $safelist);
            }
        }

        return $out;
    }

    /** @return array<string,bool> set of .class and #id tokens present in the HTML */
    private function htmlTokens(string $html): array
    {
        $tokens = [];
        if (preg_match_all('/class\s*=\s*["\']([^"\']+)["\']/i', $html, $m)) {
            foreach ($m[1] as $classList) {
                foreach (preg_split('/\s+/', trim($classList)) ?: [] as $class) {
                    if ($class !== '') {
                        $tokens['.' . $class] = true;
                    }
                }
            }
        }
        if (preg_match_all('/id\s*=\s*["\']([^"\']+)["\']/i', $html, $m)) {
            foreach ($m[1] as $id) {
                $tokens['#' . trim($id)] = true;
            }
        }
        return $tokens;
    }

    /** Split CSS into top-level statements (rules + at-blocks), brace-aware. @return string[] */
    private function statements(string $css): array
    {
        $statements = [];
        $length     = strlen($css);
        $depth      = 0;
        $start      = 0;
        $quote      = '';

        for ($i = 0; $i < $length; $i++) {
            $char = $css[$i];

            // Braces and semicolons inside a string literal are content.
            if ($quote !== '') {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === $quote) {
                    $quote = '';
                }
                continue;
            }

            if ($char === '"' || $char === '\'') {
                $quote = $char;
            } elseif ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth <= 0) {
                    $statements[] = substr($css, $start, $i - $start + 1);
                    $start = $i + 1;
                    $depth = 0;
                }
            } elseif ($char === ';' && $depth === 0) {
                $statements[] = substr($css, $start, $i - $start + 1);
                $start = $i + 1;
            }
        }
        $tail = trim(substr($css, $start));
        if ($tail !== '') {
            $statements[] = $tail;
        }

        return $statements;
    }

    /**
     * @param array<string,bool> $tokens
     * @param string[] $safelist
     */
    private function filterStatement(string $statement, array $tokens, array $safelist): string
    {
        $statement = trim($statement);
        if ($statement === '') {
            return '';
        }

        if ($statement[0] === '@') {
            $keyword = strtolower(substr($statement, 1, strcspn(substr($statement, 1), " \t\n{(")));

            if (in_array($keyword, ['media', 'supports', 'container'], true)) {
                $open = strpos($statement, '{');
                if ($open === false) {
                    return $statement;
                }
                $header = substr($statement, 0, $open + 1);
                $inner  = substr($statement, $open + 1, strrpos($statement, '}') - $open - 1);
                $kept   = '';
                foreach ($this->statements($inner) as $sub) {
                    $kept .= $this->filterStatement($sub, $tokens, $safelist);
                }
                return $kept !== '' ? $header . $kept . '}' : '';
            }

            // @font-face, @keyframes, @page, @import, @charset, :root, etc: keep.
            return $statement;
        }

        $open = strpos($statement, '{');
        if ($open === false) {
            return '';
        }
        $selectors = substr($statement, 0, $open);
        $body      = substr($statement, $open);

        $kept = [];
        foreach (explode(',', $selectors) as $selector) {
            if ($this->selectorUsed($selector, $tokens, $safelist)) {
                $kept[] = trim($selector);
            }
        }

        return $kept !== [] ? implode(',', $kept) . $body : '';
    }

    /**
     * @param array<string,bool> $tokens
     * @param string[] $safelist
     */
    private function selectorUsed(string $selector, array $tokens, array $safelist): bool
    {
        foreach ($safelist as $safe) {
            if ($safe !== '' && str_contains($selector, (string) $safe)) {
                return true;
            }
        }

        // Attribute values and functional-pseudo arguments hold '#'/'.' text that
        // is not a class or id token, e.g. a[href="#top"] or :not(.js-hidden).
        $selector = (string) preg_replace('/\[[^\]]*\]/', '', $selector);
        $selector = (string) preg_replace('/:[a-zA-Z-]+\([^)]*\)/', '', $selector);

        if (preg_match_all('/[.#]-?[A-Za-z_][A-Za-z0-9_-]*/', $selector, $m)) {
            foreach ($m[0] as $token) {
                if (! isset($tokens[$token])) {
                    return false;
                }
            }
        }

        // No class/id tokens (tag/universal/pseudo/attribute) -> keep conservatively.
        return true;
    }
}
