---
order: 95
summary: Verzování, dvě živé linie, co změnila 2.0 a kroky, po kterých je upgrade nuda.
---

# Návod k upgradu

Jak bezpečně přecházet mezi verzemi Wire a kde hledat breaking changes.

---

## Verzování

Ekosystém Wire je jedno monorepo vydávané jako [čtrnáct
balíčků](project-map.md#balicky), rozdělených z jednoho tagu. Jejich verze se
pohybují v zámku, takže je instalujte a omezujte jako celek — `wire-table` z
jednoho minoru vedle `wire-core` z jiného není kombinace, která se testuje.

Wire se od **1.0** drží sémantického verzování: breaking change počká na major,
minor jen přidává. Živé jsou dvě linie:

| Linie | Livewire | Stav |
| --- | --- | --- |
| `2.x` | 4.x | aktuální — sem přistávají novinky |
| `1.x` | 3.x | udržovaná — jen opravy |

Žádné vydání neběží na obou verzích Livewiru, a přesně proto je 2.0 major.
Omezte caret na linii, na které jste:

```jsonc
// composer.json
"require": {
    "nyoncode/wire-core":   "^2.0",
    "nyoncode/wire-forms":  "^2.0",
    "nyoncode/wire-table":  "^2.0",
    "nyoncode/wire-panels": "^2.0"
}
```

Nebo si vezměte celý stack jako jednu závislost, což je smyslem suite balíčku a
co drží sadu v zámku bez čtyř řádků, na které je potřeba myslet:

```jsonc
"require": {
    "nyoncode/wire-suite": "^2.0"
}
```

---

## Požadavky

| Závislost | Podporováno |
|------------|-----------|
| PHP | 8.2, 8.3, 8.4 |
| Laravel | 12.61+, 13.12+ |
| Livewire | 4.x |
| Tailwind CSS | 3.x nebo 4.x (témování poloměru a odsazení vyžaduje 4.x — viz [Vzhled → Rozsah](theming.md#rozsah)) |
| `nyoncode/laravel-package-toolkit` | ^2.4 |

Před upgradem ověřte, že je vaše aplikace splňuje.

---

## Livewire 4 (2.0)

**Verze 2.0 vyžaduje Livewire 4.** Linie 1.x zůstává na Livewire 3 a dál dostává
opravy; žádné vydání neběží na obou. Nejdřív povyšte Livewire, ověřte, že vaše
vlastní komponenty fungují, teprve pak posuňte Wire.

```bash
composer require livewire/livewire:^4.0
php artisan optimize:clear
composer update "nyoncode/wire-*"
```

Vlastní [upgrade guide Livewiru](https://livewire.laravel.com/docs/upgrading)
pokrývá kód vaší aplikace. Čtyři jeho změny zasahují do toho, co Wire vykresluje
za vás, a jen poslední z nich po vás něco chce.

**`liveOnBlur()` znamená pořád totéž.** Od Livewire 4 říká `wire:model.blur`, kdy
*klient* synchronizuje vlastní stav, ne kdy mluví se serverem — samotné `.blur` se
na server nedostane vůbec. Wire proto emituje `wire:model.live.blur`, takže pole
deklarované přes `->liveOnBlur()` (nebo `->validateOnBlur()`, které to zapíná) se
chová přesně jako dřív. Měnit není co, ledaže jste `wire:model.blur` napsali ručně
v přepsaném view pole — tam doplňte `.live`.

**Vícesouborové uploady se slučují samy.** Livewire 4 si nový upload přislučuje ke
stávajícím položkám vícesouborového pole sám, kdežto 3.x je nahrazoval. Wire tuhle
mezeru dřív zaceloval a už to nedělá. Pokud jste totéž sloučení napsali ve vlastním
`updated()` hooku, odstraňte ho — jinak se stávající položky započtou dvakrát.

**Endpointy Livewiru se přesunuly.** URL jsou nově `/livewire-{hash}/…` místo
`/livewire/…`, kde se hash odvozuje z vašeho `APP_KEY`. Pravidla firewallu, bypass
na CDN a cokoli dalšího, co ten prefix porovnává ručně, je potřeba upravit. Vlastní
asset routes Wiru to nezasahuje — pod tím prefixem nikdy nebyly.

**Alpine dodává Livewire, stále.** Livewire 4 dodává Alpine 3.16. Stejně jako u 3.x
Alpine neinstalujte ani nestartujte samostatně.

---

## Markup řádku a částečné renderování (2.0)

Dvě změny. Jedna je dobrovolná a můžete ji ignorovat, dokud ji nebudete chtít; ta
druhá se stala každé tabulce a stojí za deset minut pozornosti, pokud stylujete,
skriptujete nebo testujete proti markupu tabulky.

### Řádky každé tabulky se skládají jinak

Tělo řádku se dřív rozkládalo v Blade uvnitř řádkové smyčky. Teď se skládá v PHP
z markupu, který Blade zkompiluje jednou pro tabulku (`Support\RowRenderer`, a
`Support\CardRenderer` pro stacked karty). Výsledný markup je tentýž, se dvěma
rozdíly:

- **zmizely morph markery jednotlivých řádků.** Livewire vkládá dvojici
  `<!--[if BLOCK]><![endif]-->` kolem každého `@if` a `@foreach`, který
  zkompiluje, a podmínky řádkové smyčky jich emitovaly 459–999 B na řádek —
  848–1035 B na řádek i s whitespace mezi nimi, a 1 347 B na stacked kartu. Nic
  v DOM na nich nezáviselo kromě samotného morphu Livewire;
- **podmíněné děti řádku teď nesou `wire:key`**, což je to, co je při morphu
  páruje místo těch markerů: `ctx-{key}` na teleportovaném kontextovém menu,
  `sel-{key}` na výběrové buňce, `exp-{key}` na rozbalovači podřádků a
  `act-{key}-{name}` na každém akčním tlačítku vykresleném **se** záznamem.
  Tlačítko bez záznamu — hlavičková akce, hromadná akce, prázdný stav — se
  nezměnilo.

**Co zkontrolovat.** Cokoli, co prochází děti řádku podle pozice nebo počítá
komentářové uzly: CSS `:nth-child()`, které předpokládalo stabilní počet dětí,
řetězec `querySelector`, který markery překračoval, browser test, který na ně
asertoval. Běžné selektory — `[data-row-key]`, `[data-testid]`, `[data-column]`,
`tbody tr` — se nezměnily a zůstávají podporovanou cestou dovnitř.

**Pokud jste publikovali views tabulky**, tohle je ta změna, která umí kousnout
potichu. `tables/index.blade.php` už tělo řádku vůbec neobsahuje: rozdělilo se do
`partials/data-region.blade.php` a řádek s kartou se renderují z PHP.
Publikovaná kopie z 1.x dál funguje — Laravel jí dá přednost — ale drží si starou
cenu a žádné z nového chování, a `rowPartials()` nepřevezme. Publikujte znovu,
nebo lépe, kopii smažte a konfigurujte:

```bash
php artisan vendor:publish --tag=wire-table::views --force
```

### `rowPartials()` — dobrovolné a ve výchozím stavu vypnuté

Zápis může odpovědět oblastmi, kterými pohnul, místo překreslení celé tabulky:

```php
$table->rowPartials()
```

Na stránce s 25 sloupci a 20 řádky stojí uložení buňky 49,3 ms a 556 kB při
běžném renderu a 3,2 ms a 26 kB jako jeden řádek. Pro tabulku, která si o to
neřekne, se nemění nic — nevykreslí se žádná kotva a neutratí se žádný bajt.

**Co za to platíte** je, že překreslený řádek si drží svou pozici: editace, která
by záznam pod aktuálním řazením posunula, ho nechá na místě až do dalšího plného
renderu. Na široké editovatelné mřížce je to ta správná výměna, a proto je to
dobrovolné, ne zapnuté.

Viz [Pokročilé → Řádkové partials](../table/advanced.md#radkove-partials), kde je,
čím zápis odpoví u kterého tvaru tabulky, a jak tytéž kotvy slouží `poll()`
a `live()`.

---

## Per-user úložiště preferencí se přestěhovalo do wire-core (2.0)

`TablePreferenceDriver` a tabulka `table_preferences` jsou nově
`Foundation\Preferences\Contracts\PreferenceDriver` a `wire_preferences`, ve
**wire-core**.

```php
use NyonCode\WireTable\Preferences\Contracts\TablePreferenceDriver;              // [tl! --]
use NyonCode\WireCore\Foundation\Preferences\Contracts\PreferenceDriver;        // [tl! ++]

use NyonCode\WireTable\Preferences\Drivers\DatabasePreferenceDriver;             // [tl! --]
use NyonCode\WireCore\Foundation\Preferences\Drivers\DatabasePreferenceDriver;  // [tl! ++]
```

**Proč se stěhovalo.** Layout dashboardu má týž tvar jako zapamatované sloupce
tabulky — malý JSON bag podle plochy a uživatele — a widgety žijí ve `wire-core`,
na kterém `wire-table` závisí, takže se z widgetu na tabulkové úložiště nedalo
dosáhnout. Napsat druhé by byla druhá implementace jedné myšlenky.

**Na konfiguraci tabulky se nezměnilo nic.** `config('wire-table.preferences')`
je pořád místo, kde se driver tabulky vybírá, se stejnými aliasy i stejným
fallbackem pro hosty. Přesunuly se jen třídy, na které aliasy míří, a dodávaná
konfigurace je upravená s nimi.

**Tři věci k udělání**, a jen pokud jste se jich dotkli:

1. **Publikujte migraci znovu.** Je teď ve wire-core:
   `vendor:publish --tag="wire-core::migrations"`. Spuštění existující
   `table_preferences` **přejmenuje** na `wire_preferences` a `table_key` na
   `surface_key` — nikdo nepřijde o uložený layout — a na čisté instalaci tabulku
   založí.
2. **Vlastní úložiště** implementuje `PreferenceDriver` místo
   `TablePreferenceDriver`. Ty čtyři metody se nezměnily; první argument je
   `$surfaceKey` místo `$tableKey`, protože je to teď klíč tabulky *nebo*
   dashboardu.
3. **Publikovaný `config/wire-table.php`** míří aliasy `drivers` na staré
   namespacy. Opravte tři `use` řádky.

`Table::preferenceDriver()`, `rememberColumns()` i uložené pohledy zůstávají
nedotčené a celá sada testů `wire-table` prochází beze změny — což je skutečný
důkaz, že přesun zachoval chování.

Jedna malá ztráta, jen u session úložiště: prefix session klíče je `wire.` místo
`wire-table.`, takže layout, který měl uživatel v session neuložený, se jednou
resetuje. Řádky v databázi migrace převede.

---

## `TableWidget` se přestěhoval do wire-table a začal kreslit (2.0)

`WireCore\Widgets\TableWidget` je nově `WireTable\Widgets\TableWidget`.

```php
use NyonCode\WireCore\Widgets\TableWidget;    // [tl! --]
use NyonCode\WireTable\Widgets\TableWidget;   // [tl! ++]
```

**Ten přesun je důvod, proč to funguje.** Stará třída si callback z `->table(...)`
uložila a nikdy ho nezavolala: vykreslila kartu s nadpisem a prázdným `<div>` a
jediný volající `getTableCallback()` v celém frameworku byl test ověřující
getter. Nebyla to nedbalost, ale struktura — widgety žijí ve `wire-core`,
tabulkový engine ve `wire-table`, a table na core závisí, takže se z core na
engine nedalo dosáhnout.

Teď kreslí sloupce a řádky, každou buňku přes `Column::renderCell()` a dotaz
plánuje `TableQueryService`, takže badge, formáty měn i cesty přes relace
vypadají přesně jako na plné tabulce.

**Dvě věci si zkontrolujte u sebe.** Callback potřebuje zdroj dat, protože se
teď opravdu spouští:

```php
TableWidget::make()
    ->limit(5)                                    // [tl! ++]
    ->table(fn (Table $table) => $table
        ->model(Order::class)                     // [tl! ++]
        ->columns([TextColumn::make('reference')]))
```

A karta kreslí **5 řádků**, pokud `->limit()` neřekne jinak — stránkování, které
by zbytek zachytilo, tu není.

**Co záměrně nemá**: toolbar, hledání, filtry, stránkování, hromadné ani řádkové
akce. Dashboardová karta, které tohle naroste, je tabulka převlečená za widget;
postavte za ni raději stránku s `WithTable`.

---

## `Widget::lazy()` teď něco odkládá (2.0)

Metoda přežila; změnilo se, že dělá to, co říká. Před 2.0 žádná view widgetu ten
příznak nečetla — nestálo za ním `wire:init`, žádná intersect direktiva ani
island — takže widget označený jako lazy se vykreslil celý jako kterýkoli jiný.

```php
StatsOverviewWidget::make()->lazy()   // kreslilo se všechno hned
StatsOverviewWidget::make()->lazy()   // nakreslí placeholder a pak se načte
```

**Není co měnit, ale zkontrolujte, co jste označili.** Volání, které bylo dřív
bez účinku, je teď odklad: mřížka vykreslí skeleton kartu, `wire:init` zavolá na
hostiteli `loadWidget()` a odpověď nese markup toho widgetu jako `wire:partial`
oblast. Z widgetu, který ve skutečnosti pomalý není, volání smažte — pravidlo
frameworku je udělat render levným, ne ho odložit, a eager HTML nestojí žádnou
latenci při otevření.

**Co to umožnilo.** Odklad po jednotlivých widgetech potřebuje oblast, kterou
server umí pojmenovat, a `@island` jí uvnitř `@foreach` být nemůže: Blade vytvoří
jedno tělo islandu na jeden výskyt direktivy a to tělo proměnnou cyklu nikdy
nedostane. Partial je obyčejný atribut vybraný serverem — proto polling už uměl
odpovědět na tik jednoho widgetu jedním widgetem. Odklad je stejný mechanismus s
jiným spouštěčem.

Lazy na úrovni komponenty dál funguje a pro celou mřížku je pořád ten správný
nástroj: `<livewire:my-dashboard lazy />`.

Viz [Widgety → Odložené vykreslení](../core/widgets/index.md#odlozene-vykresleni).

---

## `ChartWidget::filter()` se řeší na serveru a má ho každý widget (2.0)

Rozbalovací nabídka filtru se dřív řešila v prohlížeči: `<select>` navázaný na Alpine
property a `updateChart()`, který přiřadil `this.labels` a `this.datasets`
zpátky na graf, se kterým byl sestaven. Změna výběru tedy překreslila identický
graf a closure na datasety nikdy neběžela s ničím jiným než se svou výchozí
hodnotou.

Výběr teď putuje na hostitele, ten closury znovu vyřeší a odpoví jen tím jedním
widgetem.

```php
ChartWidget::make()
    ->filter(['week' => 'This week', 'month' => 'This month'])
    ->datasets(fn (?string $filter) => $this->revenue($filter))   // teď se opravdu spustí znovu
```

**Ve vašem kódu není co měnit.** Dvě věci stojí za to znát:

- Closure teď běží při Livewire requestu, ne jednou na render stránky, takže musí
  být bezpečná při opakovaném volání — vždycky být měla, jen to nic nezkoušelo.
- `filter()`, `activeFilter()`, `getFilterOptions()`, `hasFilter()` a
  `getActiveFilter()` se přesunuly z `ChartWidget` na základní třídu widgetu
  (`HasWidgetFilter`). Má je teď každý widget. Nic se nedostalo mimo dosah; graf
  dál odpovídá na stejná volání.

**Pokud jste přepsali `widgets/chart.blade.php`, publikujte ho znovu.** Změnily
se v něm tři věci a pohled je místo, kde se všechny tři potkávají:

- `<select>` je sdílený partial (`widgets.partials.widget-filter`);
- obal nese `wire:key` obsahující aktivní filtr. Ten klíč je nosný — Alpine
  nikdy znovu nevyhodnotí `x-data` na elementu, který už inicializoval, takže
  bez něj morph atribut jen záplatuje a Chart.js kreslí dál starou sérii;
- Alpine factory `wireChart` bere teď **čtyři** argumenty, ne šest.
  `filterOptions` a `activeFilter` z ní zmizely, protože o filtru už v
  prohlížeči nic nerozhoduje, a `updateChart()` odešlo s nimi — byla to ta
  metoda, která přiřazovala tytéž dvě pole zpátky na graf.

```blade
x-data="wireChart(@js($type), @js($labels), @js($datasets), @js($filterOptions), @js($activeFilter), @js($options))"  {{-- [tl! --] --}}
x-data="wireChart(@js($type), @js($labels), @js($datasets), @js($options))"  {{-- [tl! ++] --}}
```

Publikovaný pohled ponechaný na starém volání předá `$options` tam, kde factory
teď čte `$filterOptions`, takže se graf postaví úplně bez nastavení — vykreslí se,
a vykreslí se špatně. Nic na to neupozorní, a právě proto to stojí za ty dvě
minuty.

---

## `InvalidChartDataException::notChartItems()` je teď `InvalidWidgetDataException` (2.0)

`items()` přestalo být vlastností grafu: bar charty, progress widgety i seznamové
widgety berou sérii přes jednoho vlastníka (`HasWidgetItems`), a kdyby některý z
nich házel výjimku pojmenovanou po grafech, byl by to název, který lže.

```php
catch (InvalidChartDataException $e)    // [tl! --]
catch (InvalidWidgetDataException $e)   // [tl! ++]
```

Obě rozšiřují `InvalidArgumentException` a obě implementují `WireException`,
takže `catch` na kterékoli z nich se to netýká. `InvalidChartDataException` dál
existuje a dál se hází pro neznámý typ grafu, neznámou variantu a procento
položky mimo rozsah.

Spolu s přesunem vlastníka získalo `items()` na každém widgetu, který kreslí
sérii, i tvar s closure — `->items(fn (?string $filter) => …)` — a právě to dává
filtru na bar chartu smysl.

---

## Views polí: Alpine tělo se přesunulo do bundlu (2.0)

Sedm typů polí mělo celý svůj Alpine controller inlinovaný v markupu jako `x-data`
objekt, takže stránka se šesti date pickery poslala tytéž stovky řádků šestkrát.
Těla jsou teď registrované `Alpine.data()` factory.

**Nemusíte dělat nic, pokud si nepřepisujete některý z těchto views**:
`DateTimePicker`, `TimePicker`, `Select` (searchable combobox), `Tags`, `Rating`,
`RichEditor`, `MarkdownEditor`. Pokud ano, zkopírované `x-data` už neexistuje —
zavolejte factory s konfiguračním objektem:

```blade
{{-- předtím --}}
<div x-data="{ open: false, value: $wire.entangle('data.at'), hasDate: true, /* …300 řádků… */ }">   {{-- [tl! --] --}}

{{-- potom --}}
<div x-data="wireDateTimePicker({                    {{-- [tl! ++:4] --}}
    state: $wire.entangle('data.at'),
    hasDate: true,
    typeable: true,
})">
```

Dvě věci zůstávají v markupu záměrně. **`state`**, protože `$wire.entangle`
a `@entangle` jsou Alpine *magics* a jsou ve scope jen uvnitř `x-data` výrazu —
do bundlu se přesunout nemohou. A jakýkoli **řetězec ze serveru**, který
controller potřebuje, například přeložený titulek pro `prompt()`; ten přichází
jako config.

Třetí pravidlo vás dostane, když portujete vlastní pole: Blade `@if` uvnitř těla
se musí stát runtime větví. Factory se kompiluje jednou a sdílí ji každá instance,
takže *tvar* objektu už nemůže nic měnit — jen jeho chování.

Controllery jsou v `wire-forms-fields.js`, searchable-select combobox
v `wire-core-dropdown.js` (patří core: ten partial includuje sedm povrchů napříč
forms i table). Oba jsou registrátory, takže se načítají s dokumentem, ne na
vyžádání. Každý převedený view navíc includuje
`wire-forms::partials.field-assets`, protože
[`@wireStackScripts`](getting-started.md#javascriptove-assety) je aditivní — aplikace,
která direktivu nikdy nepřidá, musí controller dostat stejně, jinak se `x-data`
vyhodnotí proti prázdnému registru a pole tiše nedělá nic.

---

## Odstraněno: každý shim označený pro 2.0 (2.0)

Linie 1.x vezla sadu metod a tříd, kterým v docblocku stálo *„Will be removed in
v2.0“*. Tohle je to vydání, takže jsou pryč — volání teď vyhodí
`BadMethodCallException` (nebo třída nebude nalezena) místo zápisu deprecace.
Náhrady existují po celou dobu linie 1.x a každá je jen přejmenování:

| Odstraněno | Použijte místo toho |
| --- | --- |
| `Action::hiddeLabel()` | `Action::hideLabel()` — starý název byl překlep |
| `ActionHalt::modalHeading()` | `ActionHalt::heading()` |
| `ActionHalt::modalDescription()` | `ActionHalt::description()` |
| `ActionHalt::body()` | `ActionHalt::description()` — halt teď mluví slovníkem modalu |
| `ActionHalt::modalIcon()` | `ActionHalt::icon()` |
| `ActionHalt::modalSubmitLabel()` | `ActionHalt::submitLabel()` |
| `ActionHalt::modalCancelLabel()` | `ActionHalt::cancelLabel()` |
| `ActionHalt::modalWidth()` | `ActionHalt::width()` |
| `ActionHalt::formValidation()` | `ActionHalt::validation()` |
| `Table::polling()` | `Table::poll()` |
| `TableNotification` | `Notification` |
| `TableNotificationManager` | `NotificationManager` |
| `confirmTableAction()`, `executeConfirmedAction()`, `closeConfirmationModal()`, `confirmBulkAction()`, `getConfirmationModalData()` | API halt modalu — viz [Životní cyklus a fronty](../core/actions/lifecycle.md#halt-vykonavani) |
| `WireForms\Components\Layout\{Section,Fieldset,Grid}` | `WireCore\Foundation\Schema\{Section,Fieldset,Grid}` |

Dalších pět bylo deprecated během 1.x, aniž by pojmenovaly vydání, a jdou stejným
tahem — každé je přejmenování se stejným chováním za sebou:

| Odstraněno | Použijte místo toho |
| --- | --- |
| `Table::rowContextMenu([...])` | `Table::recordActions([Action::make('edit')->onContextMenu(), …])` |
| `TextInputColumn::formatForSave()` | `dehydrateState()` |
| `TextInputColumn::formatAfterLoad()` | `hydrateState()` |
| `Table::flattenSubRows()` / `isFlattenSubRows()` | `subRowsDefaultExpanded()` / `isSubRowsDefaultExpanded()` |
| `toggleFlattenMode()` | `toggleAllRowExpansion()` |
| legacy magické properties (`$this->tableSearch`, `$tableFilters`, `$flattenMode`, …) | `$this->tableState->get('search')` / `->set(...)`, nebo `Table::queryString()` pro stav v URL |
| `TableQueryingPayload::$forceSortColumn` / `$forceSortDirection` | klíč `force_sort_column` na polním hooku `table.querying` |

**Legacy properties jsou to, co je potřeba zkontrolovat.** `WithTable` dřív
odpovídal na `$this->tableSearch` a dvacet sourozenců přes `__get`/`__set` a mapoval
je na stavové cesty. Jsou pryč, takže komponenta, která některou čte, dostane od
Livewiru „property does not exist“ — včetně pole `$queryString`, které je jmenuje,
což je přesně to, co dokumentace pro stav v URL dřív ukazovala. Podporovaná cesta
je [`Table::queryString()`](../table/advanced.md#perzistence-stavu-v-url), která si
načtené hodnoty i ověří:

```php
protected $queryString = ['tableSearch' => ['as' => 'q']];   // [tl! --]
public function table(Table $table): Table                    // [tl! ++]
{                                                             // [tl! ++]
    return $table->queryString();                             // [tl! ++]
}                                                             // [tl! ++]
```

**Navázání kontextového menu jako record action udělá z tabulky grid.** To je
smyslem té náhrady — menu se stane dosažitelným z klávesnice — ale znamená to, že
každý řádek nese roli a tabindex, které klávesová vrstva potřebuje, tedy zhruba
o 260 bajtů na řádek víc než myší ovládaný seznam. `ActionGroup` už se v seznamu
menu nepřijímá: naváž její akce po jedné, vykreslí se tytéž položky.

**Řádky s `modal*()` se týkají `ActionHalt` a ničeho jiného.** *Akce* má
`modalHeading()`, `modalDescription()`, `modalWidth()` i zbytek dál — ty jsou
kanonické a nemění se. Aliasy vlastního kratšího slovníku nesl jen halt objekt:

```php
$action->halt()
    ->modalHeading('Warnings detected')          // [tl! --]
    ->modalDescription('Continue anyway?');      // [tl! --]
    ->heading('Warnings detected')               // [tl! ++]
    ->description('Continue anyway?');           // [tl! ++]
```

**Pět potvrzovacích metod už bylo prázdných.** Zapsaly deprecaci a vrátily se;
halt modal běží přes `*WithData()` od chvíle, kdy přistál rámcový zásobník. Jejich
odstranění nemůže změnit chování — jen z tichého nicnedělání dělá hlasitou chybu,
což je přesně to, co si místo volání, které je pořád používá, zaslouží.

**Podtřídy layoutu ve formulářích jsou jediné odstranění, které mění vykreslení** —
a mění ho k lepšímu. `WireForms\Components\Layout\Section` existovala jen proto,
aby vyměnila form-specifickou kopii view sekce, a ta kopie zaostala za kanonickou:
žádné hlavičkové akce, žádné `aside()`, vlastní mapa sloupců. Sekce formuláře
postavená z `Foundation\Schema\Section` dostane všechno tohle a k tomu pozadí
plochy, které sekce v infolistu měla už dřív:

```php
use NyonCode\WireForms\Components\Layout\Section;   // [tl! --]
use NyonCode\WireCore\Foundation\Schema\Section;    // [tl! ++]
```

Nic dalšího se nemění — stejná třída, stejné fluent API, stejné vnořování.

---

## Deprecated shimy traitů končí (2.0)

Devět aliasů traitů pod `NyonCode\WireCore\Concerns\` bylo odstraněno. Každý
byl `class_alias()` shim s poznámkou `@deprecated … Will be removed in v2.0`
a tohle je to vydání.

Všechny ukazovaly na trait stejného jména pod `Actions\Concerns\`, takže
migrace je řádek s importem a nic víc:

```php
use NyonCode\WireCore\Concerns\HasIcons;          // [tl! --]
use NyonCode\WireCore\Actions\Concerns\HasIcons;  // [tl! ++]
```

Těch devět jmen: `HasButtonStyles`, `HasColor`, `HasDynamicProperties`,
`HasIcons`, `HasKeyboardShortcut`, `HasLifecycle`, `HasLoadingState`,
`HasModal`, `HasVisibility`.

Samotné traity zůstávají beze změny — stejné metody, stejné chování. Pokud jste
z `WireCore\Concerns\` nikdy neimportovali, není co dělat.

Vedle nich padá `NyonCode\WireTable\Concerns\TableQueryService` — týž druh
aliasu, zbylý po přesunu té třídy do `Services\`, se stejnou poznámkou
`Will be removed in v2.0`:

```php
use NyonCode\WireTable\Concerns\TableQueryService;   // [tl! --]
use NyonCode\WireTable\Services\TableQueryService;   // [tl! ++]
```

Dokumentace nikde nežádá, abyste ji vytvářeli sami, takže vás to nejspíš nepotká.

**Jedna výjimka, která stojí za to.** U barev sáhněte po
`Foundation\Concerns\HasColor`: ten je kanonickým vlastníkem a
`Actions\Concerns\HasColor` je sám jen jeho tenký alias.

---

## Tabulka umí číst i odjinud než z Eloquentu (2.0)

Nic, co máte napsané, se nemění. `->model()` i `->query()` fungují přesně jako
dřív a každá closure akce si drží svůj `Model $record`.

Nové je, že tabulka teď čte přes `DataSource`, takže jí jdou předat řádky, které
nejsou v databázi:

```php
use NyonCode\WireTable\Data\CollectionDataSource;

$table->dataSource(new CollectionDataSource([         // [tl! focus]
    ['id' => 1, 'name' => 'Ada', 'score' => 90],      // [tl! focus]
]));                                                  // [tl! focus]
```

Taková tabulka je **omezená**: zdroj deklaruje, na co umí odpovědět, a žádost
o něco, co odmítl, vyhodí `UnsupportedQueryAspectException` místo tichého vrácení
řádků, které ignorovaly půlku dotazu. U kolekce to znamená žádné raw SQL výrazy,
žádné cesty přes relace, žádné agregace přes subquery a žádné cursor stránkování.

Celá plocha je v [Zdrojích dat](../table/data-sources.md). Pokud používáte jen
Eloquent tabulky, není co dělat.

---

## Minimální verze závislostí (1.17)

**Laravel 10 a 11 končí.** Verze 1.17 přesunula JavaScriptové bundly z package
route do reálných souborů pod `public/vendor` a kód, který je tam zrcadlí, žije
v `nyoncode/laravel-package-toolkit` — vedle deklarace `hasAssets()` a publish
tagu, jehož je čtecí stranou. Toolkit stojí na
`illuminate/support ^12.61.1|^13.12.0` a minimum závislosti je i vaše minimum:
aplikace pod ním balíčky Wire nenainstaluje, ať v jejich vlastním
`composer.json` stojí `^12.0`. Nejdřív povyšte Laravel, pak Wire.

**Constraint toolkitu je `^2.4`.** Přímo si ho nevyžadujete, takže v běžném
případě ho `composer update "nyoncode/wire-*"` posune se vším ostatním a není co
řešit. Viditelný je jen ve dvou situacích:

- váš `composer.json` `nyoncode/laravel-package-toolkit` jmenuje — protože na něm
  stavíte vlastní balíček, nebo ze starého pinu — a drží ho pod 2.4. Composer pak
  hlásí jako neinstalovatelné balíčky Wire, ne toolkit jako starý, takže ten
  constraint rozšiřte na `^2.4` jako první.
- běžíte na Octane. Memo assetů, které je jinak vázané na jeden request a tady přežívá celý
  worker, se na `RequestTerminated` zahazuje přes `PublishedAssets::flush()`
  z toolkitu, a 2.4 je první vydání, které ho nese. Pod ním worker, který přežije
  deploy, dál emituje `?id=<mtime>` z minulého vydání a `wire:navigate` si nových
  bundlů nikdy nevšimne.

---

## Kroky upgradu

1. **Přečtěte si changelog.** Zkontrolujte `CHANGELOG.md` pro verze, které
   přeskakujete, zejména jakoukoli sekci **Breaking Changes**.

2. **Aktualizujte balíčky.**

   ```bash
   composer update "nyoncode/wire-*"
   ```

3. **Znovu zkontrolujte publikované soubory.** Pokud jste publikovali konfiguraci,
   pohledy nebo překlady, vaše kopie se **neaktualizují** automaticky. Porovnejte
   je s novými verzemi balíčků a zapracujte relevantní změny:

   - `config/wire-*.php`
   - `resources/views/vendor/wire-*/…`
   - `lang/vendor/wire-*/…`

   Čím méně pohledů přepisujete, tím méně je zde ke sladění — viz
   [Vzhled → Přepis pohledů](theming.md#prepis-pohledu).

4. **Vyčistěte cache a přebuildujte assety.**

   ```bash
   php artisan view:clear
   php artisan config:clear
   npm run build
   ```

5. **Spusťte testovací sadu.** [Testovací sada](testing.md) je nejrychlejší
   způsob, jak odchytit breaking change ve vlastních formulářích a tabulkách.

---

## Registrace, routing a menu

Jeden seam nahradil tři přímá čtení: menu, router i ⌘K paleta teď čtou
[`Catalog`](../panels/navigation.md#catalog-api), takže jedna registrace obslouží
všechny tři. Čtyři jména se s tím přesunula a žádné si nenechalo alias — tahle
linie ještě nevyšla a compat vrstva pro přejmenování, na kterém nikdo nevisel,
je náklad bez čtenáře.

| Dřív | Teď |
| --- | --- |
| `Core\Resources\Contracts\NavigationSource` | `Foundation\Registration\Contracts\RegistrySource` |
| `NavigationSource::navigableClasses()` | `RegistrySource::registeredClasses()` |
| `WirePanels\Resources\Contracts\ProvidesResourcePages` | `WireCore\Foundation\Routing\Contracts\ProvidesPages` |
| `WirePanels\Resources\Contracts\ConfiguresResourceRoutes` | `WireCore\Foundation\Routing\Contracts\ConfiguresRoutes` |
| `WirePanels\Routing\RoutePage` | `WireCore\Foundation\Routing\RoutePage` |

Ta tři routovací jména se přesunula **dolů**, do `wire-core`, aby stránky mohl
deklarovat i `Dashboard` — dashboard teď `Route::wireResources()` zaroutuje stejně
jako resource. URL konvence se nepřesunula: `ResourceRoutes`, tvar URL i jména
`wire.{key}.{page}` zůstávají ve `wire-panels`.

Změnily se dva konstruktory a oba se resolvují z kontejneru, takže se to týká jen
kódu, který si je stavěl ručně: `Workspace` bere `Catalog`, své navigační skupiny
a `ResolvesPageUrls`; `GlobalSearch` bere `Catalog` místo `ResourceRegistry`.

**Co teď můžete smazat.** Položka menu nese URL stránky svého klíče a výsledek
hledání URL svého záznamu, obojí doplněné z klíče — takže ručně psaná mapa
`klíč => url`, kterou si držela každá aplikace, může pryč:

```php
// dřív — mapa vedle rout, a ta kopie, co zastará
<a href="{{ $urls[$key] }}">                                  // [tl! --]
url: route('orders.show', $record),                           // [tl! --]

// teď
<a @if($item->getUrl()) href="{{ $item->getUrl() }}" @endif>  // [tl! ++]
// toGlobalSearchResult() nepředává url vůbec                    [tl! ++]
```

Explicitní `url:` nebo `->url()` pořád vyhraje — pro řádek, který vede někam, kam
konvence nedosáhne.

**Routing je pořád opt-in** a `Route::wireResources()` ve vašem route souboru
zůstává referenční cestou. Novinka vedle ní je
[`wire-panels.routes`](configuration.md#panels) — tytéž argumenty skupiny předané
jednou — a [zóny](../panels/routing.md#zony), víc mount pointů nad jedním katalogem.
Obojí je vypnuté, dokud to nezapnete.

---

## Výběr a klávesová gesta

Z výběru v tabulce se stala plnohodnotná sada gest, ne jen sloupec zaškrtávátek
(viz [Výběr řádků](../table/selection.md)). Při upgradu zkontrolujte čtyři věci.

**1. Všechna gesta nad řádkem jsou opt-in — `->gestures()`.** Z výběru se stala
plnohodnotná sada gest: `Shift`/`mod` kliky pro rozsahy, tažení po sloupci se
zaškrtávátky, které nabere celý blok, a z klávesnice šipky, `Space`, `Shift`+šipky
a `mod`+`A`. Nic z toho není zapnuté, dokud si o to tabulka neřekne — každé z nich
totiž mění chování tabulky vůči návštěvníkovi, který ji ovládat nezamýšlel: řádky
jdou do pořadí tabulátoru, označuje se aktivní řádek, tažení začne vybírat
a modifikovaný klik přestane být klikem.

Tabulkám, které to chtějí, přidejte jedno volání:

```php
->gestures()
->selectable()
```

nebo, pokud je celý projekt back office:

```php
// config/wire-table.php
'defaults' => ['gestures' => true],
```

Co změna *neovlivní*: zaškrtávátka, oba ovladače „vybrat vše“ i bulk bar fungují
beze změny a tabulka, která si o gesta neřekla, nemontuje delegovaný controller
vůbec. Stejně tak kontextové menu pod pravým tlačítkem a fill handle — o oboje
jste si stejně museli říct sami.
Šest schopností a jak je kombinovat najdete ve [Vrstvě gest](../table/gestures.md).

**2. `->onKey()` na navigační klávese nově vyhodí výjimku.** Dřív se tiše
zahodila, takže akce prostě nikdy nevystřelila. Pokud takovou vazbu máte, byla
to už dřív mrtvá větev — přemapujte ji na volnou klávesu:

```text
Enter  Space  ArrowUp  ArrowDown  Home  End  PageUp  PageDown  ContextMenu  F10  ?
```

`Backspace` zůstává k dispozici a nově funguje i jako alias klávesy `Delete`.

**3. Rozsahová gesta už neopouštějí režim „vše odpovídající“.** Když je vybráno
„vše, co odpovídá filtru“, je uložený seznam seznamem *výjimek* — takže rozsah
přes `Shift`+šipku ho nově **odznačí**, místo aby celý výběr zúžil na jednu
stránku. Pokud výběr čtete přímo, počítejte s tím, že `getSelectedRecordKeys()`
v tomto režimu záměrně vrací `[]`; použijte `selectedRecordsQuery()` nebo
`eachSelectedRecord()`.

**4. Přepublikujte view tabulky, pokud jste ho přepsali.** Gesta potřebují
markup, který zkompilovaný JavaScript hledá, a publikovaná kopie
`resources/views/vendor/wire-table/tables/index.blade.php` ho mít nebude. View
nese kontraktní značku, takže zastaralá kopie spadne hlasitě v konzoli prohlížeče
místo toho, aby tiše vybírala špatné řádky:

```bash
php artisan vendor:publish --tag=wire-table::views --force
```

Své úpravy pak naneste znovu na nový soubor. Pokud jste view přepsali jen kvůli
vzhledu, bývá [Theming](theming.md) menší cesta.

**5. Akce nad záznamem, které byly jen chováním, se na mobilní kartě nově
vykreslí jako tlačítko.** Telefon nemá dvojklik, pravý klik ani hover, kterým by
se jeden nebo druhý dal objevit — akce navázaná jen na gesto tak byla po složení
tabulky nedosažitelná. Nově se na kartě vykreslí jako obyčejné tlačítko, a jen
tam; desktopová tabulka se nemění. Nic se nezdvojí: akce už přítomná
v `->actions()` i akce povýšená přes `->alsoInRowActions()` dá právě jedno
tlačítko a fallbacková tlačítka se počítají do `->collapseActionsOnMobile()`.
Vypnout lze pro konkrétní tabulku:

```php
->recordActionButtonsOnMobile(false)
```

---

<a id="javascript-assets"></a>
## JavaScriptové assety

Alpine controllery Wire si nově deklaruje každý balíček sám a dají se vypsat
z jednoho místa ve vašem layoutu. Při upgradu udělejte dvě věci.

**1. Přidejte `@wireStackScripts` do `<head>` layoutu.**

```blade
<head>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @wireStackScripts {{-- [tl! focus] --}}
</head>
```

Je to aditivní — každý povrch si svůj bundle stále načte sám, takže aplikace bez
direktivy funguje dál. Ale je to právě ono, co opraví komponenty umírající po
návštěvě přes `wire:navigate` (`wireRecordSelection is not defined`, mrtvé
rozbalovací nabídky, šedý scrim přes tabulku): cesta cachovaného Zpět/Vpřed v Livewire
nečeká na nově injektované `<head>` skripty a imunní je jen bundle, který už
v dokumentu byl. Viz
[Začínáme → JavaScriptové assety](getting-started.md#javascriptove-assety).

Pokud si vaše aplikace tohle dřív obcházela `@include`-ováním partialů balíčků
v layoutu, tyhle includy smažte a použijte direktivu — cesty k partialům jsou
interní a direktiva se s nimi stejně deduplikuje.

**2. `window.Sortable` už se neposkytuje.** SortableJS je zkompilovaný do bundlu
`wire-sortable`, takže `config('wire-sortable.sortablejs_cdn')` je nově ve výchozím
stavu `null` a žádný CDN skript se nenačítá. Řazení to neovlivní — drag controller
používá zabundlovanou kopii a globál nikdy nečte.

Týká se to jen **vašeho vlastního** kódu, pokud na existenci toho globálu spoléhal.
Buď si o skript řekněte zpět:

```php
// config/wire-sortable.php
'sortablejs_cdn' => 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js',
```

nebo si SortableJS zabundlujte sami:

```js
// resources/js/app.js
import Sortable from 'sortablejs';
window.Sortable = Sortable;
```

Nic dalšího se nemění: konfigurační klíč po nastavení pořád funguje a aplikací,
které ho už nastavené mají, se změna nedotkne.

---

## Hledání breaking changes

`CHANGELOG.md` je zdroj pravdy. Breaking changes jsou vyznačeny pod nadpisem
**Breaking Changes** u každého vydání, často s migrační tabulkou před/po.
Například vydání `0.1.0` přesunulo akce a notifikace z
`NyonCode\WireTable\…` do `NyonCode\WireCore\…`; changelog vypisuje každou
přesunutou třídu, takže můžete `use` příkazy upravit hromadným najít-a-nahradit.

Pokud třída nebo metoda zmíněná v této dokumentaci po upgradu už neexistuje,
byla pravděpodobně přesunuta nebo přejmenována — hledejte původní název
v `CHANGELOG.md`.

---

## Viz také

- [Začínáme](getting-started.md) — požadavky a instalace
- [Konfigurace](configuration.md) — publikovatelná konfigurace
- [Vzhled](theming.md) — udržování přepisů pohledů na minimu
- [Řešení potíží](troubleshooting.md) — problémy, které se objeví po aktualizaci
