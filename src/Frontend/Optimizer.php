<?php

declare(strict_types=1);

namespace WPP\Frontend;

use WPP\Foundation\Hooks\HookProvider;
use WPP\Optimize\AssetParser;

/**
 * Runs the asset optimizer on the buffered front-end HTML via the
 * 'wpp.frontend.html' filter that PageCache applies before caching.
 */
final class Optimizer extends HookProvider
{
    public function __construct(private AssetParser $parser)
    {
    }

    protected function filters(): array
    {
        return ['wpp.frontend.html' => ['optimize', 20, 1]];
    }

    public function optimize(string $html): string
    {
        return $this->parser->parse($html);
    }
}
