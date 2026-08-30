---
order: 60
---

# Core Pluginy

Wire Core obsahuje plugin API pro rozšíření na úrovni aplikace a doprovodné balíčky. Plugin seskupuje znovupoužitelné nastavení na jednom místě: makra, registry typů, query pipes, hook callbacky, výchozí konfiguraci a integraci balíčků.

Pro jednu tabulku, formulář nebo akci nejdřív preferujte veřejné fluent API. Plugin použijte, když se má stejné chování nainstalovat jednou a znovupoužít napříč více komponentami, projekty nebo balíčky.

## Kdy použít plugin

| Potřeba | Preferujte |
|------|--------|
| Změnit jeden dotaz tabulky | `Table::modifyQueryUsing()` |
| Přidat jeden save callback formuláře | Lifecycle callbacky formuláře |
| Přidat jedno chování akce | Fluent API akce |
| Znovupoužít table/action makro všude | Plugin `boot()` |
| Přidat stejné tlačítko tabulky do mnoha tabulek | Plugin table makro, které sloučí akce |
| Přidat query pravidlo do mnoha tabulek | Plugin query pipe nebo hook `table.querying` |
| Sdílet vlastní třídu sloupce/filtru/akce podle názvu | Plugin registr typů |
| Postavit doprovodný balíček | Plugin plus package service provider |
| Přidat audit, telemetrii, tenant scope nebo policy integraci | Plugin hooky |

## Co plugin může dělat

| Schopnost | API |
|------------|-----|
| Zaregistrovat instanci pluginu | `PluginManager::register()` |
| Spustit startup kód po registraci všech pluginů | `Plugin::boot()` |
| Přidat table/action makra | Laravel `Macroable` třídy jako `Table` a `Action` |
| Zaregistrovat query pipes | `PluginManager::addQueryPipe()` |
| Zaregistrovat třídy sloupců podle názvu | `PluginManager::addColumnType()` |
| Zaregistrovat třídy filtrů podle názvu | `PluginManager::addFilterType()` |
| Zaregistrovat třídy akcí podle názvu | `PluginManager::addActionType()` |
| Zaregistrovat hook callbacky | `PluginManager::hook()` |
| Spustit array payload hooky | `PluginManager::runHook()` |
| Spustit object payload hooky | `PluginManager::runTypedHook()` |
| Číst sloučenou konfiguraci pluginu | `PluginManager::getPluginConfig()` |

## Rychlý start

Vytvořte třídu pluginu:

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

Zaregistrujte ho v `config/wire-core.php`:

```php
'plugins' => [
    App\Wire\Plugins\TenantPlugin::class,
],
```

Použijte makro z jakékoli tabulky:

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

## Kontrakt pluginu

Každý plugin implementuje `NyonCode\WireCore\Core\Plugin\Contracts\Plugin`.

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
        // Zaregistrovat hooky, query pipes, aliasy typů nebo lehká metadata.
    }

    public function boot(PluginManager $manager): void
    {
        // Zaregistrovat makra nebo resolvovat služby po registraci všech pluginů.
    }
}
```

Hodnota `getId()` musí být unikátní. Registrace dvou pluginů se stejným ID vyhodí `RuntimeException`.

## Životní cyklus

| Krok | Metoda | Použití pro |
|------|--------|---------|
| Registrace | `register(PluginManager $manager)` | Hooky, query pipes, column/filter/action typy, lehká metadata |
| Boot | `boot(PluginManager $manager)` | Makra, resolvované služby, pohledy, package setup závislý na Laravel containeru |

`PluginManager::register()` volá metodu `register()` pluginu okamžitě. `PluginManager::boot()` spustí metodu `boot()` každého pluginu jednou.

Držte `register()` lehké. Neresolvujte request-scoped služby ani nepředpokládejte, že už každá Laravel služba bootla. `boot()` použijte pro práci, která potřebuje container, pohledy, makra nebo jiné registrované pluginy.

## Registrace pluginů v configu

Publikujte core config:

```bash
php artisan vendor:publish --tag=wire-core::config
```

Přidejte třídy pluginů do `config/wire-core.php`:

```php
'plugins' => [
    App\Wire\Plugins\TenantPlugin::class,
    App\Wire\Plugins\AuditExportPlugin::class,
],
```

Wire resolvuje config-registrované pluginy přes Laravel container, když se resolvuje plugin manager. Neplatné položky se ignorují, takže se zaregistrují jen názvy tříd implementujících `Plugin`.

<a id="register-plugins-from-a-package"></a>
## Registrace pluginů z balíčku

Pokud stavíte doprovodný balíček, zaregistrujte svůj plugin z package service provideru.

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

Guard `has()` předchází duplicitní registraci, pokud aplikace plugin také uvádí v configu.

## Konfigurace pluginu

Pluginy, které přijímají uživatelské volby, mohou implementovat `HasConfiguration`.

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

        // $config je sloučená výchozí a uživatelská konfigurace.
    }
}
```

Uživatelské přepisy žijí pod `wire-core.plugins.config.{pluginId}`:

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

Manager sloučí výchozí hodnoty pluginu s uživatelskou konfigurací pomocí `array_merge()`. Top-level klíče z uživatelské konfigurace nahradí výchozí klíče.

## Závislosti pluginu

Pluginy, které vyžadují jiné pluginy, mohou implementovat `HasDependencies`.

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

Závislosti už musí být registrované. Pokud závislost chybí, `PluginManager::register()` vyhodí `RuntimeException`.

Registrujte závislé pluginy po jejich závislostech:

```php
'plugins' => [
    App\Wire\Plugins\ExportPlugin::class,
    App\Wire\Plugins\BillingExportPlugin::class,
],
```

<a id="hook-system"></a>
## Hook systém

Hooky nechají pluginy a aplikační kód komunikovat přes pojmenované callbacky.

```php
public function register(PluginManager $manager): void
{
    $manager->hook('orders.exporting', function (array $payload): array {
        $payload['query']->where('tenant_id', auth()->user()->tenant_id);

        return $payload;
    });
}
```

Spusťte hook z vlastní služby nebo komponenty:

```php
use NyonCode\WireCore\Core\Plugin\PluginManager;

$payload = app(PluginManager::class)->runHook('orders.exporting', [
    'query' => Order::query(),
]);

$query = $payload['query'];
```

Hook ovlivní runtime chování jen když nějaký kód zavolá `runHook()` nebo `runTypedHook()` pro ten název hooku. Registrace hooku uloží callback; automaticky nepatchuje chování tabulky, formuláře ani akce.

### Návratové hodnoty hooku

Array hooky dostanou aktuální payload pole.

| Návrat callbacku | Výsledek |
|-----------------|--------|
| `array` | Nahradí payload pro další callback |
| `null` nebo jiná ne-array hodnota | Ponechá aktuální payload beze změny |
| výjimka | Probublá k volajícímu |

### Priorita hooku

Callbacky běží ve vzestupné prioritě. Nižší čísla běží dřív.

```php
public function register(PluginManager $manager): void
{
    $manager->hook('table.querying', fn (array $payload) => $payload, priority: -100);
    $manager->hook('table.querying', fn (array $payload) => $payload);
    $manager->hook('table.querying', fn (array $payload) => $payload, priority: 100);
}
```

Doporučené rozsahy:

| Priorita | Použití pro |
|----------|---------|
| `-100` | Bezpečnost, tenancy, scoping |
| `0` | Normální chování feature |
| `100` | Audit, logování, telemetrie |

Callbacky se stejnou prioritou si zachovají pořadí registrace.

### Runtime hooky

Tyto hooky emitují aktuální balíčky:

| Hook | Balíček | Kdy | Payload | Konzumuje vrácený payload |
|------|---------|------|---------|---------------------------|
| `table.querying` | Table | Před naplánováním dotazu tabulky | `table`, `columns`, `filters`, `sort_column`, `sort_direction`, `search` | Ano, čte `force_sort_column` a `force_sort_direction` |
| `form.saving` | Forms | Po mutaci a před perzistencí | `config`, `data` | Ano, čte upravená `data` |
| `form.saved` | Forms | Po perzistenci a uložení relací | `config`, `record` | Ne |
| `action.executing` | Table | Před během pipeline akce | `action`, `actionName`, `actionType`, `recordIds`, `data`, `component` | Ne |
| `action.executed` | Table | Po běhu pipeline akce | `action`, `actionName`, `actionType`, `recordIds`, `result`, `component` | Ne |

Plugin manager nevynucuje názvy hooků. Pro aplikační hooky používejte názvy popisující vaši hranici, jako `orders.exporting`, `orders.exported`, `billing.invoice.saving` nebo `crm.customer.synced`.

### Příklad: Vynutit řazení tabulky v hooku

Balíček sortable používá `table.querying` k vynucení řazení, když je tabulka v režimu přeřazování. Stejný vzor funguje pro aplikačně specifická query pravidla.

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

Použijte `modifyQueryUsing()`, když potřebujete změnit jen jednu tabulku. Použijte `table.querying`, když pravidlo patří ke znovupoužitelné integraci.

## Typované hooky

`runTypedHook()` je dostupný pro rozšiřovací body, které preferují object payloady místo polí.

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

Callbacky dostanou payload objekt. Vrácení objektu nahradí payload pro další callback; vrácení `null` nebo jiného ne-objektu ponechá aktuální payload.

```php
$manager->hook('orders.exporting', function (ExportingOrders $payload): ExportingOrders {
    $payload->query->where('tenant_id', auth()->user()->tenant_id);

    return $payload;
});
```

Core také dodává typované payload DTO pod `NyonCode\WireCore\Core\Plugin\Hooks` pro běžné tvary table, form a action hooků — a runtime je **už dispatchuje**. Každý vestavěný lifecycle bod spouští oba dispatchery za sebou: `table.configuring`, `table.querying` a `table.queried` z `TableQueryService`, `form.saving` a `form.saved` ze save handleru, `action.executing` a `action.executed` z action runtime. Callback na kterémkoli z těch hooků si tedy může vzít přímo `TableQueryingPayload`, `FormSavingPayload`, `ActionExecutingPayload` a další.

### Který dispatcher dostane váš callback

Protože na každém lifecycle bodě běží oba dispatchery, musí každý callback patřit právě jednomu z nich — a rozhoduje o tom **typový hint prvního parametru**:

| První parametr | Dispatcher | Payload |
| --- | --- | --- |
| `array $payload` | `runHook()` | pole |
| DTO nebo jakýkoli jiný typový hint | `runTypedHook()` | objekt |
| **bez typového hintu nebo bez parametru** | `runHook()` | pole |

Otypujte ho. Callback bez hintu se kvůli zpětné kompatibilitě považuje za array variantu, což znamená, že typovaný payload tiše nikdy neuvidí:

```php
// Běží jen na array dispatchi — $payload je pole.
$manager->hook('form.saving', function ($payload) { /* … */ });   // [tl! --]
// Řekne si, který payload chce, a dostane ho.
$manager->hook('form.saving', function (FormSavingPayload $payload): FormSavingPayload { // [tl! ++]
    $payload->data['audited_at'] = now();                                                // [tl! ++]
                                                                                          // [tl! ++]
    return $payload;                                                                      // [tl! ++]
});                                                                                       // [tl! ++]
```

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

Pluginy automaticky neinjektují tlačítka do každé tabulky. Obvyklý vzor je zaregistrovat table makro v `boot()` a nechat každou tabulku se přihlásit. Makro by se mělo sloučit s existujícími akcemi místo jejich nahrazení.

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

## Praktický příklad: Preset akce

Akce jsou macroable přes svou základní action třídu. Tento plugin přidává znovupoužitelný admin-only preset.

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

Použijte ho na akci:

```php
Action::make('impersonate')
    ->label('Impersonate')
    ->adminOnly()
    ->requiresConfirmation()
    ->action(fn (User $record) => auth()->user()->impersonate($record));
```

## Praktický příklad: Audit formuláře

Tento plugin přidává malý audit hook kolem perzistence formuláře.

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

`form.saving` může upravit data, která se perzistují. `form.saved` je v aktuálním runtime observační, protože save handler nekonzumuje jeho vrácený payload.

## PluginManager API

| Metoda | Popis |
|--------|-------------|
| `register(Plugin $plugin): void` | Zaregistrovat plugin a zavolat jeho metodu `register()` |
| `boot(): void` | Bootnout každý registrovaný plugin jednou |
| `has(string $id): bool` | Zkontrolovat, zda je ID pluginu registrované |
| `get(string $id): ?Plugin` | Vrátit plugin podle ID |
| `all(): array` | Vrátit všechny registrované pluginy klíčované ID |
| `getPluginConfig(string $pluginId): array` | Vrátit sloučenou konfiguraci pro konfigurovatelný plugin |
| `addQueryPipe(string $name, QueryPipe $pipe): void` | Zaregistrovat query pipe |
| `getQueryPipes(): array` | Vrátit registrované query pipes |
| `addColumnType(string $name, string $columnClass): void` | Zaregistrovat alias třídy sloupce |
| `getColumnTypes(): array` | Vrátit aliasy sloupců |
| `addFilterType(string $name, string $filterClass): void` | Zaregistrovat alias třídy filtru |
| `getFilterTypes(): array` | Vrátit aliasy filtrů |
| `addActionType(string $name, string $actionClass): void` | Zaregistrovat alias třídy akce |
| `getActionTypes(): array` | Vrátit aliasy akcí |
| `hook(string $name, callable $callback, int $priority = 0): void` | Zaregistrovat hook callback |
| `runHook(string $name, array $payload = []): array` | Spustit array hook callbacky a vrátit finální payload |
| `runTypedHook(string $name, object $payload): object` | Spustit object hook callbacky a vrátit finální payload |
| `hasHook(string $name): bool` | Zkontrolovat, zda hook má callbacky |

<a id="testing-plugins"></a>
## Testování pluginů

Testujte chování pluginu instancováním `PluginManager` přímo.

```php
use NyonCode\WireCore\Core\Plugin\PluginManager;

it('registers tenant plugin', function () {
    $manager = new PluginManager();
    $plugin = new TenantPlugin();

    $manager->register($plugin);

    expect($manager->has('tenant'))->toBeTrue();
});
```

Pro makra plugin nejdřív bootněte:

```php
it('adds tenant table macro', function () {
    $manager = new PluginManager();
    $plugin = new TenantPlugin();

    $manager->register($plugin);
    $manager->boot();

    expect(\NyonCode\WireTable\Table::hasMacro('tenantScoped'))->toBeTrue();
});
```

Pro chování hooku spusťte hook s payloadem, který váš runtime kód emituje:

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

## Best practices

- Používejte stabilní, malými písmeny plugin ID jako `tenant`, `audit-export` nebo `acme-billing`.
- Držte `register()` lehké; neresolvujte tam request-scoped služby.
- Laravel makra a service-závislé nastavení dejte do `boot()`.
- Pro jednorázové chování preferujte table/form/action fluent API.
- Vraťte payload pole z array hook callbacků, když chcete upravit data hooku.
- Používejte priority hooků střídmě a dokumentujte, proč callback musí běžet dřív nebo později.
- Zabezpečte registraci balíčku pomocí `PluginManager::has()`, abyste předešli duplicitním ID.
