---
order: 60
---

# Core Plugins

Wire Core includes a plugin API for application-level extensions and companion packages. A plugin groups reusable setup in one place: macros, type registries, query pipes, hook callbacks, default configuration, and package integration.

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
| want new vocabulary on a class you did not write, used where you build | a **macro** on `Table` or `Action` | yes |
| must change a component you never see — every table in an application, or one a package ships | a **hook** | yes |
| only need to know something happened | a Laravel **event** — `TableSearched`, `CellUpdated`, `ActionExecuted`, `RecordCreated` | **no**, by design |

Where both a hook and an event exist for one moment — `action.executing` and `ActionExecuting` fire ten lines apart — the event is the observation half. Audit trails, telemetry and metrics belong there; changing what runs belongs in the hook.

The practical case for the third row is a [domain module](modules.md) installed from a package: its list is built inside code the application does not own, so a column is added by a `table.composing` hook rather than by subclassing the resource — which would collide on its key anyway.

## What A Plugin Can Do

| Capability | API |
|------------|-----|
| Register a plugin instance | `PluginManager::register()` |
| Run startup code after all plugins are registered | `Plugin::boot()` |
| Add table/action macros | Laravel `Macroable` classes such as `Table` and `Action` |
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

## Register Plugins In Config

Publish the core config:

```bash
php artisan vendor:publish --tag=wire-core::config
```

Add plugin classes to `config/wire-core.php`:

```php
'plugins' => [
    App\Wire\Plugins\TenantPlugin::class,
    App\Wire\Plugins\AuditExportPlugin::class,
],
```

Wire resolves config-registered plugins through Laravel's container when the plugin manager is resolved.

An entry that cannot be a plugin is refused, not skipped:

| In `plugins` | What happens |
|---|---|
| A class implementing `Plugin` | Registered |
| `''` — what a trailing comma leaves behind | Skipped |
| A class name that does not exist, or a class without the contract | `PluginRegistrationException`, naming the class and which of the two it is |
| Anything that is not an array | `PluginRegistrationException` |

The skip these replace was the expensive one: a typo in a class name meant the plugin — a whole [domain module](modules.md), for the axis that registers this way — simply did not exist, with no menu entry and nothing anywhere saying why. The config is read at boot, so a refusal cannot reach a request.

## Register Plugins From A Package

If you are building a companion package, register your plugin from the package service provider.

```php
use Illuminate\Support\ServiceProvider;
use NyonCode\WireCore\Core\Plugin\PluginManager;

final class AcmeWireServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->resolving(PluginManager::class, function (PluginManager $manager) {
            if (! $manager->has('acme')) {
                $manager->register($this->app->make(AcmePlugin::class));
            }
        });
    }
}
```

The `has()` guard prevents duplicate registration if the application also lists the plugin in config.

**`register()`, never `boot()`.** `resolving` fires while the container builds the manager, so the plugin is in the list before `PluginManager::boot()` runs and before the core provider spreads a module's declarations into the registries. Registering later throws `PluginRegistrationException`:

```php
public function boot(): void
{
    // Too late — and it used to be silent.
    $this->app->make(PluginManager::class)->register(new AcmePlugin);
}
```

The registration itself would have succeeded and `has('acme')` would answer `true`, which is what made it worth refusing: `boot()` is never called on a plugin that arrives then, and a module's resources, dashboards and navigation group never reach the registries. It cannot be fixed by booting the late plugin either — page routes are registered inside the provider's `boot()`, because Laravel installs a cached route collection after that, so a module arriving later cannot be routed at all.

Do not defer a provider that registers a plugin. A deferred provider runs when something it provides is resolved, and by then the manager is built, so the `resolving` callback never fires and the plugin is never registered.

## Plugin Configuration

Plugins that accept user options can implement `HasConfiguration`.

```php
<?php

namespace App\Wire\Plugins;

use NyonCode\WireCore\Core\Plugin\Contracts\HasConfiguration;
use NyonCode\WireCore\Core\Plugin\Contracts\Plugin;
use NyonCode\WireCore\Core\Plugin\PluginManager;

final class ExportPlugin implements HasConfiguration, Plugin
{
    public function getId(): string
    {
        return 'export';
    }

    public function defaultConfig(): array
    {
        return [
            'format' => 'csv',
            'chunk_size' => 500,
        ];
    }

    public function register(PluginManager $manager): void
    {
        //
    }

    public function boot(PluginManager $manager): void
    {
        $config = $manager->getPluginConfig($this->getId());

        // $config is the merged default and user configuration.
    }
}
```

User overrides live under `wire-core.plugins.config.{pluginId}`:

```php
'plugins' => [
    App\Wire\Plugins\ExportPlugin::class,

    'config' => [
        'export' => [
            'format' => 'xlsx',
        ],
    ],
],
```

The manager merges the plugin defaults with user config using `array_merge()`. Top-level keys from user config replace default keys.

## Plugin Dependencies

Plugins that require other plugins can implement `HasDependencies`.

```php
<?php

namespace App\Wire\Plugins;

use NyonCode\WireCore\Core\Plugin\Contracts\HasDependencies;
use NyonCode\WireCore\Core\Plugin\Contracts\Plugin;
use NyonCode\WireCore\Core\Plugin\PluginManager;

final class BillingExportPlugin implements HasDependencies, Plugin
{
    public function getId(): string
    {
        return 'billing-export';
    }

    public function dependencies(): array
    {
        return ['export'];
    }

    public function register(PluginManager $manager): void
    {
        //
    }

    public function boot(PluginManager $manager): void
    {
        //
    }
}
```

Dependencies must already be registered. If a dependency is missing, `PluginManager::register()` throws a `RuntimeException`.

Register dependent plugins after their dependencies:

```php
'plugins' => [
    App\Wire\Plugins\ExportPlugin::class,
    App\Wire\Plugins\BillingExportPlugin::class,
],
```

## Hook System

Hooks let plugins and application code communicate through named callbacks.

```php
public function register(PluginManager $manager): void
{
    $manager->hook('orders.exporting', function (array $payload): array {
        $payload['query']->where('tenant_id', auth()->user()->tenant_id);

        return $payload;
    });
}
```

Run the hook from your own service or component:

```php
use NyonCode\WireCore\Core\Plugin\PluginManager;

$payload = app(PluginManager::class)->runHook('orders.exporting', [
    'query' => Order::query(),
]);

$query = $payload['query'];
```

A hook only affects runtime behavior when some code calls `runHook()` or `runTypedHook()` for that hook name. Registering a hook stores the callback; it does not automatically patch table, form, or action behavior.

### Shipped Hooks

`Hook` is the canonical spelling of every name below, and a plain string is always accepted in its place — `Hook::TableComposing` and `'table.composing'` are the same name.

| Hook | Runs | Changes |
|---|---|---|
| `Hook::TableComposing` | once, when a host has composed its table | the table itself — columns and filters as rendered, searched and sorted |
| `Hook::TableConfiguring` | inside the query service, per query | what the planner is about to read |
| `Hook::TableQuerying` | after the plan is built, before it runs | the query, and a forced sort |
| `Hook::TableQueried` | after every pipe has applied | nothing — observation |
| `Hook::FormConfiguring` | once, when a schema becomes a config | the schema |
| `Hook::FormSaving` | before validated data is persisted | the data |
| `Hook::FormSaved` | after the record exists | nothing — observation |
| `Hook::ActionExecuting` | before the action pipeline | the context |
| `Hook::ActionExecuted` | after it completes | nothing — observation |

**`table.composing` and `table.configuring` are not two names for one moment.** Configuring runs inside `TableQueryService`, on the arrays the planner is about to consume, so a column added there is searched and sorted on and **never rendered**. Composing runs on the table instance the host built, so a column added there is a column the user sees. To add a column, reach for `TableComposing`; to steer a query, `TableConfiguring` or `TableQuerying`.

`FormConfiguring` and `TableComposing` are typed-only: they take a payload object through `runTypedHook()` and have no array counterpart. The seven older hooks are dispatched both ways for backwards compatibility, and each callback belongs to exactly one dispatcher — see [Which Dispatcher Gets Your Callback](#which-dispatcher-gets-your-callback).

### Scoping A Hook To One Component

An unscoped callback runs for every table, form or action in the application. `for:` narrows it to one — by the registered key of the resource a page shows, by the host component's class, or by the model:

```php
$manager->hook(Hook::TableComposing, $addColumn, for: 'invoices');            // one resource
$manager->hook(Hook::FormConfiguring, $addField, for: Invoice::class);        // one model
$manager->hook(Hook::TableComposing, $addColumn, for: ListInvoices::class);   // one page
```

This is what makes an installed [domain module](modules.md) adjustable: its list is built inside code you do not own, so the key it registered under is the handle you have on it. A page shows its key because it implements `IdentifiesHookTarget` — every resource page does; a standalone component shows none and is scoped by class or model instead.

A scoped callback is skipped where a dispatch carries no target at all, including hooks your own code dispatches without one. Running a callback written for one module against a component it has never seen is the worse of the two mistakes.

### Hook Return Values

Array hooks receive the current payload array.

| Callback return | Result |
|-----------------|--------|
| `array` | Replaces the payload for the next callback |
| `null` or another non-array value | Keeps the current payload unchanged |
| exception | Bubbles up to the caller |

### Hook Priority

Callbacks run by ascending priority. Lower numbers run earlier.

```php
public function register(PluginManager $manager): void
{
    $manager->hook('table.querying', fn (array $payload) => $payload, priority: -100);
    $manager->hook('table.querying', fn (array $payload) => $payload);
    $manager->hook('table.querying', fn (array $payload) => $payload, priority: 100);
}
```

Suggested ranges:

| Priority | Use for |
|----------|---------|
| `-100` | Security, tenancy, scoping |
| `0` | Normal feature behavior |
| `100` | Audit, logging, telemetry |

Callbacks with the same priority keep their registration order.

### Runtime Hooks

These hooks are emitted by the current packages:

| Hook | Package | When | Payload | Consumes returned payload |
|------|---------|------|---------|---------------------------|
| `table.querying` | Table | Before the table query is planned | `table`, `columns`, `filters`, `sort_column`, `sort_direction`, `search` | Yes, reads `force_sort_column` and `force_sort_direction` |
| `form.saving` | Forms | After mutation and before persistence | `config`, `data` | Yes, reads modified `data` |
| `form.saved` | Forms | After persistence and relationship save | `config`, `record` | No |
| `action.executing` | Table | Before the action pipeline runs | `action`, `actionName`, `actionType`, `recordIds`, `data`, `component` | No |
| `action.executed` | Table | After the action pipeline runs | `action`, `actionName`, `actionType`, `recordIds`, `result`, `component` | No |

The plugin manager does not enforce hook names. For application hooks, use names that describe your boundary, such as `orders.exporting`, `orders.exported`, `billing.invoice.saving`, or `crm.customer.synced`.

### Example: Force Table Sort In A Hook

The sortable package uses `table.querying` to force a sort while a table is in reorder mode. The same pattern works for application-specific query rules.

```php
public function register(PluginManager $manager): void
{
    $manager->hook('table.querying', function (array $payload): array {
        $table = $payload['table'] ?? null;

        if (! $table instanceof OrdersTable) {
            return $payload;
        }

        $payload['force_sort_column'] = 'position';
        $payload['force_sort_direction'] = 'asc';

        return $payload;
    }, priority: -100);
}
```

Use `modifyQueryUsing()` when you only need to change one table. Use `table.querying` when the rule belongs to a reusable integration.

## Typed Hooks

`runTypedHook()` is available for extension points that prefer object payloads instead of arrays.

```php
final class ExportingOrders
{
    public function __construct(
        public Builder $query,
        public string $format,
    ) {}
}

$payload = app(PluginManager::class)->runTypedHook(
    'orders.exporting',
    new ExportingOrders(Order::query(), 'csv')
);
```

Callbacks receive the payload object. Returning an object replaces the payload for the next callback; returning `null` or another non-object keeps the current payload.

```php
$manager->hook('orders.exporting', function (ExportingOrders $payload): ExportingOrders {
    $payload->query->where('tenant_id', auth()->user()->tenant_id);

    return $payload;
});
```

Core also ships typed payload DTOs under `NyonCode\WireCore\Core\Plugin\Hooks` for common table, form, and action hook shapes — and the runtime **already dispatches them**. Every built-in lifecycle point runs both dispatchers back to back: `table.configuring`, `table.querying` and `table.queried` from `TableQueryService`, `form.saving` and `form.saved` from the save handler, `action.executing` and `action.executed` from the action runtime. So a callback on any of those hooks can take `TableQueryingPayload`, `FormSavingPayload`, `ActionExecutingPayload` and the rest directly.

### Which Dispatcher Gets Your Callback

Because both dispatchers run at every lifecycle point, each callback must belong to exactly one of them, and **the first parameter's type hint is what decides**:

| First parameter | Dispatcher | Payload |
| --- | --- | --- |
| `array $payload` | `runHook()` | the array |
| a DTO, or any other type hint | `runTypedHook()` | the object |
| **no type hint, or no parameter** | `runHook()` | the array |

Type-hint it. An unhinted callback is treated as the array form for backwards compatibility, which means it silently never sees the typed payload:

```php
// Runs on the array dispatch only — $payload is an array.
$manager->hook('form.saving', function ($payload) { /* … */ });   // [tl! --]
// Says which payload it wants, and gets it.
$manager->hook('form.saving', function (FormSavingPayload $payload): FormSavingPayload { // [tl! ++]
    $payload->data['audited_at'] = now();                                                // [tl! ++]
                                                                                          // [tl! ++]
    return $payload;                                                                      // [tl! ++]
});                                                                                       // [tl! ++]
```

## Column, Filter, And Action Type Registries

Plugins can register class aliases for plugin-aware builders, admin tooling, schema importers, or package integrations.

```php
public function register(PluginManager $manager): void
{
    $manager->addColumnType('money', \App\Tables\Columns\MoneyColumn::class);
    $manager->addFilterType('date-range', \App\Tables\Filters\DateRangeFilter::class);
    $manager->addActionType('workflow', \App\Tables\Actions\WorkflowAction::class);
}
```

Read the registries from the manager:

```php
$columns = app(PluginManager::class)->getColumnTypes();
$filters = app(PluginManager::class)->getFilterTypes();
$actions = app(PluginManager::class)->getActionTypes();
```

Wire Table components still accept normal instances directly:

```php
return $table
    ->columns([
        MoneyColumn::make('total'),
    ])
    ->filters([
        DateRangeFilter::make('created_at'),
    ]);
```

Type registries are metadata registries. They do not automatically render a column, filter, or action by alias unless your own builder or package consumes the registry.

## Adding Buttons And Actions

Most buttons in Wire tables are actions:

| UI placement | Class/API |
|--------------|-----------|
| Row button | `Action` in `Table::actions()` |
| Bulk toolbar button | `BulkAction` in `Table::bulkActions()` |
| Header toolbar button | `HeaderAction` in `Table::headerActions()` |
| Button inside a table cell | `ButtonColumn` in `Table::columns()` |
| Plain Blade button | `<x-wire::button>` |

Plugins do not automatically inject buttons into every table. The usual pattern is to register a table macro in `boot()` and let each table opt in. The macro should merge with existing actions instead of replacing them.

### Header Button Macro

```php
use App\Services\InvoiceExportService;
use NyonCode\WireCore\Actions\HeaderAction;
use NyonCode\WireCore\Core\Plugin\Contracts\Plugin;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireTable\Table;

final class BillingPlugin implements Plugin
{
    public function getId(): string
    {
        return 'billing';
    }

    public function register(PluginManager $manager): void
    {
        //
    }

    public function boot(PluginManager $manager): void
    {
        Table::macro('withInvoiceExportButton', function (): static {
            return $this->headerActions([
                ...$this->getHeaderActions(),

                HeaderAction::make('export-invoices')
                    ->label('Export invoices')
                    ->icon('download')
                    ->action(fn () => app(InvoiceExportService::class)->queue()),
            ]);
        });
    }
}
```

Use the button on the tables that need it:

```php
public function table(Table $table): Table
{
    return $table
        ->model(Invoice::class)
        ->withInvoiceExportButton()
        ->columns([
            // ...
        ]);
}
```

### Row Button Macro

```php
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireTable\Table;

Table::macro('withAuditTrailButton', function (): static {
    return $this->actions([
        ...$this->getActions(),

        Action::make('audit-trail')
            ->label('Audit')
            ->icon('history')
            ->url(fn ($record) => route('audit.show', [
                'type' => get_class($record),
                'id' => $record->getKey(),
            ])),
    ]);
});
```

### Bulk Button Macro

```php
use Illuminate\Support\Collection;
use NyonCode\WireCore\Actions\BulkAction;
use NyonCode\WireTable\Table;

Table::macro('withBulkArchiveButton', function (): static {
    return $this->bulkActions([
        ...$this->getBulkActions(),

        BulkAction::make('archive-selected')
            ->label('Archive selected')
            ->icon('archive')
            ->requiresConfirmation()
            ->action(fn (Collection $records) => $records->each->archive()),
    ]);
});
```

### Cell Button Column Macro

Use `ButtonColumn` when the button is part of each row's visible columns rather than the row action area.

```php
use NyonCode\WireTable\Columns\ButtonColumn;
use NyonCode\WireTable\Table;

Table::macro('withPreviewButtonColumn', function (): static {
    return $this->columns([
        ...$this->getColumns(),

        ButtonColumn::make('preview')
            ->buttonIcon('eye')
            ->buttonLabel('Preview')
            ->actionUrl(fn ($record) => route('records.preview', $record)),
    ]);
});
```

Prefer actions for commands. Use `ButtonColumn` when the button needs to sit among other columns or when its state is naturally column-like. For cell links, use `actionUrl()`. For cell Livewire calls, use `livewireAction()` and implement that method on the Livewire table component.

## Query Pipes

Plugins can register query pipe instances with the manager. Table query execution appends plugin pipes after the default query pipeline.

```php
use Closure;
use Illuminate\Database\Eloquent\Builder;
use NyonCode\WireCore\Core\Query\Contracts\QueryPipe;
use NyonCode\WireCore\Core\Query\QueryPlan;

final class ApplyTenantScope implements QueryPipe
{
    public function handle(Builder $builder, QueryPlan $plan, Closure $next): Builder
    {
        $builder->where('tenant_id', auth()->user()->tenant_id);

        return $next($builder, $plan);
    }
}
```

Register it from the plugin:

```php
public function register(PluginManager $manager): void
{
    $manager->addQueryPipe('tenant', new ApplyTenantScope());
}
```

Retrieve registered pipes for a custom query executor:

```php
$pipes = app(PluginManager::class)->getQueryPipes();
```

Default table query pipe order:

| Order | Pipe |
|-------|------|
| 1 | `ApplyScopes` |
| 2 | `ApplySoftDeletes` |
| 3 | `ApplyRelations` |
| 4 | `ApplySearch` |
| 5 | `ApplyFilters` |
| 6 | `ApplySorting` |
| 7 | `ApplyAggregates` |
| 8 | `ApplyEagerLoads` |
| 9+ | Plugin pipes |

Use table `modifyQueryUsing()` when the change belongs to one table. Use a query pipe when you are building reusable query behavior that should run as part of the shared query planner/executor pipeline.

## Practical Example: Action Preset

Actions are macroable through their base action class. This plugin adds a reusable admin-only preset.

```php
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Core\Plugin\Contracts\Plugin;
use NyonCode\WireCore\Core\Plugin\PluginManager;

final class AdminActionPlugin implements Plugin
{
    public function getId(): string
    {
        return 'admin-actions';
    }

    public function register(PluginManager $manager): void
    {
        //
    }

    public function boot(PluginManager $manager): void
    {
        Action::macro('adminOnly', function (): static {
            return $this->authorizeUsing(
                fn ($user) => method_exists($user, 'isAdmin') && $user->isAdmin()
            );
        });
    }
}
```

Use it on an action:

```php
Action::make('impersonate')
    ->label('Impersonate')
    ->adminOnly()
    ->requiresConfirmation()
    ->action(fn (User $record) => auth()->user()->impersonate($record));
```

## Practical Example: Form Audit

This plugin adds a small audit hook around form persistence.

```php
use NyonCode\WireCore\Core\Plugin\Contracts\Plugin;
use NyonCode\WireCore\Core\Plugin\PluginManager;

final class FormAuditPlugin implements Plugin
{
    public function getId(): string
    {
        return 'form-audit';
    }

    public function register(PluginManager $manager): void
    {
        $manager->hook('form.saving', function (array $payload): array {
            $payload['data']['updated_by'] ??= auth()->id();

            return $payload;
        });

        $manager->hook('form.saved', function (array $payload): void {
            logger()->info('Form saved', [
                'record' => $payload['record'] ?? null,
            ]);
        }, priority: 100);
    }

    public function boot(PluginManager $manager): void
    {
        //
    }
}
```

`form.saving` can modify the data that will be persisted. `form.saved` is observational in the current runtime because the save handler does not consume its returned payload.

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

## Testing Plugins

Test plugin behavior by instantiating `PluginManager` directly.

```php
use NyonCode\WireCore\Core\Plugin\PluginManager;

it('registers tenant plugin', function () {
    $manager = new PluginManager();
    $plugin = new TenantPlugin();

    $manager->register($plugin);

    expect($manager->has('tenant'))->toBeTrue();
});
```

For macros, boot the plugin first:

```php
it('adds tenant table macro', function () {
    $manager = new PluginManager();
    $plugin = new TenantPlugin();

    $manager->register($plugin);
    $manager->boot();

    expect(\NyonCode\WireTable\Table::hasMacro('tenantScoped'))->toBeTrue();
});
```

For hook behavior, run the hook with the payload your runtime code emits:

```php
it('adds updated_by before form save', function () {
    $manager = new PluginManager();
    $plugin = new FormAuditPlugin();

    $manager->register($plugin);

    $payload = $manager->runHook('form.saving', [
        'data' => ['name' => 'Jane'],
    ]);

    expect($payload['data'])->toHaveKey('updated_by');
});
```

## Best Practices

- Use stable, lowercase plugin IDs such as `tenant`, `audit-export`, or `acme-billing`.
- Keep `register()` lightweight; do not resolve request-scoped services there.
- Put Laravel macros and service-dependent setup in `boot()`.
- Prefer table/form/action fluent APIs for one-off behavior.
- Return a payload array from array hook callbacks when you want to modify hook data.
- Use hook priorities sparingly and document why a callback must run early or late.
- Guard package registration with `PluginManager::has()` to avoid duplicate IDs.
- Treat type registries as metadata unless your package explicitly consumes them.
