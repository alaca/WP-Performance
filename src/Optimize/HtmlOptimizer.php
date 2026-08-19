<?php

declare(strict_types=1);

namespace WPP\Optimize;

/**
 * HTML minification entry point, mapped to the plugin's html settings group.
 * Delegates to the dependency-free HtmlMinifier.
 */
final class HtmlOptimizer
{
    private HtmlMinifier $minifier;

    public function __construct()
    {
        $this->minifier = new HtmlMinifier();
    }

    /** @param array<string, mixed> $opt html settings group */
    public function optimize(string $html, array $opt): string
    {
        return $this->minifier->minify($html, $opt);
    }
}
