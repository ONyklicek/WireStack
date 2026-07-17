---
title: Audit — persistence vrstva, hook systém, wire-component (UI primitiva)
date: 2026-07-14
scope: packages/core, packages/forms, packages/table, packages/sortable
status: audit (bez plánů — plány až nad rozhodnutími z „Otevřené otázky"); §D = kyselinový test read-pathu, premisa ADR 0019 potvrzena
related:
  - architecture/plans/v2-master-plan.md
  - architecture/decisions/0019-data-source-contract.md
  - architecture/decisions/0020-application-owner-layer.md
  - architecture/plans/v2.1-monolith-split-implementation.md
  - architecture/plans/v2.3-owner-layer-implementation.md
---

# Audit: persistence, hooky, wire-component

Podklad pro tři témata (jednotný vstup pro ukládání, rozšíření hooků, větší
modularita). **Záměrně bez plánů** — nejdřív fakta a rozhodnutí, plány potom.

Všechna čísla změřena 2026-07-14 proti pracovnímu stromu.

## Předběžně: `Resource` už naplánovaný je

`v2-master-plan.md` je označený jako *authoritative; konsoliduje V2 napříč
dokumenty* a obsahuje **V2.3 — Aplikační owner vrstva (`Resource`/`Page`/
`Workspace`)** s bránou ADR 0020 a detailním plánem
`v2.3-owner-layer-implementation.md` (tvrdá závislost: V2.1 monolith split musí
dopadnout první).

**Nepsat k tomu druhý dokument.** Cokoliv z tohoto auditu, co se `Resource`
týká, patří jako doplněk do V2.3, ne jako paralelní návrh — viz invariant
o jednom kanonickém vlastníkovi v `CLAUDE.md`.

---

## A. Persistence — jednotný vstup pro ukládání

### Zjištění: čtyři nezávislé zápisové cesty

`DB::transaction` v `packages/*/src`:

| cesta | soubor |
|---|---|
| formulářový save | `packages/forms/src/Forms/Runtime/SaveHandler.php` (+ `RelationshipSaveHandler.php`) |
| inline edit buňky | `packages/table/src/Concerns/WithTable.php` |
| panel entry | `packages/core/src/Panels/Concerns/WithEditablePanel.php` |
| reorder | `packages/sortable/src/Concerns/WithSortable.php` |

Každá má vlastní transakci, vlastní guardy a vlastní chování při konfliktu.
Optimistic locking je re-implementovaný nezávisle minimálně v `Panels/*`
a v `WithTable` (obě přes `updated_at` + `lockForUpdate`).

Import (`TableImport`) je pátá cesta a `v2.0-datasource-implementation.md:123` ho
explicitně nechává stranou: *„import zůstává mimo (write-side)"*.

### ADR 0019 tuto díru podceňovalo — OPRAVENO 2026-07-14

Původní Context v `0019-data-source-contract.md` tvrdil:

> The **write-path is already unlocked** (`Form::using(Closure)` is a functional
> command seam); the read-path is the remaining strategic strop.

To platí **jen pro formulářový save**. `Form::using()` nepokrývá inline edit
buňky, panel entry, sortable reorder ani import — tedy tři ze čtyř
transakčních cest výše plus import.

**Stav:** ADR 0019 už tohle netvrdí. Sekce *„Correction: the write-path is NOT
unlocked (2026-07-14)"* v jeho Contextu nese tabulku čtyř cest a odkazuje sem.
Rozhodnutí samotné (DataSource contract pro read-path) zůstalo nedotčené — díra
v zápisu je mimo jeho scope, ne proti němu.

Rozdíl, který je tu potřeba pojmenovat: `Form::using()` je **escape hatch**
(přepiš si save vlastní closure), ne **vrstva** (jednotný vstup, kterým zápisy
prochází). Zadání „jednotný vstup pro ukládání" míří na to druhé.

### Co by taková vrstva vlastnila

Kandidáti na sjednocení (dnes duplikované napříč cestami):

- transakční hranice,
- optimistic locking / konflikt (dnes 2× nezávisle),
- autorizace zápisu (write whitelist, `permission()`, record-aware `canEdit()`),
- server-authoritative resolve recordu (Panels to už dělá správně — vzor),
- dehydratace / casty (`Dehydrator`, `normaliseEnums`, `CastResolver` — dnes
  jen v `SaveHandler`),
- vyústění zápisových hooků (viz B).

### Vazba na ADR 0019

Je to jeho **protistrana**: 0019 odemyká read-path z `Eloquent\Builder`,
persistence vrstva by odemkla write-path ze `Model`. Pokud vznikne, mělo by to
být nové ADR ve stejné dvojici, ne přílepek.

---

## B. Hook systém

### Zjištění: 7 payloadů, žádný na zápis mimo formulář

`packages/core/src/Core/Plugin/Hooks/`:

```
ActionExecutingPayload    ActionExecutedPayload
FormSavingPayload         FormSavedPayload
TableConfiguringPayload   TableQueryingPayload   TableQueriedPayload
```

Pokrývají: akce, formulářový save, table read-path (config/query).

**Nepokrývají** přesně ty cesty z části A: inline edit buňky, panel entry,
sortable reorder, import. Dále nic pro validaci, notifikace a modal lifecycle.

`PluginManager` má vedle hooků i typové registry (`addQueryPipe`,
`addColumnType`, `addFilterType`, `addActionType`) — tedy dvě různé rozšiřovací
osy v jedné třídě.

### Souvislost s A

Zápisové hooky nemají dnes kam patřit, protože neexistuje jedno místo, kterým
zápis prochází — musely by se lepit zvlášť do čtyř cest. **Persistence vrstva je
předpoklad pro rozumné zápisové hooky, ne nezávislá úloha.** Pořadí: A → B.

---

## C. wire-component — ZAMÍTNUTO (2026-07-14)

> **Rozhodnutí: balík nevznikne.** Cíl (tenké jádro = design systém bez
> aplikačního enginu) splní **opačný pohyb** — odpojení Modals/Actions/Infolists
> **nahoru** z core, viz **část E**. Ten dosáhne téhož a přitom `HasColor` ani
> zbytek `Foundation` nemusí změnit namespace, takže odpadá i BC problém, který
> byl hlavní cenou tohoto návrhu.
>
> Zbytek části C je ponechán jako **doložení, proč se zamítlo** — tři nezávislá
> měření níže postupně sebrala všechny tři argumenty, které pro balík mluvily.
> Bez nich to bude za půl roku navrženo znovu.

### Zjištění: extrakce by byla překvapivě čistá (a přesto se nekoná)

- `packages/core/src/Foundation/View/` = **15 komponent** (`Badge`, `Button`,
  `Callout`, `Dropdown`, `EmptyState`, `Fieldset`, `Flex`, `Grid`, `Icon`,
  `Section`, `Step`, `Tab`, `Tabs`, `WidgetGrid`, `Wizard`), registrované jako
  `x-wire::*` přes `Blade::componentNamespace(…, 'wire')`
  (`WireCoreServiceProvider.php:129`).
- **Žádná** z nich neimportuje `WireForms` / `WireTable` / `WireSortable`.
- Celé `Foundation/` (Colors, Components, Concerns, Contracts, Enums, Icons,
  Schema, Support, View) = **6 823 ř.** a ven do zbytku core sahá jen **7×**:
  4× `Actions\Action`, 1× `Widgets\Widget`, 1× `Modals\Concerns\HasModalProperties`,
  1× `Core\State\StateContainer`.

Sedm vazeb napříč 6 823 řádky — `Foundation` je dnes *skoro* čistá spodní
vrstva. Těch 7 je celá cena extrakce.

### Velikost balíků

| balík | řádky |
|---|---|
| **core** | **26 085** (294 souborů) |
| table | 16 949 |
| forms | 10 179 |
| sortable | 761 |

Rozpad core: `Core` 6 826 · `Foundation` 6 823 · `Actions` 5 585 · `Modals`
1 558 · `Infolists` 1 249 · `Widgets` 1 242 · `Audit` 947 · `Notifications` 777
· `Panels` 712.

### ⚠ Řádky jsou špatná metrika — změřeno (2026-07-14)

Dřívější verze tohoto auditu z čísel výše vyvozovala, že *„kvůli
`<x-wire::badge>` si bereš 26 tisíc řádků včetně action enginu, plugin manageru
a audit vrstvy"*. **To je nepravda** a metrika je vadná: PHP autoloaduje třídy
na vyžádání, takže `require` balíku nenačte jeho kód.

Naměřeno renderem `<x-wire::badge>` v bootnuté aplikaci (počet
`get_declared_classes()` s prefixem `NyonCode\`):

| | tříd |
|---|---|
| natažené **bootem** balíku | **15** — a 6 z nich jsou testovací třídy harness |
| přidané **renderem** badge | **1** (`Foundation\View\Badge`) |
| celkem v paměti | **17** |

Tedy ~11 balíčkových tříd z 294 souborů. **Runtime footprint `wire-core` na
veřejné stránce je zanedbatelný** a extrakci `wire-component` na něm nelze
postavit.

### Skutečná cena za request (a je malá)

Ne řádky, ale to, co `WireCoreServiceProvider` dělá **bezpodmínečně** při každém
requestu:

- `bootAudit()` → `Event::subscribe(AuditEventSubscriber::class)` — audit
  subscriber visí na model eventech i na stránce, která žádný audit nechce.
- `bootPlugins()` → `PluginManager::boot()`.
- registrace ~7 Blade component namespaců, asset rout, views, translations,
  migrations, configu.

Badge sám neposílá **žádné JS** — `floating-assets` includují jen dropdown/select
views, ne `Foundation/View/Badge`.

### Není to pokryté V2.1

`v2.1-monolith-split-implementation.md` má scope *packages/table (WithTable,
Column, kolekce collaborators), packages/core (Foundation)* a řeže **table god
objecty** (`Column` 1 749 ř. / 139 metod). Extrakci UI primitiv do vlastního
balíku neřeší. wire-component je tedy nový nápad, ne duplikát.

### Protiargument — a proč padá pod „stavebnicovým" rámcem

`wire-core` **už dnes je** spodek grafu (`sortable → table → forms → core`), takže
UI primitiva jdou použít bez table/forms už teď — jen s 26k řádků přítěže.
Přínos extrakce je tedy **velikost a hranice, ne dostupnost**. Cena: nový balík
v grafu, service provider, publikace assetů, CI matice, 7 rozpletených vazeb
a přesun `Foundation`.

Mezitím padly **oba** pokusy tenhle protiargument obejít:

1. *„Pod admin rámcem to nevadí, ale jakmile je výstupem veřejný web, stane se
   z wire-component nosná věc, protože nikdo nepošle stránku do světa na audit
   vrstvě a modal enginu."* — **Vyvráceno měřením výše.** Veřejná stránka si
   z toho enginu nenatáhne nic; stojí ji jeden event subscriber a boot
   provideru. Argument stál na řádcích, a řádky jsou špatná metrika.
2. *„Odpojit to od Livewiru."* — viz níže, taky neplatí.

**Zbývající legitimní důvody pro wire-component tedy nejsou runtime, ale:**

- **composer/dependency hygiena** — abys mohl použít `<x-wire::badge>`, táhneš si
  celý `wire-core` a jeho strom závislostí (Livewire ad.). To je reálné, ale je
  to install-time, ne request-time.
- **hranice a API surface** — konceptuální oddělení design systému od
  aplikačního enginu. Legitimní cíl, ale je to **estetika architektury**, ne
  výkon.
- **velikost instalace.**

A ani ten poslední, **Livewire-free design systém**, konzumenta nemá. Naměřeno:
celé `Foundation` má **jediný** skutečný Livewire import
(`Foundation/Concerns/HasLivewire.php` → `use Livewire\Component`), `Foundation/View/`
je Livewire-free úplně (`Badge extends Illuminate\View\Component`), a `wire-core`
přesto v composeru vynucuje `livewire/livewire: ^3.0`. Extrakce by tedy design
systém odemkla pro aplikace bez Livewiru — jenže **wireStack Livewire používá
všude a je to vědomá volba**, takže ten požadavek `wire-core` nestojí nic. Cena by
byla reálná jen pro *cizí* aplikaci mimo Livewire ekosystém; to je produktové
rozhodnutí, ne architektonické.

Zbylo tedy: composer hygiena + estetika hranic. To na rozbití `HasColor` —
který `CLAUDE.md` označuje za *binding architectural extension point* a
konzumují ho core, forms i table (a podle té dokumentace i uživatelské aplikace
a pluginy) — **nestačí**. → viz rozhodnutí v hlavičce části C.

### Co NENÍ důvod (ověřeno)

Odpojení od **Livewiru** není potřeba a nemá oporu. Livewire 3 při prvním
renderu posílá běžné serverové HTML, takže veřejná stránka ani SEO na tom
nepadají; náklad je snapshot v markupu a JS bundle, což je **měřitelná
otázka**, ne architektonická překážka. Balíky se jmenují `wire-*` a Livewire je
zvolený doručovací mechanismus — z auditu tedy tato osa vypadává.

---

---

## D. Kyselinový test read-pathu (2026-07-14)

Místo dalšího plánování byl otestován **falzifikovatelný** kus ADR 0019: že
`QueryPlan` je už dnes zdrojově agnostické IR a tedy *„the natural boundary"*.

### Výsledek: premisa ADR 0019 potvrzena

| co | zmínek `Builder` / `Model` / `Eloquent` |
|---|---|
| `Core/Query/QueryPlan.php` | **0** (jediný import `RelationGraph`) |
| `Core/Relations/RelationGraph.php` | **0** ⇒ IR čisté i **tranzitivně** |
| `Core/Query/QueryPlanner.php` | 5 |
| `Core/Query/QueryExecutor.php` | **13** ← Eloquent koncentrovaný zde |

Adaptér je jediná metoda:

```php
QueryExecutor::execute(Builder $builder, QueryPlan $plan, ?string $searchTerm = null): Builder
```

Filtry se do IR **popisují**, nemutují builder: `TextFilter`, `NumberRangeFilter`
i základní `Filter` staví `FilterDefinition(column, operator, value,
relationPath, sqlExpression)` — čistá datová struktura.

**Důsledek:** nakrmit tabulku z `Collection` / API / read modelu je **adaptér
(druhý executor nad týmž plánem)**, ne přepis `packages/table`. Invariant C
z ADR 0017 („table engine musí být headless") je z velké části už splněný, jen
to nikdo nedotáhl k druhému zdroji.

### Kde to skutečně drhne: veřejné API, ne engine

- **15 tvrdých typehintů** `Builder` v signaturách, 19 souborů v
  `packages/table/src` (`Table::query()`, `getQuery()`, `Exporter`, summary).
- **`Filter::query(Closure $callback)`** — callbacku přistane živý `Builder`.
  Ten kód žije v *uživatelské* aplikaci; jiný executor s ním nic neudělá.
- **`sqlExpression`** na `FilterDefinition` / `SortClause` — syrové SQL uvnitř
  IR. Je to string, ne Eloquent typ (tvrzení „IR je čisté" platí doslova), ale
  `Collection` ani API ho nespustí.

Cena tedy není architektura, ale **zpětná kompatibilita**.

### Odpověď na to už v repu je

`packages/core/src/Core/Capabilities/` — `Capability`, `CapabilitySet`,
`CapabilityResolver`, který se **už dnes ptá na `sqlExpression`**
(`CapabilityResolver.php:54,79`). Engine tedy umí uvažovat v režimu „tenhle
zdroj tohle umí, tamten ne".

Únikové východy se proto nemusí rušit ani ohýbat: zdroj o sobě prohlásí, co
nepodporuje, a engine to ošetří. **Žádné porušení BC.** ADR 0019 to má
zarámované správně — sekce *„Capability degradation for non-Eloquent sources"*
je přesně tenhle mechanismus.

### Druhá půlka: veřejný web — doměřeno

Otázka zněla, co si stránka reálně natáhne, když chce jen `<x-wire::badge>`.
Odpověď: **skoro nic** — 17 tříd celkem, z toho 1 kvůli badge samotnému. Detail
a důsledky viz část C („Řádky jsou špatná metrika"). Tím padá i argument, že
stavebnicový rámec dělá z wire-component nosnou položku.

Obě půlky kyselinového testu tedy dopadly **proti mým vlastním odhadům** a
v obou případech ve prospěch stávající architektury:

| osa | můj odhad | naměřeno |
|---|---|---|
| read-path (Eloquent) | „zamčené, možná přepis table" | IR čisté (0/0) → **adaptér**, překážka je v public API a je to BC problém |
| veřejný web (26k core) | „nepoužitelné, nutná extrakce" | **17 tříd** → runtime footprint zanedbatelný |

---

---

## E. Odpojení Modals / Actions / Infolists od Core

Zvolený směr modularity (zadání 2026-07-14): tyhle namespacy časem odejdou z
`wire-core` do vlastních balíků **nad** ním. Tím se core scvrkne na
`Foundation` + `Core` (design systém + engine), což je **týž cíl jako
wire-component z části C** — ale bez přesunu `Foundation` dolů, takže
`NyonCode\WireCore\Foundation\Concerns\HasColor` se nehne a **žádné BC se
neporuší**. Proto část C padá ve prospěch tohohle.

### Blokující množina: 26 vazeb, čtyři velmi různé kategorie

Měřeno: co zbytek core importuje z `Actions` / `Modals` / `Infolists` /
`Widgets`.

**1. Devět `Concerns/*` — už deprecated shimy, nejsou blokátor.**

```php
namespace NyonCode\WireCore\Concerns;
use NyonCode\WireCore\Actions\Concerns\HasColor;
/** @deprecated Use {@see HasColor} instead. Will be removed in v2.0. */
class_alias(HasColor::class, 'NyonCode\WireCore\Concerns\HasColor');
```

`HasButtonStyles`, `HasColor`, `HasDynamicProperties`, `HasIcons`,
`HasKeyboardShortcut`, `HasLifecycle`, `HasLoadingState`, `HasModal`,
`HasVisibility` — devět `class_alias` můstků označených *„Will be removed in
v2.0"*. Umřou přesně v releasu, ve kterém by se split dělal.

**2. Pět `Foundation/*` — TOHLE je jediný skutečný blokátor.**

| soubor | sahá na | jak |
|---|---|---|
| `Foundation/Concerns/HasActions.php` | `Actions\Action` | typehint `action(Action $a)` |
| `Foundation/Concerns/HasPrefixAndSuffix.php` | `Actions\Action` | typy properties |
| `Foundation/Contracts/HasFieldActions.php` | `Actions\Action` | návratový typ |
| `Foundation/Schema/Section.php` | `Actions\Action` | PHPDoc generika |
| `Foundation/View/WidgetGrid.php` | `Widgets\Widget` | PHPDoc generika |

`Foundation` je spodek. Jakmile `Actions` odejde do balíku nad core, je
`core → wire-actions → core` **cyklus a composer to neinstaluje**.

**3. Tři `Panels/*` → `Infolists`** (`Panel.php` 2×, `Components/EditableEntry.php`)
— benigní, Panels stejně sedí nad Infolists (editovatelný infolist).

**4. Jedna `Audit/Actions/AuditTrailAction.php` → `Actions\Action`** — buď Audit
sedí nad Actions, nebo ta akce odejde jinam.

**5. Sedm `WireCoreServiceProvider`** (`Actions\View\*`, `Modals\View\*`) —
mechanika: každý odštěpený balík dostane vlastní provider.

### Oprava blokátoru: dependency inversion

`Foundation` musí typehintovat **kontrakt**, ne konkrétní třídu → nový
`Foundation\Contracts\Action` (interface), který `Actions\Action` implementuje.
Foundation pak o konkrétní třídě nikdy neví a `Actions` může bydlet kdekoliv nad
ním. Adresář `Foundation/Contracts/` už existuje a `CLAUDE.md` tenhle postup
přímo předepisuje (*„Prefer extending existing `Foundation/Contracts/*`"*).

`View/WidgetGrid.php` je zvláštní případ: je to widget-specifická view komponenta
sedící ve Foundation. Spíš než kontrakt zvážit **přesun do `Widgets`**.

### Hotovo: `Foundation\Enums\ModalWidth` (2026-07-14)

První instance téže práce — `Foundation` ukazovalo nahoru na
`Modals\Concerns\HasModalProperties`. Import byl použit **jen** v `{@see}`
docblocku, v kódu nikde; opraveno přeformulováním do prózy. Ověřeno: `Foundation`
neimportuje `Modals` vůbec, PHPStan `No errors`, core 1474 passed.

**Past, na kterou se přišlo až měřením:** psát tu referenci plně kvalifikovanou
v `{@see}` **nefunguje** — Pint (`fully_qualified_strict_types`, součást presetu
`laravel`; `pint.json` přidává jen `declare_strict_types`) ji při každém
`composer lint` převede zpátky na `use` import. Reference proto musí zůstat
**prostý text**, ne tag. Platí pro všech 5 souborů výše, pokud by se někdo
pokoušel řešit je docblockem místo kontraktu.

**Poznámka k designu, který se neopravuje:** rozdělení slovník ve Foundation
(`ModalWidth`) × mapování v Modals (`getMaxWidthClass`) je **správně**, ne vada:
slovník má dva konzumenty ve dvou namespacech (`Modals\HasModalProperties::width()`,
`Actions\ActionHalt::width()`) → patří nejníž; mapování má konzumenta jediného
(`Modals\View\*` 3× + `suspended.blade.php`) → `Modals` je nejnižší vrstva, která
ho může vlastnit. Že `HasColor` drží slovník i mapování pohromadě, není rozpor —
barvy jsou cross-surface, šířka modalu ne.

---

## Otevřené otázky (rozhodnout před psaním plánů)

1. **A:** Vzniká persistence vrstva jako nové ADR (protistrana 0019)? *(Samotná
   oprava ADR 0019 je hotová 2026-07-14 — Context už netvrdí, že je write-path
   odemčený, a odkazuje sem. Zbývá rozhodnout, jestli tu díru zaplní vlastní ADR,
   nebo `Resource` z V2.3 — viz otázka 3.)*
2. **A:** Je cílem jen sjednotit dnešní 4 cesty (Eloquent zůstává), nebo i
   odemknout write-path od `Model` (DTO / API / CQRS command)? To jsou dvě
   různě velké úlohy.
3. **A/V2.3:** Kdo je vlastník — samostatná persistence vrstva, nebo je to
   součást `Resource` z V2.3? (`Resource` bez jednotného zápisu bude mít stejný
   problém.)
4. **B:** Hooky jako rozšíření `PluginManager`, nebo vlastní vrstva? A oddělit
   je od typových registrů, které dnes bydlí v téže třídě?
5. **E:** `View/WidgetGrid` — dostane kontrakt (`Foundation\Contracts\Widget`),
   nebo se přesune do `Widgets`? (Je to widget-specifická view komponenta ve
   Foundation, takže přesun je nejspíš poctivější.)
6. **E:** Jde `Foundation\Contracts\Action` jako interface, který `Actions\Action`
   implementuje — a co ta čtveřice `HasActions` / `HasPrefixAndSuffix` /
   `HasFieldActions` / `Schema\Section`: stačí jim tentýž kontrakt, nebo některá
   potřebuje víc než jen „něco, co je akce"?
7. **E:** Kdy? Devět `Concerns/*` shimů má v docblocku *„removed in v2.0"*, takže
   split se nabízí do V2 — jak se to potká s `v2-master-plan.md` a jeho bránami
   (V2.1 monolith split má scope `packages/core (Foundation)`)?

## Co v tomto auditu NENÍ

- Jakýkoliv plán, fázování nebo odhad — vědomě, viz zadání.
- Ověření v prohlížeči — netýká se, tohle je statická analýza kódu a dokumentů.
- Návrh API. Až po rozhodnutí výše.
