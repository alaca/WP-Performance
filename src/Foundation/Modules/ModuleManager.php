<?php

declare(strict_types=1);

namespace WPP\Foundation\Modules;

use WPP\Foundation\Container\Container;
use RuntimeException;

/**
 * Registry and boot orchestrator for plugin modules.
 * Modules boot in registration order.
 */
final class ModuleManager
{
    /** @var array<string, Module> */
    private array $modules = [];

    /** @var array<string, bool> */
    private array $booted = [];

    public function __construct(private Container $container)
    {
    }

    public function register(Module $module): void
    {
        $id = $module->id();

        if (isset($this->modules[$id])) {
            throw new RuntimeException("WP Performance module '{$id}' is already registered.");
        }

        $this->modules[$id] = $module;
    }

    public function get(string $id): ?Module
    {
        return $this->modules[$id] ?? null;
    }

    /** @return array<string, Module> */
    public function all(): array
    {
        return $this->modules;
    }

    public function bootAll(): void
    {
        foreach ($this->modules as $id => $module) {
            if ($this->booted[$id] ?? false) {
                continue;
            }
            $module->boot($this->container);
            $this->booted[$id] = true;
            do_action('wpp.module.booted', $id);
        }
    }
}
