<?php

declare(strict_types=1);

namespace WPP\Optimize;

/**
 * Conservative, dependency-free HTML minifier.
 *
 * Protects <pre>/<textarea>/<script>/<style> verbatim, preserves conditional
 * comments, and only applies the transforms enabled in the html settings group.
 */
final class HtmlMinifier
{
    /** A single tag. Quote-aware, so a '>' inside an attribute value cannot end it. */
    private const TAG = '<\/?[a-zA-Z][a-zA-Z0-9-]*(?:"[^"]*"|\'[^\']*\'|[^"\'>])*>';

    /**
     * Tags whose surrounding whitespace never renders. Whitespace between two
     * inline elements is significant text and must survive.
     */
    private const BLOCK = 'address|article|aside|blockquote|body|br|caption|col|colgroup|dd|details'
        . '|dialog|div|dl|dt|fieldset|figcaption|figure|footer|form|h1|h2|h3|h4|h5|h6|head|header'
        . '|hgroup|hr|html|legend|li|link|main|meta|nav|noscript|ol|optgroup|option|p|section'
        . '|select|summary|table|tbody|td|tfoot|th|thead|title|tr|ul';

    /** @param array<string, mixed> $opt */
    public function minify(string $html, array $opt): string
    {
        $store = [];
        foreach (['pre', 'textarea', 'script', 'style'] as $tag) {
            $html = (string) preg_replace_callback(
                '#<' . $tag . '\b[^>]*>.*?</' . $tag . '>#is',
                static function (array $m) use (&$store): string {
                    $key = "\x02" . count($store) . "\x02";
                    $store[$key] = $m[0];
                    return $key;
                },
                $html
            );
        }

        if (! empty($opt['remove_comments'])) {
            // Preserve conditional / IE comments.
            $html = (string) preg_replace('/<!--(?!\[if)(?!\s*<!\[endif).*?-->/s', '', $html);
        }

        if (! empty($opt['remove_link_type'])) {
            $html = (string) preg_replace('#(<link\b[^>]*?)\s+type=(["\'])text/css\2#i', '$1', $html);
        }

        if (! empty($opt['minify_aggressive'])) {
            $block = '(?:' . self::BLOCK . ')';
            $html  = (string) preg_replace('#</' . $block . '>\K\s+(?=<)#i', '', $html);
            $html  = (string) preg_replace('#>\K\s+(?=</?' . $block . '[\s/>])#i', '', $html);
        }
        if (! empty($opt['minify_normal']) || ! empty($opt['minify_aggressive'])) {
            $html = $this->collapseWhitespace($html);
        }

        if (! empty($opt['remove_quotes'])) {
            $html = (string) preg_replace_callback(
                '#' . self::TAG . '#i',
                static fn (array $m): string => (string) preg_replace(
                    // A value ending in '/' would swallow the self-closing slash
                    // of '/>', and the match must end at an attribute boundary.
                    '/([a-zA-Z][a-zA-Z0-9-]*)=(["\'])([a-zA-Z0-9\-._:\/]*[a-zA-Z0-9\-._:])\2(?=[\s>])/',
                    '$1=$3',
                    $m[0]
                ),
                $html
            );
        }

        if (! empty($opt['remove_script_type'])) {
            foreach ($store as $key => $value) {
                if (stripos($value, '<script') === 0) {
                    $store[$key] = (string) preg_replace('#(<script\b[^>]*?)\s+type=(["\'])text/javascript\2#i', '$1', $value);
                }
            }
        }

        // A stashed region can hold a key stashed before it (a <pre> inside a
        // script string); strtr() never rescans what it just inserted.
        do {
            $before = $html;
            $html   = strtr($html, $store);
        } while ($html !== $before && str_contains($html, "\x02"));

        return $html;
    }

    /** Collapse whitespace runs, leaving quoted attribute values alone. */
    private function collapseWhitespace(string $html): string
    {
        $parts = preg_split('#(' . self::TAG . ')#i', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return (string) preg_replace('/\s{2,}/', ' ', $html);
        }

        foreach ($parts as $i => $part) {
            $parts[$i] = $i % 2 === 1
                ? (string) preg_replace_callback(
                    '#"[^"]*"|\'[^\']*\'|\s{2,}#',
                    static fn (array $m): string => $m[0][0] === '"' || $m[0][0] === "'" ? $m[0] : ' ',
                    $part
                )
                : (string) preg_replace('/\s{2,}/', ' ', $part);
        }

        return implode('', $parts);
    }
}
