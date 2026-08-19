<?php

declare(strict_types=1);

namespace WPP\Foundation\Modules;

use WPP\Foundation\Container\Container;

/**
 * A self-contained unit of functionality (core, or an add-on).
 * Modules plug into the same boot pipeline and may register services,
 * REST routes, hooks, and admin UI.
 */
interface Module
{
    /** Globally-unique identifier, e.g. 'core', 'cloudflare'. */
    public function id(): string;

    /** Bind services and register routes/hooks/admin pieces. */
    public function boot(Container $container): void;
}
