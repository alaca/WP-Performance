<?php

declare(strict_types=1);

namespace WPP\Foundation\Hooks;

/**
 * Base class for declarative WP hook attachment.
 * Subclasses override actions() and/or filters() and call register().
 */
abstract class HookProvider
{
    /**
     * Map of hook name to method name or [method, priority, args].
     *
     * @return array<string, string|array{0:string,1?:int,2?:int}>
     */
    protected function actions(): array
    {
        return [];
    }

    /** @return array<string, string|array{0:string,1?:int,2?:int}> */
    protected function filters(): array
    {
        return [];
    }

    public function register(): void
    {
        foreach ($this->actions() as $hook => $spec) {
            [$method, $priority, $args] = $this->normalize($spec);
            add_action($hook, [$this, $method], $priority, $args);
        }

        foreach ($this->filters() as $hook => $spec) {
            [$method, $priority, $args] = $this->normalize($spec);
            add_filter($hook, [$this, $method], $priority, $args);
        }
    }

    /** Normalise a hook spec to [method, priority, accepted_args]. */
    private function normalize(string|array $spec): array
    {
        if (is_string($spec)) {
            return [$spec, 10, 1];
        }
        return [$spec[0], $spec[1] ?? 10, $spec[2] ?? 1];
    }
}
