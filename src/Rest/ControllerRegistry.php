<?php

declare(strict_types=1);

namespace WPP\Rest;

/**
 * Collector for add-on REST controllers. Any object exposing registerRoutes() qualifies.
 */
final class ControllerRegistry
{
    /** @var object[] */
    private array $controllers = [];

    public function add(object $controller): void
    {
        $this->controllers[] = $controller;
    }

    /** @return object[] */
    public function all(): array
    {
        return $this->controllers;
    }
}
