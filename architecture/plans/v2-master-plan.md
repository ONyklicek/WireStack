---
title: V2 Master Plan — architektura napřed, fázovaná 2.x linie
date: 2026-07-06
scope: packages/core, packages/forms, packages/table, packages/sortable
status: master plan (authoritative; konsoliduje V2 napříč dokumenty)
supersedes_v2_sections_of:
  - architecture/plans/ddd-enterprise-roadmap.md   # V2-1..V2-6 (N1..N6 zůstávají jako pre-V2)
  - architecture/plans/v2-deferred-items.md         # engine refaktory #2..#7 (#1 StateContainer HOTOVO)
  - architecture/plans/v1-gaps.md                    # produktové mezery → mapované do fází
north_star:
  - architecture/decisions/0017-erp-crm-application-architecture.md
  - architecture/decisions/0013-unified-data-ui-engine.md
adrs:
  - architecture/decisions/0018-state-machine-workflow.md   # PROPOSED — design-only; impl V2.4 (N5)
  - architecture/decisions/0019-data-source-contract.md      # PROPOSED — brána V2.0
  - architecture/decisions/0020-application-owner-layer.md    # PROPOSED — brána V2.3
---

# V2 Master Plan

Jediný autoritativní plán pro V2. Konsoliduje dosud roztříštěné V2 položky
(`ddd-enterprise-roadmap.md`, `v2-deferred-items.md`, `v1-gaps.md`) do jedné
fázované sekvence ukotvené v **north-star ADR 0017**. Detailní impl. rozhodnutí
per-epic patří do ADR (zakládat při realizaci), ne sem — tenhle dokument drží
záměr, pořadí, hranice a kritéria hotového.

**Rozhodnutí o tvaru V2 (2026-07-06):**
1. **Fázovaná 2.x linie** (V2.0 → V2.6), deprecation-first. Každá fáze samostatně
   shippovatelná, BC breaky rozložené a ohlášené předem.
2. **Architektura napřed** — nejdřív odemknout read-cestu a rozřezat monolity,
   teprve na čisté základně stavět produktové features.
3. Výstup = tento master plán + odkazy; per-epic ADR se zakládají za běhu.

---

## North star (ADR 0017)

> **Filament-like DX, Nova-like ownership, ERP-safe execution model.**

Vrstvy cílové architektury: Foundation → UI Primitives (`Form`/`Table`/`Action`/
`Widget`/`Modal`) → **Execution** (headless: query/save/action/audit/policy/
tenancy/queue) → **Application Surfaces** (`Resource`/`Page`/`Workspace` — dnes
chybí) → Domain Modules → Infrastructure Adapters. Klíčové invarianty: UI a
execution oddělené (B), table engine headless (C), malá `Column` base (D), forms
rozdělené schema/state/validation/persistence/effects (E), akce jako doménové
commandy (F), authorization/scope/tenancy/audit jako first-class (G), rozšíření
přes **typed contracts** místo generic hooků (H).

---

> **Kde to právě stojí:** [`v2-progress.md`](v2-progress.md) — co je hotové, co
> je vědomě neudělané a čím pokračovat. Aktualizuje se na konci každého běhu.

## Revize proti kódu (2026-08-26)

> **Navazuje audit:** [`v2-audit-2026-08-26.md`](v2-audit-2026-08-26.md) — deset
> agentů (pět měření po fázích, ke každému oponent) proti kódu. Tabulka níže
> zůstává platná, audit ji prohlubuje a na dvou místech opravuje: `WithTable` má
> **99** deklarací metod (ne 102) a extrakce `169decb` monolit reálně zmenšila
> o 39 %, takže dnešní nárůst jde z nových odpovědností, ne z redistribuce.
> Nový nález, který tabulka nemá: **`QueryPlan` nepopisuje celý dotaz**, což je
> návrhová díra ve V2.0, ne posunuté číslo.

Plán níže je z 6. 7. Mezi tím doběhla migrace na Livewire 4 a celá výkonnostní
větev (render engine, islands, row/field partials), takže část jeho „ověřeného
stavu" už neplatí. Tohle je přeměření, ne přepis záměru — fáze i jejich pořadí
drží, mění se čísla, jména a jeden návrh.

| Tvrzení plánu | Realita 2026-08-26 | Dopad |
|---|---|---|
| `WithTable` **3213 ř.**, 96 metod | **2855 ř., 102 metod** | V2.1(A) je menší, než plán počítal |
| `Column` **1717 / 1749 ř.**, 139 metod | **1681 ř., 123 metod** | totéž pro V2.1(B) |
| V2.1(A) čeká: `TableDataset`, `TableSelection`, `TableRenderState`, `TableActionRunner` | **render polovina hotová**, pod jinými jmény a jiným plánem — `table/src/Support/` má `TableRenderPlan` + sedm slice-plánů (`Column/Action/Layout/Paging/Interaction/Shell/RowRenderPlan`), `ColumnSet`, `TableQueryState`, `RowRenderer`, `CardRenderer`, `SummaryRenderer` | **viz níže — V2.1 se přepisuje** |
| V2.0 zavede `DataSourceCapabilities` | `Core/Capabilities/{Capability,CapabilitySet}` mezitím vznikly a jsou kanonický slovník (čte je `QueryPlanner` i `Column`) | **návrh V2.0 se opravuje** — rozšířit enum, nezakládat druhý slovník (`CLAUDE.md` § kanonické vlastnictví) |
| V2.0 mapa seamů s čísly řádků `WithTable.php:614-664` atd. | čísla posunutá, soubor přepsán render-engine prací | mapu seamů **znovu odvodit** před V2.0.a, nepřebírat |
| V2.2: `SaveHandler` `app()`×8, `new`×6 | **beze změny, přesně tak** | V2.2(S1) platí doslova |
| V2.2: `ActionPipeline` `new`×4 | **×6** | mírně větší, než plán psal |
| V2.3/V2.4/V2.5 greenfield | **potvrzeno**: `Resource`, `ResourceRegistry`, `Workspace`, `NavigationItem`, `Tenancy`, `StateMachine`, `WorkflowState`, `SavedView`, `GlobalSearch` = 0 výskytů v `packages/*/src` | beze změny |
| V2.1(B) chybí ERP typy sloupců | **potvrzeno**: žádný `StatusColumn`, `MoneyColumn`, `RelationColumn`, `MetricColumn` | beze změny |
| V2.5 grouping hotový | potvrzeno (`HasGrouping`); saved views, presety i virtual scroll dál 0 | beze změny |
| Pre-V2 **N6** shim removal | **hotovo 2026-08-26** — devět `class_alias` traitů pryč, migrace v `docs/upgrade.md`. Druhá půlka N6 (`Modals\Wizard` orphaned) byla už dřív uzavřena jako neplatná (`ddd-enterprise-roadmap.md:130`) | **vstupní brána do V2 je volná** |

### Co z toho plyne pro V2.1

Fáze se nemaže, ale její těžiště se posunulo. Render polovina — „resolve všechno
v PHP, ať view jen echuje" — je hotová a stojí za ní vlastní benchmark. Co
`WithTable` drží dál, je **chování**: query cesta, selection, akce, modaly,
inline edit, polling, export. Kolaboranti, které plán jmenoval, ať tedy vzniknou
kolem *toho*, a **ne pod jmény z plánu** — `TableRenderState` by dnes byl třetí
název pro `TableRenderPlan`.

### Co z toho plyne pro V2.0

Fáze V2.0.a platí, se dvěma opravami: `capabilities()` vrací `CapabilitySet`
(enum `Capability` se rozšíří o `Joinable`, `Paginable`, `SubRows`,
`ChangeToken`), a seam mapa se přeměří proti dnešnímu `WithTable`.

**Zbývá jedna brána, která není moje:** ADR 0019 je pořád `PROPOSED`, přestože
`v2.0-datasource-implementation.md` §2 jeho čtyři otevřené otázky uzavírá
(wrapper místo edice `Model`, Laravel paginator místo `DataResult`,
`PagingRequest` se třemi režimy, export až v .c). Přijmout ADR je rozhodnutí
vlastníka repa, ne implementace.

---

## Ověřený stav (2026-07-06)

**Hotovo (mimo V2 scope):** StateContainer + `TableStateSynthesizer` (WithTable
má 0 raw public properties), Unified Engine (Metadata/Capabilities/QueryPlanner/
pipes), import pipeline (CSV), export (CSV/Excel/PDF opt-in), audit log,
permissions (`HasAuthorization`), relace (BelongsToSelect/Repeater/MorphTo),
widgety + charty, plugin runtime wiring (hooks/pipes/typed payloady/priorita),
Relation Managers (base), Select create/edit/async, Tabs/Wizard, optimistic
locking a auth record-context (viz pre-V2 níže).

**Otevřeno pro V2 (ověřeno v kódu):**

| Oblast | Nález | Fáze |
|---|---|---|
| Read-cesta zamčená na Eloquent | `QueryExecutor::execute(Builder)`, `Table::getQuery(): Builder`; žádný `DataSource`/`RecordContract` | V2.0 |
| `WithTable` monolit | **3213 řádků** (state+query+action+modal+inline edit+polling+export) | V2.1 |
| `Column` god object | **1717 řádků**; chybí Money/Status/Relation/Editable/Metric typy | V2.1 |
| Modal shell inline | ~600 řádků v `index.blade.php`; není `<x-wire::modal>` | V2.1 |
| Filter views mimo form fields | 5 samostatných Blade šablon | V2.1 |
| Hydration/StateHydrator neintegrovány | SaveHandler volá `update()` napřímo; StateManager bez type hydrace | V2.2 |
| Aplikační owner vrstva | žádný `Resource`/`Page`/`Workspace` v `packages/*/src` | V2.3 |
| Multi-tenancy | žádný `tenant` scope v `packages/*/src` | V2.4 |
| Workflow / state machine | žádný `StateMachine`/`Transition` v kódu (jen plánovaný ADR) | V2.4 |
| Queued/background operace | žádná queueable action/export/import job abstrakce | V2.4 |
| DB notification center | jen transientní toast notifikace; není persistent bell/center | V2.4 |
| Saved views / filter presets | neexistuje | V2.5 |
| Global search (⌘K) | neexistuje | V2.5 |
| Large-table UX | chybí grouping (rozšíření), virtual scroll, advanced filters, column presets | V2.5 |

---

## Pre-V2 (1.8.x / 1.9) — prerekvizity, ne součást V2

Drženo v `ddd-enterprise-roadmap.md` (N1–N6, autoritativní pro pre-V2 stav). Musí
dopadnout **před** startem V2, protože V2 na nich staví nebo by je jinak muselo
řešit uprostřed refaktoru. **Stav 2026-07-06: N1–N5 ✅ hotové** (viz roadmap):

- **N1 Split→Flex** ✅ — rename kompletní (0 leftover, Flex/schema testy zelené).
- **N2 Optimistic locking** ✅ — `Form::optimisticLock()` opt-in, `StaleModelException`; BC-safe.
- **N3 Auth record-context** ✅ (zúženo) — `HasAuthorization` callback dostává
  `($user, $record)` → **per-record autorizace akcí** hned funguje. `Column::canView()`
  je **strukturální** (volá se bez recordu na ~12 místech, sub-rows memoizuje
  „once per parent") → record do něj **záměrně nefoldíme**; filtry řádek nemají.
  Per-row *cell* redakce (mzda/marže per řádek) = samostatný follow-up **F1** na
  `Column::isVisibleForRecord()`, **ne** do `canView()`.
- **N4 `using()` × relationship cascade** ✅ — runtime-ověřeno: cascade už běží pro
  jakýkoli `Model` návrat (i z `using()`); žádná změna kódu. Dořešeno docs +
  2 regresní testy (past = `using()` dostává i relationship-repeater data).
- **N5 ADR 0018 State-machine** ✅ — přijat (PROPOSED, design-only); odemyká V2.4 workflow.
- **N6 Hygiena** ⬜ — potvrdit „deprecated shim `Concerns/*` padne v 2.0", vyřešit orphaned `Modals\Wizard`.
- **F1 Per-row cell auth** ⬜ (1.9, follow-up z N3) — `Column::isVisibleForRecord($record)`
  povýšit na base a nechat `renderCell($record)` ho konzultovat vedle strukturálního `canView()`.
- **Docs messaging drift** (v1-gaps #9) — root README uvádí Tailwind 3.x, zatímco
  package READMY (`forms`/`table`) už mají Tailwind 4 sekce; sladit messaging.
  Netýká se runtime, jen product/docs konzistence — hotové mimo V2 fáze.

**Vstupní brána do V2.0:** N1 ✅ hotové, N5 ✅ ADR přijaté; zbývá N6 rozhodnutí o
shim removalu (seznam BC breaků pro 2.0). F1 je 1.9, neblokuje V2.

---

## Release model (fázovaná 2.x, deprecation-first)

- **Aditivní napřed:** nové kontrakty (`DataSource`, `RecordContract`, owner vrstva)
  přijdou jako opt-in vedle stávajícího API; default chování se nemění.
- **Deprecation-first:** staré API žije celý 2.x cyklus s `@deprecated` +
  `Core\Support\Deprecation`; tvrdé odstranění až v 3.0.
- **BC breaky rozložené:** shim removal (`core/src/Concerns/*`) — **hotovo
  2026-08-26**, devět `class_alias` traitů pryč, migrace v `docs/upgrade.md`;
  orphaned API a raw-property přístupy padnou rovněž v **2.0**; vše ostatní je
  aditivní přes 2.x.
- **Každá fáze = zelené CI:** PHPStan čistý, Pint čistý, nový kód 100% coverage
  (repo politika — pozor na shape coverage, ne jen happy path), docs + boost
  guidelines sync (stop hook to vynucuje).
- **Migrace:** per-fáze migration guide (before/after) + kde možno Rector rules.

---

## Fáze V2

### V2.0 — Odemčení read-cesty (`DataSource` kontrakt) — **HOTOVO 2026-08-26**

> Všechny tři podfáze doběhly. Exit kritérium splněno: tabulka běží nad
> `Collection` zdrojem bez modelu i builderu, Eloquent cesta beze změny chování.
>
> - **.a** kontrakt + `EloquentDataSource`; `paginateQuery`, nepaginované čtení
>   i poll token delegují na zdroj (nulová duplicita — `simplePaginate`,
>   `cursorPaginate` a `PER_PAGE_ALL` existují v repu právě jednou).
> - **.b** `RecordContract` + `EloquentRecord`/`ArrayRecord`; čtyři resolution
>   místa přes zdroj, unwrap na hranici. Rollupy nad výběrem a zámek fill handlu
>   zůstávají na builderu — kontrakt je vyjádřit neumí, a to je zapsané.
> - **.c** `CollectionDataSource`, export přes `chunk()`, docs EN/CS + boost.
>   `->query()`/`getQuery()` **nedeprecated** (viz plán .c).
>
> Odchylky od původního plánu a jejich důvody jsou v
> [`v2.0-datasource-implementation.md`](v2.0-datasource-implementation.md);
> ADR 0019 je ACCEPTED.

**Proč první:** dnes bounded context nemůže tabulku nakrmit read modelem/DTO/API
zdrojem — celá read pipeline cílí na `Illuminate\…\Builder`. Bez tohoto je čisté
DDD/CQRS strop a všechny vyšší vrstvy se nalepí na Eloquent.

**Scope:**
- Kontrakt `DataSource { query() / paginate() / count() / resolveRecord($key) }`;
  default `EloquentDataSource`. Pipeline (filters/sort/search/aggregates) cílí na
  kontrakt místo `Builder`.
- `RecordContract` (`getKey()`, atribut access) — akce a render přestanou
  vyžadovat `Model $record` (V2-2 z ddd-roadmap; samostatně nemá smysl, jede s V2.0).

**Klíčové soubory (hot / blast radius):** `Core/Query/QueryExecutor.php`,
`Core/Query/Pipes/*`, `Services/TableQueryService.php`, `Table.php:233 getQuery()`,
metadata vrstva, action render call-sites (`Model $record`).

**Prerekvizita:** ADR 0019 (hranice kontraktu; co s metadaty/relacemi u
non-Eloquent zdrojů; navazuje na ADR 0013).

**BC/riziko:** vysoké (hot files). Mitigace: `EloquentDataSource` je default,
`Model` implementuje `RecordContract` adapterem → stávající kód beze změny.

**Exit:** tabulka běží nad `Collection`/DTO zdrojem v testu; Eloquent cesta
beze změny chování; benchmark bez regrese.

**Detailní plán:** [`v2.0-datasource-implementation.md`](v2.0-datasource-implementation.md)
(seam mapa, finální kontrakty, sub-fáze V2.0.a/.b/.c, roadmap, testy).

---

### V2.1 — Rozřezání monolitů (headless engine + malá Column base)

**Proč teď:** ADR 0017 varuje, že pokud se `WithTable`/`Column` nerozřežou
**před** owner vrstvou, nová Resource/Page vrstva se jen nalepí na monolity.

**⚠️ Korekce scope (ověřeno 2026-07-06):** dva ze čtyř původních bodů jsou už
hotové — **modal shell (#6) HOTOVO** (`action-modal`/`halt-modal` partial už
používají kanonické `<x-wire-modals::*>` z core, ne inline) a **filter→form-field
(#5) z velké části HOTOVO** (base/Date/NumberRange přes `form-field.blade`; custom
jen Select+Ternary, oprávněně). V2.1 = reálně **(A) rozřezat `WithTable` + (B)
zmenšit `Column`**.

**Scope:**
- **(A) `WithTable` (3213 ř., 96 metod, 435 Livewire couplingů) → headless
  collaborators + Livewire adapter** (invariant C): `TableDataset` (nosič V2.0.a) ·
  `TableSelection` (nosič V2.0.b) · `TableRenderState` · `TableActionRunner` ·
  tenký `WithTable` adapter. Žádná trait nedrží celý stack.
- **(B) `Column` base (1749 ř., 139 metod) → menší** (invariant D) + chybějící
  ERP typy: `StatusColumn` (extends BadgeColumn, ADR 0018), `MoneyColumn`,
  `RelationColumn`, `MetricColumn`. Base už deleguje design concerns do Foundation
  traitů — jde o přesun surface-specific chování, ne god-object dekompozici.
- ~~Modal shell~~ HOTOVO · ~~Filter views~~ z velké části HOTOVO (reziduum = docs).

**BC/riziko:** střední–vysoké. Mitigace: strangler-fig — `WithTable` public API
(96 metod) beze změny, tělo do collaborators; `Column::make()` a typy beze změny;
přesuny z base deprecation-first.

**Koordinace s V2.0:** `RecordContract` (V2.0.b) je **nosič** kroku A-2
(`TableSelection`) — realizovat uvnitř té extrakce, ne dvakrát.

**Detailní plán:** [`v2.1-monolith-split-implementation.md`](v2.1-monolith-split-implementation.md)
(collaborators, pořadí extrakce A-1..A-5, Column typy B-1..B-6, golden-master
testy, milníky).

**Exit:** collaborators testovatelné bez Livewire lifecyclu; golden-master table
suite zelená beze změny; `WithTable` adapter výrazně pod 3213 ř.; nové Column typy.

---

### V2.3 — Aplikační owner vrstva

> **Stav 2026-08-30: uzavřená.** Všech pět kroků (R, P, RM, W, I). Owner vrstva
> **není** v `core` ani v `packages/table`, jak plán psal — je rozmístěná podle
> typů, které kontrakty jmenují, a to, co jmenuje `Table` nebo host traity, je
> v novém top balíčku `wire-panels`. Důvod a Filamentův precedens:
> [`v2-progress.md`](v2-progress.md) §2, rozhodnutí
> [`v2.3-…`](v2.3-owner-layer-implementation.md) § R.1.

### V2.2 — Utažení execution seamů (engine integrace už hotová)

> **Stav 2026-08-30: S1 + S2 uzavřené, S3 z poloviny.** Scope se změřením posunul
> potřetí — S1 se ukázala jako **už hotová** (`ActionPipeline` bere stages
> konstruktorem, `SaveHandler` má 25 vlastních testů, které ho konstruují přímo),
> a S2 mířila na redundanci za 0,163 µs, zatímco vedle ní seděl **ostrý defekt**:
> nehintovaný hook callback patřil oběma dispatcherům a běžel dvakrát. Doložení,
> čísla a co zbývá ze S3: [`v2-progress.md`](v2-progress.md) §1, §2 a §3.

**⚠️ Korekce scope (ověřeno 2026-07-06):** engine integrace je **hotová** —
**#3 Hydration** (`SaveHandler::persist` už používá `Dehydrator`), **#4 StateHydrator**
(běží ve `FormRuntime`+`Field`), **#2 Capabilities auto-resolve** (`TableQueryService`
volá `CapabilityResolver`) i **typed hook payloady** (`runTypedHook` dispatchován).
V2.2 se smršťuje na **seam-tightening** (ADR 0017 Phase 4 + gap #6), který zbývá.

**Scope (reálný):**
- **(S1) Redukce `app()`/`new` v execution seamech** (gap #6): SaveHandler
  `app()`×8/`new`×6, ActionPipeline `new`×4 → injektované deps (default fallback
  v konstruktoru, BC-safe). **Table strana (`TableQueryService`/`WithTable`) ⟶ V2.1**
  collaborator konstruktory (nedělat dvakrát).
- **(S2) Typed contracts/hooks primární** (invariant H): dnes běží array +
  typed hook **oba** na každém bodě → typed primární, array `runHook` `@deprecated`
  s BC bridgem. Hook systém (ADR 0014) zůstává pro integrace/edge.
- **(S3) Konsolidace hydration seamů** — verifikační: zmapovat směr
  request→typed→persist (`StateHydrator`/`Dehydrator`/`normaliseEnums`/`CastResolver`),
  zavřít případnou mezeru; jinak docs-only.

**BC/riziko:** nízké (DI s default fallbackem; deprecation-first na array hooku).

**Detailní plán:** [`v2.2-execution-seams-implementation.md`](v2.2-execution-seams-implementation.md)
(S1/S2/S3, překryv s V2.1, milníky). **Nejmenší architektonická fáze (~5–6 d),**
protože #2/#3/#4 jsou hotové.

**Exit:** execution třídy bez `app()`/`new` v hot path (injektované, mockovatelné);
jediný typed dispatch na lifecycle bodech (array hook `@deprecated` bridge);
hydration tok zdokumentovaný; suite zelená beze změny.

---

### V2.3 — Aplikační owner vrstva (`Resource` / `Page` / `Workspace`) 🔴 framework positioning

**Proč teď (ne dřív):** owner vrstva má smysl až nad headless enginem (V2.1) a
odemčenou read-cestou (V2.0) — jinak jen zabetonuje monolity. Toto je největší
mezera vůči „application framework" pozici (ADR 0017 gap #1, Phase 3).

**Stav (ověřeno 2026-07-06):** greenfield (owner vrstva = 0 v `packages/*/src`),
ale **všechny skládané primitivy jsou zralé** — `Infolist` (kompletní entry systém
→ `ViewPage` podklad), `RelationManager` (jediný owner = šablona), `Table`/
`WithForms`/`Widget`, boost `ComponentScanner`+`Describe*` (vzor registrace).

**Scope:**
- Kontrakty/owneři: `Resource` + `ResourceRegistry`, `ListPage`/`CreatePage`/
  `EditPage`/`ViewPage`, `RelationManager` (povýšit), `Workspace`/`Dashboard`/
  `NavigationItem`.
- Owner **skládá** `Form`/`Table`/`Infolist`/`Widget` (nevlastní internals);
  standalone primitivy zůstávají first-class (dvě cesty).

**Prerekvizita:** ADR 0020. **Hard-gate: V2.1 DONE** — bez headless enginu se
owneři „jen nalepí na monolity" (přímé riziko ADR 0017).

**BC/riziko:** nízké (čistě aditivní, žádné deprecations). Riziko je **návrhové** —
nerozbít standalone jednoduchost + nesklouznout do Panel Builderu (žádný URL
shell ve V2.3, to je V3).

**Detailní plán:** [`v2.3-owner-layer-implementation.md`](v2.3-owner-layer-implementation.md)
(Resource hybrid API, Pages přes host traity, registry, fáze V2.3.a/b/c,
standalone-parity testy). ~10–12 d.

**Exit:** `Resource` složí list+edit+view+relation z primitives; standalone
`Table`/`Form`/`Infolist`/`RelationManager` beze změny (parity testy); boost
`DescribeResource` + docs.

---

### V2.4 — ERP-safe execution invarianty (na čisté základně)

Produktové features, které patří do **execution** vrstvy a těží z V2.0–V2.3.

- **Multi-tenancy first-class** (invariant G; v1-gaps #2): tenant scope jako
  systémový invariant, ne ruční `where()`. Stavět na plugin hooku `table.querying`
  s prioritou `-100` (viz v2-deferred #7C) + `DataSource` scope; tenant-safe default.
- **Workflow / state machine** (v1-gaps #3; ADR 0018/N5): `WorkflowState` +
  `StatusColumn` + `TransitionAction` (guard + povolené přechody), delegace na
  doménu (objednávky/MES routing/CRM pipeline). **Pozn.:** status/workflow
  slovník, **ne** `HasState` — ten už existuje jako field value-state (kolize,
  viz ADR 0018). Enum status staví na `Enum\HasColor/HasLabel/HasIcon` + `BadgeColumn`.
- **Queued/background operace** (v1-gaps #5): queueable action/export/import job
  abstrakce + notifikace po dokončení; bezpečná async cesta pro velké datasety.
- **DB notification center** (ddd-roadmap V2-6; odloženo z 1.x): persistent
  notifikace + bell/center (abstrakce a drivery už existují).

**Stav (ověřeno 2026-07-06):** vše otevřené, podklady hotové — tenancy **seam
připraven** (`PluginManager` hook priorita, komentář `-100: security/scope
(multi-tenancy)`); workflow enum kontrakty + `BadgeColumn` hotové; **`StatusColumn`
rendering typ vzniká v V2.1 B-2** (V2.4 dodá jen transition engine — nedělat
dvakrát); DB notifikace = driver pattern hotový, přidat `DatabaseDriver` + persist.

**BC/riziko:** nízké–střední (aditivní). Tenancy je bezpečnostně citlivá —
default musí být **fail-safe** (scope chybí → nic neprojde, ne vše); bez fail-safe
testu neshipovat.

**Detailní plán:** [`v2.4-erp-execution-implementation.md`](v2.4-erp-execution-implementation.md)
(balíky T/WF/Q/N, fail-safe tenancy, koordinace WF×V2.1, N→Q pořadí, milníky).
~15–18 d; T/WF/N paralelní, Q po N.

**Exit:** tenant scope vynucen na query i mutaci (fail-safe); přechod stavu
respektuje guard; dlouhá bulk akce běží na queue s notifikací; notifikace
persistují a čtou se v bell.

---

### V2.5 — Power-user & large-table UX

**⚠️ Korekce scope (ověřeno 2026-07-06):** **grouping je HOTOVÝ** (`HasGrouping`);
a **saved views mají celý podklad hotový** — stav je serializovatelný
(`StateContainer`) a už URL-enkódovaný (`TableUrl`) → nízké úsilí, vysoká hodnota.

- **(SV) Saved views + column/filter presets** (v1-gaps #6): uložit tentýž state
  blob do DB per-user + obnovit + UI; presets = partial state. 🟢 quick win.
- **(GS) Global search (⌘K)** napříč resources — **hard-dep na V2.3** ResourceRegistry;
  výsledky s **povinným** policy + tenant filtrem (leak = security bug).
- **(LT) Large-table UX** (v1-gaps #8): **virtual scrolling** (jediná těžká novinka);
  grouping už hotový (max. collapse polish); advanced filter builder = stretch.

**BC/riziko:** nízké (aditivní UI); GS je security-aware (leak filtr povinný).

**Detailní plán:** [`v2.5-power-user-ux-implementation.md`](v2.5-power-user-ux-implementation.md)
(SV nad serializovatelným stavem, GS nad V2.3 registry, LT virtual scroll,
milníky). ~11–14 d; SV nezávislé, GS po V2.3, LT těží z V2.1.

**Exit:** saved view round-trip (identický blob); ⌘K napříč resources s policy+tenant
filtrem; virtuální scroll opt-in mode drží perf na 100k+ řádcích, koexistuje se
selection/sort.

---

### V2.6 / horizont — Domain module axis 🔶 kandidát na V3

ADR 0017 Phase 5 / layer 5: modulární skeleton pro ERP/CRM domény (`crm`/`sales`/
`billing`/`inventory`/…) — moduly deklarují resources/workspaces/workflows/policies
nad sdílenými primitives. **Druhá osa** vedle technických packages.

**Stav:** greenfield, ale registrační základ hotový (`Plugin` systém =
higher-order registration base; V2.3 owneři = co modul grupuje). Modul = kompozice,
**nezavádí nový runtime**.

**🔶 Rozhodovací bod (po V2.5):** V2.6 **vs V3**. „Teď" jen když existuje reálná
potřeba **≥2 doménových modulů** / distribuce business balíčků. Jinak **default V3**
— owner vrstva (V2.3) pokrývá single-domain aplikace sama; předčasná modularizace
= náklady bez protihodnoty. Není blocker pro žádnou nižší fázi.

**Tentýž test platí i pro technickou osu.** Otázka „rozpadnout `wire-core` na
samostatné balíčky" byla položena a zodpovězena stejně:
[ADR 0025](../decisions/0025-core-module-layers.md) — bez jmenovaného konzumenta,
který chce modul bez zbytku, se neštěpí. Místo toho se hranice modulů vynucují
uvnitř core arch testem. Detaily, proč by rozpad dnes nedodal nezávislost
(překlady bez namespace, jeden config, jeden JS bundle, `self.version` lockstep),
jsou v ADR.

**Detailní plán:** [`v2.6-domain-modules-implementation.md`](v2.6-domain-modules-implementation.md)
(`DomainModule` nad plugin lifecyclem, dvě osy architektury, referenční modul,
rozhodovací kritéria V2.6/V3). ~6–9 d *pokud poběží*.

---

## Sekvence a závislosti

```
pre-V2 (N1 Flex, N5 ADR 0018, N6 shim rozhodnutí)
   │
V2.0 DataSource + RecordContract ───────────────► odemyká vše ostatní
   │
V2.1 rozřezat WithTable/Column + modal + filter views
   │
V2.2 Hydration/StateHydrator/Capabilities + typed contracts + seam cleanup
   │
V2.3 Resource/Page/Workspace owner vrstva
   │
   ├─► V2.4 tenancy · workflow · queue · DB notifikace   (execution)
   └─► V2.5 saved views · global search · large-table UX (UX; global search ⟵ V2.3)
   │
V2.6 domain module axis (nebo V3)
```

| Fáze | Páteř | Hodnota | Riziko | BC |
|---|---|---|---|---|
| V2.0 | DataSource + RecordContract | 🔴 strategická | vysoké | aditivní (Eloquent default) |
| V2.1 | rozřezat WithTable/Column, modal, filtry | 🔴 vysoká | stř.–vys. | fasáda + legacy |
| V2.2 | Hydration/StateHydrator/Capabilities, seamy | střední | střední | opt-in flagy |
| V2.3 | Resource/Page/Workspace | 🔴 vysoká (positioning) | nízké (návrh. stř.) | aditivní |
| V2.4 | tenancy/workflow/queue/notifikace | 🔴 vysoká | nízké–stř. | aditivní |
| V2.5 | saved views/global search/large-table | vysoká | nízké | aditivní |
| V2.6 | domain modules | střední | střední | aditivní |

---

## Kritéria dokončení (per fáze)

1. PHPStan čistý (`composer analyse`), Pint čistý (`composer lint`).
2. Nový kód plně otestován (repo 100% coverage; shape coverage — nested/kolizní
   varianty, ne jen happy path; viz `architecture/audit.md`).
3. Aditivní API má opt-in default; BC break jen se zapsanou deprecation (odstranění 3.0).
4. Migration guide (before/after) pro každou fázi s BC dopadem; kde možno Rector.
5. Docs + boost guidelines sync (stop hook vynucuje); ADR pro strukturální rozhodnutí.
6. Benchmark před/po — žádná perf regrese v hot paths.

## Co tenhle dokument nahrazuje

- **V2 sekce** `ddd-enterprise-roadmap.md` (V2-1..V2-6) → sem. N1–N6 tam **zůstávají**
  jako pre-V2 (1.8/1.9).
- **`v2-deferred-items.md`** engine refaktory #2–#7 → mapované do V2.1/V2.2
  (#1 StateContainer je HOTOVO). Dokument ponechat jako detailní impl. referenci.
- **`v1-gaps.md`** produktové mezery → mapované do V2.3–V2.5. Ponechat jako
  odůvodnění (proč jsou mezery bolestivé).
