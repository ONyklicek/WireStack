---
order: 96
summary: Doménová osa — jedna business oblast deklarovaná na jednom místě, registrovaná jako plugin a rozvezená do registrů, které vlastní resources, dashboardy a menu.
---

# Doménové moduly

Balíčky jsou technická osa tohohle frameworku: core, forms, table, sortable.
**Doménový modul** je ta druhá — `billing` vedle `operations` vedle `crm` —
a existuje proto, aby business oblast byla deklarovaná na jednom místě místo
rozsypaná po provideru aplikace jako tři nesouvisející seznamy.

Modul nevlastní žádné primitivy a žádný neforkuje. Pojmenuje, z čeho se oblast
skládá; vrstvy, které ty věci už vlastní, je vlastní dál.

## Jak to funguje

Modul je **plugin**, ne paralelní registrační systém. To je celé to designové
rozhodnutí a je to ono, co drží lifecycle poctivý:

1. Registruje se z `config('wire-core.plugins')` jako každý jiný plugin, takže
   se modul instaluje stejnou cestou jako všechno ostatní.
2. `PluginManager` mu dá záruky, které modul potřebuje a už je měl: jedno id na
   modul, všechny moduly zaregistrované dřív, než se kterýkoli bootne, a
   závislost, která musí být zaregistrovaná první, jinak se registrace odmítne.
3. `WireCoreServiceProvider` pak přečte, co který modul deklaruje, a naplní
   [registr resources](resources.md), [registr dashboardů](widgets.md) a
   [navigační skupiny](resources.md#navigace-a-workspace).

Oba registry jsou zdroje jednoho [`Catalog`u](resources.md#catalog-api), takže se
resources a dashboardy modulu z téhle jediné deklarace dostanou do menu, do
routeru i do palety globálního hledání.

Krok 3 dělá provider, ne modul, a to schválně. Dashboard bydlí ve widgetové
vrstvě a kontrakt modulu, který by sáhl na `DashboardRegistry`, by byl import,
který architektonický test odmítá; pojmenovat třídu žádný import nestojí, takže
modul zůstává deklarací a zapojení dělá provider, který všechny registry stejně
už drží.

Registr modulů **záměrně neexistuje**: `PluginManager` ten seznam už drží
a druhý registr nad jedním seznamem je přesně ta duplicita, kterou tenhle
codebase pořád odstraňuje.

## Jak se deklaruje

```php
use NyonCode\WireCore\Core\Modules\DomainModule;   // [tl! focus:start]
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroup;

final class BillingModule extends DomainModule
{
    public function getId(): string
    {
        return 'billing';
    }

    public function resources(): array
    {
        return [InvoiceResource::class, CreditNoteResource::class];
    }

    public function navigation(): ?NavigationGroup
    {
        return NavigationGroup::make('billing')
            ->label(__('nav.billing'))
            ->icon('outline:banknotes')
            ->sort(20);
    }
}   // [tl! focus:end]
```

```php
// config/wire-core.php
'plugins' => [
    App\Modules\BillingModule::class,
    App\Modules\OperationsModule::class,
],
```

Povinné je jen id, všechno ostatní je volitelné. Modul, který deklaruje jen
resources, je běžný; stejně tak ten, který deklaruje jen dashboard.

## Závislost na jiném modulu

`dependencies()` je z plugin systému beze změny — vyjmenuj id, která musí být
zaregistrovaná první:

```php
use NyonCode\WireCore\Core\Plugin\Contracts\HasDependencies;

final class OperationsModule extends DomainModule implements HasDependencies
{
    public function getId(): string
    {
        return 'operations';
    }

    public function dependencies(): array   // [tl! focus]
    {
        return ['billing'];
    }

    public function dashboards(): array
    {
        return [OverviewDashboard::class];
    }
}
```

Registrace `operations` před `billing` vyhodí výjimku místo bootu do napůl
postavené aplikace — pořadí se kontroluje, ne doufá.

## Co modul nedělá

| Tohle ne | Protože |
| --- | --- |
| Registrovat workflow | Workflow má jednu skupinu konzumentů a nese ho resource, který vlastní entitu. Viz [Workflow a přechody](actions.md#workflow-a-prechody) |
| Registrovat policies | Ty vlastní Laravelí `Gate` |
| Vyjmenovávat workspaces | `Workspace` je služba nad registry, ne třída k vyjmenování |
| Forkovat primitiv | Modul skládá `Table`, `Form`, `Widget` a `Resource` beze změny; je to doménová osa, ne druhá implementace |

## Introspekce

`describe-module` reportuje, co moduly aplikace deklarují — jediná věc, kterou
`describe-resource` ukázat nemůže, protože resource neví, do které business
oblasti patří:

```text
describe-module              # každý registrovaný modul
describe-module billing      # jeden, podle id
```

## DomainModule API

| Metoda | Vrací | K čemu |
| --- | --- | --- |
| `getId(): string` | `string` | Id modulu, unikátní mezi všemi pluginy. Povinné |
| `resources(): array` | `array<int, class-string>` | Třídy resources, ze kterých se oblast skládá |
| `dashboards(): array` | `array<int, class-string>` | Třídy dashboardů, které přináší |
| `navigation(): ?NavigationGroup` | `NavigationGroup\|null` | Skupina menu, pod kterou její položky patří |
| `dependencies(): array` | `array<int, string>` | Id modulů, které se musí zaregistrovat dřív (přes `HasDependencies`) |
| `register()` / `boot()` | `void` | Plugin lifecycle; výchozí prázdný, přepiš pro hooky nebo bindingy |
