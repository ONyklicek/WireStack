# Plugin Development

The Wire plugin system allows extending tables, forms, queries, and other components through a unified registration and lifecycle model.

See [ADR 0014](../decisions/0014-plugin-architecture.md) for the decision record.

---

## Table of Contents

1. [Plugin Interface](#plugin-interface)
2. [Plugin Lifecycle](#plugin-lifecycle)
3. [Registering Plugins](#registering-plugins)
4. [Hook System](#hook-system)
5. [Custom Query Pipes](#custom-query-pipes)
6. [Custom Column Types](#custom-column-types)
7. [Custom Filter Types](#custom-filter-types)
8. [PluginManager API](#pluginmanager-api)
9. [Complete Example: Export Plugin](#complete-example-export-plugin)
10. [Complete Example: Audit Plugin](#complete-example-audit-plugin)
11. [Complete Example: Multi-Tenancy Plugin](#complete-example-multi-tenancy-plugin)
12. [Testing Plugins](#testing-plugins)

---

## Plugin Interface

Every plugin implements `NyonCode\WireCore\Core\Plugin\Contracts\Plugin`:

```php
namespace NyonCode\WireCore\Core\Plugin\Contracts;

use NyonCode\WireCore\Core\Plugin\PluginManager;

interface Plugin
{
    /**
     * Unique plugin identifier (e.g., 'export', 'audit', 'multi-tenancy').
     */
    public function getId(): string;

    /**
     * Register bindings, pipes, strategies, types.
     * Called during service provider register phase.
     * Do NOT resolve services here — they may not be available yet.
     */
    public function register(PluginManager $manager): void;

    /**
     * Boot the plugin after all plugins are registered.
     * Called during service provider boot phase.
     * Safe to resolve services, register views, publish assets.
     */
    public function boot(PluginManager $manager): void;
}
```

### Minimal Plugin

```php
namespace App\Wire\Plugins;

use NyonCode\WireCore\Core\Plugin\Contracts\Plugin;
use NyonCode\WireCore\Core\Plugin\PluginManager;

class MyPlugin implements Plugin
{
    public function getId(): string
    {
        return 'my-plugin';
    }

    public function register(PluginManager $manager): void
    {
        // Register extensions here
    }

    public function boot(PluginManager $manager): void
    {
        // Boot-time logic here
    }
}
```

---

## Plugin Lifecycle

```
1. Service Provider register()
   └── PluginManager::register($plugin)
       └── $plugin->register($manager)      ← register bindings, pipes, types

2. Service Provider boot()
   └── PluginManager::boot()
       └── foreach plugin: $plugin->boot($manager)  ← resolve services, register views
```

**Rules:**
- `register()` — register extensions (pipes, types, hooks). Do NOT resolve services from the container.
- `boot()` — safe to resolve services, register Blade views, publish config/assets.
- Plugin IDs must be unique. Duplicate registration throws `RuntimeException`.
- `boot()` is called exactly once. Subsequent calls are no-ops.

---

## Registering Plugins

### Via Config (recommended)

```php
// config/wire-core.php
return [
    'plugins' => [
        \App\Wire\Plugins\ExportPlugin::class,
        \App\Wire\Plugins\AuditPlugin::class,
    ],
];
```

The `WireCoreServiceProvider` automatically instantiates and registers these.

### Manual Registration

```php
// In a service provider
use NyonCode\WireCore\Core\Plugin\PluginManager;

public function register(): void
{
    $this->app->resolving(PluginManager::class, function (PluginManager $manager) {
        $manager->register(new ExportPlugin());
    });
}
```

### Checking Plugin State

```php
$manager = app(PluginManager::class);

$manager->has('export');              // bool
$manager->get('export');              // ?Plugin
$manager->all();                      // ['export' => Plugin, ...]
```

---

## Hook System

Hooks allow plugins to tap into key lifecycle events without modifying core code.

### Available Hooks

| Hook Name | When Fired | Payload |
|-----------|------------|---------|
| `table.configuring` | Before table config is finalized | `['table' => Table]` |
| `table.querying` | Before query execution | `['query' => Builder, 'plan' => QueryPlan]` |
| `table.queried` | After query execution | `['query' => Builder, 'results' => Collection]` |
| `form.saving` | Before form save | `['form' => Form, 'data' => array]` |
| `form.saved` | After form save | `['form' => Form, 'model' => Model]` |
| `action.executing` | Before action execution | `['action' => Action, 'context' => ActionContext]` |
| `action.executed` | After action execution | `['action' => Action, 'result' => ActionResult]` |

### Registering Hooks

```php
public function register(PluginManager $manager): void
{
    // Simple hook
    $manager->hook('table.querying', function (array $payload) {
        Log::debug('Query plan', ['plan' => $payload['plan']]);
        return $payload; // return modified payload (or null to keep unchanged)
    });

    // Hook that modifies payload
    $manager->hook('form.saving', function (array $payload) {
        $payload['data']['updated_by'] = auth()->id();
        return $payload;
    });

    // Multiple hooks on the same event (executed in registration order)
    $manager->hook('action.executed', function (array $payload) {
        if ($payload['result']->isSuccess()) {
            Audit::log($payload['action'], $payload['result']);
        }
        return $payload;
    });
}
```

### Hook Execution

Hooks execute sequentially in registration order. Each callback receives the payload array and can:
- Return a **modified array** to update the payload for subsequent hooks
- Return **null** to keep the payload unchanged
- Throw an exception to abort (use with care)

```php
// How hooks run internally
$payload = $manager->runHook('table.querying', [
    'query' => $builder,
    'plan' => $queryPlan,
]);
// $payload now contains any modifications from registered hooks
```

### Checking Hooks

```php
$manager->hasHook('table.querying'); // true if any callbacks registered
```

---

## Custom Query Pipes

Add custom query pipeline steps that execute alongside the built-in 8 pipes.

### Creating a Query Pipe

```php
namespace App\Wire\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use NyonCode\WireCore\Core\Query\Contracts\QueryPipe;
use NyonCode\WireCore\Core\Query\QueryPlan;

class TenantScopePipe implements QueryPipe
{
    public function handle(Builder $query, QueryPlan $plan, Closure $next): Builder
    {
        // Add tenant filtering to every query
        $query->where('tenant_id', auth()->user()->tenant_id);

        return $next($query);
    }
}
```

### Registering

```php
public function register(PluginManager $manager): void
{
    $manager->addQueryPipe('tenant-scope', new TenantScopePipe());
}
```

Plugin pipes are appended after the default 8 pipes in the QueryExecutor pipeline.

### Conditional Pipes

```php
class ConditionalPipe implements QueryPipe
{
    public function handle(Builder $query, QueryPlan $plan, Closure $next): Builder
    {
        // Only modify if search is active
        if ($plan->hasSearch()) {
            $query->withCount('search_hits');
        }

        return $next($query);
    }
}
```

### Pipe Ordering

Default pipeline (in order):
1. `ApplyScopes`
2. `ApplySoftDeletes`
3. `ApplyRelations`
4. `ApplySearch`
5. `ApplyFilters`
6. `ApplyAggregates`
7. `ApplySorting`
8. `ApplyEagerLoads`
9. *Your custom pipes (appended)*

---

## Custom Column Types

Register new column types that can be used in table definitions.

### Creating a Column

```php
namespace App\Wire\Columns;

use NyonCode\WireTable\Columns\Column;

class SparklineColumn extends Column
{
    protected array $dataPoints = [];
    protected string $chartColor = 'primary';
    protected int $height = 32;

    public function dataPoints(array|Closure $points): static
    {
        $this->dataPoints = $points;
        return $this;
    }

    public function chartColor(string $color): static
    {
        $this->chartColor = $color;
        return $this;
    }

    public function height(int $height): static
    {
        $this->height = $height;
        return $this;
    }

    public function getDataPoints(): array
    {
        return $this->evaluate($this->dataPoints);
    }

    public function getChartColor(): string
    {
        return $this->chartColor;
    }

    public function getHeight(): int
    {
        return $this->height;
    }
}
```

### Registering

```php
public function register(PluginManager $manager): void
{
    $manager->addColumnType('sparkline', SparklineColumn::class);
}
```

### Usage

```php
$table->columns([
    TextColumn::make('name'),
    SparklineColumn::make('views_over_time')
        ->dataPoints(fn ($record) => $record->daily_views->pluck('count'))
        ->chartColor('success')
        ->height(24),
]);
```

### Publishing Views

In the plugin's `boot()` method, publish the Blade view for the column:

```php
public function boot(PluginManager $manager): void
{
    $this->loadViewsFrom(__DIR__ . '/../resources/views', 'wire-sparkline');
}
```

Create `resources/views/columns/sparkline.blade.php`:

```blade
<div
    class="inline-flex items-center"
    style="height: {{ $getHeight() }}px"
    x-data="sparkline({ points: @js($getDataPoints()), color: '{{ $getChartColor() }}' })"
>
    <canvas x-ref="chart"></canvas>
</div>
```

---

## Custom Filter Types

Register new filter types.

### Creating a Filter

```php
namespace App\Wire\Filters;

use NyonCode\WireTable\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class GeoRadiusFilter extends Filter
{
    protected float $defaultRadius = 10.0;
    protected string $unit = 'km';
    protected ?string $latColumn = null;
    protected ?string $lngColumn = null;

    public function radius(float $radius): static
    {
        $this->defaultRadius = $radius;
        return $this;
    }

    public function unit(string $unit): static
    {
        $this->unit = $unit;
        return $this;
    }

    public function coordinates(string $latColumn, string $lngColumn): static
    {
        $this->latColumn = $latColumn;
        $this->lngColumn = $lngColumn;
        return $this;
    }

    public function apply(Builder $query, mixed $value): Builder
    {
        if (empty($value['lat']) || empty($value['lng'])) {
            return $query;
        }

        $lat = $value['lat'];
        $lng = $value['lng'];
        $radius = $value['radius'] ?? $this->defaultRadius;

        return $query->whereRaw(
            'ST_Distance_Sphere(point(?, ?), point(??, ??)) <= ?',
            [$lng, $lat, $this->lngColumn, $this->latColumn, $radius * 1000]
        );
    }

    public function getDefaultRadius(): float
    {
        return $this->defaultRadius;
    }

    public function getUnit(): string
    {
        return $this->unit;
    }
}
```

### Registering

```php
public function register(PluginManager $manager): void
{
    $manager->addFilterType('geo-radius', GeoRadiusFilter::class);
}
```

### Usage

```php
$table->filters([
    GeoRadiusFilter::make('location')
        ->coordinates('latitude', 'longitude')
        ->radius(25)
        ->unit('km'),
]);
```

---

## PluginManager API

### Plugin Management

```php
$manager->register(Plugin $plugin): void       // Register a plugin (calls $plugin->register())
$manager->boot(): void                         // Boot all plugins (calls $plugin->boot())
$manager->has(string $id): bool                // Check if plugin registered
$manager->get(string $id): ?Plugin             // Get plugin by ID
$manager->all(): array<string, Plugin>         // All registered plugins
```

### Extension Points

```php
// Query Pipes
$manager->addQueryPipe(string $name, QueryPipe $pipe): void
$manager->getQueryPipes(): array<string, QueryPipe>

// Column Types
$manager->addColumnType(string $name, string $columnClass): void
$manager->getColumnTypes(): array<string, class-string>

// Filter Types
$manager->addFilterType(string $name, string $filterClass): void
$manager->getFilterTypes(): array<string, class-string>

// Hooks
$manager->hook(string $name, callable $callback): void
$manager->runHook(string $name, array $payload = []): array
$manager->hasHook(string $name): bool
```

---

## Complete Example: Export Plugin

A plugin that adds CSV export to any table.

```php
namespace App\Wire\Plugins;

use NyonCode\WireCore\Core\Plugin\Contracts\Plugin;
use NyonCode\WireCore\Core\Plugin\PluginManager;

class ExportPlugin implements Plugin
{
    public function getId(): string
    {
        return 'export';
    }

    public function register(PluginManager $manager): void
    {
        // Add export header action column type
        $manager->addColumnType('export-button', ExportButtonColumn::class);

        // Hook into query results
        $manager->hook('table.queried', function (array $payload) {
            // Store last results for export (if export requested)
            if (session()->has('wire.export_requested')) {
                session(['wire.export_data' => $payload['results']]);
            }
            return $payload;
        });
    }

    public function boot(PluginManager $manager): void
    {
        // Register views
        // Register export route
    }
}
```

Usage in table:

```php
use NyonCode\WireCore\Actions\HeaderAction;

$table->headerActions([
    HeaderAction::make('export')
        ->label('Export CSV')
        ->icon('download')
        ->action(function () {
            session(['wire.export_requested' => true]);
            // trigger download...
        }),
]);
```

---

## Complete Example: Audit Plugin

Logs all table actions and inline edits.

```php
namespace App\Wire\Plugins;

use App\Models\AuditLog;
use NyonCode\WireCore\Core\Events\ActionExecuted;
use NyonCode\WireCore\Core\Events\CellUpdated;
use NyonCode\WireCore\Core\Plugin\Contracts\Plugin;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use Illuminate\Support\Facades\Event;

class AuditPlugin implements Plugin
{
    public function getId(): string
    {
        return 'audit';
    }

    public function register(PluginManager $manager): void
    {
        // Hook into action execution
        $manager->hook('action.executed', function (array $payload) {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => $payload['action']->getName(),
                'success' => $payload['result']->isSuccess(),
                'context' => json_encode($payload['result']),
            ]);
            return $payload;
        });

        // Hook into form saves
        $manager->hook('form.saved', function (array $payload) {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'form.save',
                'model_type' => get_class($payload['model']),
                'model_id' => $payload['model']->getKey(),
            ]);
            return $payload;
        });
    }

    public function boot(PluginManager $manager): void
    {
        // Also listen to Laravel events for inline edits
        Event::listen(CellUpdated::class, function (CellUpdated $event) {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'cell.update',
                'table_id' => $event->tableId,
                'column' => $event->column,
                'record_id' => $event->recordId,
                'old_value' => $event->oldValue,
                'new_value' => $event->newValue,
            ]);
        });
    }
}
```

---

## Complete Example: Multi-Tenancy Plugin

Automatically scopes all table queries to the current tenant.

```php
namespace App\Wire\Plugins;

use App\Wire\Pipes\TenantScopePipe;
use NyonCode\WireCore\Core\Plugin\Contracts\Plugin;
use NyonCode\WireCore\Core\Plugin\PluginManager;

class MultiTenancyPlugin implements Plugin
{
    public function getId(): string
    {
        return 'multi-tenancy';
    }

    public function register(PluginManager $manager): void
    {
        // Add tenant scope to every table query
        $manager->addQueryPipe('tenant-scope', new TenantScopePipe());

        // Inject tenant_id into form saves
        $manager->hook('form.saving', function (array $payload) {
            $payload['data']['tenant_id'] = auth()->user()->tenant_id;
            return $payload;
        });

        // Add tenant filter to table config
        $manager->hook('table.configuring', function (array $payload) {
            // Could add a hidden filter or modify the base query
            return $payload;
        });
    }

    public function boot(PluginManager $manager): void
    {
        // Nothing to boot
    }
}
```

The `TenantScopePipe`:

```php
namespace App\Wire\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use NyonCode\WireCore\Core\Query\Contracts\QueryPipe;
use NyonCode\WireCore\Core\Query\QueryPlan;

class TenantScopePipe implements QueryPipe
{
    public function handle(Builder $query, QueryPlan $plan, Closure $next): Builder
    {
        $model = $query->getModel();

        // Only apply if model has tenant_id column
        if (in_array('tenant_id', $model->getFillable())) {
            $query->where(
                $model->getTable() . '.tenant_id',
                auth()->user()->tenant_id
            );
        }

        return $next($query);
    }
}
```

---

## Testing Plugins

### Unit Testing

```php
use NyonCode\WireCore\Core\Plugin\PluginManager;

it('registers the plugin', function () {
    $manager = new PluginManager();
    $plugin = new ExportPlugin();

    $manager->register($plugin);

    expect($manager->has('export'))->toBeTrue();
    expect($manager->get('export'))->toBe($plugin);
});

it('adds query pipes', function () {
    $manager = new PluginManager();
    $manager->register(new MultiTenancyPlugin());

    expect($manager->getQueryPipes())->toHaveKey('tenant-scope');
});

it('registers hooks', function () {
    $manager = new PluginManager();
    $manager->register(new AuditPlugin());

    expect($manager->hasHook('action.executed'))->toBeTrue();
    expect($manager->hasHook('form.saved'))->toBeTrue();
});

it('runs hooks and modifies payload', function () {
    $manager = new PluginManager();

    $manager->hook('form.saving', function (array $payload) {
        $payload['data']['modified'] = true;
        return $payload;
    });

    $result = $manager->runHook('form.saving', [
        'data' => ['name' => 'John'],
    ]);

    expect($result['data']['modified'])->toBeTrue();
    expect($result['data']['name'])->toBe('John');
});

it('prevents duplicate plugin registration', function () {
    $manager = new PluginManager();
    $manager->register(new ExportPlugin());

    expect(fn () => $manager->register(new ExportPlugin()))
        ->toThrow(RuntimeException::class, "Plugin 'export' is already registered.");
});

it('boots only once', function () {
    $manager = new PluginManager();
    $callCount = 0;

    $plugin = new class implements Plugin {
        public function getId(): string { return 'test'; }
        public function register(PluginManager $m): void {}
        public function boot(PluginManager $m): void {
            // track externally
        }
    };

    $manager->register($plugin);
    $manager->boot();
    $manager->boot(); // second call is no-op
});
```

### Integration Testing

```php
it('applies tenant scope in query execution', function () {
    $manager = new PluginManager();
    $manager->register(new MultiTenancyPlugin());
    $manager->boot();

    // Verify the pipe is in the pipeline
    $pipes = $manager->getQueryPipes();
    expect($pipes)->toHaveKey('tenant-scope');
    expect($pipes['tenant-scope'])->toBeInstanceOf(TenantScopePipe::class);
});
```

### Testing Custom Columns

```php
it('creates sparkline column', function () {
    $column = SparklineColumn::make('views')
        ->dataPoints([1, 5, 3, 8, 2])
        ->chartColor('success')
        ->height(24);

    expect($column->getName())->toBe('views');
    expect($column->getDataPoints())->toBe([1, 5, 3, 8, 2]);
    expect($column->getChartColor())->toBe('success');
    expect($column->getHeight())->toBe(24);
});
```
