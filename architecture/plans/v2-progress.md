---
title: V2 — kde to stojí a čím pokračovat
date: 2026-09-02
scope: V2.0–V2.5 (hotové), ADR 0025 (rozpracované), V2.6 (běží — kroky 1 a 2 hotové)
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

**Krok 10 hotový 2026-08-30, dodělaný 2026-09-01** — `wireFillHandle` je venku ze
sdíleného bundlu (38 365 → **29 235 B**, −23,8 %), viz §2. Práce z 30. 8. ale
nebyla commitnutá a **prohlížečová brána v ní našla defekt**: nový entry
registroval jen na `alpine:init`, takže po `wire:navigate` neregistroval nic a
Alpine umřel na celém datovém regionu (`wireFillHandle is not defined`). Opravené,
zapsané v ADR i v `architecture/assets.md`, a nově chytané `FillHandleAssetTest`.
**Nedokončený krok 8** — viz §3.

### V2.5 — power-user & large-table UX ✅

Všechny tři položky (SV, GS, LT), a měření změnilo zadání u všech tří.

| | Plán / §4 slibovaly | Uděláno |
|---|---|---|
| **SV** saved views | „state je už serializovatelný, jen ho ulož" | tvar pohledu **neexistoval** — `persistViewPreferences()` skládá bag ze dvou klíčů. Vznikl `Preferences\TableViewPayload` jako jediný vlastník toho, co pohled nese; `TablePreferenceDriver` dostal dimenzi jména (BC změna kontraktu, `unique` na trojici); switcher je v **existujícím** view menu |
| **GS** global search | „hard-dep na tom, kde žije registr" | `ResourceRegistry` je **už v `core/src/Core/Resources/` (L1)** → cyklus neexistuje. Nový L2 modul `GlobalSearch/`: kontrakt `GloballySearchable`, služba, `GlobalSearchPalette` (⌘K) |
| **LT** large-table UX | virtual scrolling (windowing) | **zamítnuto měřením** — čtyři chování čtou řádky z DOM. Místo toho `Table::collapsibleGroups()`: sbalená skupina se nerenderuje vůbec, takže gesta vidí konzistentní seznam |

---

### V2.6 — domain modules 🔨 znovu otevřená (rozhodnutí vlastníka 2026-09-01)

Rozhodovací bod z [`v2.6-…`](v2.6-domain-modules-implementation.md) §7 padl na
**„odložit"** — a měření k tomu přidalo tvrdší důvod než „není potřeba".
**Kontrakt `DomainModule` se dnes napsat nedá:** ze osmi metod tři pojmenovávají
typy, které v `packages/*/src` neexistují (`Dashboard`, `NavigationGroup`,
`Workspace` jako bázová třída), jedna nemá kam registrovat (žádný registr
workflow — `WorkflowState::for()` se staví na místě volání), dvě už jsou na
`Plugin` a jedna je Laravelu. Celý verdikt po metodách: §0a toho plánu.

**Co `Workspace` chybí: nic.** Existuje od V2.3, je otestovaný a zdokumentovaný
(EN i CS), a jeho vlastní docblok už říká, že doménová osa má sedět nad ním. Plán
je starší než ten soubor a zachází s ním jako s třídou k vyjmenování — to se řeší
škrtnutím metody, ne prací na `Workspace`.

**Vlastník repa pak rozhodl chybějící části doplnit** (§0b téhož plánu). Verdikt
§0a se tím neruší — mění se na zadání: co v něm stálo jako „neexistuje", je teď
seznam práce. Pořadí se ale kvůli jednomu nálezu obrátilo proti plánu:

**`Workspace` nemá v celém repu jediného konzumenta.** Nic nevykresluje navigaci —
ani `wire-panels`, ani jedno preview, ani jeden driver. Takže `NavigationGroup`
i `DomainModule` by se dnes stavěly do prázdna: skupina s ikonou je vlastnost
menu, které se nekreslí, a modul je vidět jen tím menu. **První krok je proto
konzument navigace ve workbenchi**, ne kontrakt — a s ním druhý resource, protože
s jedním (`InvoiceResource`) se skupiny nedají ukázat. Celé pořadí a co u každého
kroku změřit: §0b.

`v2-master-plan.md` má V2.6 vyřešenou jako odloženou; **až bude krok 5 hotový,
přepiš ji tam** — do té doby ta věta popisuje rozhodnutí, které vlastník změnil.

#### Krok 1 hotový 2026-09-02: konzument navigace

Workbench registruje **tři** resources místo jednoho — `InvoiceResource`
(`Billing`), nový `TaskResource` a `DocumentResource` (oba `Operations`) — a nad
nimi stojí shell `/previews/workspace/{resource?}`: sidebar z
`app(Workspace::class)->navigation()` vedle seznamu vybraného resource, s
`wire:navigate` mezi nimi. Driver `verify-workspace-nav.mjs` je **24/24**.

**První nakreslené menu okamžitě našlo dvě věci, které žádná brána vidět
nemohla** — obě v §2:

1. **položka bez vlastního labelu se nejmenovala nijak** (`NavigationItem::make()`
   je tvar, který napsali oba reální konzumenti, a `getLabel()` na něm vracel
   `null`);
2. **položky neměly identitu** — `navigation()` vracela `array<int, …>`, takže
   menu nešlo na nic nalinkovat, a `NavigationItem` záměrně nedrží URL.

Obojí opravené v jediném vlastníkovi (`Workspace::items()`), s testy, mutací
oběma směry a docs v EN i CS.

**Co to řeklo o kroku 2** (`NavigationGroup`) je v §0c toho plánu: pořadí skupin
se nedá deklarovat, skupina nemá label oddělený od klíče, nemá viditelnost ani
ikonu, a sbalení nemá vlastníka.

#### Krok 2 hotový 2026-09-02: `NavigationGroup`

`Core\Resources\Navigation\NavigationGroup` (klíč + kanonické `HasName`/
`HasLabel`/`HasIcon`/`HasVisibility`/`HasSortOrder`), registr `NavigationGroups`
jako singleton, a `Workspace::navigation()` vrací
`array<string, NavigationGroup>` — skupina si nese své položky. Sidebar ve
workbenchi kreslí nadpis s ikonou a pořadí skupin je **deklarované**, ne pořadí
registrace; driver 27/27. Celý rozklad „co ten string neuměl" je v §0d plánu.

Tři věci stojí za zapamatování:

- **Sbalení se vědomě nedoplnilo.** Nikdo nekreslí sbalitelné menu a slovník
  `collapsible`/`collapsed` v repu **už existuje dvakrát** (`Section`, tabulkové
  `collapsibleGroups()`). Třetí kopie před konzumentem je přesně to, co §2 tohoto
  souboru popisuje jinde jako druhé kolo.
- **`sort()` je od teď kanonický `HasSortOrder`** — extrahovaný z `NavigationItem`,
  ne napsaný podruhé na skupině. Docblok explicitně říká, že to **není** řazení
  dotazu (`Column::sortable()`, `SortClause`), protože to je jediné slovo, kde
  tahle dvě témata kolidují. Pint na tom mimochodem ukázal past: `{@see}` na
  `SortClause` si `fully_qualified_strict_types` přepsal na `use`, což by z L0
  souboru udělalo import do L1 — `ModuleLayersTest` by to chytil až v CI.
- **Nedosažitelnou větev jsem si napsal sám a našla ji brána, ne čtení** — viz §2.

**Vstup pro krok 3:** `Workspace` iteruje jen `ResourceRegistry`, takže dashboard
se dnes do menu nemá jak dostat. To je první otázka kroku 3, ne poslední.

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

### V2.4 — ERP execution ✅

Čtyři nezávislé balíky, všechny hotové včetně Q-3 — to ale s jiným zadáním, než plán psal, a v obou půlkách jinak. Viz §2.

| Balík | Co vzniklo |
|---|---|
| **N** ✅ | `Notifications\{DatabaseNotification, NotificationCenter, NotificationBell}`, `Drivers\{DatabaseDriver, StackDriver}`, `Contracts\ResolvesNotifiable` + `AuthenticatedNotifiable`, migrace, EN/CS překlady. Config `default` bere **seznam** driverů |
| **Q** ✅ (Q-1/2/4) | `Actions\Concerns\Queueable` na `BaseAction`, `Actions\Jobs\RunActionJob`, `Exceptions\QueuedActionException`, seamy `resolveActionByName()` / `resolveRecordsByKey()` na table hostiteli |
| **Q-3** ✅ | `Export\Contracts\Exporter::writeTo()` ve všech třech exportérech, `TableExport::store()`, `Export\Jobs\RunExportJob`, `Import\Jobs\RunImportJob`, `queueTableExport()` / `queueTableImport()`, `ImportException::fileNotFound()`. **Export potřeboval nový režim zápisu, import ani řádek** — viz §2 |
| **T** ✅ | `Core\Tenancy\{Tenancy, TenantScope, NullTenantResolver}`, `Contracts\TenantResolver`, `Concerns\BelongsToTenant`, `Exceptions\TenancyException`, config. **Globální scope, ne plugin hook** — viz §2 |
| **WF** ✅ | `Core\Workflow\WorkflowState`, `Actions\TransitionAction`, `Exceptions\IllegalTransitionException`. **Žádná barva/popisek/ikona** — enum kontrakty + `BadgeColumn` to už vlastní |

**Plán poprvé v téhle sérii seděl na stavu kódu**: všechny čtyři balíky opravdu
nula v `src`. Dvě korekce: WF-4 počítá se `StatusColumn` z V2.1 B-2, který byl
zamítnut — `BadgeColumn` to už umí, závislost je splněná (§2, krok 14) — a Q-3
mělo jedno zadání pro dvě věci, které spolu nemají nic společného (§2, krok 15).

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

**V2.4/WF: závislost, kterou plán vedl jako blokující, byla splněná dřív, než
vznikla.** WF-4 zní „`StatusColumn` wiring — rendering typ vzniká ve V2.1 B-2".
Jenže `StatusColumn` byl v kroku 14 zamítnut jako prázdná podtřída: `BadgeColumn`
vrací barvu, ikonu **i** label přes `EnumResolver`. WF-4 tedy nemá co napojovat —
a `WorkflowState` proto **žádnou barvu ani popisek nedrží**. Kdyby držel, byl by
to přesně ten paralelní slovník, co se v tomhle repu pořád maže.

`TransitionAction` bere label/barvu/ikonu z cílového enumu stejnou kanonickou
cestou jako `BadgeColumn`, takže tlačítko a badge se nemůžou neshodnout.

Dvě rozhodnutí ve stroji, která se liší záměrně: **nelegální hrana vyhodí
výjimku** (mlčet znamená nechat záznam tam, kde uživatel věří, že se posunul),
**guard veto vrátí false** (to je doménová odpověď, ne rozbitý stroj). A guardy
na jednom stavu musí projít **všechny** — schvalovací limit a kontrola úplnosti
jsou samostatná pravidla a `&&` do jedné closury ztratí, které z nich řeklo ne.

Sedm mutací, sedm chycených. Dvě drobnosti při psaní: enum kontrakty se jmenují
`getLabel/getColor/getIcon`, ne `label/color/icon`, a akce nemá `isVisible()`,
má `isHidden()` — obojí chytila až první spuštěná sada, ne čtení.

**V2.4/T: hook, který plán jmenuje, tenancy vynutit nemůže — a bezpečnost se
nedá stavět na seamu, který se nemusí spustit.** T-2 chce registrovat
`table.querying` hook na prioritě -100 a scopnout query. Změřeno:

- **polový** `table.querying` hook (krok 2.5 v `TableQueryService`) dostane
  `table`, `columns`, `filters`, `sort_*`, `search` — **query vůbec ne**;
- **typovaný** (krok 3.5) query má, ale jeho vlastní docblok ho vede jako
  „read-only plan inspection or observation";
- oba se spustí **jen když je navázaný `PluginManager`** (`if ($pluginManager !== null)`);
- a i kdyby, pokryjí jednu čtecí cestu. Nic z toho neplatí pro `SaveHandler`,
  relaci, export, frontovaný job ani `Model::find()` v controlleru aplikace.

Tenancy, která drží na výpisu tabulky a nedrží na `find()`, není tenancy. T-4
říká „scope se aplikuje na source" a míří správně, ale ještě dál sahá **globální
Eloquent scope**: pokryje každý dotaz, který Eloquent postaví, včetně `update()`
a `delete()`, které by hook nikdy neviděl. Proto `Core\Tenancy\TenantScope` +
`Concerns\BelongsToTenant`, ne `TenancyPlugin`.

**Fail-safe je zapsaný tak, aby šel asertovat přímo**, ne přes počet řádků:
`Tenancy::shouldBlockEverything()`. Zapnutá tenancy bez tenanta emituje `0 = 1`.
Záměrně **ne** `whereNull(sloupec)` — řádek, který nikdo nevlastní, by pak viděli
všichni, což je tentýž únik v jiném kabátě; test na to je.

**A test chytil ostrou chybu v mém vlastním návrhu.** `TenantScope` si držel
`Tenancy` z konstruktoru. Globální scope se ale přidává **jednou na třídu na
proces**, takže by odpovídal podle toho, kdo byl aktuální při prvním doteku toho
modelu — špatně na druhém requestu pod Octane a u každého jobu po prvním. Teď se
`Tenancy` resolvuje až v `apply()`.

Sedm mutací, sedm chycených — až po tom, co jsem doplnil test s **joinem**:
nekvalifikovaný sloupec jako jediný prošel, protože žádný test nejoinoval, a to
je přesně tvar, který v produkci praskne (joinovaná tabulka mívá vlastní
`tenant_id`).

**V2.4/Q: exekuční cesta akcí je stavěná pro živou komponentu s modálem, a to
mění, co „queued action" vůbec může znamenat.** `actionCallbackBindings()` dává
callbacku `set`, `setParent`, `setFrame`, `close`, `replace` — samé operace nad
modálním zásobníkem. Job žádný nemá. Plán mluví o „spustí `ActionPipeline`
v jobu", jenže spustit se dá jen *podmnožina*, a ta chybějící půlka se musí
**ozvat**, ne degradovat na no-op: tichý `$close()` vypadá, že fungoval, a
vývojář se to dozví, až uživatel nahlásí, že se modál nezavírá. Proto jsou ty
bindingy navázané na výjimku.

Druhá věc, kterou plán jmenuje správně a stojí za doložení: `ActionContext` drží
`Model` a `Collection<Model>`. Job veze **jména a klíče**, hostitele znovu
postaví a záznamy načte čerstvé — takže řádek změněný mezi kliknutím a během se
zpracuje v podobě z běhu, ne ze zařazení. To je vlastnost, ne detail, a je
zdokumentovaná.

**Q-3 (export/import na frontě) se tak, jak plán psal, udělat nedá.**
`CsvExporter::export()` vrací `StreamedResponse` — download, který v jobu
vzniknout nemůže. Není to „přidat `->queue()` na `ExportAction`": jsou to **dvě
různé doručovací cesty** (stream do response synchronně vs. zápis na disk +
notifikace s odkazem), a exportéry uměly jen tu první. Q-3 tedy začalo tím, že
exportéry dostaly režim zápisu na disk — vlastní krok, ne dopsání jednoho volání.

**A pak se ukázalo, že jedno zadání drželo pohromadě dvě nesouvisející věci.**
Export musel dostat nový režim zápisu; import nepotřeboval **ani řádek** —
`TableImport::import(string $path)` je cesta dovnitř, výsledek ven, žádná response
v cestě. Plán je psal jako jednu položku („export/import na frontě"), protože se
oba tak jmenují; měření říká, že jedna z nich byla nový mechanismus a druhá
adaptér nad hotovým. Poučení pro §4: **položka pojmenovaná dvěma podstatnými jmény
je skoro vždycky dvě položky s různou cenou.**

`writeTo(string $path, …)` je v kontraktu proto, že `php://output` **je cesta**.
Download je ta samá metoda v response obalu, ne druhá implementace — dvě kopie
„udělej ze záznamů soubor" se rozejdou v okamžiku, kdy se jedna z nich dozví o
novém typu sloupce. Mutace to potvrdila: vypnutí hlaviček ve `writeTo()` položí
šest testů napříč oběma doručeními.

**Nález, který zadání nepojmenovalo: export na frontě ztrácel filtry.**
`RunExportJob` veze třídu komponenty (dotaz jsou closury a builder, serializaci
nepřežije), takže worker hostitele mountuje čerstvého — a čerstvý hostitel má
výchozí stav. Kdo si tabulku zúžil na dvacet řádků a dal export na frontu, dostal
všech deset tisíc, v souboru natolik věrohodném, že to nikdo nezkontroluje. Job
proto veze i `tableState->all()` a po mountu ho vrátí přes `replace()`. Mutace
„stav do jobu necestuje" padá právě na jednom testu, tom, který porovnává obsah
souboru — což je zároveň důkaz, že ostatních sedm by chybu nechytilo.

**Zrcadlový nález na importu: tichá nula.** `CsvImporter::rows()` bere nečitelnou
cestu jako „žádné řádky" a má na to test — správně, když u toho uživatel stojí.
Na frontě je to lež: „naimportováno 0 řádků, 0 chyb" se nedá odlišit od prázdného
souboru, a job by se tvářil jako úspěch. `RunImportJob` proto sahá na disk sám,
a když soubor není, **vyhodí** `ImportException::fileNotFound()`, aby se běh
opakoval. Zároveň bere cestu na disku, ne reálnou: worker smí být jiný stroj a
`fopen()` neumí S3.

**Třetí nález našel až důsledek prvního: uložený soubor lhal o svém obsahu.**
`export()` u Excelu i PDF přejmenuje soubor na `.csv`, když příslušná knihovna
chybí — s komentářem „the reader has to be told what they actually got".
`store()` si jméno stavěl z formátu, takže na frontě by vznikl `.xlsx` s CSV
uvnitř: lež, která vyplave až mnohem později, když ho někdo otevře. Sync cesta to
pravidlo měla a queued cesta ho ztratila — přesně ta třída chyby, kvůli které se
`writeTo()` dávalo do kontraktu. Vlastníkem je teď `Exporter::extension()`:
exportér ví, jestli formát vůbec unesl, a `fullFileName()` se ptá jeho, ne
formátu. Mimochodem to smazalo dva `str_replace('.xlsx', '.csv', …)`, každý s
vlastní příponou natvrdo.

**Dvě mutace mi na importu přežily a obě ukázaly na díru, ne na zbytečný kód.**
`mountWithTable()` v jobu nebylo ničím podepřené — protože moje fixture v
`table()` na stavový bag nesahala; hostitel, který na něj sáhne (filtr s výchozí
hodnotou, řazení odvozené ze stavu), bez mountu fataluje na neinicializované
typované property, což je běžnější tvar než ten můj. A rozlišení
`warning` / `success` podle `hasFailures()` přežilo, protože jsem v testu
kontroloval počty, ne typ notifikace. Obojí teď drží test; u prvního jsem
odpověď **změřil** (`public StateContainer $tableState` se inicializuje až v
`mountWithTable()`) místo aby hádal, jestli je mount potřeba.

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

**`v2-deferred-items.md` §3: obě otevřené podpoložky měření zamítlo — a shodly se
v tom, že obě chtěly postavit druhé kolo vedle hotového vlastníka.**

§3.2 („`mutateDataBeforeSave()` se wrappne jako MutationPipeline before hook")
není splnitelná, protože ty dva tvary nejsou převoditelné:

| | `mutateDataBeforeSave` | `MutationPipeline::before()` |
|---|---|---|
| Signatura | `Closure(array $data): ?array` | `Closure(mixed $value, string $attribute): mixed` |
| Vidí | **celá data najednou** (cross-field) | jednu hodnotu |
| Umí zrušit save | ano — `null` ukončí `save()` | **ne**, žádný kanál |

A per-atributové tvarování na zápisové straně už kanonického vlastníka má:
`DehydratesState::dehydrateState()` (ADR 0021), čtyři implementace, krok 2.5
v `SaveHandler`. `MutationPipeline` navíc nemá **žádné API na registraci**, takže
zapojení do `dehydrate()` by přidalo smyčku přes trvale prázdné pole.

§3.3 („`RelationshipSaveHandler` ručně iteruje → přenést do `Dehydrator`") stojí
na premise, která neplatí. `RelationshipSaveHandler` **je** kanonický vlastník
kaskády se **dvěma** konzumenty (`SaveHandler::save()` krok 6 a
`BelongsToSelect::applyEditOptionUpdate()`) a zdokumentovanou maticí podle typu
relace. Co by se stěhovalo, není dot-notation, ale sémantika Eloquent zápisu —
kaskáda, která **načítá modely**, aby padly eventy a respektovaly se
`SoftDeletes`, plus `sync()` pivotu. `Dehydrator` oproti tomu **jen setuje
atributy a nikdy neukládá**, a `Repeater` je třída z `wire-forms`, tedy nad
`wire-core` — přesun by obrátil graf balíčků.

**A ten `Dehydrator` má nedosažitelnou větev, kterou docblok popisuje jako
funkci.** Dot-notation půlka („traverses the relation path and sets the attribute
on the related model") je nepokrytá — smazat ji celou projde **všemi 3300 testy**
core + forms — a z jediného volajícího se **nedá dosáhnout**: dotted název pole
přijde z Livewiru už zanořený (`company.name` → `['company' => ['name' => …]]`),
takže klíč, který `persist()` vidí, tečku neobsahuje. Ověřeno prototypem na
hostiteli se statePath: hlavní formulář s `TextInput::make('company.name')` skončí
na `no such column: company`, ne v té větvi. A i kdyby se do ní dostal (callback
v `mutateDataBeforeSave` vrátí dotted klíč), atribut se nastaví na relačním
modelu, který nikdo neuloží — tichá ztráta zápisu. Schopnost „zapsat zpět přes
dotted cestu" má přitom skutečného vlastníka s dokumentovanou maticí:
`BelongsToSelect`. Docbloky i `unified-engine.md` to teď říkají; **smazání je
rozhodnutí vlastníka repa** (`dehydrateAttribute()` je public API v publikovaném
balíčku), a leží v §3 vedle `MutationPipeline`.

**Rozhodnuto 2026-09-02: smazat.** Vlastník repa rozhodl metodu odstranit celou.
Měření u toho opravilo dvě věci v samotném zadání:

1. **„Smazat metodu" nešlo doslova.** `dehydrateAttribute()` **měla volajícího** —
   `dehydrate()`, tedy hlavní vstup, kterým jde každé uložení formuláře. Smazat
   se dala až po přesunu skalární poloviny těla (cast → `reverseTransform` →
   `setAttribute`) do `dehydrate()`; nedosažitelná zůstala jen ta relační, a
   s ní zmizela i privátní `dehydrateRelation()`, jejíž jediný volající to byl.
2. **Celý `Dehydrator` neměl jediný přímý test.** Grep přes `packages/*/tests`
   i `tests/` nenašel ani zmínku jména třídy — pokrytá byla jen nepřímo, cestou
   ukládání formuláře. To je přesně důvod, proč tam celá public metoda mohla
   nedosažitelná sedět: nikdo se jí nikdy nezeptal přímo. Testy má od teď.

**A první verze toho testu byla lživá** — přesně ten druh, který tenhle soubor
popisuje o kus výš u měny. Asertoval `getAttribute('amount') === 12.5`, jenže
Eloquent skalární cast aplikuje **při čtení**, takže to platí i s vymazanou
konverzí: mutace prošla. Rozdíl, který ta konverze dělá, je vidět jen na **raw**
atributech (`getAttributes()`) — tedy na tom, co půjde do databáze: float, ne
string, který poslal formulář. Po přepsání na raw mutace padá.

**Audit ale našel to, co hledal — v půlce, která vypadala hotově.** Větev, kvůli
které `saveRepeater()` načítá model místo query-builder `->update()`, je
**nepokrytá**: záměna za přesně ten anti-pattern, před kterým komentář varuje,
prošla všemi 1129 testy balíčku `forms` — **včetně testu jménem „editing an
existing relation row applies casts (no cast bypass / corruption)"**, který je na
tu regresi psaný jmenovitě.

Důvod je poučnější než ten nález. Komentář tvrdil, že query-builder update
zahodí i `array`/`json` cast a zapíše `Array to string conversion`. To už neplatí:
**query grammar json_encoduje array binding sám**, takže právě ta půlka, kterou
test asertoval, vyjde stejně oběma cestami. Framework mezitím pokryl přesně ten
případ, na kterém test stál — a test tím přestal cokoli rozlišovat, aniž
zčervenal. Skutečně bypassnuté zůstávají **model eventy, mutátory a casty, které
něco dělají** (`encrypted`, enum, jakýkoli `CastsAttributes`). Dvě nové sady to
drží: cast, jehož `set()` hodnotu mění, a `updating`/`updated` eventy — obojí
zrcadlo testů, které delete větev měla a update větev ne. Komentář teď říká, co
platí.

**Diff brána pokrytí je červená z minulého běhu, ne z tohohle.** `verify-coverage
--diff=origin/1.x` hlásí neposlouchané řádky jen v `packages/table/src/Export/*`,
přidané v Q-3. Rozpadají se na dvě skupiny a ani jedna nejde pokrýt testem tak,
jak repo teď stojí — viz §3.

**ADR 0025 krok 10: zadání mířilo na bajty a celá cena byla v jedné `import`
řádce.** Plán ho vede jako „vyříznout `wireFillHandle` z 38 KB bundlu", tedy jako
packaging úkol. Bajty vyšly a potvrdily, že to má smysl:

| | bajtů |
|---|---|
| `wire-core-dropdown.js` dřív | 38 365 |
| `wire-core-dropdown.js` teď | **29 235** (−9 130, −23,8 %) |
| nový `wire-table-fill.js` | 9 230 |
| součet pro konzumenta s tabulkou | 38 465 (**+100 B**) |

Duplikace sdílených modulů (`editable/sync`, `support/autoscroll`, `support/rows`)
stojí po minifikaci sto bajtů — tedy nic. Kdo tabulku nemá, přestal platit 9 KB za
gesto, které nemůže spustit. To je tentýž argument, kterým se ve V2.3 stěhovala
owner vrstva („forms-only resource musel instalovat tabulkový balíček").

**Skutečná cena ale nebyla ve stěhování, byla v jedné hraně.** `support/partials.js`
— klientská půlka `wire:partial`, která zůstává v core — importovala
`isFillDragging()` **z fill controlleru**, aby cílený morph řádku neběžel přes
probíhající drag. Ten guard není kosmetika: jeho vynechání je zapsaný defekt
(„targeted fill wipe the cells it had just painted"). A bundly jsou samostatné
IIFE, takže po rozdělení ten import **neexistuje** — a co hůř, `esbuild` by ho
mlčky vyřešil ze zdrojů a **vrátil celý fill controller zpátky do core bundlu**,
takže by rozdělení nic neušetřilo a nikdo by si nevšiml.

Vlastníka toho signálu jsem nemusel vymýšlet — už existoval. Controller vedle
`draggingControllers.add(this)` na **tomtéž řádku** píše `document.body.classList
.add('wire-filling')`, ta třída už řídí CSS fill handle a **dva browser drivery ji
už asertují**. Takže se nezaváděl nový protokol, jen se začal číst ten publikovaný.
Fail-safe směr sedí: bez tabulkového bundlu třída nikdy nevznikne, odpověď je
`false` — což je správně, protože pak není co táhnout.

Obě půlky drží jeden test (`FillHandleAssetTest`), schválně v jednom souboru:
každá zvlášť je tichá ztráta dat.

**A ten přesun vytáhl tři věci, které měření nepředpovědělo:**

1. `EditableCellVersionSourceTest` byl **cross-package invariant** psaný jako
   core test — asertoval `editable/sync.js`, `dropdown.js` **a** `fill/*`. Po
   přesunu se rozpadl na vlastníka (core, kde bydlí kanonický `versionOf`) a
   konzumenta (`EditableCellVersionConsumerTest` v table). Nešlo o přejmenování:
   `versionOf` je **jediný čtenář** té precedence a odešel s fillem, takže se
   tree-shakingem ztratil z core bundlu — asertovat ho tam dál by znamenalo
   asertovat něco, co v souboru není.
2. `WireStackScriptsTest` počítá `<script>` tagy natvrdo. Osm → devět, a je to
   správně: **nepřibyla devátá váha**, jen se 9 KB přesunulo z jednoho bundlu do
   druhého.
3. Cesta `/wire-table/assets/{asset}.js` je generická, takže nový bundle se
   servíruje bez jediné změny v routách — jediné, co bylo potřeba, je
   `Bundle::make()` v provideru a jeden esbuild příkaz v `package.json`.

**Poučení pro zbývající kroky ADR 0025 a pro §5 té ADR: počítej běhové hrany, ne
bajty.** Bajty se přesunuly za deset minut. Jeden import mezi bundly byl celá
práce — a byl to zrovna ten, jehož selhání nemá žádný symptom kromě smazaných dat.

**V2.6/krok 1: `Workspace` neměl konzumenta — a první nakreslené menu bylo
prázdné.** §0a plánu i tenhle soubor psaly, že `Workspace` **nechybí nic**:
existuje od V2.3, je otestovaný (`WorkspaceTest`, pět testů) a zdokumentovaný
v obou jazycích. Bylo to změřené správně a přesto to bylo špatně, protože se to
měřilo proti *testům a docs*, ne proti konzumentovi. Ten do 2026-09-02
neexistoval — a v okamžiku, kdy vznikl, menu vyrenderovalo **dva prázdné řádky
ze tří**:

| Co se rozbilo | Proč to nikdo neviděl |
|---|---|
| `NavigationItem::make()` bez labelu → `getLabel()` vrací `null` | **všech pět** fixtur ve `WorkspaceTest` a **všechny** příklady v docs label předávají. Přitom oba skuteční konzumenti v repu — workbench `InvoiceResource` a boost `describe-resource`, který `navigation.label` reportuje — ho **nepředávají**. Testovaný byl tvar z docs, ne tvar z kódu |
| položky bez identity: `array<int, NavigationItem>` na skupinu | dokud nic nekreslí menu, „na co ta položka odkazuje" není otázka. `NavigationItem` **záměrně** URL nedrží (registr s URL je panel), takže bez klíče resource se položka nedá nalinkovat — a menu, které nikam nevede, není menu |

Mutace oběma směry: doplnění label fallbacku neshodilo **ani jeden** z 21 testů
na `Workspace`/`NavigationItem`/`ResourceRegistry`, zatímco klíčování jeden test
shodilo (asertoval `array_map` přes položky skupiny, a ten klíče zachovává) —
takže půlka téhle změny byla nepokrytá úplně a půlka jen v jednom směru.

Oprava je na **jednom** místě a v tom vlastníkovi, který má obě poloviny naráz:
`Workspace::items()` klíčuje položky klíčem resource a jméno bere z
`pluralLabel()`, když si ho položka neurčila sama. Jméno resource už vlastní
`DescribesRecords`; menu, které by ho nutilo napsat podruhé, je ten druhý
slovník, co se v tomhle repu pořád maže. `usort` → `uasort`, aby klíče přežily
řazení.

**A ten sidebar je nakreslený v aplikaci, ne v balíčku** — `Workspace` ve svém
docbloku říká „what renders the result is the application's", takže konzument je
workbench shell `/previews/workspace/{resource?}`, ne nová view vrstva v core.
Krok 1 tedy nezavedl žádný runtime, přesně jak §0b pro celou řadu chce.

**Vedlejší nález, provozní: workbench databáze driftuje.** Drivery do ní zapisují
a zůstane to tam — `Task` číslo 1 se dnes jmenuje `sortable-partial-1788287606465`,
protože ho kdysi přepsal `verify-sortable-partials`. Aserce na obsah řádku je
proto v driverech **fixture v přestrojení**; nový driver tvrdí strukturu (sloupce,
které deklaruje resource; klíče položek menu) a rozdíly (hledání → 0 řádků →
zpět), ne konkrétní data. Seeder u některých tabulek slibuje „deterministic on
purpose — the CDP drivers address rows by name"; pro `tasks` a `documents` to
už neplatí.

**V2.6/krok 2: obě kopie jednoho pravidla jsem napsal ve stejné hodině — a ta
nedosažitelná vypadala jako ta hlavní.** `Workspace::navigation()` dostalo
`if (! $group->isVisible()) { continue; }`, což se čte jako místo, kde pravidlo
„skrytá skupina není v menu" bydlí. Bydlí jinde: `entries()` položky skryté
skupiny zahodí dřív, takže bucket pro takovou skupinu **nikdy nevznikne** a ten
guard nemůže proběhnout. Nenašlo to čtení ani code review — našla to
`verify-coverage --diff`, která hlásí přidané řádky bez testu, a jediný takový
řádek v celé změně byl ten `continue`. Poučení není „psát míň guardů", ale to,
co §2 opakuje od V2.1: **u každého pravidla se ptej, kdo ho vlastní, i když obě
kopie píšeš ty sám** — o hodinu později už to vypadá jako dvě různá pravidla.

**A druhý nález ze stejného souboru: `items()` slibovalo v docbloku pořadí,
které nedělalo.** „Every visible entry, flat and ordered" — vracelo pořadí
registrace. Test na `items()` počítal položky, takže na pořadí se nikdy nikdo
nezeptal. Teď řadí podle `sort()` **a** shoduje se s `navigation()` v tom, co je
v menu (položky skryté skupiny v něm nejsou) — „je to v menu" musí být jedna
otázka, ne dvě odpovědi.

**Druhý vedlejší nález: index previews zaostal za routami, přestože komentář
tvrdil, že to nemůže.** `workbench/routes/web.php` má nad indexem komentář „the
index cannot fall behind the routes again", ale řádky indexu se skládaly ze tří
kolekcí a routy se registrovaly ze **čtyř** — čtyři resource stránky (V2.3) a
paleta globálního hledání (V2.5) na indexu nikdy nebyly. Prozradilo to mapování
sekcí: `'resource-' => 'Resources (owner layer)'` je sekce, do které se nemohl
dostat žádný řádek. Opraveno tím, že index bere všechny kolekce, ne tři ze čtyř.

---

## 3. Co je vědomě neudělané

| Věc | Proč | Kde |
|---|---|---|
| ~~ADR 0025 krok 8 — Blade coupling (`callInfolistAction` natvrdo ve view)~~ | **rozhodnuto 2026-09-01: nedělat.** Tři důvody z měření: (1) `ModuleLayersTest` čte PHP `use`, Blade nevidí — číslo dluhu by se nezměnilo; (2) měřený dluh vede **opačně**, `InteractsWithActions` (Actions) importuje `Infolists\{Entry, RepeatableEntry, Infolist}`, a to zůstane; (3) premisa („wire-core bez Actions by nerenderoval") padla s §1 ADR, která rozhodla core neštěpit. Vazba na fázi 0 [`action-render-unification.md`](action-render-unification.md) byla navíc **mylná** — ten plán řeší `Action`/`HeaderAction`/`BulkAction`/`ActionGroup` a `component-action.blade.php` v něm není ani v §1.1, ani v žádné fázi. Čtení ale našlo **ostrý defekt**, viz §2 | ADR 0025 |
| ADR 0025 krok 4 — `Trans`/`Deprecation` do Foundation | Zdůvodnění padlo: vrstvy L2→L1 **povolují**, takže by to bylo 33 souborů za nulový přínos pro hranice. Zbývá argument kanonického vlastnictví, ale to je jiný důvod | ADR 0025 |
| `modal-host.blade.php` instancuje `Modals\Html\*` | **Není defekt** — je to výsledek [`rule5-framework-wide-modal-sweep.md`](rule5-framework-wide-modal-sweep.md): framework nesmí záviset na `<x-*>` | — |
| Systematické hledání duplicitních abstrakcí napříč V2 | Průřez auditu padl na session limit. `DataSourceCapabilities`/`CapabilitySet` byl nalezen mimo audit a nejspíš nezůstal sám | [`v2-audit-2026-08-26.md`](v2-audit-2026-08-26.md) §6 |
| `ShellRenderPlan`, `InteractionRenderPlan` — host pořád `mixed` | Polling, live kanál, readiness, přístup ke stavu nemají pojmenovaný kontrakt | [`v2.1-…`](v2.1-monolith-split-implementation.md) §0a |
| ~~`resolveActionType()` — public static, nula volajících v src~~ | **rozhodnuto 2026-09-01: ponechat.** Měření našlo, že to není jedna metoda, ale **tři** — `resolveColumnType()`, `resolveFilterType()` i `resolveActionType()`, identický tvar, všechny s nula volajícími. A nejsou to zapomenuté zbytky: vytvořilo je zapsané doporučení v [`v2-deferred-items.md`](v2-deferred-items.md) §7A.5 („ponechat registry pro introspekci, přidat `resolveColumnType()` / `resolveFilterType()` metody na Table pro budoucí config-driven use-case"). Nula volajících je tedy záměr. Smazat jednu ze tří by navíc rozbilo souměrnost plugin API. **Není to stejná otázka jako `Dehydrator::dehydrateAttribute()`** — tam jde o nedosažitelnou větev uvnitř používané metody, tady o nepoužitou, ale záměrně přidanou trojici | `Table.php:1780–1830` |
| Tenancy nekryje non-Eloquent `DataSource` | Globální Eloquent scope nemá co scopovat u `CollectionDataSource` ani u zdroje nad API. Zdokumentované v `docs/authorization.md` jako „co scopované není"; správné místo je dekorátor nad `DataSource` (T-4 tak, jak ho plán psal). **Dokud to nevznikne, tenancy nezapínej nad non-Eloquent zdrojem** | [`v2.4-…`](v2.4-erp-execution-implementation.md) T-4 |
| `Core\Hydration\MutationPipeline` — nula volajících, **zůstává jako stavební blok** | Nález S3. Sourozenec `Hydrator` byl smazán (nula volajících, žádný plán); `MutationPipeline` ne — vlastník repa 2026-08-30 rozhodl nechat. **Od 2026-08-30 už to ale není nedodělaný krok:** §3.2 měření zamítlo (tvar callbacku není převoditelný, per-atributového vlastníka má `dehydrateState()`, třída nemá API na registraci) — viz §2 | [`v2-deferred-items.md`](v2-deferred-items.md) §3.2 |
| ~~`Core\Hydration\Dehydrator` — dot-notation větev je nedosažitelná~~ | **uzavřeno 2026-09-02: smazáno** rozhodnutím vlastníka repa. `dehydrateAttribute()` i privátní `dehydrateRelation()` jsou pryč, skalární půlka těla se přesunula do `dehydrate()`. Měření cestou opravilo zadání dvakrát a odhalilo, že třída neměla **jediný přímý test** — viz §2 | §2 |
| ~~Export: optional-library cesty nikdy nespustil žádný test~~ | **uzavřeno 2026-09-01.** `openspout ^4.0` a `barryvdh/laravel-dompdf ^3.0` jsou v `require-dev` (root i balíček) a v `suggest`; `ExporterLibraryTest` testuje `writeTo()` obou proti reálnému souboru (xlsx čte přes `ZipArchive`, PDF přes `%PDF-`). `verify-coverage --diff=origin/1.x` je **poprvé od Q-3 zelená**, pokrytí table 91,8 % → 92,9 %, floor 91 → 92. Měření cestou opravilo tři věci, viz §2. Dvě poznámky k prostředí: obě knihovny potřebují PHP rozšíření, která workflow nejmenovaly (`fileinfo`, `xmlreader` pro openspout), a composer bez nich odmítne nainstalovat cokoli — doplněno do `tests`, `coverage`, `database-tests` i `static-analysis`. A `openspout` v4.32 chce PHP `~8.3`, což je v pořádku jen proto, že `composer.lock` je v `.gitignore` — každé prostředí si resolvuje sám, takže na 8.2 spadne constraint `^4.0` na starší 4.x. Kdyby se lock někdy začal commitovat, tohle je první věc, která se o tom dozví | §2 |
| ~~`v2-deferred-items.md` §3 je hotová z jedné čtvrtiny~~ | **uzavřeno 2026-08-30.** §3.1 hotová, §3.2 i §3.3 **zamítnuté měřením** (viz §2), §3.4 tím bezpředmětná. Není to nedodělaná položka, je rozhodnutá — a `RelationshipSaveHandler` z toho čtení dostal dva chybějící testy | [`v2-deferred-items.md`](v2-deferred-items.md) §3 |
| ~~Tři workbench soubory drží pohromadě a žádný z nich není commitnutý~~ | **uzavřeno 2026-09-01.** Refaktor preview rout je hotový — `workbench/routes/web.php` má jednu tabulku `$screens`, ze které se generují routy **i** index, takže index už nemůže zaostat za routami. `verify-resource-pages` na něm projde, takže trojice šla konečně commitnout naráz a resource stránky jsou od teď v CI | — |
| ~~V2.5 nemá dokumentaci~~ | **uzavřeno 2026-09-01.** `savedViews()` a `collapsibleGroups()` doplněné do stránek, které to téma už vlastní (`table/advanced.md` § Saved Views, `table/grouping.md` § Collapsible Groups); globální hledání má vlastní stránku `core/global-search.md`, protože kontrakt + služba + paleta se do sekce nevejdou. EN i CS, obě v jednom commitu; `docs:check`, `docs:standard` i `docs:api` zelené, boost mirror sesynchronizovaný. Psaní docs našlo tři defekty, viz §2 | — |
| Boost guidelines neznají plugin hooky | `guidelines/` ani `skills/` nepopisují `PluginManager` vůbec — takže pravidlo „hint rozhoduje dispatcher" tam není a nemohlo zestárnout. Doplnit až s vlastní plugin sekcí, ne ad hoc | — |
| ~~Boost docs mirror rozjetý~~ | **zavřeno 2026-08-30.** `packages/boost/resources/boost/docs/` je *commitnutá* kopie `docs/` (viz `scripts/sync-boost-docs.php`) a `composer boost:check-docs` je brána v `docs-check.yml`. Byla červená **už před tímhle během**: `money.md` a `metric.md` z V2.1 se do balíčku nikdy nedostaly. Po každé změně v `docs/` pouštěj `composer boost:sync-docs` | `.github/workflows/docs-check.yml` |

---

## 4. Co je na řadě

**Šest fází hotových (V2.0–V2.5). Běží V2.6** — měřením odložená (§0a jejího
plánu), pak **znovu otevřená rozhodnutím vlastníka** (§0b): chybějící části se
doplní. Pořadí je v §0b a **není to pořadí z §2 toho plánu**: začalo se
konzumentem navigace, protože `Workspace` žádného neměl, a bez vykresleného menu
nemá `NavigationGroup` ani `DomainModule` kde selhat v prohlížeči.

**Kroky 1 a 2 jsou hotové (2026-09-02)** — sidebar ve workbenchi nad třemi
resources (driver 24/24), a na něm postavená `NavigationGroup`: klíč oddělený od
nadpisu, ikona, deklarované pořadí skupin, viditelnost celé skupiny (driver
27/27). Sbalení se vědomě nedoplnilo. Detaily v §0c a §0d plánu.

**Na řadě je krok 3, `Dashboard`** — owner nad hotovými widgety
(`packages/core/src/Widgets/`), které dnes skládá ručně preview. První otázka je
změřená už teď: `Workspace` iteruje **jen `ResourceRegistry`**, takže dashboard
se do menu nemá jak dostat, dokud se nerozhodne, jestli je to resource, nebo
druhý zdroj položek navigace.

### Co zbývá otevřené

Nic z toho není fáze; jsou to jmenované položky ze §3, které čekají na rozhodnutí
vlastníka repa nebo na jmenovaného konzumenta:

| Věc | Čeká na |
|---|---|
| Tenancy nad non-Eloquent `DataSource` | dekorátor nad `DataSource` (T-4); do té doby **tenancy nad non-Eloquent zdrojem nezapínej** |
| `ShellRenderPlan` / `InteractionRenderPlan` — host pořád `mixed` | polling, live kanál a readiness nemají pojmenovaný kontrakt |
| Systematické hledání duplicitních abstrakcí napříč V2 | průřez auditu padl na session limit; `DataSourceCapabilities`/`CapabilitySet` nejspíš nezůstal sám |
| Boost guidelines neznají plugin hooky | doplnit s vlastní plugin sekcí, ne ad hoc |
| Coverage floor `table` | 93,0 % proti flooru 92 — `composer coverage:floors` ho zvedne, až se ustálí |

### Co je uzavřené a nemá se otevírat

- **`Table.php`** — nezbyla metoda nad 19 řádků, každý soudržný shluk má concern.
- **`WithTable`** — čtyři z osmi největších metod jsou doražené kroky, které
  skončily v téhle velikosti záměrně (`updateTableCell` 73 ř. jde proti
  doloženému rozhodnutí o vlastnictví transakce). Jediný nesporný kandidát je
  `submitHaltModal` (52 ř.), a to je jedna metoda, ne krok.
- **`@php` bloky ve views** a **`*Skeleton` bez testu zapečených podmínek** —
  obojí dotažené, viz §1 a §2.
- **ADR 0025 kroky 4, 8, 10** — 4 a 8 zamítnuté měřením, 10 hotový a dodělaný.
- **`resolveActionType()` a jeho dva sourozenci** — nula volajících je zapsaný
  záměr, ne mrtvý kód.
- **V2.6 `DomainModule`** — vlastník repa V2.6 znovu otevřel (§0b jejího plánu),
  ale `DomainModule` sám je pořád krok **5**: otevírat ho až po krocích 2–4, a
  i pak začít měřením, ne §2 toho plánu.

## 5. Jak pokračovat

**V2 je uzavřená, takže tenhle prompt už neposílá do fáze, ale na položku ze
§4 „Co zbývá otevřené".** Postup se nemění.

```
Pokračuj podle architecture/plans/v2-progress.md.
Přečti §2 (co měření změnilo na zadání) a §4 (co zbývá otevřené) a vezmi jednu
položku ze §4. Žádná z nich není fáze — u každé nejdřív změř, jestli je pořád
pravda.

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

**Poznámka k `verify:drivers`, doplněná 2026-09-01 měřením:** červený driver je
napřed **podezření na prostředí**, ne na kód. `verify-global-search` spadl na
pěti kontrolách uvnitř sady; bisekce ukázala, že **na commitnutém stromu bez
jakékoli mé změny padal 3× ze 3**, a po **restartu preview serveru** prošel
10/10 dvakrát za sebou. Příčina je degradace dlouho běžícího `testbench serve`
(jednoprocesový PHP dev server, v sadě obslouží stovky requestů; skript ho
restartuje jen když úplně přestane odpovídat) — pomalejší odpověď prohraje race
a driver na ni nebyl odolný. Opraveno v driveru dvakrát: kontrola fokusu se
**pollује** místo jednoho čtení, a když přesto selže, driver input zaostří sám,
aby jedna prohraná race neshodila pět kontrol za sebou (psaní jde do
`document.activeElement`, takže nezaostřený input čte jako rozbitá paleta).
Postup bisekce, který to určil: pusť driver samotný → `git stash push -- <cesta>`
→ pusť znovu → restartuj server → pusť znovu.

Poznámka k `verify:drivers`: **nepouštěj u toho nic jiného.** Drivery sdílejí jeden
preview server a jeden headless Chrome a jejich čekání má rozpočet pokusů
(`until(…, tries = 40)` po 150 ms); souběžný `pest` nebo druhý běh driveru ten
rozpočet vyčerpá a brána nahlásí defekt, který neexistuje. Stalo se to 2026-09-01:
`verify-global-search` spadl na pěti kontrolách dvakrát za sebou vedle běžícího
`pest`, a na klidném stroji **týž pracovní strom** prošel 10/10. Než takovému
červenému driveru uvěříš, pusť ho samotného (`npm run verify:drivers -- <jméno>`)
a pak `git stash push -- <cesta>` a znovu — ať je „moje změna vs. prostředí"
změřené, ne odhadnuté.

Běží přes dvacet minut a **výstup si přesměruj do souboru**, ne přes `| tail`. Roura drží všechno v bufferu do konce procesu, takže
když se běh ukončí jinak, než čekáš, nezbude z něj ani jeden řádek a brána se
musí pustit celá znovu.

**Nový balíček se drátuje na osmi místech** a zapomenout kterékoli je tichá
chyba: `composer.json` (repositories, require, autoload-dev), `phpunit.xml`
(testsuite **i** `<source>` — bez něj se balíček neměří), `tests/Pest.php`,
`scripts/coverage-floors.json`, `phpstan.neon` (paths + excludePaths pro
`WithTable`/`WithForms` hosty), `.github/workflows/split.yml`, README, a
`vendor/orchestra/testbench-core/laravel/bootstrap/cache/` smazat, jinak ho
workbench neuvidí.

**Nepřeskakuj měření — to je jediné pravidlo, které si z tohohle souboru odnes,
když nemáš čas na zbytek.** Za čtyři běhy opravilo zadání šestadvacetkrát. Běh
2026-09-01 přidal jedenáct:

| Krok | §3/§4 slibovala | Měření našlo |
|---|---|---|
| Export `CsvExporter:40` | „nepokrytá defenzivní větev, tichá `return`" | větev je **nedosažitelná**: Laravel převede warning z `fopen` na `ErrorException` dřív, než se na `=== false` dojde. Tichý return nikdy neběžel — volající dostával chybu o streamu, ne o svém exportu |
| Export `TableExport:248` | „nepokrytá" | `tempnam()` **nikdy nevrátí `false`** — změřeno pro `/nonexistent`, `/` i `/dev/null`, vždycky spadne zpátky do systémového tempu. Není to k pokrytí, je to k označení |
| Export — rozsah | dvě knihovní cesty (`Excel`, `Pdf`) | **čtyři zápisové cesty se třemi různými chováními**: `Csv` tichý return, `Pdf` nekontrolovaný `file_put_contents`, `Excel` cizí `IOException`, `store()` už házel. Jeden kontrakt místo tří |
| `PdfExporter::isAvailable()` | — | ptá se `class_exists`, ale render jde přes fasádu, která tahá `dompdf.wrapper` **z kontejneru**. Bez registrovaného providera → `BindingResolutionException` místo dokumentovaného CSV fallbacku. Nalezeno až tím, že knihovna reálně přibyla |
| V2.5/SV | „state je serializovatelný, saved view = uložit tentýž blob" | žádný takový blob se neukládá — preferenční cesta skládá **dva klíče ručně**. Co pohled nese, se muselo rozhodnout: nový vlastník `TableViewPayload`, a `selection`/`modal`/kurzor do něj **nepatří** |
| V2.5/GS | „core na table nevidí, registr je v `packages/table`" (plán) · „patří do `wire-panels`" (§4) | `ResourceRegistry` je **už v core L1** → obě zapsaná umístění zastaralá, cyklus neexistuje, `core/src/GlobalSearch/` je správně |
| V2.5/GS | „delegovat hledání na `DataSource`/`QueryPlan`" | `QueryPlan` staví search klauzule z **table komponenty**; paleta žádnou nemá. Fiktivní tabulka per resource per úhoz by byla ta druhá kopie, které planner brání → resource jmenuje atributy |
| V2.5/LT | virtual scrolling (windowing) | **čtyři chování čtou řádky z DOM** (`navRows()`, fill grid, live sync, range gesta) → windowing chce paralelní cestu pro každé a tři selhávají tiše. Místo toho sbalitelné skupiny: co je skryté, tam **není** |
| ADR 0025 krok 8 | „patří do action-render-unification.md, fáze 0 blokuje" | vazba **mylná** — ten plán má v §1.1 čtyři třídy akcí a `component-action.blade.php` mezi nimi není, stejně jako v žádné z fází 0–5. A samotný krok by dluh nesnížil: `ModuleLayersTest` čte PHP `use`, ne Blade, a měřený dluh vede **opačně** (Actions → Infolists) |
| ADR 0025 krok 8 — co za tím leželo | — | `wire:target` a spinner nesly **holé jméno metody**, které Livewire matchuje proti každému jeho volání → klik na jednu akci disabloval a rozsvítil **všechna** infolist tlačítka na stránce; u `RepeatableEntry` jedno na řádek. Týž tvar v obou footer partialech. Mutace prošla **4 581 testy** |
| ADR 0025 krok 10 | „hotové 2026-08-30" | prohlížečová brána našla **`wireFillHandle is not defined` po `wire:navigate`** — nový entry vzal registrační řádek a nechal za sebou idiom. Server-side to nevidí nikdo: tag je doručený, `alpine:init` v bundlu je, a test pojmenovaný „registers the fill-handle Alpine data" prochází |

Druhý běh 2026-09-01 (dokumentace V2.5 + verdikt V2.6) přidal pět:

| Krok | Zadání / dojem | Měření našlo |
|---|---|---|
| V2.5 docs | „`docs:api` hlídá docs proti API" | hlídá **jen stránky, které existují** — porovnává deklarovaný fluent povrch třídy s její *referenční stránkou*. Tři veřejné schopnosti V2.5 (`savedViews()`, `collapsibleGroups()`, celé globální hledání) neměly stránku **žádnou**, takže brána byla zelená a neměla co ohlásit. Kontrola, která to najde, je grep jmen veřejného API proti `docs/`, ne `docs:api` |
| SV — strážní klauzule | „uložené pohledy jsou pokryté, 44 testů" | `applyTableView()` a `deleteTableView()` měly strážní klauzuli **bez jediného testu**: smazání obou prošlo všemi **2 430** testy balíčku. Sourozenec `saveTableView()` měl na tutéž klauzuli dva testy — a to je ten mechanismus: **pokrytí jednoho ze sourozenců se čte jako pokrytí všech**. Našla to až `--diff=origin/1.x` brána, kterou předchozí běh nepustil |
| SV — co ta klauzule drží | — | `''` není „prázdné jméno", je to **nepojmenovaný bag = živý layout**: `deleteTableView('')` by uživateli smazal layout, na kterém stojí, a `applyTableView('')` by mu ho „obnovil" i s resetem stránky. Druhá půlka (`$key === null`) drží fatal — jsou to public Livewire endpointy, takže je na tabulce bez uložených pohledů může zavolat cokoli na stránce |
| GS — slíbený override | docblock `GloballySearchable`: „resource, který potřebuje víc, přebije `globalSearchQuery()`" | **taková metoda neexistuje nikde v repu** — ani v plánu V2.5, který ji nikdy nejmenoval. A únik, který slibovala, nebyl dosažitelný ani oklikou: `searchResource()`/`matchAny()` jsou `protected`, ale paleta si hledání stavěla `new GlobalSearch(...)`, takže binding v kontejneru ignorovala. **`{@see}` na jméno metody nekontroluje žádná brána**; slíbený override potřebuje test, který ho fakticky přebije |
| GS — cena palety | docs jsem psal větu „jeden dotaz na přihlášený resource na úhoz" | **byly tři.** `$this->results` je cachovaná computed property, ale `flatResults()` i nový `groupLabels()` volaly `getResultsProperty()` **přímo**, což cache obchází a pustí celé hledání znovu. Render tedy hledal 2× ještě před tímhle během a 3× po něm. Chytila to až věta v dokumentaci a test, který počítá dotazy — žádná brána tuhle třídu chyby nevidí |
| GS — hlavička skupiny | — | paleta psala do hlavičky skupiny **klíč resource** (`orders`), zatímco `pluralLabel()` je kanonický vlastník množného jména. Druhý slovník pro totéž slovo, špatný ve chvíli, kdy se klíč a popisek úmyslně liší (`orders` / `Sales Orders`). CSS `uppercase` to schovávalo jako „skoro popisek" |

**A pravidlo, které z toho padá: napsat do dokumentace číslo je měření.** Věta
„jeden dotaz na resource na úhoz" byla domněnka z čtení `search()`; test, který
dotazy spočítal, vrátil tři. Číslo v dokumentaci je tvrzení o chování a patří
k němu aserce, ne odhad z kódu.

Plus jedno chování, které bylo správně a nikdo ho nedržel: **sbalená skupina si
nechává svůj mezisoučet** (řádek subtotalu se vykresluje nezávisle na sbalení).
Otázku vynutilo psaní dokumentace — „co uživatel po sbalení uvidí?" — a odpověď
je teď v testu, protože to je celý důvod, proč je sbalování úleva a ne ztráta
informace.

Běh 2026-08-30 přidal jedenáct:

| Krok | Plán / §4 slibovala | Měření našlo |
|---|---|---|
| V2.4/T tenancy | hook `table.querying` na prioritě -100 | hook query nedostane a pokryje jednu cestu z šesti → **globální Eloquent scope** |
| V2.4/WF | čeká na `StatusColumn` z V2.1 | ten byl zamítnut; `BadgeColumn` to umí → závislost splněná, WF-4 nemá co napojovat |
| V2.4/Q-3 | „přidat `->queue()` na `ExportAction`" | exportér vrací `StreamedResponse` → **dvě doručovací cesty**, potřebuje režim zápisu na disk |
| V2.4/Q-3 | „export/import na frontě" jako jedna položka | export = nový mechanismus, import = **ani řádek** změny v pipeline → dvě položky s různou cenou |
| V2.4/Q-3 stav | job veze třídu komponenty, hotovo | čerstvě mountovaný hostitel **ztratí filtry** → kdo zúžil na 20 řádků, dostane 10 000 |
| V2.4/Q-3 jméno | `store()` staví jméno z formátu | bez knihovny se zapisuje CSV → **`.xlsx` s CSV uvnitř**; příponu vlastní `Exporter::extension()` |
| `@php` bloky ve views | čtyři hnízda podle *počtu* bloků | tři z nich jsou jen aliasy render plánu → **nedělat**; čtvrté drželo rozjetou kopii pravidla a **ostrý defekt** |
| V2.2/S1 | injektovat deps, „testy nemůžou mockovat" | `ActionPipeline` už stages konstruktorem bere, `SaveHandler` má 25 vlastních testů → **nedělat** |
| V2.2/S2 | zrušit dvojí dispatch (redundance) | stojí 0,163 µs → **nechat**; vedle byl **defekt**, nehintovaný callback běžel dvakrát |
| V2.3 umístění | `Resource` do core (rozhodnuto 2026-08-26) | náčrt R.1 tak nejde napsat; Filament dává owner vrstvu **nad** komponenty → nový balíček `wire-panels` |
| V2.3 tvar | jedna třída / jeden interface, osm metod | `AI_CODING_STANDARD.md` § Interfaces to zakazuje → rozpad na identitu + povrchy |
| `v2-deferred-items` §3.2 | „`mutateDataBeforeSave()` se wrappne jako before hook" | tvary nejsou převoditelné (celá data + abort vs. jedna hodnota) a per-atributového vlastníka má `dehydrateState()` → **nedělat** |
| `v2-deferred-items` §3.3 | „`RelationshipSaveHandler` ručně iteruje → do `Dehydrator`u" | je to kanonický vlastník se **dvěma** konzumenty; `Dehydrator` neukládá a sedí **pod** `Repeater`em v grafu → **nedělat**; audit místo toho našel nepokrytou update větev |
| ADR 0025 krok 10 | „vyříznout `wireFillHandle` z 38 KB bundlu" (packaging) | bajty seděly (−23,8 %, +100 B pro tabulku), ale cena byla **jeden import mezi bundly** u guardu, jehož selhání maže data — a esbuild by ho mlčky vyřešil zpátky |

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

**Pravidlo z V2.5 — „už to jde serializovat" není totéž co „to někdo ukládá".**
SV stál na větě, že saved view je jen ten blob, co už se ukládá do URL. Ukázalo
se, že perzistentní cesta ukládá **dva ručně vybrané klíče**, ne serializovaný
state — takže co pohled *je*, se muselo rozhodnout, a to je ta práce. Otázka,
která to odhalí, zní: *kdo přesně ten tvar dnes zapisuje, a kde je ta metoda?*
Ne: *existuje serializér?*

A druhá půlka, na kterou se dá spolehnout: **u ukládaného stavu se ptej, co tam
nepatří.** Do pohledu nepatří výběr (obnovil by zaškrtnutí, které uživatel
neudělal, a `mode: all` znamená „všechno, co filtr matchuje" proti filtru, který
se mezitím pohnul), otevřený modal, kurzor stránkování ani per-record expanze.
Tři z těch čtyř by nebylo vidět, dokud by někdo neobnovil starý pohled.

**Pravidlo z V2.5/LT — než postavíš „rychlejší render", spočítej, kdo čte DOM.**
Plán chtěl virtual scrolling. Čtyři chování si berou řádky přímo z DOM
(`record-actions.js::navRows()`, fill grid, live sync buněk, klávesová rozsahová
gesta), takže windowing potřebuje paralelní cestu pro každé — a tři z nich
selhávají **tiše**: fill nezapíše přes řádek, který není vykreslený, rozsah ho
přeskočí. Sbalitelné skupiny dávají stejnou úlevu a ten tvar nemají, protože
sbalená skupina se **nerenderuje**; co není vidět, tam opravdu není. Driver to
kontroluje počítáním `tr[data-row-key]` a asercí, že žádný přeživší řádek není
jen schovaný CSS.

**Pravidlo z V2.5 — skeleton měří i větev, která se nevykreslí.**
`TablePayloadFuseTest` hlídá bajty group headeru na skupinu. Přidání `@if` do
toho jednoho partialu to číslo posunulo, **přestože se ta větev nikdy
nevykreslila**. Chytilo to dvakrát: nejdřív komentář, který jsem dal dovnitř
řádkové smyčky (Blade komentář zmizí, jeho newliny ne — a ve smyčce jsou to
bajty na každý řádek), pak samotnou větev. Řešení je druhá šablona, ne chytřejší
podmínka: dva tvary, dva soubory, a tabulka bez té featury platí přesně to co
dřív.

**Pravidlo z běhu 2026-09-01 — když něco vyřízneš, spočítej i to, co pro to
dělal soubor, ze kterého to odchází.** Krok 10 poctivě spočítal *importy ven* z
odcházejícího kódu a našel jeden (`support/partials.js` → fill controller).
Nespočítal, co odcházející kód dostával zadarmo od svého okolí: `dropdown.js`
registruje své controllery idiomem `window.Alpine` / `alpine:init`, a
`wireFillHandle` byl jeden řádek uvnitř toho registrátoru. Nový entry vzal řádek a
nechal idiom, takže po `wire:navigate` neregistroval nic a Alpine umřel na celém
datovém regionu. **Hrany nejsou jen to, co kód importuje, ale i to, co za něj
dělal soubor, ve kterém seděl.**

A druhá půlka, ostřejší než minule: **test pojmenovaný podle pravidla znovu
neprokázal nic.** `FillHandleAssetTest` měl pět asercí včetně „the shipped bundle
registers the fill-handle Alpine data" a `toContain('alpine:init')` — a **všech pět
prošlo i na rozbitém entry**, protože rozbitá verze `alpine:init` samozřejmě
obsahuje. Mutace to ukázala za jeden běh. Rozlišuje až tvar idiomu, asertovaný ve
zdroji i v minifikovaném distu — což `DropdownAssetTest` uměl už rok a nový soubor
si to nepřenesl. **Když píšeš testy pro vyříznutou věc, přenes invarianty z testu
původního vlastníka, ne jenom asercie na obsah.**

**Pravidlo z téhož běhu — knihovna, která je jen v dokumentaci, je cesta, kterou
nikdy nikdo nespustil.** `docs/table/exports.md` říká „composer require
openspout/openspout", ale ani `require-dev`, ani `suggest` ji neměly, takže
`ExcelExporter::writeTo()` za `isAvailable()` neběžel v žádném CI běhu — a dva
testy, které ho měly krýt, se samy přeskakovaly přes `markTestSkipped`.
**`markTestSkipped` na „knihovna není nainstalovaná" je zelená, která znamená
nezměřeno.** Když se obě knihovny reálně nainstalovaly, spadly tři věci naráz:
`PdfExporter::isAvailable()` se ptal na `class_exists`, ale render potřebuje
binding `dompdf.wrapper` z kontejneru (bez registrovaného providera →
`BindingResolutionException` místo dokumentovaného CSV fallbacku); OpenSpout píše
XLSX buňky jako `inlineStr`, takže `xl/sharedStrings.xml` je prázdný a hodnoty jsou
jen v `sheet1.xml`; a CSV fallback větev `writeTo()` byla do té doby pokrytá
**omylem** — běžela jen proto, že knihovna chyběla, a s nainstalovanou knihovnou
zůstala jako jediná nepokrytá. Obecně: **než uvěříš, že je volitelná závislost
otestovaná, zjisti, jestli je vůbec v repu.**

**Pravidlo z V2.4/T — bezpečnost se nedá stavět na seamu, který se nemusí
spustit.** Plán chtěl tenancy vynutit hookem. Hook běží jen když je navázaný
`PluginManager`, pokrývá jednu čtecí cestu a v polové variantě query ani
nedostane. Než něco takového použiješ na bezpečnostní invariant, projdi **všechny**
cesty, které to má krýt — `SaveHandler`, relace, export, frontovaný job,
`Model::find()` v aplikaci — a zeptej se, kolik z nich ten seam vidí. U tenancy
to byla jedna ze šesti.

Druhá půlka: **fail-safe napiš tak, aby šel asertovat přímo.**
`Tenancy::shouldBlockEverything()` existuje proto, aby test netvrdil „vrátilo to
nula řádků" (což může být pravda i omylem), ale „pravidlo říká zablokovat".

**Pravidlo z V2.4/WF — než uvěříš blokující závislosti, zkontroluj, jestli
nebyla splněná zamítnutím.** WF-4 čekal na `StatusColumn` z V2.1 B-2. Ten byl
zamítnutý jako prázdná podtřída, protože `BadgeColumn` to už uměl — takže
závislost byla splněná, ale plán o tom nevěděl a §2 to zapsané mělo. Číst §2
před §4 není formalita.

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

**Pravidlo z ADR 0025 kroku 10: u rozdělení bundlu počítej běhové hrany, ne
bajty.** Zadání znělo „vyříznout 9 KB". Bajty byly deset minut práce; celá cena
byla **jeden `import` mezi tím, co zůstává, a tím, co odchází** —
`support/partials.js` se ptal fill controlleru, jestli běží drag, a bundly jsou
samostatné IIFE, takže ten import po rozdělení neexistuje. Zákeřné na tom je, že
**by to nespadlo**: esbuild import mlčky vyřeší ze zdrojů a vtáhne celý
controller zpátky do core bundlu, takže by rozdělení neušetřilo nic a nikdo by
si nevšiml. Než rozdělíš bundle, vypiš si `grep -rn "from '.*<co-odchází>'"` přes
to, co zůstává — a u každé hrany se ptej, jestli má vlastník už **publikovaný**
signál. Tady měl: `wire-filling` na `<body>`, psaný na tomtéž řádku jako interní
registr, používaný CSS a asertovaný dvěma drivery. Nový protokol se nepsal.

A druhá půlka: **když přesuneš jednu stranu cross-package invariantu, ten test se
musí rozdělit taky.** `EditableCellVersionSourceTest` asertoval tři soubory ze
dvou balíčků; po přesunu nešlo jen opravit cestu, protože jediný čtenář té
precedence odešel a **tree-shaking ji z původního bundlu odstranil**. Vlastníkovi
zůstává pravidlo o zdroji, konzumentovi pravidlo o jeho bundlu.

**Pravidlo z běhu 2026-08-30 (druhá půlka dne): test pojmenovaný podle regrese
není důkaz, že tu regresi chytá — mutuj i tam, kde správně pojmenovaný test už
je.** `RelationshipSaveHandlerTest` měl test „editing an existing relation row
applies casts (no cast bypass / corruption)", psaný jmenovitě na to, že update
větev nesmí použít query-builder `->update()`. Ta záměna prošla — **včetně toho
testu**. Důvod: komentář i test stály na tvrzení, že builder update zahodí
`array` cast a zapíše `Array to string conversion`, jenže query grammar array
binding json_encoduje sám. Framework mezitím pokryl přesně ten případ, na kterém
test stál, takže test přestal cokoli rozlišovat a **nikdy kvůli tomu
nezčervenal**. Rozlišuje až cast, jehož `set()` hodnotu mění, a model eventy.
Obecně: **když se ptáš, jestli je pravidlo pokryté, ptej se mutací, ne názvem
testu** — a když mutace projde testem, který je na ni psaný, je stará premisa, ne
mrtvý test.

**A druhá půlka, procedurální: ověř, že mutace vůbec sedla.** První běh té mutace
„prošel" proto, že se vzor v souboru netrefil a nezměnilo se nic — false pass,
který by celý nález schoval. Každá mutace přes skript musí selhat hlasitě, když
vzor nenajde (`assert old in s`), a `git diff --stat` po ní je jeden příkaz.

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

**Pravidlo z druhého běhu 2026-09-01 — brána, která porovnává docs s kódem,
nevidí to, co v docs vůbec není.** `docs:api` je zelená na 122 stránkách a
nemohla ohlásit, že tři veřejné schopnosti V2.5 nemají stránku žádnou: porovnává
deklarovaný povrch třídy s *její referenční stránkou*, takže neexistující
stránka není nesoulad, je to prázdná množina. Totéž platí obecně — **gate na
drift nehlídá absenci.** Kontrola po dokončení featury je grep jmen nového
veřejného API proti `docs/`, ne zelená brána.

**A druhá půlka: sourozenecké endpointy se čtou jako pokryté, když je pokrytý
jeden z nich.** `saveTableView()` měl na svou strážní klauzuli dva testy;
`applyTableView()` a `deleteTableView()` mají tutéž klauzuli a **žádný** —
smazání obou prošlo všemi 2 430 testy. Vypadalo to jako pokrytá oblast (44 testů
na uložené pohledy), protože pravidlo *bylo* otestované, jen na jiné metodě.
Když najdeš tentýž guard na třech metodách, ověř všechny tři — a nejrychleji to
udělá `verify-coverage --diff`, která ukazuje řádky, ne pocity.

**Pravidlo z téhož běhu — `{@see}` na metodu nekontroluje nic.** Docblock
kontraktu `GloballySearchable` posílal čtenáře přebít `globalSearchQuery()`,
která v repu **neexistuje** a nikdy neexistovala; PHPStan ji nevidí (je to text),
`docs:api` taky ne (není to docs). Slíbený rozšiřovací bod potřebuje test, který
ho fakticky přebije — ten hned ukázal druhou půlku: únik byl nedosažitelný i
kdyby metoda existovala, protože si paleta hledání stavěla `new`, a binding v
kontejneru tím ignorovala. **Rozšiřovací bod bez testu, který ho použije, je
komentář, ne API.**

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
