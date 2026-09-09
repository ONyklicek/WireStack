---
order: 10
summary: "Co mají všechny widgety společné — základní třídu, polling, autorizaci a kompletní API — a po kterém typu sáhnout."
---

# Widgety

Widget je jeden panel dashboardu: číslo, graf, malá tabulka nebo vlastní pohled.
Jsou to obyčejné PHP objekty vykreslující se do HTML, které hostitelská komponenta
skládá do responzivní mřížky — takže widget nic neví o stránce, na které je, a
stránka nic neví o tom, co widget kreslí.

Každý widget sdílí stejný fluent builder, takže nadpis, viditelnost, autorizace, column span, polling, filtry a odklad fungují identicky napříč všemi typy.

## Typy widgetů přehledně

| Widget | Třída | Nejlepší pro |
| --- | --- | --- |
| **Stats overview** | `StatsOverviewWidget` | KPI, počítadla a souhrnné metriky s volitelnými sparkliny |
| **Chart** | `ChartWidget` | Line, bar, pie a doughnut charty poháněné Chart.js |
| **Chart presety** | `LineChartWidget` / `PieChartWidget` / `DoughnutChartWidget` | Deklarativní `ChartWidget` presety (pie/doughnut ukazují legendu ve výchozím stavu) |
| **Bar chart** | `BarChartWidget` | Čistě CSS vertikální/horizontální sloupce (finance, systém) — bez JavaScriptu |
| **Progress** | `ProgressWidget` | Čistě CSS řádky výplně proti dráze — jak daleko je číslo ke svému cíli |
| **List** | `ListWidget` | Čistě CSS feed posledních záznamů nebo událostí, každý volitelně odkaz |
| **Table** | `TableWidget` (ve `wire-table`) | Pár řádků tabulky uvnitř dashboardové karty — bez toolbaru a stránkování |
| **Custom** | `CustomWidget` | Jakýkoli Blade pohled vykreslený jako widget |

> Míchejte typy widgetů volně uvnitř jednoho `WithWidgets` dashboardu — každý widget řídí svůj vlastní column span, viditelnost a interval obnovení. Viz [Dashboard layout](dashboards.md#dashboard-layout-withwidgets).

## Widget Base

Všechny widgety rozšiřují `NyonCode\WireCore\Widgets\Widget` — abstraktní třídu implementující `Htmlable`.

```php
use NyonCode\WireCore\Widgets\Widget;
```

Každý widget podporuje:

```php
->heading(?string $heading)          // titulek widgetu
->description(?string $description)  // podtitulek
->columnSpan(int|string $span)       // column span gridu (1-12, 'full')
->extraAttributes(array $attrs)      // vlastní HTML atributy
->hidden(bool|Closure $hidden)       // řízení viditelnosti
->visible(bool|Closure $visible)     // řízení viditelnosti
->permission(string $permission)     // autorizace přes Gate
->authorize(string $ability)         // autorizace přes Gate ability
->authorizeUsing(Closure $callback)  // vlastní autorizační callback
->filter(array $options, ?string $default = null)  // select, který řeší server
->lazy(bool $condition = true)       // odložit první render
->headerActions(array $actions)      // tlačítka v hlavičce widgetu
```

Widgety se vykreslují přes Blade pohledy a podporují `toHtml()` / `__toString()` pro přímý výstup.

---

## Polling

Všechny widgety podporují auto-obnovení přes Livewire polling.

```php
use NyonCode\WireCore\Widgets\Concerns\HasPolling;
```

### Použití

```php
StatsOverviewWidget::make()
    ->pollingInterval('30s')
    ->stats([...])

ChartWidget::make()
    ->pollingInterval('60s')
    ->pollingOnlyVisible()            // pozastavit polling, když je widget mimo obrazovku
```

### Polling API

```php
->pollingInterval(?string $interval)       // '5s', '10s', '30s', '60s', atd.
->getPollingInterval(): ?string
->isPolling(): bool
->pollingOnlyVisible(bool $only = true)    // pollovat jen když viditelné ve viewportu
->isPollingOnlyVisible(): bool
->getPollingDirective(): ?string           // vrací řetězec wire:poll direktivy
```

> **Polling je ve výchozím stavu vědomý si viditelnosti.** `pollingOnlyVisible` je výchozí `true`, takže widgety používají `wire:poll.visible` a pozastavují requesty, když jsou vyscrollovány mimo dohled. Zavolejte `->pollingOnlyVisible(false)` pro udržení obnovování mimo obrazovku.

<a id="a-tick-refreshes-the-widget-not-the-dashboard"></a>
### Tik obnoví widget, ne celý dashboard

Holé `wire:poll` je `$refresh` — Livewire vykreslí celou komponentu. Na dashboardu
to znamená, že jeden pollující widget překreslí každý další widget vedle sebe
i případnou tabulku na stránce: naměřeno na mřížce dvanácti widgetů **6,5 ms
a 57 219 B**, aby se doručilo 3 940 B jednoho widgetu.

Tik proto widget pojmenuje. Každý widget nese klíč a mřížka ten pollující ukotví:

```blade
<div wire:poll.30s.visible="refreshWidget('w1')">   {{-- [tl! focus] --}}
    <div wire:partial="widget-w1"> … </div>         {{-- [tl! focus] --}}
</div>
```

`WithWidgets::refreshWidget()` vykreslí jen ten widget a odpověď nese pouze jeho
markup. Není co nastavovat — takhle se pollující widget prostě chová.

Klíče se odvozují z pozice widgetu v `getWidgets()`, takže skrytí jednoho
widgetu ostatní nepřečísluje. Tam, kde se pořadí deklarace bude nejspíš měnit,
si klíč pojmenujte sami:

```php
StatsOverviewWidget::make()
    ->key('revenue')               // [tl! focus]
    ->pollingInterval('30s')
    ->stats([...])
```

Pokud klíč při dalším requestu nic nepojmenuje — widget byl skryt, přestal
pollovat nebo zmizel — tik se vrátí k plnému renderu, místo aby odpověděl půlkou
stránky.

> **Mřížka dodá bundle z wire-core, když nějaký widget polluje.** Kotva je jen
> atribut; kód, který oblast aplikuje, je v `wire-core-dropdown.js`. Dashboard bez
> dropdownu, modalu nebo tabulky nemá jiný důvod ho načíst, takže si o něj mřížka
> řekne — jinak by odpověď dorazila a na stránce by se nezměnilo nic.

---

<a id="filters"></a>

## Filtry

Widget může nést select, který zúží, co ukazuje — a výběr se řeší **na serveru**,
což je jediné místo, kde to jde, protože closure, která z klíče filtru dělá data,
je PHP.

```php
ListWidget::make()
    ->heading('Recent orders')
    ->filter(['week' => 'This week', 'month' => 'This month'], 'week')   // [tl! focus]
    ->items(fn (?string $filter) => $this->orders($filter))              // [tl! focus]
```

`filter()` je na základní třídě widgetu, takže ho má každý typ: datasety grafu,
položky seznamu i řádky progress nástěnky berou stejný tvar closure.

### Jak round trip funguje

Select zavolá `filterWidget()` na hostitelské komponentě. Hostitel si volbu
zapíše proti klíči widgetu, před renderem ji do widgetu vrátí a odpoví **jen
markupem toho jednoho widgetu** — stejnou `wire:partial` oblastí, jakou používá
tik pollingu:

```blade
<select wire:change="filterWidget('w0', $event.target.value)">   {{-- [tl! focus] --}}
    <option value="week" selected>This week</option>
    <option value="month">This month</option>
</select>
```

Tři důsledky, které stojí za to znát:

- **Výběr přežije.** Žije na hostiteli (`$widgetFilters`), ne na widgetu — widget
  se z deklarace staví znovu při každém requestu a nemůže si pamatovat nic.
- **Widget odpovídá jen za volby, které vyjmenoval.** Klíč i hodnota cestují v
  requestu, takže hodnota, kterou widget nikdy nenabídl, se ignoruje, místo aby
  skončila ve vašem `match`.
- **Widget bez filtru spadne zpět na plný render**, jako každé volání, které
  nezařadilo žádnou oblast.

> **Dřív to nedělalo nic.** Před 2.0 žil filtr jen na `ChartWidget` a řešil se v
> prohlížeči: Alpine `updateChart()` přiřadil `this.labels` a `this.datasets`
> zpátky na graf, se kterým byl sestaven, takže změna výběru překreslila
> identický graf a closure na datasety nikdy neběžela s ničím jiným než se svou
> výchozí hodnotou.

---

<a id="deferred-rendering"></a>

## Odložené vykreslení

Widget, jehož číslo stojí dotaz, na který nemá zbytek stránky čekat, může nejdřív
nakreslit placeholder a načíst se až potom:

```php
StatsOverviewWidget::make()
    ->heading('Lifetime revenue')
    ->lazy()                       // [tl! focus]
    ->stats([Stat::make('Revenue', $this->lifetimeRevenue())])
```

Mřížka vykreslí skeleton kartu o velikosti widgetu, `wire:init` zavolá na
hostiteli `loadWidget()` a odpověď nese markup toho jednoho widgetu jako
`wire:partial` oblast. Stránka je interaktivní dřív, než se číslo vrátí.

**Jednou načteno, načteno.** Hostitel si zapíše, které widgety už byly staženy,
takže pozdější tik pollingu, změna filtru nebo plný render nakreslí skutečný
widget, místo aby spadly zpátky na skeleton.

**Placeholder je karta, ne spinner** — mřížka už se rozhodla pro rozložení a
placeholder nižší než jeho widget způsobí, že stránka poskočí, až ten skutečný
dorazí. Nadpis se kreslí doopravdy, protože ho server už zná, a skeletonové
pruhy nesou `aria-busy` s živou oblastí oznamující čekání.

> **Sahejte po tom jako po posledním.** Pravidlo frameworku je udělat render
> *levným*, ne ho odložit: eager HTML zachová celý DOM, nepřidá žádnou latenci
> při otevření a nepotřebuje překreslení na klientovi. `lazy()` je pro widget,
> který je **pomalý**, ne pro ten, který je jen velký.

---

<a id="writing-your-own-widget"></a>

## Vlastní widget

Dva soubory — třída, která řeší stav, a Blade pohled, který ho kreslí — a
generátor napíše oba:

```bash
php artisan make:wire-widget Revenue
```

```text
app/Widgets/RevenueWidget.php
resources/views/widgets/revenue.blade.php
```

Třída rozšiřuje `Widget`, pojmenuje pohled ve `viewName()` a předá mu data z
`getViewData()`; `$widget` je uvnitř pohledu vždy v rozsahu. Všechno z této
stránky — polling, filtry, odklad, column span, autorizace — na něm funguje bez
jediného dalšího řádku.

```php
namespace App\Widgets;

use App\Models\Invoice;
use NyonCode\WireCore\Widgets\Widget;

class RevenueWidget extends Widget
{
    protected function viewName(): string      // [tl! focus]
    {
        return 'widgets.revenue';              // [tl! focus]
    }

    protected function getViewData(): array    // [tl! focus:start]
    {
        return [
            'value' => Invoice::whereMonth('paid_at', now()->month)->sum('total'),
        ];
    }                                          // [tl! focus:end]
}
```

Existující pohled se nikdy nepřepíše — opětovné spuštění generátoru bývá o tom
dostat zpátky třídu, a nahradit markup, který někdo napsal, není obchod, který
si generátor smí dovolit. Třídu přepíšete přepínačem `--force`.

Obě šablony jsou publikovatelné, takže aplikace může změnit, co generátor
vyrábí:

```bash
php artisan vendor:publish --tag=wire-core::stubs
```

Skončí v `stubs/wire-core/`, jmenně oddělené podle balíčku, aby si dva balíčky se
`stubs/dashboard.stub` nepřepsaly navzájem — a generátor je čte odtamtud.
(`stubs/` se zkouší jako druhé, kvůli souboru položenému tam ručně.)

Pro widget, který je jen Blade pohled bez vlastního stavu, sáhněte raději po
[`CustomWidget`](custom.md) — tam není co psát.

---

<a id="header-actions"></a>

## Akce v hlavičce

Widget může nést tlačítka ve své hlavičce — obnovit číslo, exportovat řádky,
označit frontu jako přečtenou:

```php
use NyonCode\WireCore\Actions\Action;

StatsOverviewWidget::make()
    ->heading('Open tickets')
    ->headerActions([                                              // [tl! focus:start]
        Action::make('refresh')
            ->label('Refresh')
            ->icon('outline:arrow-path')
            ->action(fn () => $this->recount()),
        Action::make('report')->label('Full report')->url(route('tickets.report')),
    ])                                                             // [tl! focus:end]
    ->stats([Stat::make('Waiting', $this->waiting())])
```

`headerActions()` je stejný slovník, jaký používá hlavička schema sekce a
infolist entry — jeden vlastník, `Foundation\Concerns\HasActions` — takže akce
napsaná pro jednu z těch ploch tu funguje beze změny. Kreslí je každý typ
widgetu, každý ve své hlavičce.

### Co widgetová akce je a co není

Spustí svůj **callback** na serveru a widget se pak překreslí — ta jedna oblast,
jako u tiku pollingu. Není to *životní cyklus* akce:

| Funguje | Nefunguje |
| --- | --- |
| `->action(fn () => …)` | `->requiresConfirmation()` |
| `->url(…)`, `->openUrlInNewTab()` | `->form([...])`, `->slideOver()`, `->modal()` |
| `->label()`, `->icon()`, `->color()`, `->tooltip()` | `->wizard()` |
| `->hidden()` / `->visible()` | mountování a cokoli, co se vrací do namountované akce |

Akce, která svůj vlastní widget *odstraní* — tlačítko „Dismiss", jehož callback
překlopí to, co čte `visible()` widgetu — se zodpoví plným renderem. Partial umí
element nahradit, ne smazat.

Je to stejný obchod, jaký uzavírají akce infolist entry, a ze stejného důvodu:
widget se z deklarace staví znovu při každém requestu, takže tlačítko nese
*jméno*, ne closure, a není do čeho se vracet. Akce, která se musí nejdřív
zeptat, patří na stránku — hostitel skládající `WithActions` vlastní modal host —
nebo dovnitř [`CustomWidget`](custom.md), jehož pohled vykreslí
`<x-wire-actions::button>`.

### Jak se vůbec dostane k Action

Stojí za jeden odstavec, protože je to vrstvové pravidlo frameworku v malém.
`Widgets` a `Actions` jsou sourozenecké moduly a ani jeden nesmí importovat ten
druhý, takže nic v hostiteli widgetů neví, co je `Action`: widget najde akci
podle jména a odpoví kontraktem `Foundation\Contracts\ActionContract`, a
`Foundation\Contracts\RunsComponentActions` — registrovaný v kontejneru,
implementovaný na straně Actions — je to, co callback spustí. Widget v balíčku,
který modul Actions nevyžaduje, se pořád zkompiluje; jeho tlačítka jen nemají co
spouštět.

---

<a id="customisable-dashboards"></a>

## Přizpůsobitelné dashboardy

Dashboard může nechat každého uživatele rozhodnout, které widgety na něm jsou, v
jakém pořadí a jak velké. **Ve výchozím stavu je to vypnuté** a dashboard, který
nic neřekne, se chová přesně jako dnes — žádné úložiště se nečte a layoutem je
deklarace.

```php
class SalesDashboard extends Dashboard
{
    public function customisable(): bool   // [tl! focus]
    {
        return true;                       // [tl! focus]
    }

    public function widgets(): array
    {
        return [/* … */];
    }
}
```

Layout se ukládá podle `(klíč dashboardu, uživatel)` přes totéž
[úložiště preferencí](../../table/advanced.md#prepinani-sloupcu), jaké používá
rozložení sloupců tabulky — konfiguruje se ale zvlášť, pod `wire-core.preferences`,
protože jestli layout dashboardu stojí za řádek v databázi je vlastní otázka. Bez
nakonfigurovaného driveru dashboard dál funguje, jen si nic nepamatuje.

### Co se ukládá

Seřazený seznam toho, co je *umístěné*, každá položka se šířkou a výškou:

```php
['widgets' => [
    ['key' => 'revenue', 'w' => 2, 'h' => 1],
    ['key' => 'orders',  'w' => 1, 'h' => 2],
]]
```

Pozice plyne z pořadí a velikost ze dvou čísel; výsledek poskládá CSS Grid.
Žádné souřadnice, takže není co kolidovat ani jakou díru přeskládávat — a taky
není jak widget přibít na konkrétní buňku. `w` je rozpon sloupců (1–4), `h`
rozpon řádků (1–6).

### Pravidla, která stojí za to znát

- **Seznam je to, co je umístěné.** Deklarovaný widget, jehož klíč v něm není,
  na dashboardu není — je k dispozici k vrácení. Jedno pravidlo dělá dvě práce
  místo příznaku `hidden` vedle pořadí, který by s ním mohl nesouhlasit.
- **Prázdný seznam není totéž co žádný layout.** Nic uloženého znamená, že
  rozhoduje deklarace — to musí vidět uživatel, který se dashboardu nikdy
  nedotkl. Uložený prázdný seznam znamená, že někdo všechno sundal, a má na
  prázdný dashboard nárok.
- **Layout je preference, nikdy oprávnění.** Widget, který layout umístí a
  `visible()` nebo `permission()` skryje, zůstane skrytý.
- **Klíč, který deklarace už nemá, se zahodí.** Layout nedokáže vykouzlit zpátky
  widget, který vlastník dashboardu přejmenoval nebo odebral.
- **Bag přichází z requestu**, takže velikost mimo mřížku se ořízne a klíč
  uvedený dvakrát se umístí jednou, na své první pozici.

### Aby výška něco znamenala, musí být řádek

Dlaždice s `row-span-2` potřebuje řádky známé výšky, takže mřížka, která takovou
dlaždici má, dostane výchozí výšku řádku a dlaždice si scrollují vlastní obsah
místo aby řádek natahovaly. Mřížka, kde nic řádky nepřesahuje, tu výchozí výšku
nikdy nedostane — karta je tam pořád vysoká podle obsahu, přesně jako dřív.

### Jak se přeskládá

Úpravy jsou **režim**, ne trvalá sada úchytů. `Upravit` odhalí úchyty a
stepper­y velikosti; `Uložit` uspořádání zachová, `Zrušit` ho zahodí, `Zpět na
výchozí` layout úplně zapomene a vrátí to, co dashboard deklaruje.

Metody dává hostitelský trait; chrome kolem mřížky je tvoje, protože patří
tomu, kdo dashboard vykresluje, ne mřížce:

```blade
@if($editingWidgets)
    <button wire:click="saveWidgetLayout">Save layout</button>       {{-- [tl! focus] --}}
    <button wire:click="cancelEditingWidgets">Cancel</button>        {{-- [tl! focus] --}}
    <button wire:click="resetWidgetLayout">Reset to default</button> {{-- [tl! focus] --}}
@else
    <button wire:click="startEditingWidgets">Customise</button>      {{-- [tl! focus] --}}
@endif

@include('wire-core::widgets.widget-grid', [
    'widgets' => $this->getVisibleWidgets(),
    'columns' => 2,
    'editing' => $editingWidgets,
])
```

**Dokud nedáš Uložit, nic se nezapíše.** Uspořádání žije po dobu režimu v draftu
na komponentě — a právě to dává `Zrušit` co zahodit. Živý drag by zapisoval při
každém puštění, takže náhodné chycení by přepsalo layout, se kterým byl někdo
spokojený, a nešlo by to vzít zpět.

**Drag nestojí žádný JavaScript.** Je to `x-sort`, vlastní Alpine plugin
Livewiru, který si s sebou nese SortableJS i ducha při tažení. Dlaždici umísťuje
server: puštění nahlásí „tenhle klíč, tahle pozice", draft se přeskládá a mřížka
se překreslí. Buňky nesou `wire:key`, takže je morph páruje podle klíče, ne
podle pozice, a puštěné DOM se s odpovědí nemůže rozejít.

**Změna velikosti jsou steppery, ne tažení za roh.** Rozpon je 1–4 sloupce a 1–6
řádků, takže dosažitelných velikostí je jedenáct a tlačítko říká, kterou dostaneš.

### API režimu úprav

```php
->startEditingWidgets(): void      // otevřít režim, z aktuálního uspořádání
->saveWidgetLayout(): void         // zachovat ho
->cancelEditingWidgets(): void     // zahodit draft
->resetWidgetLayout(): void        // zapomenout layout; zpět na deklaraci
->moveWidget(string $key, int $position): void
->resizeWidget(string $key, int $width, int $height): void
public bool $editingWidgets
public array $widgetLayoutDraft
```

Na dashboardu, který se nepřihlásil, všechny nedělají nic — jsou to veřejné
Livewire metody, takže je prohlížeč může zavolat, kdy se mu zachce.

### Zásobník

Widgety, které si uživatel může dát na dashboard, ale nemá je tam. Objeví se s
režimem úprav a je s mřížkou **jeden drag** — vytažená dlaždice se vrátí do
zásobníku, vtažená přistane na dashboardu. Tlačítka dělají totéž pro toho, kdo
ukazovací zařízení nepoužívá.

```php
StatsOverviewWidget::make()
    ->key('revenue')
    ->heading('Revenue')
    ->group('Money')            // [tl! focus]
    ->sizes([[2, 1], [4, 2]])   // [tl! focus]
    ->stats([Stat::make('Total', $this->revenue())])
```

**`group()` je nadpis v zásobníku a nic víc.** Na dashboardu samotném nic
neseskupuje — tam rozhoduje uživatelovo pořadí. Widgety bez skupiny jdou první,
v jednom prostém běhu: jedna skupina je nadpis nad vším, což je nadpis, který nic
neříká.

**`sizes()` zúží velikosti, ve kterých se widget nabízí**, jako dvojice
`[šířka, výška]`. Řádek se sparklinou není dlaždice 1×1 a jediné číslo není 4×6 —
a nechat to uživatele zjistit tažením je horší než mu to nenabídnout. První
dvojice je velikost, se kterou widget přistane. Bez deklarace zůstává celá
mřížka: 1–4 sloupce a 1–6 řádků.

Dvojice jsou *nabídka*, ne slib o úložišti: layout, který dorazí s velikostí
mimo ně, se pořád jen ořízne na mřížku, protože uložený layout může přežít
deklaraci, která ho utvářela.

**„Odebraný" a „dostupný" je týž stav.** Nikde se nezaznamenává, že byl widget
sundán — prostě už není v layoutu, a to ho vrací do zásobníku. Jedno pravidlo
dělá dvě práce, takže si nemohou odporovat.

Widget, který skrývá politika, se nikdy nenabídne: zásobník s něčím, co po
přidání zmizí, je horší než zásobník, který to nenabízí.

### API zásobníku

```php
->group(?string $group)                    // nadpis v zásobníku, pod kterým se widget nabízí
->sizes(array $sizes)                      // [[w, h], …] — velikosti, které smí mít
->getGroup(): ?string
->getSizes(): array
->getDefaultSize(): array                  // první nabídnutá dvojice, nebo [1, 1]
```

na hostiteli:

```php
->placeWidget(string $key, int $position): void   // přidat, nebo přesunout, když už tam je
->removeWidget(string $key): void
->getAvailableWidgets(): array                    // skupina => widgety, pro zásobník
->widgetGridData(?int $columns = null): array     // všechno, co view mřížky potřebuje
```

`widgetGridData()` je jedno volání místo pěti klíčů k poskládání, protože čtyři z
nich se spolu musí shodnout: mřížka bez `available` nakreslí prázdný zásobník a
mřížka bez `trayGroup` dá zásobník a mřížku do různých drag skupin, takže mezi
nimi dlaždice nepřejde. Ani jedno není nikde chyba.

```php
public function render()
{
    return view('wire-core::widgets.widget-grid', $this->widgetGridData(2));
}
```

### Hotové ovládání

Metody si můžeš volat, odkud chceš — ale běžný případ je hotový:

```blade
@include('wire-core::widgets.partials.widget-layout-controls')
@include('wire-core::widgets.widget-grid')
```

Obojí na dashboardu, který nikdo přeskládat nesmí, nevykreslí nic, takže je
bezpečné je includovat bez podmínky a není co držet v souladu. `DashboardPage` ve
wire-panels už oboje includuje, takže deklarovaný dashboard s `customisable()` je
přeskládatelný bez jediného vlastního view.

Samostatný balíček na to není. Úložiště je wire-core, stránka wire-panels a
widgety patří aplikaci — modul by nevlastnil nic, a „volitelné" už znamená
`customisable()` a konfigurace driveru.

### Co je potřeba, aby to celé fungovalo

```php
// config/wire-core.php — nebo to nech být a layouty budou žít jen po dobu stránky
'preferences' => ['default' => 'database'],
```

```bash
php artisan vendor:publish --tag="wire-core::migrations"   # pro database driver
php artisan migrate
```

```php
class SalesDashboard extends Dashboard
{
    public function customisable(): bool { return true; }

    public function widgets(): array
    {
        return [
            StatsOverviewWidget::make()->key('revenue')->heading('Revenue')  // [tl! focus]
                ->group('Money')->sizes([[2, 1], [4, 2]])                    // [tl! focus]
                ->stats([Stat::make('Total', $this->revenue())]),
        ];
    }
}
```

Každý widget na přizpůsobitelném dashboardu potřebuje vlastní `key()`. Bez něj se
klíč odvodí z *pozice* widgetu, a uložený layout adresuje widgety podle klíče —
takže vložení widgetu navrch deklarace by způsobilo, že každý uložený layout
popisuje jiné widgety než včera, a stránka se přitom vykreslí. Přizpůsobitelný
dashboard to raději odmítne.

---

<a id="dashboard-layout-withwidgets"></a>

## Autorizace

Widgety dědí autorizaci z `HasVisibility`, který používá trait `HasAuthorization`. Detaily viz [Autorizace](#autorizace).

```php
StatsOverviewWidget::make()
    ->permission('view-dashboard-stats')
    ->stats([...])

ChartWidget::make()
    ->authorize('view-revenue-chart')
    ->heading('Revenue')

CustomWidget::make()
    ->authorizeUsing(fn ($user) => $user->hasRole('manager'))
    ->view('dashboard.manager-panel')
```

Neautorizované widgety jsou automaticky vyloučeny z `getVisibleWidgets()`.

---

<a id="widget-api-reference"></a>

## Reference Widget API

### Widget (základní třída)

```php
Widget::make(): static                              // statická factory
->heading(?string $heading): static
->getHeading(): ?string
->description(?string $description): static
->getDescription(): ?string
->key(string $key): static
->getKey(): ?string
->usesPartialAnchor(): bool                         // true, když ho cílí tik, načtení nebo filtr
->render(): View
->toHtml(): string
```

Zděděné z traitů:

```php
->columnSpan(int|string $span): static       // HasColumnSpan
->getColumnSpan(): int|string

->rowSpan(?int $span): static           // HasRowSpan — 1–6, null je jeden řádek podle obsahu
->getRowSpan(): ?int
->spansRows(): bool

->group(?string $group): static             // nadpis v zásobníku, pod kterým se nabízí
->sizes(array $sizes): static               // [[w, h], …] — velikosti, které smí mít
->getGroup(): ?string
->getSizes(): array
->getDefaultSize(): array

->extraAttributes(array $attrs): static      // HasExtraAttributes
->getExtraAttributes(): array

->pollingInterval(?string $interval): static // HasPolling
->pollingOnlyVisible(bool $only = true): static

->lazy(bool $condition = true): static       // CanBeLazy
->isLazy(): bool

->filter(array $options, ?string $default = null): static   // HasWidgetFilter
->activeFilter(?string $filter): static
->applyFilter(?string $filter): static       // ignoruje klíč, který widget nenabízí
->getFilterOptions(): ?array
->getActiveFilter(): ?string
->hasFilter(): bool

->items(array|Closure $items): static        // HasWidgetItems — fn (?string $filter): array
->emptyState(?string $message): static
->getItems(): array
->hasItems(): bool
->getEmptyState(): string

->headerActions(array $actions): static      // HasActions — array<ActionContract>
->actions(array $actions): static            // tentýž seznam pod kanonickým jménem
->action(ActionContract $action): static     // přidat jednu
->getActions(): array                        // viditelné, v pořadí deklarace
->hasActions(): bool
->getFieldAction(string $name): ?ActionContract
->getRenderableActions(): array          // ty, které mají kam odeslat — stačí klíč
->hasRenderableActions(): bool
->getActionExpression(ActionContract $action): ?string

->hidden(bool|Closure $hidden): static       // HasVisibility + HasAuthorization
->visible(bool|Closure $visible): static
->permission(?string $permission): static
->authorize(?string $ability): static
->authorizeUsing(?Closure $callback): static
->isVisible(): bool
->isAuthorized(): bool
```

## Blade komponenty

```blade
<x-wire::widget-grid :widgets="$widgets" :columns="2" />
```

Pohledy, které jednotlivé widgety vykreslují, publikovatelné přes
`vendor:publish --tag=wire-core::views`:

```text
wire-core::widgets.stats-overview
wire-core::widgets.chart
wire-core::widgets.bar-chart
wire-core::widgets.bar-chart.vertical-finance
wire-core::widgets.bar-chart.vertical-system
wire-core::widgets.bar-chart.horizontal-system
wire-core::widgets.progress
wire-core::widgets.list
wire-core::widgets.custom
wire-core::widgets.widget-grid
wire-core::widgets.widget-cell
wire-core::widgets.partials.widget-header
wire-core::widgets.partials.widget-filter
wire-core::widgets.partials.widget-actions
wire-core::widgets.partials.widget-placeholder
wire-core::widgets.partials.list-item
```

## V této sekci

| Stránka | Co pokrývá |
| --- | --- |
| [Statistiky](stats.md) | `StatsOverviewWidget` a hodnotový objekt `Stat` — čísla s trendem |
| [Grafy](charts.md) | `ChartWidget`, `BarChartWidget` a `ChartItem` |
| [Progress](progress.md) | `ProgressWidget` a hodnotový objekt `ProgressItem` — výplň proti cíli |
| [Seznamy](list.md) | `ListWidget` a hodnotový objekt `ListItem` — krátký feed záznamů nebo událostí |
| [Tabulky a vlastní pohledy](custom.md) | `TableWidget` a `CustomWidget` |
| [Dashboardy](dashboards.md) | Skládání widgetů na komponentě a deklarace dashboardu jako vlastníka |

## Související

- [Panely: Stránky](../../panels/pages.md) — `DashboardPage`, stránka, která deklarovaný dashboard vykreslí
- [Akce](../actions/index.md) — tlačítka, která widget může nést
- [Autorizace](../../start/authorization.md) — gate, na kterou se ptá viditelnost widgetu
- [Tabulky](../../table/overview.md) — co vkládá tabulkový widget
