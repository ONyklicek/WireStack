---
order: 70
summary: Modul je manifest jedné business oblasti — resources, dashboardy a nadpis v menu, ze kterých se skládá — registrovaný jako plugin a rozvezený do registrů, které je vlastní.
---

# Moduly

Balíčky jsou technická osa tohohle frameworku: core, forms, table, sortable.
**Modul** je ta druhá — `billing` vedle `operations` vedle `crm` — a existuje
proto, aby business oblast byla deklarovaná na jednom místě místo rozsypaná
po provideru aplikace jako tři nesouvisející seznamy.

Modul nevlastní žádné primitivy a žádný neforkuje. Pojmenuje, z čeho se oblast
skládá; vrstvy, které ty věci už vlastní, je vlastní dál.

> **Je to manifest, ne doménová vrstva.** Tahle třída se dřív jmenovala
> `DomainModule` a to slibovalo víc, než dělá: drží tři seznamy názvů tříd
> a nadpis do menu. Není to bounded context, není to hranice agregátu a není to
> místo, kde se cokoli modeluje — modul nemá žádné vlastní chování a nic tady
> neizoluje kód jedné oblasti od druhé. Když tohle aplikace chce, chce to ve
> vlastních namespacech a vlastních testech.

## Jak to funguje

Modul je **plugin**, ne paralelní registrační systém. To je celé to designové
rozhodnutí a je to ono, co drží životní cyklus poctivý:

1. Registruje se jako každý jiný plugin — z `config('wire-core.plugins')`, když
   ho deklaruje aplikace, nebo z vlastního service provideru balíčku, když ho
   dodává balíček — takže se modul instaluje stejnou cestou jako všechno ostatní.
2. `PluginManager` mu dá záruky, které modul potřebuje a už je měl: jedno id na
   modul, všechny moduly zaregistrované dřív, než se kterýkoli bootne, a
   závislost, která musí být zaregistrovaná první, jinak se registrace odmítne.
3. `WireCoreServiceProvider` pak přečte, co který modul deklaruje, a naplní
   [registr resources](resources.md), [registr dashboardů](../core/widgets/index.md) a
   [navigační skupiny](navigation.md).

Oba registry jsou zdroje jednoho [`Catalog`u](navigation.md#catalog-api), takže se
resources a dashboardy modulu z téhle jediné deklarace dostanou do menu, do
routeru i do palety globálního hledání — včetně [zón](routing.md#zony), které
si ze stejného katalogu vybírají podle klíče.

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
use NyonCode\WireCore\Core\Modules\Module;   // [tl! focus:start]
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroup;

final class BillingModule extends Module
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

final class OperationsModule extends Module implements HasDependencies
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

## Modul jako balíček

Modul je plugin, takže balíček ho dodává stejně jako kterýkoli jiný plugin: jeho
vlastní service provider ho zaregistruje a aplikace balíček nainstaluje. Do
`config/wire-core.php` se nepřidává nic — balíček ten soubor upravit nemůže,
a nepotřebuje to.

```php
use Illuminate\Support\ServiceProvider;
use NyonCode\WireCore\Core\Plugin\PluginManager;

final class BillingModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // `resolving`, v register() — callback běží ve chvíli, kdy container   // [tl! focus:start]
        // staví manager, takže modul je v seznamu dřív než boot() a dřív, než
        // ho core provider rozprostře do registrů.
        $this->app->resolving(PluginManager::class, function (PluginManager $manager) {
            if (! $manager->has('billing')) {                 // idempotentní, i když ho aplikace uvádí taky
                $manager->register(new BillingModule);
            }
        });                                                   // [tl! focus:end]
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'billing');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
```

Registrace v `boot()` místo toho hodí výjimku — pravidlo o fázi a důvod, proč
pozdní příchod nejde zachránit, je v
[Registrace pluginů z balíčku](../core/plugins/registration.md#registrace-pluginu-z-balicku).

Dvě cesty a co je čí:

| Cesta | Kdo ji používá |
| --- | --- |
| `config('wire-core.plugins')` | Aplikace, která deklaruje své vlastní moduly |
| `$this->app->resolving(PluginManager::class, …)` | Balíček, který modul dodává aplikacím, do kterých nevidí |

Obě končí ve stejném seznamu, takže modul z balíčku se rozprostře do registru
resources, registru dashboardů a navigačních skupin přesně jako lokální a do
menu, routeru i vyhledávací palety se dostane přes stejný
[`Catalog`](navigation.md#catalog-api).

Všechno ostatní, co balíček s modulem nese — config, views, překlady, migrace
a assety — je běžná práce balíčku a patří jeho vlastnímu service provideru.

### Balíček přidává, nepřepisuje

Modul registruje klíče, na které si nikdo jiný nedělá nárok. Dvě různé třídy na
jednom klíči se odmítnou, místo aby se rozsoudily, takže nainstalovaný balíček
nikdy nemůže převzít resource, routu ani položku menu, kterou už vlastní
aplikace.

Platí to i opačným směrem: aplikace, která chce upravit, co modul dodává, mění
**komponentu, ne třídu**:

```php
$manager->hook(Hook::TableComposing, function (TableComposingPayload $payload) {
    $payload->columns = [...$payload->columns, TextColumn::make('internal_note')];

    return $payload;
}, for: 'invoices');   // klíč, pod kterým se modul zaregistroval
```

[Hook](../core/plugins/hooks.md#zuzeni-hooku-na-jednu-komponentu) dosáhne na list toho modulu
a na nic jiného, a přežije jeho další vydání — což fork ne. Podědit resource
z modulu nefunguje: potomek si nese klíč rodiče a koliduje s ním.

Ten samý klíč dosáhne i na ostatní plochy modulu — a právě to dělá z věty výše
tvrzení o celém modulu, ne jen o jeho listu:

| Změna | Hook |
| --- | --- |
| sloupec v jeho listu | `Hook::TableComposing` |
| pole v jeho formuláři | `Hook::FormConfiguring` |
| řádek v jeho detailu | `Hook::InfolistConfiguring` |
| co obsahuje jeho export | `Hook::ExportConfiguring` |
| co mapuje jeho import | `Hook::ImportConfiguring` |
| s čím přijde pole ve formuláři | `Hook::FormFilling` |
| co zapíše inline editace buňky | `Hook::CellUpdating` |
| veřejný stav na její stránce při mountu | `Hook::PageMounting` |
| jestli se vůbec objeví v menu | `Hook::NavigationBuilding` (zúžený zónou) |

## Co modul nedělá

| Tohle ne | Protože |
| --- | --- |
| Registrovat workflow | Workflow má jednu skupinu konzumentů a nese ho resource, který vlastní entitu. Viz [Workflow a přechody](../core/actions/workflow.md#workflow-a-prechody) |
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

## Module API

| Metoda | Vrací | K čemu |
| --- | --- | --- |
| `getId(): string` | `string` | Id modulu, unikátní mezi všemi pluginy. Povinné |
| `resources(): array` | `array<int, class-string>` | Třídy resources, ze kterých se oblast skládá |
| `dashboards(): array` | `array<int, class-string>` | Třídy dashboardů, které přináší |
| `navigation(): ?NavigationGroup` | `NavigationGroup\|null` | Skupina menu, pod kterou její položky patří |
| `dependencies(): array` | `array<int, string>` | Id modulů, které se musí zaregistrovat dřív (přes `HasDependencies`) |
| `register()` / `boot()` | `void` | Životní cyklus pluginu; výchozí prázdný, přepište pro hooky nebo bindingy |
