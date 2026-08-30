---
title: V2 — kde to stojí a čím pokračovat
date: 2026-08-30
scope: V2.0–V2.3 (hotové), ADR 0025 (rozpracované), V2.4/V2.5/V2.6 (na řadě)
status: progress record — aktualizovat na konci každého běhu
---

# V2: stav a další krok

Jeden soubor, do kterého se dá vstoupit po týdnech a vědět, co je hotové, proč
se některé věci neudělaly a co je na řadě. Detailní odůvodnění jsou v plánech
a ADR, na které se odkazuje.

---

## 1. Hotové

### V2.0 — DataSource kontrakt ✅

Všechny tři podfáze. Exit kritérium splněno: tabulka běží nad `Collection`
zdrojem bez modelu i builderu, Eloquent cesta beze změny chování.
[ADR 0019](../decisions/0019-data-source-contract.md) je ACCEPTED.

Odchylky od plánu a jejich důvody:
[`v2.0-datasource-implementation.md`](v2.0-datasource-implementation.md) § V2.0.c.

### ADR 0025 — vrstvy uvnitř core ✅ (částečně)

[ADR 0025](../decisions/0025-core-module-layers.md): core se **neštěpí** na
balíčky, hranice modulů hlídá `ModuleLayersTest`. Dluh spadl z 19 zakázaných
hran na 12, cyklus `Actions ↔ Modals` je pryč.

**Nedokončené kroky 8 a 10** — viz §3.

### V2.1 — hotová ✅

Fáze A: čtrnáct kroků (třináct extrakcí + audit base, který skončil „nepřesouvat").
Fáze B: uzavřená — dva nové typy sloupců, dva zamítnuté. Detaily níže.

| # | Metoda | Bylo → je | Vlastník |
|---|---|---|---|
| 1 | `validateTableCell` | 46 → 21 | `Services\CellEditPipeline::validateAgainstRecord()` |
| 2 | `resolveTableSummaries` | 54 → 35 | `Services\SummarySet` |
| 3 | `buildSubRowGrandTotalQuery` | 44 → 33 | `Services\SubRowQuery` + `Support\SubRowRelation` |
| 4 | `buildTableQuery` | 62 → 42 | `Services\TableQueryEvents` |
| 5 | `queueChangedRowPartials` | 67 → 47 | `Support\RowStamps` |
| 6 | `queueSatellitePartials` | 46 → 0 | `Support\TablePartials` |
| 7 | `mountWithTable` | **110 → 19** | `Concerns\TableStateSchema::initialFor()` |
| 8 | `updatedTableState` | 59 → 53 | `Support\StateInvalidation` |
| 9 | Table: whole-row interaction | 15 metod | `Concerns\HasRecordActions` |
| 10 | Table: akce — kolekce a prezentace | 27 metod | `Concerns\HasTableActions` |
| 11 | Table: akce na telefonu | 19 metod | `Concerns\CollapsesActionsOnMobile` |
| 12 | Table: stacked karty | 9 metod | `Concerns\StacksOnMobile` + `MobileCard::shapeSignature()` |
| 13 | WithTable: grupování | 4 metody | `Concerns\CanGroupRecords` + `Support\GroupPartitions` — **našlo defekt**, viz §2 |
| 14 | B-1: audit `Column` base | — | **žádný přesun**; audit našel defekt v responzivní buňce, viz §2 |
| 15 | Tři zkompilované buňky | — | testy na zapečené podmínky; bez defektu, viz §2 |
| 16 | `@php` bloky ve views | 3 views → 1 vlastník | `Support\SubRowPanel` — **našlo defekt**, viz §2; tři ze čtyř jmenovaných views „nedělat" |

**Fáze B — ERP typy sloupců: uzavřená.** `MoneyColumn` a `MetricColumn` hotové
(+ rozšířený kanonický `FormatsState::money()`, nový `Foundation\View\Sparkline`,
sdílený `Concerns\RendersAsFigure`, EN/CS docs). `StatusColumn` ani
`RelationColumn` **se dělat nebudou** — oba by byly prázdné podtřídy, ověřeno.
B-1 (audit base) hotový a jeho závěr je „nepřesouvat", viz §2. **Tím V2.1 končí.**

Plus **tři host kontrakty** — `Contracts\{ShowsTableColumns, ExpandsTableRows,
SummarisesTable}` — které poprvé umožnily testovat render větev bez Livewire
komponenty (DoD 2, částečně splněno).

**Čtyři ostré defekty, které ta práce našla** — všechny byly v produkci, žádný
neshodil jediný test, a všechny čtyři mají symptom jen v prohlížeči:

| Defekt | Symptom |
|---|---|
| `cachedGroupPartitions` se nezneplatňovala v `setPage()` / `setTableCursor()` | Po stránkování v jednom requestu skupina na obrazovce sečetla **0**, skupina, která tam nebyla, ukazovala svoje staré číslo |
| `$max = max($data) ?: 1` v sparkline | Každá řada končící na nule (burndown k cíli) zmáčknutá, nikdy nedosáhla nahoru |
| Rovná řada / jedno čtení v sparkline | Stabilní číslo vypadalo jako spadlé na nulu; jedno čtení nenakreslilo nic |
| `renderMobileCell()` vracela holý text | Sloupec ztratil odkaz na záznam, ikonu a **kopírovací tlačítko** na telefonu, zatímco na desktopu je měl |

Plus dvě chybějící schopnosti a jeden lživý test: `money(null)` s koncovou
mezerou, přesnost měny řízená jejím *zápisem* (`'Kč'` vs `'CZK'`), a test jménem
„formats money values correctly for CZK (0 decimals)", který asertoval
`toContain('CZK')` a tvrdil opak reality.

**Čísla:** `WithTable` 2880 → 2632 → **2486** ř. (99 → 95 metod, 1689 → 1580
řádků v tělech) · `Table` 2935 → 2730 → 2061 → **1880** ř. (190 → **135** metod,
1340 → **891** řádků v tělech).

**Nejdelší metoda v celém souboru má 19 řádků** (`getSubRowCell`). Nad 25 řádků
už není nic — to byla celou dobu ta metrika a je splněná.

Akční cluster je uzavřený: 61 metod ve třech concernech, jak měření předpovědělo.
U karet se ale měření spletlo v druhou stranu, viz §2.

Nic se nezměnilo než umístění — reflektovaný veřejný a protected povrch `Table`
je 295 signatur před i po, bez rozdílu. Jediné, co přibylo, je
`MobileCard::shapeSignature()`, a to je přesun těla, ne nové chování.

Dvě vazby jsou vědomé a pojmenované v docblocích:

- `CollapsesActionsOnMobile` volá privátní `composeRowActions()` a
  `renderEmptyStateActions()` z `HasTableActions`. To je ten smysl — telefon
  ukazuje *tytéž* akce, které složil desktop, takže je nesmí skládat sám.
- `StacksOnMobile` se ptá `MobileCard` na tvar karty místo aby si ho počítal.
  Tvar je vlastnost karty; slot přidaný v `MobileCard` tak nemůže být zapomenut
  v cache klíči.

### V2.2 — utažení execution seamů ✅

**V tomhle souboru do 2026-08-30 vůbec nebyla** — §1 skákala z V2.1 rovnou na
V2.3, přestože `v2-master-plan.md` ji má v sekvenci mezi nimi. Nebylo to
rozhodnutí „odložit"; vypadla z evidence. Odtud i to, že se dala z velké části
zavřít za jeden běh: jedna ze tří částí byla hotová už předem a nikdo to
neověřil.

| Krok | Plán | Výsledek |
|---|---|---|
| S1 `app()`/`new` v execution seamech | injektovat deps do `SaveHandler` + `ActionPipeline` | **nedělat** — premisa („testy nemůžou mockovat") je nepravdivá, viz §2 |
| S2 typed dispatch primární | zrušit dvojí dispatch na lifecycle bodech | **jinak** — dvojí dispatch stojí 0,163 µs a nechává se; skutečná redundance byla jinde a byl v ní **ostrý defekt**, viz §2 |
| S3 hydration seamy | audit směru dat | **bez mezery** — oba směry mají kanonického vlastníka a pojmenovaný pár (ADR 0021). Audit ale našel **osiřelou dvojici** `Hydrator`/`MutationPipeline`, viz §2 a §3 |

### V2.3 — owner vrstva ✅

Všech pět kroků plánu: `R` (kontrakty + registr), `P` (čtyři stránky),
`RM` (`RelationManager` pod vrstvou), `W` (`NavigationItem` + `Workspace`)
a `I` (boost `describe-resource`).

| Krok | Co vzniklo |
|---|---|
| R.1 | `Resources\Contracts\{DescribesResource, ProvidesResourceTable, ProvidesResourceForm, ProvidesResourceInfolist}` + `Resources\Concerns\DescribesRecords` |
| R.2 | `Managers\ResourceRegistry` + `config('wire-table.resources')` + singleton binding |
| P (4/4) | `ListPage`, `CreatePage`, `EditPage`, `ViewPage` + `BelongsToResource` / `ResolvesOneRecord` concerny, `ResourcePageException`, views, EN/CS překlady |
| RM | `Contracts\ProvidesRelationManagers` + `Concerns\EmbedsRelationManagers`; Edit/View je vkládají. **Žádný BC break** — přímý mount funguje beze změny |
| W | `Core\Resources\Navigation\NavigationItem` (nad `HasLabel`/`HasIcon`/`HasVisibility`) + `Core\Resources\Workspace` |
| I | boost `Support\ResourceReflector` + `Mcp\Tools\DescribeResource`, zaregistrovaný v `WireBoostServer` |
| prototyp | workbench `InvoiceResource` (všech 5 kontraktů) + 4 stránky + relation manager + `verify-resource-pages.mjs` (14/14 v prohlížeči) — **našel 2 defekty**, viz §2 |
| ADR 0020 | `PROPOSED` → **ACCEPTED**, všechny čtyři otevřené otázky zavřené |
| — | **Přesun 2026-08-30**: vrstva rozmístěna podle typů, které kontrakty jmenují; `wire-panels` je nový top balíček. Viz §2 |

**Dvě rozhodnutí z plánu se změřením otočila** — obě v §2: umístění (Filament
dává owner vrstvu nahoru, ne dolů) a tvar kontraktu (osmimetodový interface
neprojde vlastním standardem repa).

---

## 2. Co měření změnilo na zadání

Tohle je ta část, kvůli které se plán nedá číst bez tohohle souboru.

**`WithTable` není tenká delegační vrstva.** Jen 20 z 99 metod je do pěti
řádků; 12 metod nad 40 řádků drželo 37 % kódu v tělech. Ale **65 metod je
public a jsou to Livewire endpointy** — `updateTableCell` volá Alpine jako
`$wire.updateTableCell(…)`. Přesunout se nedají; stěhují se **těla**, endpoint
zůstává tenký. Metrikou je *počet řádků v tělech*, ne délka souboru.

**`Table.php` je opak.** 197 z 205 metod public, 93 z nich do pěti řádků, jen
čtyři nad 25 řádků. Je to široký fluent builder — „rozřezat" je špatný rám,
nic se v něm neschovává. Co jde, je grupovat soudržné featury do concernů,
jak už dělá pro data source, grouping, polling, sub-rows a gesta.

**Čtyři „levné" shluky ve `WithTable` jsou dohromady 13 % objemu.** Query cache
je z nich nejhorší kandidát: `generateQueryCacheKey` je jednořádková delegace a
`queryCacheScope` je **zdokumentovaný override hook**. Extrakce by odebrala
rozšiřovací bod a nezmenšila nic.

**B-1 dopadl stejně jako `Table.php` — a to je ten výsledek.** Plán chtěl
rozdělit 139 metod base na *cross-cutting* (zůstává) a *surface-specific* (patří
do typu) a přesunout druhou skupinu s `@deprecated` shimem. Změřeno: 123 metod,
**117 z nich public**, a žádný shluk, který by patřil do typu:

| Shluk | Verdikt |
|---|---|
| Inline edit (12 metod) | Tenká delegace na `Core\Capabilities\Capability::Editable`; `isEditable()` je sdílený slovník s panel entries v core. Přesun by rozbil zdokumentované `Column::make()->editable()`. |
| Agregáty `counts()`/`sums()`/… (10) | **Není duplicita** tečkové notace, jak to vypadalo. Jsou to *rollupy* se službou `Services\AggregateSubqueries`, napojené na souhrny a sub-row constraint. Tečková notace jde přes core `QueryPlanner`. Dvě různé věci, obě zdokumentované. |
| `renderCell` + `renderCellFast` (103 ř.) | Vědomý §7 design se `supportsCellSkeleton()` přes reflexi a **důkladnou** paritní sadou (configs × obsah × id, únik cache mezi řádky, fallback podtříd). |
| Mobilní sloty, copyable, responzivní viditelnost | Cross-cutting; kterýkoli sloupec je smí použít. |

Sken „metody bez volajícího v src" našel šest kandidátů (`desktopOnly`,
`getInlineEditAbility`, `getTextSize`, `getTextWeight`, `mobileSlot`,
`renderDesktopCell`) — všechny mají testy a jsou to gettery/API pro konzumenta.
Mrtvá plocha to není.

Závěr je tedy stejný jako u `Table.php` v §2: **široký fluent builder, ne god
object.** B-6 (přesun + deprecation shimy, odhad 2 dny) by byl čistý náklad.

**Ale audit něco našel: responzivní buňka zahazovala chrome sloupce.**
`renderMobileCell()` vracela holý escapovaný text — bez odkazu na záznam, bez
ikony, bez třídy velikosti, **bez kopírovacího tlačítka**, bez popisku. Druhá
půlka téhož sloupce přitom propadla na `renderCell()` a všechno si nechala.
Takže jeden sloupec renderoval s odkazem a kopírováním na desktopu a bez nich na
telefonu — na šířce, kde je palec potřebuje nejvíc. Na stacked kartě to bylo
horší: `CardRenderer` volá `renderMobileCell()` přímo, takže responzivní sloupec
seděl vedle obyčejného, který si chrome nechal.

Nikdo si nevšiml, protože existující test asertuje jen obsah closure (`M:Ada`) —
což platilo tak i tak — a **všechny příklady v docs deklarují obě closure**, což
obě půlky ořeže symetricky a rozdíl schová.

Pravidlo je to, které `displayUsing()` dodržoval už předtím: **closure dodává
obsah, chrome sloupce ho obaluje.** Je to změna chování a je zdokumentovaná
v obou jazycích.

**Sparkline byl `@php` blok uvnitř Blade — a měl tři chyby, které nikdo nemohl
vidět.** `MetricColumn` má podle plánu kreslit „agregace/sparkline nad existující
infrastrukturou". Agregace existuje (tečková notace dělá `withCount`/`withSum`),
sparkline taky — jenže jako výpočet min/max/rozsahu a mapování souřadnic přímo
v šabloně `widgets/stats-overview.blade.php`. To je přesně proti
`AI_CHANGE_PROTOCOL.md` („stav řeš v PHP, markup v Blade"), nedosažitelné
z tabulky a netestovatelné.

Po přesunu do `Foundation\View\Sparkline` (L0) vylezly tři defekty, všechny
viditelné jen okem na grafu:

1. **`$max = max($data) ?: 1`** — ochrana proti dělení nulou na hodnotě, která
   není dělitel. Dělitel je *rozsah* a ten svoji ochranu měl. Jediné, co to
   dělalo, bylo přepsat maximum `0` na `1` a natáhnout rozsah. Každá řada
   končící přesně na nule — burndown k cíli, doplacený zůstatek — byla
   zmáčknutá a nikdy nedosáhla na horní hranu. (`[-5,-2,0]` → poslední bod na
   6.67 místo 2.)
2. **Rovná řada se kreslila po dně**, takže stabilní číslo vypadalo jako spadlé
   na nulu. Teď jde středem.
3. **Jedno čtení** vygenerovalo `<polyline>` s jedním bodem, což prohlížeč
   nenakreslí vůbec. Teď je z toho rovná čára.

Widget kreslí přes stejného vlastníka (extrahovat a delegovat, ne druhá kopie),
`MetricColumn` je druhý konzument — a teprve ten druhý konzument tu extrakci
ospravedlňuje.

**Plánovaný `StatusColumn` je prázdná podtřída — nedělat.** Plán (B-2, ADR 0018,
a závislost z V2.4 WF-4) ho popisuje jako „enum status → barva/ikona přes
`Enum\HasColor/HasLabel/HasIcon`". Změřeno na enumu implementujícím všechny tři
kontrakty: `BadgeColumn` už vrací barvu ✓ ikonu ✓ **i label** ✓, přes
`EnumResolver`. Zbylo by `class StatusColumn extends BadgeColumn {}` — přesně ten
„druhý kolo" z `AI_CODING_STANDARD.md` § Adapters. **Závislost V2.4 WF-4 je tím
splněná**: transition engine si má napojit `BadgeColumn`.

**`MoneyColumn` naopak smysl má, ale ne ten, co plán psal.** `money()` už
existuje v kanonickém `FormatsState` (sdíleném s infolist `TextEntry`), takže
formátování se nepíše znovu. Co typ přidává, jsou **výchozí hodnoty**: pravé
zarovnání, `tabular-nums`, nezalamování. A pravé zarovnání má v tomhle repu druhý
význam — `MobileCard` z něj odvozuje metriku stacked karty. Komentář tam přitom
tvrdil „což je to, co produkuje `money()` a `numeric()`", jenže `money()`
**nezarovnával** (ověřeno: `getAlignment()` = `left`). Takže ten komentář popisoval
záměr, který kód nikdy nesplnil; `MoneyColumn` ho teprve dělá pravdou.

Ve formátovači byly při té příležitosti dva reálné defekty: `money(null)` vracelo
`"1 234,50 "` s koncovou mezerou, a přesnost se řídí tím, **jak je měna napsaná**
(`'Kč'` → 0 míst, `'CZK'` → 2). Druhé jsem nechal — tabulky na tom stojí — ale je
to teď pojmenované, přebitelné (`->money('Kč', 2)`) a **zadokumentované**. Existující
test se přitom jmenoval „formats money values correctly for CZK (0 decimals)",
tvrdil v komentáři „CZK uses 0 decimals" a asertoval jen `toContain('CZK')` — takže
dokumentoval opak reality a procházel.

**Extrakce našla ostrý defekt, a našla ho tím, že se ptala „kdo tuhle memo
zneplatňuje".** `cachedGroupPartitions` se nulovala ručně na pěti místech, která
nulují `cachedRecords` — a jen na třech z nich. Chyběly `setPage()` a
`setTableCursor()`. Stránkování uvnitř jednoho requestu tedy nechalo podsoučty
skupin popisovat **předchozí stránku**: skupina na obrazovce sečetla 0, zatímco
skupina, která už na stránce nebyla, dál ukazovala svoje číslo. Ověřeno na
dvoustránkové tabulce: po `setPage(2)` vracelo `Acme` 300 a `Zeta` (jediná
skupina na stránce) 0.

Oprava není šestý `= null`. `GroupPartitions` si nese **identitu záznamů, které
rozdělil**, takže pravidlo je jedno porovnání na jednom místě místo řádku, který
si musí pamatovat každý volající. Všech pět ručních nulování je pryč. Tohle je
ta třída chyby, kterou soubor `AI_CODING_STANDARD.md` § Adapters popisuje z
druhé strany: rozsypané zneplatňování je duplicitní znalost a rozjede se.

**Odhad počtu metod stárne, jakmile hýbeš sousedy.** §4 psala o „mobilním
shluku (22 metod)". Po kroku 11 jich zbylo **devět**: těch dvacet dva bylo
měřeno, když v `Table` ještě seděl mobilní collapsing, který mezitím odešel do
vlastního concernu. Skutečná práce byla jinde než v počtu — největší metoda
souboru (`getMobileCardSkeleton`, 43 ř.) počítala klíč cache z pěti přístupových
metod cizího objektu. To není délka, to je vlastnictví: tvar karty je vlastnost
karty. Přesun do `MobileCard::shapeSignature()` metodu zkrátil a hlavně zařídil,
že slot přidaný v `MobileCard` nemůže být zapomenut v klíči.

**Grupování odhalí, co testy nehlídají — a je to pokaždé jinde, než čekáš.**
Mobilní collapsing vypadal jako nejrizikovější půlka akčního clusteru (dvě
brány `verify-drivers` jsou právě na něj) a měl **nejhustší pokrytí v celém
souboru**: prahy, klamp na 1, dividery, ne-spustitelné akce, klonování bez
klávesové zkratky, literální breakpoint třídy. Nepokrytá byla nudná půlka —
**chrome akčního sloupce**. `getActionCellSkeleton()` neměl jediný test a
`actionsAlignment()` neměl žádné tvrzení nad vyrenderovaným markupem, přestože
jedno volání musí dojít na dvě místa ve dvou slovnících: `text-*` na hlavičkové
`<th>` a `justify-*` na flex řádek v buňce. Buňka, která centruje tlačítka pod
hlavičkou zarovnanou doprava, prošla všemi branami. Mutace to potvrdila:
zadrátovaný `justify-end` neshodil nic v celém balíčku `table`.

Krok 13 potvrdil, že to platí i mimo `Skeleton`: memoizace rozdělení stránky
**neexistovala z pohledu testů vůbec** — zahodit ji celou prošlo všemi 2264
testy. Nepozorované byly obě půlky: že se cachuje, i že se to zneplatňuje. Druhá
z nich byla přitom rozbitá.

Krok 12 to zopakoval do písmene. `getMobileCardSkeleton` neměl jedinou zmínku
v `tests/` a **plochá memoizace místo klíčované tvarem prošla všemi 2258 testy**
— přitom právě ta klíčovaná je to, co docblok označuje za důvod existence
metody: schovej sloupec a karta se větví jinak. Stejně tak odsazení `pl-12`,
kterým detailní mřížka a řádek akcí obcházejí sloupec s checkboxem; bez něj
visí pod checkboxem a nikde to nepraskne.

**`@php` bloky ve views: tři ze čtyř jmenovaných nemají co odevzdat, čtvrtý
schovával rozjetou kopii pravidla.** §4 jmenovala čtyři hnízda podle *počtu*
bloků. Počet bloků není ta metrika — obsah je. Změřeno:

| View | Bloky | Co v nich je | Verdikt |
|---|---|---|---|
| `data-region.blade.php` | 6 | rozbalení `tableRenderPlan()` do lokálních proměnných | **nedělat** — žádné pravidlo, jen aliasy |
| `tables/index.blade.php` | 4 | totéž | **nedělat** |
| `forms/radio.blade.php` | 5 | gettery pole (`getOptions`, `getColors`, …) | **nedělat** |
| `sub-rows.blade.php` | 5 | součty, colspan, výška patičky, **pravidlo „je filtr aktivní"** | udělat |

Aritmetika v Blade je nepokrytý kód s vizuálním symptomem (pravidlo z kroku 15),
ale *aliasing* v Blade je jen aliasing. Tři views vypadaly stejně jako sparkline
a nebyly to ony.

**Sub-row panel byl napsaný dvakrát a rozjel se — a rozjel se opačně, než by
člověk čekal.** Panel expandovaného rodiče se renderuje dvakrát: desktopová
`<table>` a seznam ve stacked kartě. Čtyři řádky, které počítají „Zobrazit ještě
N", byly do obou zkopírované doslova. Vedle nich měl desktopový blok vlastní
kopii pravidla *„je aktivní sub-row filtr?"* — a **ta kopie byla ta správná**:

```php
// sub-rows.blade.php — správně, počítá se seedovanými sloty
fn ($v) => is_array($v) ? $v !== [] : ($v !== null && $v !== '')

// SubRowFilters::hasActiveInteractive() — kanonický vlastník, zastaralé
if ($value !== null && $value !== '') { return true; }
```

Sloty se **seedují při mountu** (`null` pro skalár, `[]` pro multi-select), aby
měl select kam entanglovat. Vlastník o tom nevěděl, takže každá tabulka s jedním
multi-select sub-row sloupcem četla `[]` jako „filtr je aktivní" — **trvale, od
mountu, bez jediného uživatelského zásahu**. To vypíná `eagerLoadSubRows()`
a rychlou cestu v `getSubRowsTotalCount()`: jeden dotaz na stránku se mění na
jeden dotaz na otevřeného rodiče **plus COUNT ke každému**.

Nikdo si nevšiml, protože fallback na per-parent dotaz je **správný, jen pomalý**.
Tabulka vypadá přesně jak má. A `resetSubRowFilters()` seed obnovuje, takže se
z toho stavu nedá vyjít.

Mutace to potvrdila oběma směry: opravené pravidlo prošlo **všemi 2311 testy**
balíčku `table` — nepokryté nebylo v jednom směru, bylo nepokryté úplně.
Existující test `hasActiveInteractive` sice pokrýval `''`, `null` i prázdné pole
*jako celek*, ale nikdy `['product' => []]` — seedovaný slot. A `SubRowFilterBindingTest`
měl komponentu s přepínačem `multiSelect`, který se u pravidla o aktivitě
filtru **nikdy nezapnul**.

Oprava je jeden predikát (`SubRowFilters::isActiveValue()`), který používají
všechny tři metody služby — `activeScoped()` ho měl inline a správně, zbylé dvě
ne. Panel se přesunul do `Support\SubRowPanel` a **obě** views ho čtou; kontrakt
`ExpandsTableRows` se rozšířil z jednoho predikátu na sedm metod ze stejného
důvodu, který má `SummarisesTable` napsaný ve svém docbloku — je to jedna
schopnost ptaná v různých hloubkách, a rozdělená by nechala panel ptát se čtyř
rozhraní na jednu věc.

**Co měření řeklo NEdělat:** `getSubRowsTotalCount()` čte `*_count` atribut až
poté, co ověří `relationLoaded()`, zatímco mobilní blok ho četl rovnou — vypadá
to jako třetí kopie téhož pravidla s tím, že vlastník je pozadu. Není. Atribut
z uživatelského `->withCount('items')` **není omezený** `subRowQuery()` ani
scoped sub-row filtry, kdežto `loadCount()` z frameworkového eager loadu ano.
Ta `relationLoaded()` podmínka je tedy nosná: čte se jen ve stavu, který nechal
za sebou vlastní omezený eager load. „Sjednotit" to by rozšířilo existující
riziko špatného čísla. Sdílená je jen **znalost jména atributu**
(`Str::snake($relation).'_count'`) a pořadí dvou zdrojů — to je teď
`getLoadedSubRowCount()` a čtou ho všichni tři; kontrakty zůstávají dva.

**V2.2/S2: plán mířil na redundanci, která nic nestojí, a minul defekt vedle
ní.** Každý lifecycle bod (`table.configuring/querying/queried`,
`form.saving/saved`, `action.executing/executed`) volá `runHook()` i
`runTypedHook()` za sebou. Plán to označil za „dvojí průchod a dvě API pro totéž"
a chtěl jeden z nich zrušit. Změřeno: **0,163 µs na lifecycle bod** bez
registrovaných listenerů — a zrušení jednoho z těch dvou průchodů ušetří zhruba
půlku, tedy ~0,08 µs na bod. Sedm bodů na request = **~0,6 µs** proti renderu,
který má podle vlastního benchmarku repa 20,5 ms. **Nedělat** — a navíc by to rozbilo
druhou skupinu listenerů, protože každý callback patří právě jednomu dispatcheru.

Právě to „právě jednomu" byl ale ten defekt. Členství rozhodovaly **dvě nezávislé
otázky**:

```php
callbackExpectsObject() => $type !== null && $type !== 'array';
callbackExpectsArray()  => $type === 'array';
```

Pro callback **bez typového hintu** (`function ($payload)`, i `function ()`)
odpověděly obě **ne** — takže nepatřil ani jednomu dispatcheru, a proto ho
spustily **oba**. Důsledky:

1. **Vedlejší efekty se zdvojily.** Počítadlo, audit řádek, log — dvakrát na
   každý lifecycle bod.
2. **Běžný tvar `$payload['data']` spadl.** Druhý průchod předá DTO
   (`FormSavingPayload`), které není `ArrayAccess` → fatal *poté*, co callback
   svou práci na prvním průchodu už udělal.

Mutace: přepsání pravidla prošlo **všemi 5588 testy** (core 2083, forms 1129,
table 2329, Integration 47). Nepokryté úplně — všechny testy i všechny příklady
v docs mají první parametr otypovaný, takže na ten případ nikdo nesáhl.

Oprava je jeden predikát a jeho negace, protože to **je** rozklad, ne dvě otázky.
Nehintovaný callback padá na **array** stranu — tedy na tu deprecated. To je
záměr: kdo psal callback bez hintu, psal ho v době, kdy array payload byl jediný
(a `docs/core/plugins.md` to tvrdilo), takže poslat mu DTO by rozbilo přesně ty
pluginy, které 2.x BC slib chrání.

**A ta docs věta byla lež, která ten defekt vyráběla.** `docs/core/plugins.md`
psalo: *„The current runtime hooks use array payloads, so these DTOs are most
useful when building your own typed extension points."* Runtime přitom DTO
dispatchuje na **každém** vestavěném bodě. Čtenář tedy neotypoval — a spadl do
dvojího běhu. Opraveno v EN i CS, včetně tabulky „který hint kam patří".

**Redundance, která opravdu stála, byla jinde: reflexe na každém dispatchi.**
`getFirstParameterTypeName()` dělalo `new ReflectionFunction` pro **každý callback
v každém dispatcheru na každém lifecycle bodě každého requestu** — kvůli odpovědi,
která se po registraci nemůže změnit. Členství se teď rozhoduje v `hook()` a nese
si ho záznam. K tomu `warnSkippedCallback()` sahalo na `config('app.debug')` při
každém přeskočení (a přeskočen je **každý správně otypovaný** callback tím druhým
dispatcherem) — resolvnuto jednou. Skip-pass: **0,94 → 0,50 → 0,24 µs**, bez
změny chování a bez BC.

**Třetí defekt spadl ze samotného měření.** Benchmark mimo nabootovanou aplikaci
spadl na `BindingResolutionException`. Strážce `! function_exists('config')` se
ptá „je framework autoloadnutý", což není ta otázka — helper existuje, jakmile ho
Composer viděl, nabootováno nebo ne, a `config()` pak resolvuje z prázdného
kontejneru. Takže **diagnostika chyby v hintu spadla místo aby ji nahlásila**,
přesně ve standalone kontextu, který má `CLAUDE.md` v požadavcích („testable in
isolation, usable from other contexts"). Chybějící půlka je `app()->bound('config')`.

**Prototyp na reálné entitě našel dva defekty, které přežily celou unit sadu.**
Plán V2.3 si sám uložil „prototyp R.1 na 1 reálné entitě před rozšířením" a měl
pravdu. `workbench/app/Resources/InvoiceResource.php` deklaruje **všech pět
kontraktů** na jedné třídě, běží nad reálnou `Invoice` s daty a
`verify-resource-pages.mjs` ho projíždí v prohlížeči přes skutečné stránky
frameworku, ne přes něco, co si workbench vyrobil.

| Defekt | Proč ho testy neviděly |
|---|---|
| `ListPage` **nenavazoval model**, který resource deklaruje — na rozdíl od formulářových stránek | každá fixture volala `->model()` uvnitř vlastního `table()`, takže to, že stránka model nenavazuje, bylo neviditelné |
| `CreatePage` **neseedoval stavový bag**, takže select a datetime entanglovaly cestu, která v bagu není — kontrolka se vykreslí a nikdy nezapíše | Livewire to hlásí jen jako **konzolovou chybu v prohlížeči**; server-side test projde |

Plus jeden nález v samotném workbenchi: `bootstrap/cache/packages.php` drží
discovery cache, takže nový balíček se objeví až po jejím smazání — `No hint path
defined for [wire-panels]`.

Druhá chyba je ta poučnější. `Form::getInitialState()` existuje přesně proto
(docblok: *„Hosts (e.g. action modals) use this to seed the Livewire state bag so
array fields never start missing/null"*) a já ho na nové stránce nezavolal.
Přesně ta samá třída chyby jako seedované sub-row sloty z prvního kroku téhle
session — a i tady byl symptom pozorovatelný jen v prohlížeči.

**V2.3: „kam s `Resource`" má odpověď u Filamentu, a je opačná než naše.**
Rozhodnutí z 2026-08-26 dalo kontrakt do `core`, tedy na **dno** grafu
`sortable → table → forms → core`, a z toho plynulo omezení „nesmí jmenovat
`Table`/`Form`/`Infolist`". Náčrt R.1 je ale všemi třemi psaný, takže **nešel
napsat tak, jak stojí v plánu**.

Ověřeno proti Filament docs 5.x: `Resource` tam bydlí v **panel balíčku**, který
závisí na `filament/tables`, `filament/forms` i `filament/schemas`. Komponentové
balíčky o `Resource` nevědí a jdou použít samostatně. Filament to omezení nemá,
protože owner vrstvu dal **nahoru**.

*(První oprava zněla „dát to do `packages/table`, které na forms i core závisí".
To bylo správné odvození aplikované špatně — `wire-table` **je** jedna z těch
komponent, Filamentův protějšek je `filament/tables`, a `Resource` tam
rozhodně není. Vlastník repa to poznal na konkrétním případu; konečné
rozmístění je o pár odstavců níž.)*

**A tvar kontraktu neprojde vlastním standardem repa.** Plán i ADR 0020 popisují
`Resource` jako jednu třídu s osmi metodami. `AI_CODING_STANDARD.md` § Interfaces
říká *„one interface represents exactly one capability. Never create large
interfaces containing unrelated methods."* Takže ani „interface místo abstract
class" nestačí — rozpadlo se to podle schopností:

| Kontrakt | Kdo ho čte |
|---|---|
| `DescribesResource` (static: `key`, `modelClass`, `label`, `pluralLabel`) | registr, menu, introspekce — **bez instancování** |
| `ProvidesResourceTable` | `ListPage` |
| `ProvidesResourceForm` | `Create/EditPage` |
| `ProvidesResourceInfolist` | `ViewPage` |

Read-only audit log implementuje identitu a tabulku a nic dalšího; stránka, která
chce formulář, nemůže dostat resource, který ho nemá. Hybrid static/instance
z Q1 tím dostal mechanický důvod místo stylového: menu se ptá na popisek a
`forModel()` směruje model **dřív, než se cokoli instancuje**.

Odvození klíče a popisku z **modelu** (`DescribesRecords`) je taky pravidlo, ne
úspora řádků: klíč je to, na čem registr směruje, popisek to, co ukazuje menu, a
vzít je ze dvou míst znamená, že se rozejdou. Mutace to potvrdila — čtyři zásahy
do registru (nezneplatněná memo mapa, neznormalizované lomítko, tichý přepis
duplicitního klíče, model-less resource v mapě) shodily každý právě jeden test.

**Owner vrstva: „nejmenší krok" obětoval přesně to, na čem záleželo.** Umístil
jsem `Resource` do `packages/table` s odůvodněním „Filament dává owner vrstvu nad
komponenty". To odvození bylo správné a aplikace špatná: `wire-table` **je** jedna
z těch komponent — Filamentův protějšek je `filament/tables`, a `Resource` tam
rozhodně není. Vlastník repa to poznal na konkrétním případu: **forms-only
resource musel instalovat tabulkový balíček** s jeho assety, migracemi, konfigem
a Livewire synthesizerem. Za nic.

Oprava není přesun celku jinam, ale **rozmístění podle toho, co která smlouva
jmenuje** — a umožnil ho ten rozpad na malé kontrakty, který už existoval; jen
byly nesmyslně slepené v jednom balíčku:

| Co | Kde | Protože jmenuje |
|---|---|---|
| `DescribesResource`, `DescribesRecords`, `ResourceRegistry` | `wire-core` (L1) | nic než skaláry |
| `ProvidesResourceForm` | `wire-forms` | `Form` |
| `ProvidesResourceInfolist` | `wire-core`, v `Infolists/Contracts/` | `Infolist` — a to je **L2**, takže do L1 `Core/Resources` nesmí |
| `ProvidesResourceTable`, všechny Pages | `wire-panels` (nový, nad vším) | `Table`, `Form`, host traity |

Původní rozhodnutí z 2026-08-26 („kontrakt do core") tedy nebylo špatně. Špatně
byl náčrt R.1, který identitu a povrchy slepil do jednoho osmimetodového
interface — a já z toho odvodil, že se musí stěhovat celek.

Druhá podmínka od vlastníka: **table obsahuje table, owner vrstva je mimo.**
Hlídá to `packages/table/tests/Unit/Architecture/TableOwnsTablesTest.php` —
zakazuje owner namespace, owner import a page view v `packages/table`, a jmenuje
i osy, které tam nemají přibýt (`Workflow`, `StateMachine`, `Workspace`,
`Navigation`). Mutace: vrácení namespace, importu i view shodí každé právě jeden
test.

**Tři věci, které ten přesun vytáhl:**

1. `ModuleLayersTest` zachytil **moji** chybu: `Exceptions/ResourceRegistrationException`
   (L0) importovala `Core\Resources\Contracts\DescribesResource` (L1). L0 nesmí
   jmenovat nic nad sebou. Jméno kontraktu teď přichází jako řetězec z L1
   volajícího, takže reference zůstane compile-checked na konci, který ji smí
   udělat.
2. Filtrování konfigurační listiny („není pole → ignoruj, prázdný řetězec →
   přeskoč") sedělo v service provideru, kde ho šlo otestovat jen nabootováním
   balíčku. Je z něj `ResourceRegistry::registerMany()` — pravidlo u vlastníka,
   endpoint tenký, testovatelné bez provideru.
3. Nový balíček je potřeba zadrátovat na **osmi** místech: `composer.json`
   (repositories, require, autoload-dev), `phpunit.xml` (testsuite **i** source
   pro coverage), `tests/Pest.php`, `scripts/coverage-floors.json`, `phpstan.neon`
   (paths + excludePaths pro `ListPage`), `.github/workflows/split.yml`. Zapomenout
   `<source>` znamená, že se balíček tiše neměří.

**V2.2/S3: mezera tam není, zato je tam dvojice tříd, kterou nikdo nevolá.**
Plán se ptal, jestli hydratační seamy netvoří díru („typ nehydratovaný před
validací"). Netvoří — směr má v obou polovinách jednoho vlastníka a ten pár je
pojmenovaný v docblocích (ADR 0021):

| Směr | Cesta |
|---|---|
| model → stav | `FormRuntime::fill()` → `StateHydrator::hydrate()` (typ podle `getStateType()`) → `hydrateFields()` → `$component->hydrateState()`; `StateManager::fill()` navíc sráží enum instance přes `EnumResolver::scalarDeep()` |
| stav → model | `SaveHandler::dehydrateFields()` → `$field->dehydrateState()` → `persist()` → `Dehydrator(ValueTransformer, CastResolver)` |

Ta asymetrie, která vypadá jako mezera, je záměr: per-pole konverze na zápisové
straně běží **až po validaci**, protože validační pravidla popisují uživatelský
vstup, ne perzistovaný typ. Čtvrtý seam, který plán jmenuje (`normaliseEnums`),
už neexistuje — je z něj `EnumResolver::scalarDeep()`.

Nález je jinde. `Core\Hydration\Hydrator` — třída pojmenovaná přesně pro směr
model → stav — má **nula volajících** v `packages/*/src`, `workbench/` i
`tests/`; totéž `MutationPipeline`. Přitom **obě mají unit testy** (takže vypadají
živě a drží pokrytí zelené) a **obě jsou zdokumentované v
`architecture/core/unified-engine.md` s příkladem použití**, jako by je engine
používal. `Dehydrator` se používá doopravdy; jeho čtecí zrcadlo nikdo nikdy
nepřipojil.

To je ta samá třída doc-lži jako věta o array payloadech v `plugins.md` o odstavec
výš, jen v interní dokumentaci: stránka popisuje zamýšlený engine, ne ten běžící.
`unified-engine.md` teď říká, co se doopravdy spouští, a osiřelou dvojici označuje.
Jestli se má smazat (2.0 je major, `AI_CODING_STANDARD.md` § Adapters mluví jasně
o „druhém kole"), nebo nechat jako stavební blok pro konzumenta, je **odstranění
tříd z publikovaného balíčku** — rozhodnutí lidské, ne moje. Leží v §3 vedle
`resolveActionType()`, což je přesně stejná otázka.

**V2.2/S1: nedělat, protože přínos, který plán slibuje, už existuje.** Plán píše
*„unit testy injektují mock deps (dřív nešlo — to je hlavní přínos)"*. Změřeno:

| Cíl | Plán | Skutečnost |
|---|---|---|
| `SaveHandler` | „nejde mockovat" | **25 vlastních testů**, které ho konstruují přímo (`new SaveHandler($config, $runtime)`) |
| `ActionPipeline` | „stage instance přes injektovaný seznam" | **už to tak je** — `__construct(array $stages = [])`, `ActionPipelineTest:144` injektuje; ty čtyři `new` jsou *defaulty* v `resolveStages()`, dosažitelné jen když se nic neinjektovalo |
| „hot path" | ADR 0017 gap #6 | ani jedno: `SaveHandler` je jeden na odeslání formuláře, `ActionPipeline` je bound transient, jeden na kliknutí |

A z těch `new`×6 v `SaveHandler` jsou **dva payload DTO** (payload se konstruovat
musí) a **jedna vyhozená výjimka**. Skutečné závislosti jsou dvě. Plán mířil na
počet výskytů `new`, ne na to, co ty výskyty jsou.

---

## 3. Co je vědomě neudělané

| Věc | Proč | Kde |
|---|---|---|
| ADR 0025 krok 8 — Blade coupling (`callInfolistAction` natvrdo ve view) | Patří do [`action-render-unification.md`](action-render-unification.md), jehož fáze 0 je golden-master připnutí markupu; §6.2 tam označuje vizuální deltu za jediné reálné riziko regrese | ADR 0025 |
| ADR 0025 krok 10 — vyříznout `wireFillHandle` z 38 KB bundlu | Samostatný, nezačatý | ADR 0025 |
| ADR 0025 krok 4 — `Trans`/`Deprecation` do Foundation | Zdůvodnění padlo: vrstvy L2→L1 **povolují**, takže by to bylo 33 souborů za nulový přínos pro hranice. Zbývá argument kanonického vlastnictví, ale to je jiný důvod | ADR 0025 |
| `modal-host.blade.php` instancuje `Modals\Html\*` | **Není defekt** — je to výsledek [`rule5-framework-wide-modal-sweep.md`](rule5-framework-wide-modal-sweep.md): framework nesmí záviset na `<x-*>` | — |
| Systematické hledání duplicitních abstrakcí napříč V2 | Průřez auditu padl na session limit. `DataSourceCapabilities`/`CapabilitySet` byl nalezen mimo audit a nejspíš nezůstal sám | [`v2-audit-2026-08-26.md`](v2-audit-2026-08-26.md) §6 |
| `ShellRenderPlan`, `InteractionRenderPlan` — host pořád `mixed` | Polling, live kanál, readiness, přístup ke stavu nemají pojmenovaný kontrakt | [`v2.1-…`](v2.1-monolith-split-implementation.md) §0a |
| `resolveActionType()` — public static, **nula volajících v src** | Nález z kroku 9; plugin API, nebo mrtvý kód. Nerozhodnuto | — |
| `Core\Hydration\MutationPipeline` — nula volajících, **ale zůstává** | Nález S3. Sourozenec `Hydrator` byl smazán (nula volajících, žádný plán); `MutationPipeline` **ne** — `v2-deferred-items.md` §3.2 je živý nedodělaný plán na jeho zapojení do `dehydrate()` (`mutateDataBeforeSave()` jako before-hook). Není zapomenutý, je postavený dopředu. **Rozhodnuto vlastníkem repa 2026-08-30: nechat.** Zapojit ho znamená dodělat §3.2 jako vlastní krok | [`v2-deferred-items.md`](v2-deferred-items.md) §3.2 |
| `v2-deferred-items.md` §3 je hotová z jedné čtvrtiny, ne celá | V2.2 korekční tabulka ji označila za HOTOVOU. Hotová je **§3.1** (Dehydrator v `persist()`). §3.2 (MutationPipeline) a §3.3 (relation dehydrace) ne — `RelationshipSaveHandler` pořád ručně iteruje 174 řádků. §3.4 (BC) je bezpředmětná, dokud §3.2 nepadne | [`v2-deferred-items.md`](v2-deferred-items.md) §3 |
| Boost guidelines neznají plugin hooky | `guidelines/` ani `skills/` nepopisují `PluginManager` vůbec — takže pravidlo „hint rozhoduje dispatcher" tam není a nemohlo zestárnout. Doplnit až s vlastní plugin sekcí, ne ad hoc | — |
| ~~Boost docs mirror rozjetý~~ | **zavřeno 2026-08-30.** `packages/boost/resources/boost/docs/` je *commitnutá* kopie `docs/` (viz `scripts/sync-boost-docs.php`) a `composer boost:check-docs` je brána v `docs-check.yml`. Byla červená **už před tímhle během**: `money.md` a `metric.md` z V2.1 se do balíčku nikdy nedostaly. Po každé změně v `docs/` pouštěj `composer boost:sync-docs` | `.github/workflows/docs-check.yml` |

---

## 4. Co je na řadě

**Čtyři fáze ze sedmi jsou hotové: V2.0, V2.1, V2.2, V2.3.** Co je uvnitř nich a
co v nich měření změnilo, je v §1 a §2; tahle sekce je jen o tom, co dál.

### Další fáze

| Fáze | Obsah | Poznámka před startem |
|---|---|---|
| **V2.4** | tenancy · workflow · queue · DB notifikace | Master plán ji vede jako 🔴 vysoké riziko. `WF-4` (transition engine) má napojit `BadgeColumn`, ne nový `StatusColumn` — ta závislost padla v kroku 14, viz §2 |
| **V2.5** | saved views · global search · large-table UX | **Global search patří do `wire-panels`**, ne do `core/src/GlobalSearch/`, jak ho vede master plán. Staví nad `ResourceRegistry` a core na table nevidí — je to přesně ten cyklus, kvůli kterému se owner vrstva stěhovala, viz §2 |
| **V2.6** | domain modules | `Workspace` je zamýšlený seam; grupuje resources a nevlastní routing |

Nezačaté, žádná z nich není blokovaná.

### Než se do nich pustíš

Tři věci z §3, které se samy nezmenší a každá je na půl dne:

1. **`v2-deferred-items.md` §3 je hotová z jedné čtvrtiny**, ne celá, jak tvrdila
   V2.2. §3.2 (zapojit `MutationPipeline` do `dehydrate()`) a §3.3 (relation
   dehydrace) otevřené — `RelationshipSaveHandler` pořád ručně iteruje 174 řádků.
2. **ADR 0025 kroky 8 a 10** — Blade coupling `callInfolistAction` a vyříznutí
   `wireFillHandle` z 38 KB bundlu.
3. **`resolveActionType()`** — public static, nula volajících v `src`. Plugin API,
   nebo mrtvý kód. Nerozhodnuto od kroku 9.

### Co je uzavřené a nemá se otevírat

- **`Table.php`** — nezbyla metoda nad 19 řádků, každý soudržný shluk má concern.
- **`WithTable`** — čtyři z osmi největších metod jsou doražené kroky, které
  skončily v téhle velikosti záměrně (`updateTableCell` 73 ř. jde proti
  doloženému rozhodnutí o vlastnictví transakce). Jediný nesporný kandidát je
  `submitHaltModal` (52 ř.), a to je jedna metoda, ne krok.
- **`@php` bloky ve views** a **`*Skeleton` bez testu zapečených podmínek** —
  obojí dotažené, viz §1 a §2.

---

## 5. Jak pokračovat

```
Pokračuj ve V2 podle architecture/plans/v2-progress.md.
Přečti §2 (co měření změnilo na zadání) a §4 (co je na řadě) a vezmi první
položku ze §4.

Postup je pokaždé stejný a v tomhle pořadí:
1. ZMĚŘ, než cokoli napíšeš. Zadání v plánech je starší než kód a bylo špatně
   pětkrát ze sedmi — viz §2. U extrakce měř řádky v tělech, ne délku souboru;
   u aditivní práce se ptej „kdo tuhle schopnost už vlastní?".
2. Najdi, co není pokryté: grep metod s nula zmínkami v `tests/`, a `@php`
   bloky ve views. Když najdeš stejné pravidlo dvakrát, **nejdřív zjisti, která
   kopie je novější** — ne která vypadá rozbitě.
3. Mutuj PROTI STÁVAJÍCÍ SADĚ, než napíšeš test — to je důkaz, že pravidlo bylo
   nepokryté, ne tvůj dojem. Pak napiš test a mutuj znovu.
4. Tělo k vlastníkovi, endpoint tenký. Adaptér se extrahuje a deleguje, nikdy
   nepíše vedle jako druhá kopie.
5. **U nové UI vrstvy postav prototyp na reálné entitě ve workbenchi a projeď ho
   driverem.** Fixture dokazuje kontrakt, ne zapojení: V2.3 měla zelenou unit
   sadu a v prohlížeči spadla na dvou defektech, které žádný server-side test
   vidět nemůže (nenavázaný model, neseedovaný stavový bag → tichý entangle
   no-op).
6. Brány podle AI_CHANGE_PROTOCOL.md včetně verify:drivers a obou docs bran,
   pokud jsi sáhl na veřejné API (EN i CS stránka v jednom commitu).
   **Když jsi sáhl na `docs/`, pusť i `composer boost:sync-docs`** — boost veze
   commitnutou kopii docs a `boost:check-docs` je CI brána; zapomnělo se na ni
   dvakrát po sobě.
7. **Coverage pouštěj v obou režimech.** `coverage:verify` drží floory i s
   nepokrytým novým řádkem; CI pouští navíc
   `php scripts/verify-coverage.php build/clover.xml --diff=origin/1.x`, a ta
   brána byla červená tři běhy, než ji někdo pustil.

Když měření řekne „nedělat", je to platný výsledek — napiš proč a dolož to.
Na konci aktualizuj tenhle soubor a commitni.
```

Poznámka k `coverage:verify`: composer ho zabíjí na 300s. Pouštěj ho jako
`COMPOSER_PROCESS_TIMEOUT=1200 composer coverage:verify`.

**Nový balíček se drátuje na osmi místech** a zapomenout kterékoli je tichá
chyba: `composer.json` (repositories, require, autoload-dev), `phpunit.xml`
(testsuite **i** `<source>` — bez něj se balíček neměří), `tests/Pest.php`,
`scripts/coverage-floors.json`, `phpstan.neon` (paths + excludePaths pro
`WithTable`/`WithForms` hosty), `.github/workflows/split.yml`, README, a
`vendor/orchestra/testbench-core/laravel/bootstrap/cache/` smazat, jinak ho
workbench neuvidí.

**Nepřeskakuj měření — to je jediné pravidlo, které si z tohohle souboru odnes,
když nemáš čas na zbytek.** Za tři běhy opravilo zadání dvanáctkrát. Běh
2026-08-30 přidal pět:

| Krok | Plán / §4 slibovala | Měření našlo |
|---|---|---|
| `@php` bloky ve views | čtyři hnízda podle *počtu* bloků | tři z nich jsou jen aliasy render plánu → **nedělat**; čtvrté drželo rozjetou kopii pravidla a **ostrý defekt** |
| V2.2/S1 | injektovat deps, „testy nemůžou mockovat" | `ActionPipeline` už stages konstruktorem bere, `SaveHandler` má 25 vlastních testů → **nedělat** |
| V2.2/S2 | zrušit dvojí dispatch (redundance) | stojí 0,163 µs → **nechat**; vedle byl **defekt**, nehintovaný callback běžel dvakrát |
| V2.3 umístění | `Resource` do core (rozhodnuto 2026-08-26) | náčrt R.1 tak nejde napsat; Filament dává owner vrstvu **nad** komponenty → nový balíček `wire-panels` |
| V2.3 tvar | jedna třída / jeden interface, osm metod | `AI_CODING_STANDARD.md` § Interfaces to zakazuje → rozpad na identitu + povrchy |

A jeden nález, který měření **nenašlo a našel ho až prohlížeč**: V2.3 měla
zelenou unit sadu a dva defekty, které server-side test vidět nemůže. Proto krok
5 v postupu výš. V běhu
2026-08-26: query cache vypadala jako nejlevnější první krok a byla nejhorší,
`updateTableCell` vypadal na 119 řádků k přesunu a byl už extrahovaný,
`Table.php` vypadal jako druhý monolit a je to fluent builder. V běhu
2026-08-28/29 sedělo zadání ze §4 **dvakrát ze sedmi**:

| Krok | §4 slibovala | Měření našlo |
|---|---|---|
| 12 stacked karty | 22 metod | **9** — zbytek odešel v kroku 11 |
| 13 `WithTable` | tři jmenované metody | největší je `updateTableCell` (73) a v seznamu chyběl; 4 z 8 největších už doražené |
| 14 `StatusColumn` | nový typ | `BadgeColumn` to už umí (barva + ikona + label) |
| 15 `MetricColumn` | „sparkline nad existující infrastrukturou" | sparkline byl `@php` blok se **třemi** chybami |
| 16 `RelationColumn` + B-1 | napsat typ, přesunout metody z base | obojí **nedělat** |

Kroky 10–11 a 17 seděly. Pointa není, že plány jsou špatné — jsou to poctivé
plány psané ke stavu kódu, který mezitím zestárl, mimo jiné o předchozí kroky
téhle řady.

**Pravidlo z V2.3/P: `@livewire` v dokumentaci je kód, ne text — a spálilo mě to
dvakrát v jednom kroku.** Nejdřív v PHP docbloku (`@livewire(...)` se parsuje
jako PHPDoc tag → `phpDoc.parseError`), pak v boost guidelines, což je
`.blade.php`, takže se **zkompilovalo jako Blade direktiva** a shodilo šest
boost testů na `Undefined variable $order`. V docbloku pomůže backtick, v Blade
`@@livewire`. Obecně: než napíšeš `@něco` do souboru, zjisti, kdo ten soubor
parsuje.

**A druhé pravidlo z téhož kroku, tvrdší:** extrahoval jsem `resolveRecord()` do
concernu regulárním výrazem a ten mi u `ViewPage` **sežral vedlejší metodu
`infolist()`**. Prošlo to `php -l`, prošlo to lintem, spadlo to až na testu.
Regex nad PHP je nástroj na jednorázový přejmenovací sweep, ne na vyřezávání
metod; po každé takové úpravě si nech vypsat `grep -n "function "` a porovnej,
co v souboru zbylo.

**Pravidlo z V2.2:** u dvou predikátů, které mají dohromady **rozdělit** vstup,
se ptej na případ, kde odpoví **stejně**. `callbackExpectsArray()` a
`callbackExpectsObject()` byly napsané jako dvě nezávislé otázky a pro
nehintovaný callback odpověděly obě „ne" — takže nepatřil nikam, a proto ho
spustily obě větve. Rozklad se píše **jednou a jako negace**; dvě samostatné
podmínky, které mají být komplementární, jsou vždycky čekající díra. Stejná věc
pak platí i pro odhad: `phpstan` tenhle druh chyby nevidí (obě metody byly
korektně otypované) a testy taky ne, dokud někdo nenapíše ten třetí případ.

A druhá půlka: **měření samo je test.** Benchmark, který jsem psal jenom proto,
abych rozhodl, jestli má smysl rušit dvojí dispatch, spadl na
`BindingResolutionException` — a to byl třetí defekt toho běhu. Kdybych ten
odhad vzal z plánu místo změření, nenajdu ho.

**Pravidlo z běhu 2026-08-30:** když najdeš stejné pravidlo napsané dvakrát,
**neopravuj tu kopii, která vypadá rozbitě — zjisti, která z nich je novější.**
Zvyk říká „vlastník je pravda, kopie zestárla"; tady to bylo obráceně. Blade
kopii někdo opravil, když se zaváděly seděné sloty, a `SubRowFilters` — službu,
kterou se ptá dotazová cesta — nechal být. Kopie v Blade byla proto správně
a vlastník špatně, a protože fallback na pomalejší cestu je *korektní*, nebylo
to vidět ani okem, ani v testech. Dvě věci z toho: hledej ten commit, který
kopii rozdělil, a ptej se **kdo z těch dvou míst reálně řídí chování** (tady
dotaz, ne markup).

Druhá půlka: **počet `@php` bloků není metrika, obsah je.** Ze čtyř views
jmenovaných v §4 podle počtu bloků měly tři jen aliasy render plánu. Aritmetika
v Blade je nebezpečná, aliasing ne — a když jsou aliasy tím, co drží tvar
direktiv (morph markery), je jejich odstranění naopak riziko.

**Pravidlo z běhu 2026-08-28/29:** adaptér se **extrahuje a deleguje**,
nikdy nepíše vedle jako druhá kopie — `AI_CODING_STANDARD.md` § Adapters.
Stálo to dva reálné defekty v jednom commitu.

**Pravidlo z běhu 2026-08-28:** test na „pravidlo pozorovatelné jen v
prohlížeči" nehledej tam, kde je feature nejsložitější — tam už testy jsou,
protože právě tam je někdo psal. Hledej ho **greppem po metodách s nula
zmínkami v `tests/`**. Zabralo to jeden příkaz a našlo to `getActionCellSkeleton`
uprostřed jinak hustě pokrytého clusteru — a v dalším kroku `getMobileCardSkeleton`
úplně stejně. A mutuj **před** psaním testu i po něm: mutace zadrátovaného
`justify-end` proti staré sadě je důkaz, že to pravidlo opravdu bylo nepokryté,
ne jen tvůj dojem.

**Pravidlo z kroku 16:** audit, který skončí „nic nepřesouvat", **není
neúspěch** — je to výsledek, pokud je podložený, a ušetří odhadované dva dny
deprecation shimů. Ale nikdy ho nekonči u samotného verdiktu: čtení base kvůli
klasifikaci je nejlevnější příležitost najít, co je v ní rozbité. B-1 nenašel
nic k přesunu a našel zahozený chrome v responzivní buňce.

**Pravidlo z kroku 15:** aritmetika v `@php` bloku uvnitř Blade je **nepokrytý
kód s vizuálním symptomem** — nejhorší kombinace, jakou tenhle repozitář má.
`AI_CHANGE_PROTOCOL.md` to říká („stav řeš v PHP, markup v Blade") a sparkline to
porušoval tři chyby dlouho. **Grepni `@php` napříč views**, kdykoli hledáš další
takové místo; a extrakci ospravedlňuje až druhý konzument, jinak je to spekulace.

**Pravidlo z kroku 14:** u **aditivní** práce je měření to samé co u extrakce, jen
se ptáš jinak: *kdo tuhle schopnost už vlastní?* Ze čtyř plánovaných ERP typů měl
jeden (`StatusColumn`) hotového vlastníka úplně, druhý (`MoneyColumn`) měl hotové
formátování a chyběly mu jen výchozí hodnoty, a třetí (`RelationColumn`) na tom
je nejspíš stejně. Plán psaný před rokem počítá se stavem kódu před rokem.

**Pravidlo z kroku 13:** u každé memoizace se ptej **kdo ji zneplatňuje**, ne
jestli funguje. Zneplatnění rozsypané po volajících je duplicitní znalost a
rozjede se — tady se rozjelo na dvou z pěti míst a nikdo si nevšiml, protože
cache samotná nebyla ničím pozorovaná. Oprava, která drží: ať si memo nese
identitu vstupu, ze kterého vzniklo.

**Dvakrát to byl `*Skeleton`, a to není náhoda.** Zkompilovaný tvar je přesně
to, co Pest vidí jako řetězec a nikdo neasertuje, protože „to je jen markup" —
jenže podmínky zapečené do tvaru (zarovnání, odsazení za checkboxem, klíč cache)
jsou rozhodnutí v PHP se symptomem jen v prohlížeči. **Každý nový `Skeleton`
chce test na svoje zapečené podmínky, hned s sebou.**

Zbylé tři jsou dotažené (2026-08-29) a ukázaly, kde přesně je ta hranice:
**tvarový klíč** si někdo pohlídal u všech (plochá memoizace sub-row buňky shodí
dva existující testy), **zapečenou podmínku** u žádného. Tedy: klíč cache vypadá
jako výkon a lidi ho testují; zapečená podmínka vypadá jako markup a netestuje ji
nikdo — přestože to je ta, která píše `x-on:click`.
