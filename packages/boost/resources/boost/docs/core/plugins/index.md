---
order: 10
summary: "When a plugin is the right shape, what it may do, the contract it implements, and the order its lifecycle runs in."
---

# Plugins

A plugin groups reusable setup in one place: macros, type registries, query
pipes, hook callbacks, default configuration and package integration. It is the
supported way for an application or a companion package to change what the
framework does — and the first question on this page is whether you need one at
all, because a closure, an event or a macro is often the smaller answer.

For a single table, form, or action, prefer the public fluent API first. Use a plugin when the same behavior should be installed once and reused across multiple components, projects, or packages.

## When To Use A Plugin

| Need | Prefer |
|------|--------|
| Change one table query | `Table::modifyQueryUsing()` |
| Add one form save callback | Form lifecycle callbacks |
| Add one action behavior | Action fluent API |
| Reuse a table/action macro everywhere | Plugin `boot()` |
| Add the same table button to many tables | Plugin table macro that merges actions |
| Add a query rule to many tables | Plugin query pipe or `table.querying` hook |
| Add a column or filter to a table you do not own | `table.composing` hook, scoped with `for:` |
| Add a field to a form you do not own | `form.configuring` hook, scoped with `for:` |
| Share a custom column/filter/action class by name | Plugin type registry |
| Build a companion package | Plugin plus package service provider |
| Add audit, telemetry, tenant scope, or policy integration | Plugin hooks |

## Hook, Event, Macro Or Callback

Four ways to change behaviour exist in this stack, and the one to reach for is decided by a single question: **who holds the reference to the component?**

| You… | Reach for | Can it change the value? |
|---|---|---|
| build the component yourself | the fluent API — `modifyQueryUsing()`, `beforeSave()`, `afterSave()`, an action's callbacks | yes |
| want new vocabulary on a class you did not write, used where you build | a **macro** on `Table`, `Form`, `Column`, `Field`, `Filter` or `Action` | yes |
| must change a component you never see — every table in an application, or one a package ships | a **hook** | yes |
| only need to know something happened | a Laravel **event** — `TableSearched`, `CellUpdated`, `ActionExecuted`, `RecordCreated` | **no**, by design |

Where both a hook and an event exist for one moment — `action.executing` and `ActionExecuting` fire ten lines apart — the event is the observation half. Audit trails, telemetry and metrics belong there; changing what runs belongs in the hook.

The practical case for the third row is a [module](../../panels/modules.md) installed from a package: its list is built inside code the application does not own, so a column is added by a `table.composing` hook rather than by subclassing the resource — which would collide on its key anyway.

## What A Plugin Can Do

| Capability | API |
|------------|-----|
| Register a plugin instance | `PluginManager::register()` |
| Run startup code after all plugins are registered | `Plugin::boot()` |
| Add macros to a shipped class | Laravel `Macroable` on `Table`, `Form`, `Column`, `Field`, `Filter` and `Action` |
| Register query pipes | `PluginManager::addQueryPipe()` |
| Register column classes by name | `PluginManager::addColumnType()` |
| Register filter classes by name | `PluginManager::addFilterType()` |
| Register action classes by name | `PluginManager::addActionType()` |
| Register hook callbacks | `PluginManager::hook()` |
| Scope a hook callback to one component | `PluginManager::hook(..., for: 'invoices')` |
| Run array payload hooks | `PluginManager::runHook()` |
| Run object payload hooks | `PluginManager::runTypedHook()` |
| Read merged plugin config | `PluginManager::getPluginConfig()` |

## Quick Start

Create a plugin class:

```php
<?php

namespace App\Wire\Plugins;

use Illuminate\Database\Eloquent\Builder;
use NyonCode\WireCore\Core\Plugin\Contracts\Plugin;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireTable\Table;

final class TenantPlugin implements Plugin
{
    public function getId(): string
    {
        return 'tenant';
    }

    public function register(PluginManager $manager): void
    {
        //
    }

    public function boot(PluginManager $manager): void
    {
        Table::macro('tenantScoped', function (?int $tenantId = null): static {
            $tenantId ??= auth()->user()?->tenant_id;

            return $this->modifyQueryUsing(
                fn (Builder $query) => $query->where('tenant_id', $tenantId)
            );
        });
    }
}
```

Register it in `config/wire-core.php`:

```php
'plugins' => [
    App\Wire\Plugins\TenantPlugin::class,
],
```

Use the macro from any table:

```php
public function table(Table $table): Table
{
    return $table
        ->model(Order::class)
        ->tenantScoped()
        ->columns([
            // ...
        ]);
}
```

## Plugin Contract

Every plugin implements `NyonCode\WireCore\Core\Plugin\Contracts\Plugin`.

```php
<?php

namespace App\Wire\Plugins;

use NyonCode\WireCore\Core\Plugin\Contracts\Plugin;
use NyonCode\WireCore\Core\Plugin\PluginManager;

final class ExamplePlugin implements Plugin
{
    public function getId(): string
    {
        return 'example';
    }

    public function register(PluginManager $manager): void
    {
        // Register hooks, query pipes, type aliases, or lightweight metadata.
    }

    public function boot(PluginManager $manager): void
    {
        // Register macros or resolve services after all plugins are registered.
    }
}
```

The `getId()` value must be unique. Registering two plugins with the same ID throws a `RuntimeException`.

## Lifecycle

| Step | Method | Use for |
|------|--------|---------|
| Registration | `register(PluginManager $manager)` | Hooks, query pipes, column/filter/action types, lightweight metadata |
| Boot | `boot(PluginManager $manager)` | Macros, resolved services, views, package setup that depends on the Laravel container |

`PluginManager::register()` calls the plugin's `register()` method immediately. `PluginManager::boot()` runs each plugin's `boot()` method once, and closes registration: a plugin offered afterwards is refused rather than accepted into a list nothing reads again.

Keep `register()` lightweight. Do not resolve request-scoped services or assume every Laravel service has already booted. Use `boot()` for work that needs the container, views, macros, or other registered plugins.

## PluginManager API

| Method | Description |
|--------|-------------|
| `register(Plugin $plugin): void` | Register a plugin and call its `register()` method |
| `boot(): void` | Boot every registered plugin once |
| `has(string $id): bool` | Check whether a plugin ID is registered |
| `get(string $id): ?Plugin` | Return a plugin by ID |
| `all(): array` | Return all registered plugins keyed by ID |
| `getPluginConfig(string $pluginId): array` | Return merged config for a configurable plugin |
| `addQueryPipe(string $name, QueryPipe $pipe): void` | Register a query pipe |
| `getQueryPipes(): array` | Return registered query pipes |
| `addColumnType(string $name, string $columnClass): void` | Register a column class alias |
| `getColumnTypes(): array` | Return column aliases |
| `addFilterType(string $name, string $filterClass): void` | Register a filter class alias |
| `getFilterTypes(): array` | Return filter aliases |
| `addActionType(string $name, string $actionClass): void` | Register an action class alias |
| `getActionTypes(): array` | Return action aliases |
| `hook(Hook\|string $name, callable $callback, int $priority = 0, ?string $for = null): void` | Register a hook callback, optionally scoped to one component |
| `runHook(Hook\|string $name, array $payload = [], ?HookTarget $target = null): array` | Run array hook callbacks and return the final payload |
| `runTypedHook(Hook\|string $name, object $payload): object` | Run object hook callbacks and return the final payload; a payload implementing `HasHookTarget` scopes them |
| `hasHook(Hook\|string $name): bool` | Check whether a hook has callbacks |

## Best Practices

- Use stable, lowercase plugin IDs such as `tenant`, `audit-export`, or `acme-billing`.
- Keep `register()` lightweight; do not resolve request-scoped services there.
- Put Laravel macros and service-dependent setup in `boot()`.
- Prefer table/form/action fluent APIs for one-off behavior.
- Return a payload array from array hook callbacks when you want to modify hook data.
- Use hook priorities sparingly and document why a callback must run early or late.
- Guard package registration with `PluginManager::has()` to avoid duplicate IDs.
- Treat type registries as metadata unless your package explicitly consumes them.

## In This Section

| Page | What it covers |
| --- | --- |
| [Registering Plugins](registration.md) | From config, from a package, with configuration and dependencies |
| [Hooks](hooks.md) | The hook system, scoping, and the typed hooks |
| [Extending Surfaces](extending.md) | Type registries, adding buttons and actions, query pipes |
| [Examples And Testing](examples.md) | Two worked plugins, and how to test one |

## Related

- [Modules](../../panels/modules.md) — a business area declared as a plugin
- [Foundation](../foundation/index.md) — the concerns a plugin registers into
- [Configuration](../../start/configuration.md) — where `plugins` is declared
- [Custom Fields](../../forms/custom-fields.md) — the other extension path, for one component
