---
order: 10
summary: "Kdy je plugin správný tvar, co smí dělat, jaký kontrakt implementuje a v jakém pořadí běží jeho životní cyklus."
---

# Pluginy

Plugin sdružuje opakovaně použitelné nastavení na jedno místo: makra, registry
typů, query pipes, callbacky hooků, výchozí konfiguraci a integraci balíčku. Je to
podporovaná cesta, jak aplikace nebo doprovodný balíček změní chování frameworku —
a první otázka na téhle stránce je, jestli ho vůbec potřebuješ, protože closure,
event nebo makro bývají menší odpověď.

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
| Přidat sloupec nebo filtr do tabulky, kterou nevlastníte | hook `table.composing`, zúžený přes `for:` |
| Přidat pole do formuláře, který nevlastníte | hook `form.configuring`, zúžený přes `for:` |
| Sdílet vlastní třídu sloupce/filtru/akce podle názvu | Plugin registr typů |
| Postavit doprovodný balíček | Plugin plus package service provider |
| Přidat audit, telemetrii, tenant scope nebo policy integraci | Plugin hooky |

## Hook, event, makro nebo callback

Tenhle stack má čtyři způsoby, jak změnit chování, a který z nich sáhnout rozhoduje jediná otázka: **kdo drží referenci na komponentu?**

| Vy… | Sáhněte po | Umí to změnit hodnotu? |
|---|---|---|
| stavíte komponentu sami | fluent API — `modifyQueryUsing()`, `beforeSave()`, `afterSave()`, callbacky akce | ano |
| chcete nový slovník na třídě, kterou jste nepsali, použitý tam, kde stavíte | **makro** na `Table`, `Form`, `Column`, `Field`, `Filter` nebo `Action` | ano |
| musíte změnit komponentu, kterou nikdy neuvidíte — každou tabulku v aplikaci, nebo tu, kterou dodává balíček | **hook** | ano |
| potřebujete jen vědět, že se něco stalo | Laravel **event** — `TableSearched`, `CellUpdated`, `ActionExecuted`, `RecordCreated` | **ne**, záměrně |

Kde pro jeden okamžik existuje hook i event — `action.executing` a `ActionExecuting` se spouští deset řádků od sebe — je event ta pozorovací půlka. Audit, telemetrie a metriky patří tam; změna toho, co se provede, patří do hooku.

Praktický případ třetího řádku je [modul](../../panels/modules.md) nainstalovaný z balíčku: jeho list se staví uvnitř kódu, který aplikace nevlastní, takže sloupec se přidá hookem `table.composing`, ne poděděním resource — to by stejně kolidovalo na klíči.

## Co plugin může dělat

| Schopnost | API |
|------------|-----|
| Zaregistrovat instanci pluginu | `PluginManager::register()` |
| Spustit startup kód po registraci všech pluginů | `Plugin::boot()` |
| Přidat makra na dodávanou třídu | Laravel `Macroable` na `Table`, `Form`, `Column`, `Field`, `Filter` a `Action` |
| Zaregistrovat query pipes | `PluginManager::addQueryPipe()` |
| Zaregistrovat třídy sloupců podle názvu | `PluginManager::addColumnType()` |
| Zaregistrovat třídy filtrů podle názvu | `PluginManager::addFilterType()` |
| Zaregistrovat třídy akcí podle názvu | `PluginManager::addActionType()` |
| Zaregistrovat hook callbacky | `PluginManager::hook()` |
| Zúžit hook callback na jednu komponentu | `PluginManager::hook(..., for: 'invoices')` |
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

`PluginManager::register()` volá metodu `register()` pluginu okamžitě. `PluginManager::boot()` spustí metodu `boot()` každého pluginu jednou a uzavře registraci: plugin nabídnutý potom se odmítne, místo aby se přijal do seznamu, který už nikdo nečte.

Držte `register()` lehké. Neresolvujte request-scoped služby ani nepředpokládejte, že už každá Laravel služba bootla. `boot()` použijte pro práci, která potřebuje container, pohledy, makra nebo jiné registrované pluginy.

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
| `hook(Hook\|string $name, callable $callback, int $priority = 0, ?string $for = null): void` | Zaregistrovat hook callback |
| `runHook(Hook\|string $name, array $payload = [], ?HookTarget $target = null): array` | Spustit array hook callbacky a vrátit finální payload |
| `runTypedHook(Hook\|string $name, object $payload): object` | Spustit object hook callbacky a vrátit finální payload |
| `hasHook(Hook\|string $name): bool` | Zkontrolovat, zda hook má callbacky |

<a id="testing-plugins"></a>

## Best practices

- Používejte stabilní, malými písmeny plugin ID jako `tenant`, `audit-export` nebo `acme-billing`.
- Držte `register()` lehké; neresolvujte tam request-scoped služby.
- Laravel makra a service-závislé nastavení dejte do `boot()`.
- Pro jednorázové chování preferujte table/form/action fluent API.
- Vraťte payload pole z array hook callbacků, když chcete upravit data hooku.
- Používejte priority hooků střídmě a dokumentujte, proč callback musí běžet dřív nebo později.
- Zabezpečte registraci balíčku pomocí `PluginManager::has()`, abyste předešli duplicitním ID.

## V této sekci

| Stránka | Co pokrývá |
| --- | --- |
| [Registrace pluginů](registration.md) | Z configu, z balíčku, s konfigurací a závislostmi |
| [Hooky](hooks.md) | Hook systém, zúžení na komponentu a typované hooky |
| [Rozšiřování povrchů](extending.md) | Registry typů, přidávání tlačítek a akcí, query pipes |
| [Příklady a testování](examples.md) | Dva rozpracované pluginy a jak plugin otestovat |

## Související

- [Moduly](../../panels/modules.md) — byznysová oblast deklarovaná jako plugin
- [Foundation](../foundation/index.md) — concerny, do kterých plugin registruje
- [Konfigurace](../../start/configuration.md) — kde se deklaruje `plugins`
- [Vlastní pole](../../forms/custom-fields.md) — druhá cesta rozšíření, pro jednu komponentu
