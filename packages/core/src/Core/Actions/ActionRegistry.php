<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Actions;

use Closure;

/**
 * Registry for named action handlers.
 */
final class ActionRegistry
{
    /**
     * @var array<string, Closure>
     */
    private array $actions = [];

    /**
     * Register an action handler by name.
     */
    public function register(string $name, Closure $handler): void
    {
        $this->actions[$name] = $handler;
    }

    /**
     * Determine if an action is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->actions[$name]);
    }

    /**
     * Get a registered action handler.
     */
    public function get(string $name): ?Closure
    {
        return $this->actions[$name] ?? null;
    }

    /**
     * Get all registered actions.
     *
     * @return array<string, Closure>
     */
    public function all(): array
    {
        return $this->actions;
    }

    /**
     * Remove a registered action.
     */
    public function remove(string $name): void
    {
        unset($this->actions[$name]);
    }
}
