<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Plugin;

use NyonCode\WireCore\Core\Plugin\Contracts\HasConfiguration;
use NyonCode\WireCore\Core\Plugin\Contracts\HasDependencies;
use NyonCode\WireCore\Core\Plugin\Contracts\HasHookTarget;
use NyonCode\WireCore\Core\Plugin\Contracts\Plugin;
use NyonCode\WireCore\Core\Query\Contracts\QueryPipe;
use NyonCode\WireCore\Exceptions\PluginRegistrationException;
use NyonCode\WireCore\Foundation\Enums\Hook;
use ReflectionFunction;

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

    /** @var array<string, array<int, array{callback: callable, priority: int, expectsArray: bool, for: string|null}>> */
    private array $hooks = [];

    /**
     * Whether the mis-hint warning should be produced at all, resolved once.
     *
     * Every correctly hinted callback is skipped by the *other* dispatcher, so
     * this question is asked once per callback per lifecycle point — and the
     * answer cannot change inside a request. Left unresolved it was two
     * container lookups each time, to decide not to log.
     */
    private ?bool $warningsEnabled = null;

    /** @var array<string, class-string> */
    private array $actionTypes = [];

    /** @var array<string, array<string, mixed>> */
    private array $pluginConfigs = [];

    private bool $booted = false;

    /**
     * Register a plugin.
     *
     * @throws PluginRegistrationException If the manager has booted, the ID is taken,
     *                                     or a dependency is missing
     */
    public function register(Plugin $plugin): void
    {
        $id = $plugin->getId();

        // Refused rather than accepted quietly, and checked before anything else
        // because it is the one failure that leaves the registry looking correct:
        // the plugin would be in the list, `has()` would answer true, and none of
        // the passes that read the list — boot(), and the provider spreading a
        // module's declarations into the registries — would ever see it again.
        if ($this->booted) {
            throw PluginRegistrationException::registeredAfterBoot($id);
        }

        if (isset($this->plugins[$id])) {
            throw PluginRegistrationException::alreadyRegistered($id);
        }

        if ($plugin instanceof HasDependencies) {
            foreach ($plugin->dependencies() as $dep) {
                if (! $this->has($dep)) {
                    throw PluginRegistrationException::missingDependency($id, $dep);
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
     * Available hooks: every case of {@see Hook}, which is also the canonical
     * spelling — `Hook::TableConfiguring` and `'table.configuring'` are the same
     * name, and a package may dispatch a name of its own that the enum does not
     * carry.
     *
     * `$for` scopes the callback to one component. It is matched against the
     * payload's {@see HookTarget} — a registered key, the host component's class,
     * or the model — so a callback written for one installed module does not run
     * for every table in the application:
     *
     * ```php
     * $manager->hook(Hook::TableComposing, $addColumn, for: 'invoices');
     * ```
     *
     * A scoped callback is skipped where the dispatch carries no target at all;
     * see `outOfScope()` for why that direction is the safe one.
     */
    public function hook(Hook|string $name, callable $callback, int $priority = 0, ?string $for = null): void
    {
        $name = Hook::name($name);

        // Which dispatcher owns this callback is a property of the callback, so
        // it is decided once, here. Deciding it at dispatch meant a
        // ReflectionFunction per callback per dispatcher on every lifecycle
        // point of every request — for an answer that cannot change after
        // registration.
        $this->hooks[$name][] = [
            'callback' => $callback,
            'priority' => $priority,
            'expectsArray' => $this->callbackExpectsArray($callback),
            'for' => $for,
        ];

        // Keep the list sorted by priority so runHook/runTypedHook never need to sort.
        usort(
            $this->hooks[$name],
            static fn (array $a, array $b): int => $a['priority'] <=> $b['priority'],
        );
    }

    /**
     * Run all array-based callbacks for a hook, sorted by priority.
     *
     * Callbacks can return a modified payload array to pass downstream.
     * Non-array returns are ignored (payload passes through unchanged).
     * Callbacks that type-hint an object parameter are skipped (they belong to runTypedHook).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed> Modified payload
     */
    public function runHook(Hook|string $name, array $payload = [], ?HookTarget $target = null): array
    {
        $name = Hook::name($name);
        $hooks = $this->hooks[$name] ?? [];

        if ($hooks === []) {
            return $payload;
        }

        foreach ($hooks as $hook) {
            if ($this->outOfScope($hook['for'], $target)) {
                continue;
            }

            if (! $hook['expectsArray']) {
                $this->warnSkippedCallback($name, $hook['callback'], 'runHook', 'runTypedHook');

                continue;
            }

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
     * Callbacks that type-hint an array parameter are skipped (they belong to runHook).
     *
     * @template T of object
     *
     * @param  T  $payload
     * @return T
     */
    public function runTypedHook(Hook|string $name, object $payload): object
    {
        $name = Hook::name($name);
        $hooks = $this->hooks[$name] ?? [];

        if ($hooks === []) {
            return $payload;
        }

        // A typed payload carries its own origin, so a scoped callback needs
        // nothing from the dispatch site beyond the payload it already builds.
        $target = $payload instanceof HasHookTarget ? $payload->hookTarget() : null;

        foreach ($hooks as $hook) {
            if ($this->outOfScope($hook['for'], $target)) {
                continue;
            }

            if ($hook['expectsArray']) {
                $this->warnSkippedCallback($name, $hook['callback'], 'runTypedHook', 'runHook');

                continue;
            }

            $result = ($hook['callback'])($payload);
            if ($result !== null && is_object($result)) {
                $payload = $result;
            }
        }

        return $payload;
    }

    /**
     * Log a debug warning when a callback is skipped because it was registered
     * against the wrong dispatcher (runHook vs runTypedHook).
     * Only logs in debug mode to keep production logs clean.
     */
    private function warnSkippedCallback(string $hook, callable $callback, string $calledVia, string $correctMethod): void
    {
        if (! $this->warningsEnabled()) {
            return;
        }

        try {
            $ref = new ReflectionFunction($callback(...));
            $location = $ref->getFileName().':'.$ref->getStartLine();
        } catch (\ReflectionException) {
            $location = 'unknown';
        }

        if (function_exists('logger')) {
            logger()->debug("[WireCore] Hook '{$hook}' callback skipped in {$calledVia}(). "
                ."Use {$correctMethod}() for this type hint. Registered at {$location}.");
        }
    }

    /**
     * Which of the two dispatchers owns this callback.
     *
     * Every lifecycle point dispatches **both** ways — `runHook()` for the array
     * payload and `runTypedHook()` for the DTO, back to back (TableQueryService,
     * SaveHandler, InteractsWithActions). That is correct only while each
     * callback belongs to exactly one of them, which is what this decides. It is
     * a *partition*, not two independent questions, so it is written once and the
     * other predicate is its negation — as two separate tests they answered "no"
     * in unison for a case neither had considered.
     *
     * That case was the unhinted callback. `function ($payload)` — and
     * `function ()` — matched neither "expects array" nor "expects object", so it
     * belonged to both and ran **twice per lifecycle point**: a counter or an
     * audit line booked double, and the ordinary `$payload['data']` shape died
     * outright on the second pass, because the DTOs are not ArrayAccess.
     *
     * It resolves to the **array** side, which is the deprecated one, and that is
     * deliberate rather than a concession. A callback written without a hint was
     * written when the array payload was the only payload — `docs/core/plugins.md`
     * said so — so handing it a DTO would break the very plugins the 2.x BC
     * promise covers. A callback that wants the typed payload names its type, and
     * naming it is what every documented example already does.
     */
    private function callbackExpectsArray(callable $callback): bool
    {
        $type = $this->getFirstParameterTypeName($callback);

        return $type === null || $type === 'array';
    }

    /**
     * Whether a mis-hint is worth reporting here — debug mode, in a booted app.
     *
     * `function_exists('config')` answers "is the framework autoloaded", which
     * is not the question: the helper exists as soon as Composer has seen it,
     * booted app or not, and reading through it then resolves out of an empty
     * container and throws. So the diagnostic for a hint mistake crashed rather
     * than reported it, in exactly the standalone context the manager is meant
     * to survive. Asking whether `config` is *bound* is the missing half.
     */
    private function warningsEnabled(): bool
    {
        return $this->warningsEnabled ??= function_exists('config')
            && app()->bound('config')
            && (bool) config('app.debug');
    }

    /**
     * Get the type name of a callback's first parameter, or null if untyped.
     */
    private function getFirstParameterTypeName(callable $callback): ?string
    {
        try {
            $ref = new ReflectionFunction($callback(...));
        } catch (\ReflectionException) {
            return null;
        }

        $params = $ref->getParameters();
        if ($params === []) {
            return null;
        }

        $type = $params[0]->getType();
        if ($type === null) {
            return null;
        }

        return $type instanceof \ReflectionNamedType ? $type->getName() : null;
    }

    /**
     * Check if any callbacks are registered for a hook.
     */
    public function hasHook(Hook|string $name): bool
    {
        return ! empty($this->hooks[Hook::name($name)]);
    }

    /**
     * Whether a scoped callback should sit this dispatch out.
     *
     * An unscoped callback always runs — that is every 2.x plugin, and the
     * default. A scoped one runs only where the target says it belongs, and a
     * dispatch that carries **no** target is not a match: the alternative is
     * running a callback written for one module against a component it has never
     * seen, which is the mutation nobody would look for. A dispatch site that
     * wants scoped callbacks to reach it passes a target; the shipped ones all do.
     */
    private function outOfScope(?string $for, ?HookTarget $target): bool
    {
        if ($for === null) {
            return false;
        }

        return $target === null || ! $target->matches($for);
    }
}
