---
order: 40
summary: "Adding your own column, filter and action types, putting buttons on surfaces you do not own, and pipes that shape every query a table runs."
---

# Extending Surfaces

Three things a plugin does that change what other people's code renders: it
registers **types** the framework can then build by name, it adds **buttons and
actions** to surfaces it does not own, and it puts **pipes** in front of the query
every table runs. Each one is a registry, not a patch.

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

Plugins do not automatically inject buttons into every table. The usual pattern is to register a table macro in `boot()` and let each table opt in. The macro should merge with existing actions instead of replacing them. `Form`, `Column`, `Field` and `Filter` are macroable too, so the same pattern works one level down — a `Column::macro()` gives every column type a word it did not have.

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

## Related

- [Plugins](index.md) — the class doing the registering
- [Hooks](hooks.md) — the other half of the same lifecycle
- [Custom Fields](../../forms/custom-fields.md) — writing the type a registry then holds
- [Data Sources](../../table/data-sources.md) — where a query pipe ends up
