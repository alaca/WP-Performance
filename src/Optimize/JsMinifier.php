<?php

declare(strict_types=1);

namespace WPP\Optimize;

/**
 * Conservative, dependency-free JS minifier.
 *
 * A character state machine strips comments and collapses insignificant
 * whitespace while passing string, template, and regex literals through
 * verbatim. Newlines are preserved (collapsed runs keep one), so Automatic
 * Semicolon Insertion is never broken. Correctness over maximum compression;
 * gzip recovers the rest.
 */
final class JsMinifier
{
    /** Keywords after which a '/' starts a regex literal rather than a division. */
    private const REGEX_KEYWORDS = [
        'return', 'typeof', 'case', 'in', 'of', 'new', 'delete', 'void',
        'do', 'else', 'instanceof', 'yield', 'await', 'throw',
    ];

    public function minify(string $js): string
    {
        $len    = strlen($js);
        $out    = '';
        $last   = '';
        $prev   = '';
        $word   = '';
        $before = '';
        $nl     = false;
        $sp     = false;
        $i      = 0;

        while ($i < $len) {
            $c = $js[$i];
            $n = $i + 1 < $len ? $js[$i + 1] : '';

            if ($c === ' ' || $c === "\t" || $c === "\r" || $c === "\n") {
                if ($c === "\n" || $c === "\r") {
                    $nl = true;
                } else {
                    $sp = true;
                }
                $i++;
                continue;
            }

            if ($c === '/' && $n === '/') {
                $i += 2;
                while ($i < $len && $js[$i] !== "\n") {
                    $i++;
                }
                continue;
            }

            if ($c === '/' && $n === '*') {
                $i += 2;
                while ($i < $len && ! ($js[$i] === '*' && ($js[$i + 1] ?? '') === '/')) {
                    $i++;
                }
                $i += 2;
                $sp = true;
                continue;
            }

            if ($c === '"' || $c === "'") {
                $this->flush($out, $nl, $sp);
                $out .= $this->readString($js, $i, $c);
                $prev = $last;
                $last = $c;
                $word = '';
                continue;
            }

            if ($c === '`') {
                $this->flush($out, $nl, $sp);
                $out .= $this->readTemplate($js, $i);
                $prev = $last;
                $last = '`';
                $word = '';
                continue;
            }

            if ($c === '/' && $this->regexAllowed($last, $prev, $word, $before)) {
                $regex = $this->readRegex($js, $i);
                if ($regex === null) {
                    // Ambiguous '/': scanning on would flip string parity and
                    // delete code. Unminified beats corrupted.
                    return $js;
                }
                $this->flush($out, $nl, $sp);
                $out .= $regex;
                $prev = $last;
                $last = '/';
                $word = '';
                continue;
            }

            $this->flush($out, $nl, $sp);
            $out .= $c;
            $prev = $last;
            $last = $c;
            if (ctype_alnum($c) || $c === '_' || $c === '$') {
                if ($word === '') {
                    $before = $prev;
                }
                $word .= $c;
            } else {
                $word = '';
            }
            $i++;
        }

        return trim($out);
    }

    private function flush(string &$out, bool &$nl, bool &$sp): void
    {
        if ($out === '') {
            $nl = false;
            $sp = false;
            return;
        }
        if ($nl) {
            $out .= "\n";
        } elseif ($sp) {
            $out .= ' ';
        }
        $nl = false;
        $sp = false;
    }

    private function readString(string $s, int &$i, string $quote): string
    {
        $start = $i;
        $len   = strlen($s);
        $i++;
        while ($i < $len) {
            $c = $s[$i];
            if ($c === '\\') {
                $i += 2;
                continue;
            }
            if ($c === $quote) {
                $i++;
                break;
            }
            $i++;
        }
        return substr($s, $start, $i - $start);
    }

    private function readTemplate(string $s, int &$i): string
    {
        $start = $i;
        $len   = strlen($s);
        $depth = 0;
        $i++;
        while ($i < $len) {
            $c = $s[$i];
            if ($c === '\\') {
                $i += 2;
                continue;
            }
            if ($depth === 0 && $c === '`') {
                $i++;
                break;
            }
            if ($c === '$' && ($s[$i + 1] ?? '') === '{') {
                $depth++;
                $i += 2;
                continue;
            }
            if ($depth > 0 && $c === '}') {
                $depth--;
            }
            $i++;
        }
        return substr($s, $start, $i - $start);
    }

    private function readRegex(string $s, int &$i): ?string
    {
        $len     = strlen($s);
        $j       = $i + 1;
        $inClass = false;
        $closed  = false;
        while ($j < $len) {
            $c = $s[$j];
            if ($c === '\\') {
                $j += 2;
                continue;
            }
            if ($c === "\n") {
                return null;
            }
            if ($c === '[') {
                $inClass = true;
            } elseif ($c === ']') {
                $inClass = false;
            } elseif ($c === '/' && ! $inClass) {
                $j++;
                $closed = true;
                break;
            }
            $j++;
        }
        if (! $closed || $j > $len) {
            return null;
        }
        while ($j < $len && ctype_alpha($s[$j])) {
            $j++;
        }
        $regex = substr($s, $i, $j - $i);
        $i = $j;
        return $regex;
    }

    /**
     * @param string $prev   character emitted before $last
     * @param string $word   trailing identifier, empty when $last is not a word character
     * @param string $before character preceding $word
     */
    private function regexAllowed(string $last, string $prev, string $word, string $before): bool
    {
        if ($last === '') {
            return true;
        }
        if ($word !== '') {
            // '.' means a property named like a keyword, so still an operand.
            return $before !== '.' && in_array($word, self::REGEX_KEYWORDS, true);
        }
        if (($last === '+' && $prev === '+') || ($last === '-' && $prev === '-')) {
            return false;
        }
        return ! ($last === ')' || $last === ']' || $last === '}');
    }
}
