<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Plugin;

use NyonCode\WireCore\Core\Plugin\Contracts\HasConfiguration;
use NyonCode\WireCore\Core\Plugin\Contracts\HasDependencies;
use NyonCode\WireCore\Core\Plugin\Contracts\Plugin;
use NyonCode\WireCore\Core\Query\Contracts\QueryPipe;
use RuntimeException;

/**
 * Central plugin manager for the Wire ecosystem.
 *
 * Manages plugin lifecycle (register → boot), and provides
 * extension points for query pipes, column types, filter types,
 * and custom hooks.
 */
final class PluginManager
{
    /** @var array<string, Plugin> */
    private array $plugins = [];

    /** @var array<string, QueryPipe> */
    private array $queryPipes = [];

    /** @var array<string, class-string> */
    private array $columnTypes = [];

    /** @var array<string, class-string> */
    private array $filterTypes = [];

    /** @var array<string, array<int, array{callback: callable, priority: int}>> */
    private array $hooks = [];

    /** @var array<string, class-string> */
    private array $actionTypes = [];

    /** @var array<string, array<string, mixed>> */
    private array $pluginConfigs = [];

    private bool $booted = false;

    /**
     * Register a plugin.
     *
     * @throws RuntimeException If a plugin with the same ID is already registered
     * @throws RuntimeException If a required dependency is not registered
     */
    public function register(Plugin $plugin): void
    {
        $id = $plugin->getId();

        if (isset($this->plugins[$id])) {
            throw new RuntimeException("Plugin '{$id}' is already registered.");
        }

        if ($plugin instanceof HasDependencies) {
            foreach ($plugin->dependencies() as $dep) {
                if (! $this->has($dep)) {
                    throw new RuntimeException(
                        "Plugin '{$id}' requires '{$dep}' which is not registered."
                    );
                }
            }
        }

        $this->plugins[$id] = $plugin;

        if ($plugin instanceof HasConfiguration) {
            $configKey = "wire-core.plugins.config.{$id}";
            $userConfig = function_exists('config') ? (config($configKey, []) ?: []) : [];
            $this->pluginConfigs[$id] = array_merge($plugin->defaultConfig(), $userConfig);
        }

        $plugin->register($this);
    }

    /**
     * Get merged configuration for a plugin.
     *
     * @return array<string, mixed>
     */
    public function getPluginConfig(string $pluginId): array
    {
        return $this->pluginConfigs[$pluginId] ?? [];
    }

    /**
     * Boot all registered plugins.
     *
     * Called once during the service provider boot phase.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->plugins as $plugin) {
            $plugin->boot($this);
        }

        $this->booted = true;
    }

    /**
     * Check if a plugin is registered.
     */
    public function has(string $id): bool
    {
        return isset($this->plugins[$id]);
    }

    /**
     * Get a registered plugin by ID.
     */
    public function get(string $id): ?Plugin
    {
        return $this->plugins[$id] ?? null;
    }

    /**
     * Get all registered plugins.
     *
     * @return array<string, Plugin>
     */
    public function all(): array
    {
        return $this->plugins;
    }

    // ─── Query Pipe Extension ─────────────────────────────────────

    /**
     * Register an additional query pipe.
     *
     * Pipes added here are appended to the default pipeline
     * when QueryExecutor is used through the plugin system.
     */
    public function addQueryPipe(string $name, QueryPipe $pipe): void
    {
        $this->queryPipes[$name] = $pipe;
    }

    /**
     * @return array<string, QueryPipe>
     */
    public function getQueryPipes(): array
    {
        return $this->queryPipes;
    }

    // ─── Column Type Extension ────────────────────────────────────

    /**
     * Register a custom column type.
     *
     * @param  class-string  $columnClass
     */
    public function addColumnType(string $name, string $columnClass): void
    {
        $this->columnTypes[$name] = $columnClass;
    }

    /**
     * @return array<string, class-string>
     */
    public function getColumnTypes(): array
    {
        return $this->columnTypes;
    }

    // ─── Filter Type Extension ────────────────────────────────────

    /**
     * Register a custom filter type.
     *
     * @param  class-string  $filterClass
     */
    public function addFilterType(string $name, string $filterClass): void
    {
        $this->filterTypes[$name] = $filterClass;
    }

    /**
     * @return array<string, class-string>
     */
    public function getFilterTypes(): array
    {
        return $this->filterTypes;
    }

    // ─── Action Type Extension ────────────────────────────────────

    /**
     * Register a custom action type.
     *
     * @param  class-string  $actionClass
     */
    public function addActionType(string $name, string $actionClass): void
    {
        $this->actionTypes[$name] = $actionClass;
    }

    /**
     * @return array<string, class-string>
     */
    public function getActionTypes(): array
    {
        return $this->actionTypes;
    }

    // ─── Hook System ──────────────────────────────────────────────

    /**
     * Register a hook callback.
     *
     * Priority determines execution order (lower = earlier):
     * - -100: security/scope (e.g. multi-tenancy)
     * -    0: default
     * +  100: audit/logging
     *
     * Available hooks:
     * - 'table.configuring'  — before table config is finalized
     * - 'table.querying'     — before query execution
     * - 'table.queried'      — after query execution
     * - 'form.saving'        — before form save
     * - 'form.saved'         — after form save
     * - 'action.executing'   — before action execution
     * - 'action.executed'    — after action execution
     */
    public function hook(string $name, callable $callback, int $priority = 0): void
    {
        $this->hooks[$name][] = ['callback' => $callback, 'priority' => $priority];
    }

    /**
     * Run all callbacks for a hook, sorted by priority.
     *
     * Callbacks can return a modified payload array to pass downstream.
     * Non-array returns are ignored (payload passes through unchanged).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed> Modified payload
     */
    public function runHook(string $name, array $payload = []): array
    {
        $hooks = $this->hooks[$name] ?? [];

        if ($hooks === []) {
            return $payload;
        }

        usort($hooks, static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);

        foreach ($hooks as $hook) {
            $result = ($hook['callback'])($payload);
            if (is_array($result)) {
                $payload = $result;
            }
        }

        return $payload;
    }

    /**
     * Run a typed hook with a payload DTO.
     *
     * Each callback receives the payload object and may return a modified instance.
     * If a callback returns null or a non-object, the original payload continues.
     *
     * @template T of object
     *
     * @param  T  $payload
     * @return T
     */
    public function runTypedHook(string $name, object $payload): object
    {
        $hooks = $this->hooks[$name] ?? [];

        if ($hooks === []) {
            return $payload;
        }

        usort($hooks, static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);

        foreach ($hooks as $hook) {
            $result = ($hook['callback'])($payload);
            if ($result !== null && is_object($result)) {
                $payload = $result;
            }
        }

        return $payload;
    }

    /**
     * Check if any callbacks are registered for a hook.
     */
    public function hasHook(string $name): bool
    {
        return ! empty($this->hooks[$name]);
    }
}
