<?php

declare(strict_types=1);

namespace WPP\Foundation\Container;

use Closure;
use RuntimeException;

/**
 * Explicit service container: no auto-wiring, no annotations.
 * Cross-module runtime comms flow through WP hooks, not the container.
 * Bind by class-string; domain classes receive deps via constructor.
 */
final class Container
{
    /** @var array<string, Closure> */
    private array $bindings = [];

    /** @var array<string, object> */
    private array $instances = [];

    /** Factory receives the container so it can resolve collaborators. */
    public function bind(string $id, Closure $factory): void
    {
        $this->bindings[$id] = $factory;
        unset($this->instances[$id]);
    }

    /** Register an already-constructed instance. */
    public function instance(string $id, object $instance): void
    {
        $this->instances[$id] = $instance;
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }

    /**
     * @template T of object
     * @param class-string<T>|string $id
     * @return T|object
     */
    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (! isset($this->bindings[$id])) {
            throw new RuntimeException("WP Performance container: no binding registered for {$id}");
        }

        $instance = ($this->bindings[$id])($this);
        $this->instances[$id] = $instance;

        return $instance;
    }
}
