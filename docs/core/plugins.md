# Core Plugins

Wire Core includes a small plugin API for applications and companion packages that need reusable extension points. Plugins are useful when you want to register macros, shared presets, type registries, query pipes, or hook callbacks in one place.

For normal tables and forms, prefer the public fluent APIs first. Reach for a plugin when the same extension should be available across multiple components or projects.

## What A Plugin Can Do

| Capability | API |
|------------|-----|
| Register a plugin instance | `PluginManager::register()` |
| Run startup code after all plugins are registered | `Plugin::boot()` |
| Add table/action macros | Laravel `Macroable` classes such as `Table` and `Action` |
| Register query pipes | `PluginManager::addQueryPipe()` |
| Register column classes by name | `PluginManager::addColumnType()` |
| Register filter classes by name | `PluginManager::addFilterType()` |
| Register custom hook callbacks | `PluginManager::hook()` |
| Run custom hook callbacks | `PluginManager::runHook()` |

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

The `getId()` value must be unique. Registering two plugins with the same ID throws an exception.

## Lifecycle

| Step | Method | Use for |
|------|--------|---------|
| Registration | `register(PluginManager $manager)` | Add hooks, query pipes, column types, filter types |
| Boot | `boot(PluginManager $manager)` | Register macros, resolve services, perform setup that depends on the Laravel container |

`PluginManager::boot()` runs each plugin's `boot()` method once.

## Register Plugins In Config

Publish the core config:

```bash
php artisan vendor:publish --tag=wire-core-config
```

Add your plugin class to `config/wire-core.php`:

```php
'plugins' => [
    App\Wire\Plugins\TenantPlugin::class,
],
```

Wire resolves config-registered plugins through Laravel's container when the plugin manager is resolved.

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

## Practical Example: Tenant Table Macro

This plugin adds a reusable `tenantScoped()` table macro.

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

Use it in any table:

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

## Hook Registry

The hook registry lets plugins and your own application code communicate through named payload callbacks.

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

Hook callbacks run in registration order. A callback may return a modified payload array. If it returns `null` or another non-array value, the current payload is kept.

### Suggested Hook Names

Wire does not enforce hook names. Use names that describe your application boundary.

| Pattern | Example |
|---------|---------|
| Before an operation | `orders.exporting` |
| After an operation | `orders.exported` |
| Before saving | `orders.saving` |
| After saving | `orders.saved` |
| Before authorization | `orders.authorizing` |

Core also reserves broad ecosystem names such as `table.configuring`, `table.querying`, `table.queried`, `form.saving`, `form.saved`, `action.executing`, and `action.executed` for plugin-aware integrations.

Important: registering a hook only stores the callback. The hook affects runtime behavior only when some code calls `runHook()` for that hook name.

## Column And Filter Type Registries

Plugins can register class aliases for column and filter types.

```php
public function register(PluginManager $manager): void
{
    $manager->addColumnType('money', \App\Tables\Columns\MoneyColumn::class);
    $manager->addFilterType('date-range', \App\Tables\Filters\DateRangeFilter::class);
}
```

Read them from the manager when building plugin-aware tooling:

```php
$columns = app(PluginManager::class)->getColumnTypes();
$filters = app(PluginManager::class)->getFilterTypes();
```

Wire Table components still accept normal column and filter instances directly:

```php
return $table
    ->columns([
        MoneyColumn::make('total'),
    ])
    ->filters([
        DateRangeFilter::make('created_at'),
    ]);
```

## Query Pipes

Plugins can register query pipe instances with the manager.

```php
use NyonCode\WireCore\Core\Query\Contracts\QueryPipe;
use NyonCode\WireCore\Core\Query\QueryPlan;
use Closure;
use Illuminate\Database\Eloquent\Builder;

final class ApplyTenantScope implements QueryPipe
{
    public function handle(Builder $builder, QueryPlan $plan, Closure $next): Builder
    {
        $builder->where('tenant_id', auth()->user()->tenant_id);

        return $next($builder, $plan);
    }
}
```

Register it:

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

Use table `modifyQueryUsing()` when you only need to change one table query. Use query pipes when you are building a reusable query integration.

## PluginManager API

| Method | Description |
|--------|-------------|
| `register(Plugin $plugin): void` | Register a plugin and call its `register()` method |
| `boot(): void` | Boot every registered plugin once |
| `has(string $id): bool` | Check whether a plugin ID is registered |
| `get(string $id): ?Plugin` | Return a plugin by ID |
| `all(): array` | Return all registered plugins keyed by ID |
| `addQueryPipe(string $name, QueryPipe $pipe): void` | Register a query pipe |
| `getQueryPipes(): array` | Return registered query pipes |
| `addColumnType(string $name, string $columnClass): void` | Register a column class alias |
| `getColumnTypes(): array` | Return column aliases |
| `addFilterType(string $name, string $filterClass): void` | Register a filter class alias |
| `getFilterTypes(): array` | Return filter aliases |
| `hook(string $name, callable $callback): void` | Register a hook callback |
| `runHook(string $name, array $payload = []): array` | Run hook callbacks and return the final payload |
| `hasHook(string $name): bool` | Check whether a hook has callbacks |

## Testing Plugins

Test plugin behavior by instantiating `PluginManager` directly.

```php
use NyonCode\WireCore\Core\Plugin\PluginManager;

it('registers tenant hook', function () {
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

## Best Practices

- Use stable, lowercase plugin IDs such as `tenant`, `audit-export`, or `acme-billing`.
- Keep `register()` lightweight; do not resolve request-scoped services there.
- Put Laravel macros and service-dependent setup in `boot()`.
- Prefer table/form fluent APIs for one-off behavior.
- Return a payload array from hook callbacks when you want to modify hook data.
- Guard package registration with `PluginManager::has()` to avoid duplicate IDs.
