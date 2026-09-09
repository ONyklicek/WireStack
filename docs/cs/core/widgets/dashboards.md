---
order: 50
summary: "Skládání widgetů na komponentě přes `WithWidgets` a deklarace dashboardu jako vlastníka, kterého najde menu i router."
---

# Dashboardy

Dostat widgety na obrazovku jde dvěma způsoby a je to totéž dělení, jaké dělá
resource: napsat si je na komponentu, nebo deklarovat **dashboard**, který je
jmenuje, a nechat ho vykreslit stránkou. To druhé je to, co dá položku do menu a
URL do routeru, aniž by se kdokoli z nich dozvěděl, co je widget.

## Dashboard layout (WithWidgets)

Použijte trait `WithWidgets` na Livewire komponentě pro složení widgetového dashboardu.

```php
use NyonCode\WireCore\Widgets\Concerns\WithWidgets;
use NyonCode\WireCore\Widgets\Contracts\HasWidgets;
```

### Použití

```php
class Dashboard extends Component implements HasWidgets
{
    use WithWidgets;

    protected function getWidgets(): array
    {
        return [
            StatsOverviewWidget::make()
                ->columns(4)
                ->stats([
                    Stat::make('Users', User::count()),
                    Stat::make('Orders', Order::count()),
                    Stat::make('Revenue', '$' . number_format(Order::sum('total'), 2)),
                    Stat::make('Products', Product::count()),
                ]),

            ChartWidget::make()
                ->heading('Monthly Revenue')
                ->type('line')
                ->columnSpan(2)
                ->labels($this->getMonthLabels())
                ->datasets($this->getRevenueDatasets()),

            TableWidget::make()
                ->heading('Recent Orders')
                ->table(fn ($table) => $this->configureRecentOrdersTable($table)),
        ];
    }

    protected function getWidgetColumns(): int
    {
        return 2;  // 2-sloupcový grid layout
    }
}
```

### Blade šablona

Vykreslete dashboard komponentou `<x-wire::widget-grid>`.
`getVisibleWidgets()` je veřejná a vrací jen widgety, které projdou svými
kontrolami viditelnosti a autorizace; každý widget ctí svůj vlastní `columnSpan()`
a polling interval uvnitř gridu:

```blade
<div>
    <x-wire::widget-grid :widgets="$this->getVisibleWidgets()" :columns="2" />
</div>
```

Každý widget je také `Htmlable`, takže můžete komponentu přeskočit a rozložit je
sami: `@foreach ($this->getVisibleWidgets() as $widget) {{ $widget }} @endforeach`.

### WithWidgets API

```php
abstract protected function getWidgets(): array      // definovat widgety
protected function getWidgetColumns(): int           // sloupce gridu (výchozí: 2)
public function getVisibleWidgets(): array           // filtrované podle viditelnosti + autorizace
public function refreshWidget(string $key): void     // tik pollingu, zodpovězený tím widgetem
public function filterWidget(string $key, string $value): void   // výběr filtru
public function callWidgetAction(string $key, string $name): void // akce v hlavičce
public function loadWidget(string $key): void        // první render odloženého widgetu
public array $widgetFilters                          // jaký filtr který widget ukazuje
public array $loadedWidgets                          // klíče, které už byly staženy
```

### Stav drží hostitel, widgety žádný

Widget je obyčejný objekt, který se z `getWidgets()` staví znovu při každém
requestu, takže si přes round trip nemůže pamatovat nic. Hostitel může, a dělá
to: zapíše si, jaký filtr který widget ukazuje a které odložené widgety už byly
staženy, a obojí do widgetů vrátí v `getVisibleWidgets()` — až po orazítkování
klíčů a ještě před dotazem na viditelnost, aby closure ve `visible(fn () => …)`
mohla přečíst filtr, který uživatel zvolil.

Všechny čtyři metody výše odpovídají **markupem jednoho widgetu**, stejnou
`wire:partial` oblastí:

| Spouštěč | Volá | Odpoví |
| --- | --- | --- |
| `refreshWidget()` | `wire:poll` na obalu widgetu | ten widget, překreslený |
| `filterWidget()` | `wire:change` na selectu widgetu | ten widget, znovu vyřešený novým klíčem |
| `callWidgetAction()` | `wire:click` na tlačítku v hlavičce | ten widget, poté co akce doběhla — nebo plný render, když ho akce odstranila |
| `loadWidget()` | `wire:init` na obalu odloženého widgetu | ten widget, poprvé vykreslený |

Klíč, který nic nepojmenuje — widget skrytý od vykreslení stránky, widget bez
filtru, widget, který zmizel — nezařadí žádnou oblast a request spadne zpět na
plný render, místo aby odpověděl půlkou stránky.

### Rozhraní HasWidgets

```php
interface HasWidgets
{
    public function getWidgets(): array;
}
```

---

## Dashboard (deklarovaný, ne komponenta)

`WithWidgets` výše dává dashboard *dovnitř* Livewire komponenty, což stačí,
dokud ho nepotřebuje něco dalšího: komponenta se nedá zaregistrovat, vypsat
v menu ani použít na druhé stránce a její widgety nejsou odnikud jinud
dosažitelné.

`Dashboard` drží deklaraci sám, stejně jako `Resource` drží tabulku:

```php
use NyonCode\WireCore\Widgets\Dashboard;   // [tl! focus:start]

final class SalesDashboard extends Dashboard
{
    public function widgets(): array
    {
        return [
            StatsOverviewWidget::make()->stats([Stat::make('Tržby', '1.2M')]),
            ChartWidget::make()->heading('Posledních 30 dní')->type('line'),
        ];
    }

    public function columns(): int
    {
        return 3;
    }
}   // [tl! focus:end]
```

`php artisan make:wire-dashboard Sales` přesně tohle vygeneruje — do
`app/Dashboards/`, ze stubu, který jde publikovat a upravit:

```bash
php artisan vendor:publish --tag=wire-core::stubs
```

Publikovaný `stubs/dashboard.stub` má přednost před balíčkovým, stejně jako to
dělá Laravelí `stub:publish`.

Registruje se stejně jako resources:

```php
// config/wire-core.php
'dashboards' => [
    App\Dashboards\SalesDashboard::class,
],
```

### Kde je aplikace registruje

Dashboardy ani navigační skupiny nepatří žádnému resource ani dashboardu — jsou
to věci, které skládá aplikace. wire-core na to dodává provider, stejně jako to
dělá Cashier a Fortify:

```bash
php artisan vendor:publish --tag=wire-core::providers
```

Publikuje se jako `app/Providers/WireDashboardServiceProvider.php` a je tvůj
k úpravám: zaregistruj ho v `bootstrap/providers.php` a deklaruj v něm své
dashboardy i skupiny menu na jednom místě.

### Jak se vykresluje

`WirePanels\Resources\Pages\DashboardPage` ho vykreslí přesně tak, jako
`ListPage` vykresluje tabulku resource — skládá `WithWidgets`, takže mřížka,
pravidla viditelnosti i polling jednotlivých widgetů výše zůstávají beze změny:

```php
use NyonCode\WirePanels\Resources\Pages\DashboardPage;

class SalesDashboardPage extends DashboardPage
{
    protected static ?string $dashboard = SalesDashboard::class;
}
```

Obě cesty zůstávají rovnocenné: stránka, která si deklaruje vlastní
`getWidgets()` a žádný dashboard nejmenuje, funguje jako dřív. Stránka, která
nedeklaruje ani jedno, se odmítne vykreslit místo prázdné mřížky — prázdná se
čte jako „žádné widgety", ne jako chyba.

### Jak se dostane do menu

Dashboard se do menu dostane stejně jako resource — implementací
`ProvidesNavigation`:

```php
public static function navigation(): NavigationItem
{
    return NavigationItem::make('Prodej')->icon('outline:chart-bar')->group('insights');
}
```

`Workspace` přitom vůbec neví, co je dashboard. `DashboardRegistry` je
`RegistrySource` a workspace vypisuje to, co mu katalog podá — díky tomu může
menu míchat resources, dashboardy a cokoli, co aplikace zaregistruje později.
Router čte tentýž katalog, takže dashboard, který deklaruje `pages()`, zaroutuje
`Route::wireResources()` stejně jako resource.
Viz [Resources](../../panels/navigation.md).

### Dashboard API

| Metoda | Vrací | K čemu |
| --- | --- | --- |
| `widgets(): array` | `array<int, Widget>` | Widgety v pořadí rozložení. Povinné |
| `columns(): int` | `int` | Sloupce mřížky; výchozí 2 |
| `static key(): string` | `string` | Identita, odvozená z názvu třídy bez `Dashboard` |
| `static label(): string` | `string` | Lidský název; výchozí nadpis stránky |

---

<a id="authorization"></a>

## Přidání widgetu na dashboard, který nevlastníte

Dashboard z nainstalovaného [modulu](../../panels/modules.md) deklaruje svoje
widgety uvnitř balíčku, takže aplikace k nim přidá přes
[hook `widget.configuring`](../plugins/hooks.md):

```php
$manager->hook(Hook::WidgetConfiguring, function (WidgetConfiguringPayload $payload) {
    $payload->widgets[] = StatsOverviewWidget::make()->stats([Stat::make('Otevřené', '12')]); // [tl! focus]

    return $payload;
}, for: 'sales');
```

`for:` pojmenuje klíč, pod kterým se dashboard zaregistroval — `DashboardPage` jím
odpoví. Stránka, která si widgety deklaruje sama, neukazuje nic registrovaného
a zúží se podle třídy.

**Běží dřív, než se orazítkují klíče, a dřív, než seznam profiltruje viditelnost**
— a obojí je podstatné: klíč widgetu se odvozuje z jeho indexu v neprofiltrovaném
seznamu, takže widget přidaný později by žádný neměl (a poll tick by ho nedosáhl),
nebo by si vzal ten, na který už jiný widget slyší; a `visible()` přidaného widgetu
platí, místo aby se přeskočilo.

## Související

- [Widgety](index.md) — to, co se skládá
- [Panely: Stránky](../../panels/pages.md) — `DashboardPage`, komponenta, která deklarovaný dashboard vykreslí
- [Panely: Navigace](../../panels/navigation.md) — jak se dashboard dostane do menu
- [Panely: Moduly](../../panels/modules.md) — deklarace dashboardů oblasti spolu s jejími resourcy
