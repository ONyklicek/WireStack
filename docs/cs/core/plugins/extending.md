---
order: 40
summary: "Přidání vlastních typů sloupců, filtrů a akcí, tlačítka na povrchy, které nevlastníš, a pipes tvarující každý dotaz tabulky."
---

# Rozšiřování povrchů

Tři věci, kterými plugin mění, co vykresluje cizí kód: registruje **typy**, které
pak framework umí postavit podle jména, přidává **tlačítka a akce** na povrchy,
které nevlastní, a staví **pipes** před dotaz, který každá tabulka pouští. Každá z
nich je registr, ne patch.

## Registry typů sloupců, filtrů a akcí

Pluginy mohou zaregistrovat aliasy tříd pro plugin-aware buildery, admin nástroje, schema importéry nebo integrace balíčků.

```php
public function register(PluginManager $manager): void
{
    $manager->addColumnType('money', \App\Tables\Columns\MoneyColumn::class);
    $manager->addFilterType('date-range', \App\Tables\Filters\DateRangeFilter::class);
    $manager->addActionType('workflow', \App\Tables\Actions\WorkflowAction::class);
}
```

Čtěte registry z manageru:

```php
$columns = app(PluginManager::class)->getColumnTypes();
$filters = app(PluginManager::class)->getFilterTypes();
$actions = app(PluginManager::class)->getActionTypes();
```

Komponenty Wire Table stále přijímají normální instance přímo:

```php
return $table
    ->columns([
        MoneyColumn::make('total'),
    ])
    ->filters([
        DateRangeFilter::make('created_at'),
    ]);
```

Registry typů jsou metadatové registry. Automaticky nevykreslí sloupec, filtr ani akci podle aliasu, dokud váš vlastní builder nebo balíček registr nekonzumuje.

<a id="adding-buttons-and-actions"></a>

## Přidávání tlačítek a akcí

Většina tlačítek ve Wire tabulkách jsou akce:

| Umístění v UI | Třída/API |
|--------------|-----------|
| Řádkové tlačítko | `Action` v `Table::actions()` |
| Tlačítko hromadného toolbaru | `BulkAction` v `Table::bulkActions()` |
| Tlačítko hlavičkového toolbaru | `HeaderAction` v `Table::headerActions()` |
| Tlačítko uvnitř buňky tabulky | `ButtonColumn` v `Table::columns()` |
| Prosté Blade tlačítko | `<x-wire::button>` |

Pluginy automaticky neinjektují tlačítka do každé tabulky. Obvyklý vzor je zaregistrovat table makro v `boot()` a nechat každou tabulku se přihlásit. Makro by se mělo sloučit s existujícími akcemi místo jejich nahrazení. Makrovatelné jsou i `Form`, `Column`, `Field` a `Filter`, takže ten samý vzor funguje o úroveň níž — `Column::macro()` dá každému typu sloupce slovo, které neměl.

### Makro hlavičkového tlačítka

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

Použijte tlačítko na tabulkách, které ho potřebují:

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

### Makro řádkového tlačítka

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

### Makro hromadného tlačítka

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

### Makro sloupce s tlačítkem v buňce

Použijte `ButtonColumn`, když je tlačítko součástí viditelných sloupců každého řádku spíš než oblasti řádkových akcí.

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

Pro příkazy preferujte akce. Použijte `ButtonColumn`, když tlačítko potřebuje sedět mezi ostatními sloupci nebo když je jeho stav přirozeně sloupcový. Pro odkazy v buňce použijte `actionUrl()`. Pro Livewire volání v buňce použijte `livewireAction()` a implementujte tu metodu na Livewire table komponentě.

<a id="query-pipes"></a>

## Query pipes

Pluginy mohou zaregistrovat instance query pipe s managerem. Vykonání dotazu tabulky připojí plugin pipes za výchozí query pipeline.

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

Zaregistrujte ho z pluginu:

```php
public function register(PluginManager $manager): void
{
    $manager->addQueryPipe('tenant', new ApplyTenantScope());
}
```

Získejte registrované pipes pro vlastní query executor:

```php
$pipes = app(PluginManager::class)->getQueryPipes();
```

Výchozí pořadí query pipe tabulky:

| Pořadí | Pipe |
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

Použijte table `modifyQueryUsing()`, když změna patří jedné tabulce. Použijte query pipe, když stavíte znovupoužitelné query chování, které má běžet jako součást sdílené query planner/executor pipeline.

## Související

- [Pluginy](index.md) — třída, která registruje
- [Hooky](hooks.md) — druhá polovina téhož životního cyklu
- [Vlastní pole](../../forms/custom-fields.md) — jak napsat typ, který pak registr drží
- [Zdroje dat](../../table/data-sources.md) — kde query pipe skončí
