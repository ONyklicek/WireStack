---
title: DDD & enterprise roadmap — teď (1.8.x/1.9) vs V2
date: 2026-07-06
scope: packages/core, packages/forms, packages/table, packages/sortable
status: in progress (N1 ✅, N2 ✅, N3 ✅ scoped; audit 2026-07-06)
related:
  - architecture/plans/v2-master-plan.md
  - architecture/decisions/0013-unified-data-ui-engine.md
  - architecture/decisions/0017-erp-crm-application-architecture.md
  - architecture/plans/v2-deferred-items.md
  - architecture/plans/erp-crm-features.md
  - architecture/plans/filament-parity-2026-07-01 (memory)
---

# DDD & enterprise roadmap

> **⚠️ Konsolidace (2026-07-06):** Autoritativní pro **V2** je nyní
> [`v2-master-plan.md`](v2-master-plan.md). Sekce **V2-1..V2-6** níže jsou tam
> přemapované do fází V2.0–V2.6 — ber je jako historii/odůvodnění, ne jako živý
> plán. **Pre-V2 část N1–N6 zůstává autoritativní zde** (vstupní brána do V2).
> ADR: [0018](../decisions/0018-state-machine-workflow.md) (N5),
> [0019](../decisions/0019-data-source-contract.md) (V2-1),
> [0020](../decisions/0020-application-owner-layer.md).

Vychází z auditu 2026-07-06 (DDD seams, kvalita kódu, doménová parita pro
ERP/CRM/MES/CMS/SaaS). Tento dokument **nedupluje** dokončené vlny:

- `erp-crm-features.md` (relace, bulk, dashboardy, export, permissions, audit) — HOTOVO.
- `v2-deferred-items.md` (StateContainer, Metadata/Capabilities auto-resolve,
  Hydration/SaveHandler, plugin wiring) — interní engine refaktory, převážně HOTOVO.

Drží **nové** mezery, které audit odhalil a které v žádném existujícím plánu nejsou.

## Systémový nález (kontext hranice teď/V2)

Celá **read-cesta je zamčená na Eloquent** (`Table::getQuery()` → `Builder`,
`QueryExecutor::execute(Builder …)`, akce mluví `Model $record`). To je strop
čistého DDD/CQRS: bounded context nemůže tabulku nakrmit read modelem/DTO/API
zdrojem. **Write-cesta je na tom líp** — `Form::using(Closure)` je funkční command
seam. Proto:

- **teď** = malé, aditivní, BC-safe věci, které nevyžadují odemčení read-cesty;
- **V2** = odemčení read-cesty (`DataSource`) a vše, co na něm staví, + BC breaky.

---

## 🟢 TEĎ — 1.8.x / 1.9 (aditivní, BC-safe; ~3–4 dny)

> **Stav 2026-07-06:** N1 ✅, N2 ✅, N3 ✅ (zúženo — viz níže). N4/N5/N6 otevřené.
> Ověřeno: `composer test:core` (1391), `test:table` (919), `test:forms` (667),
> `analyse` (No errors), `lint` (passed). Nové řádky 100% pokryté; celkové
> repo coverage zůstává na pre-existing ~85 % (WIP branch 1.8.1, ne regres).

### N1 ✅ — Dokončit rename Split → Flex
- **Stav:** rozjeté na branchi `1.8.1` (`git status`).
- **Proč teď:** půl migrace = runtime „view not found".
- **Krok:** grep zbylých referencí `x-wire::split`, `Schema\Split`, `View\Split`,
  `schema/split.blade`, docs; safelist + `LayoutTagsTest`. Atomicky dokončit.
- **Test:** `composer test:core` + `npm run build` + `php docs-site/build.php`.
- **Riziko:** nízké. **Odhad:** ½ dne.

### N2 ✅ — Optimistic locking (opt-in) 🔴 nejvyšší doménová hodnota
- **Co:** `Form::optimisticLock(string $column = 'updated_at')`. Před `save()`
  porovná hydratovanou verzi s aktuální DB hodnotou; při neshodě vyhodí
  `StaleModelException` + konflikt notifikace, neuloží.
- **Kde:** `SaveHandler::persist()` (`packages/forms/src/Forms/Runtime/SaveHandler.php:112`),
  jen ve větvi `$model instanceof Model` (update mode). Nová Foundation výjimka +
  zpráva do `wire-forms::messages`.
- **Proč teď:** tichá ztráta dat při souběžné editaci je nejhorší třída chyby
  v ERP/MES; změna je opt-in → nulový BC dopad.
- **Test:** feature — dvě instance, druhý save hodí konflikt.
- **Riziko:** nízké. **Odhad:** 1 den.

### N3 ✅ (zúženo) — Autorizační record-context
- **Dodáno:** `HasAuthorization::isAuthorized($context)` nyní protahuje record do
  custom callbacku — `->authorizeUsing(fn ($user, $record) => …)`. BC-safe (staré
  jednoargumentové closury fungují dál; PHP extra argument ignoruje).
  `packages/core/src/Foundation/Concerns/HasAuthorization.php`.
- **Dopad:** **per-record autorizace akcí** funguje okamžitě — akce už record jako
  `$context` předávaly (`Actions/Concerns/HasVisibility.php:89` → `canExecute($record)`),
  jen ho `isAuthorized` neposílal do closury. Nejčastější enterprise potřeba (schválit
  jen vlastní záznam, guard přechodu jen pro vlastníka) je tím pokrytá.
- **⚠️ Caveat POTVRZEN → zúžení:** `Column::canView()` je **strukturální** (rozhoduje,
  jestli je sloupec v tabulce vůbec) a volá se **bez recordu** na ~12 místech
  (`index.blade` hlavička, column toggle, export, a `sub-rows.blade` ho dokonce
  memoizuje „once per parent, not per cell"). Přetížit ho per-record by rozbilo
  sémantiku + výkon. Proto record **záměrně nefoldím do `canView()`**. Filtry
  navíc řádek nemají (jsou table-level).
- **Follow-up F1** (viz níže): per-row **cell** autorizace (skrýt/redigovat buňku
  mzda/marže podle řádku) je samostatná featura — patří na existující seam
  `Column::isVisibleForRecord($record)` (dnes na `ButtonColumn`), ne do `canView()`.
- **Testy:** `HasAuthorizationTest` — per-record forwarding + BC jednoarg. (18 passed).
- **Riziko:** nízké. **Hotovo.**

### N4 ✅ — `using()` × relationship cascade past (write-side)
- **Zjištění (runtime-ověřeno):** původní auditní premisa byla nepřesná — cascade
  (`SaveHandler.php:78`) běží pro **jakýkoli** `$record instanceof Model`, tedy
  **i** pro `using()`, který vrátí Model. Žádná změna kódu nebyla potřeba.
- **Reálná past → dodáno:** `using()` closura dostává `$data` **včetně**
  relationship-repeater polí (`children`), zatímco default cesta je stripuje.
  Naivní `Model::create($data)` by je mass-assignoval. Zdokumentováno v
  `docs/forms/save-lifecycle.md` (vrať Model → cascade doběhne; nepřiřazuj
  relationship klíče; non-Model návrat → cascade skip).
- **Testy:** 2 regresní v `SaveHandlerTest` (using→Model cascaduje děti;
  using→non-Model skip). `test:forms` zelené.
- **Riziko:** nízké. **Hotovo.**

### N5 ✅ — ADR: State machine / workflow seam (jen návrh, impl ve V2)
- **Hotovo:** `architecture/decisions/0018-state-machine-workflow.md` existuje
  (PROPOSED, design-only). Návrh: enum-backed status (reuse `Enum\HasColor/HasLabel/HasIcon`
  + `EnumResolver`), `WorkflowState` s guardy, `StatusColumn` (specializace
  `BadgeColumn`), `TransitionAction extends BaseAction` (guard + `HasAuthorization`
  + ActionPipeline + audit + N2 lock). Delegace domény, ne workflow engine.
- **⚠️ Oprava návrhu proti původnímu N5 náčrtu:** ADR **nepoužívá** „canonical
  `HasState`" — kolidovalo by s existujícím `Foundation/Concerns/HasState`
  (form field value-state) a `Core/State/*` (component state). Zvolena
  status/workflow slovní zásoba (`HasStatus`/`WorkflowState`).
- **Riziko:** žádné (jen dokument). **Hotovo.**

### N6 ✅ — Hygiena (rozhodnutí + inventář; bez změny kódu)

**1) Deprecated shim vrstva `packages/core/src/Concerns/*` → removal v 2.0.**
Ověřeno: `use NyonCode\WireCore\Concerns\Has*` mimo shim adresář = **0 výskytů** —
shimy nikdo interně nepoužívá (jsou čistě externí BC). Není co „přestat rozšiřovat";
odstranění je čistý externí BC break → patří do **V2.0 gate** (deprecation-first,
removal 2.0). **BC-break inventář pro 2.0** (9 shimů, každý `@deprecated → Foundation/Concerns/*`):
`HasButtonStyles`, `HasColor`, `HasDynamicProperties`, `HasIcons`,
`HasKeyboardShortcut`, `HasLifecycle`, `HasLoadingState`, `HasModal`, `HasVisibility`.

**2) `Modals\Wizard` — NENÍ orphaned (stará memory neaktuální).**
Runtime-ověřeno: `Modals\Wizard implements ModalContract`, `HasModal` ho konzumuje
přes `->modal(Wizard::make()->steps([...]))` (`HasModal.php:345` `$modal instanceof Wizard
=> modalSteps = $modal->getSteps()`), je dokumentovaný (`docs/core/modals.md`) i
testovaný. `->steps([...])` je jen shortcut ke stejné cestě. **Byl integrován**
(modals consolidation) → **žádný delete kandidát**, mazat by rozbilo `HasModal`.
Opraveno v memory (`wizard_runtime_2026_06_27`).

**Závěr:** N6 pre-V2 část hotová (rozhodnutí + inventář zapsané); vlastní shim
removal je execution item ve **V2.0**.

### F1 ✅ — Per-row cell authorization (follow-up z N3)
- **Dodáno:** kanonický `Column::visibleForRecord(Closure)` + `isVisibleForRecord(Model)`
  na base `Column`; `renderCell($record)` ho konzultuje **vedle** strukturálního
  `canView()`. Zapojeno napříč celou rodinou (text/badge/boolean/icon/image/poll/
  select/toggle/text-input/split(+child)/stacked). Skrytá buňka → prázdný render,
  sloupec zůstává v ostatních řádcích.
- **Konsolidace:** `ButtonColumn::visibleWhen(Closure)` je teď BC alias na
  `visibleForRecord()` (odstraněn duplicitní lokální `$visibleWhen` + override).
  Bonus: `StackedColumn` dostal chybějící `canView()`+F1 guard (sjednocení rodiny).
- **Orthogonální k N3:** samostatná closura (`fn ($record) => …`), **ne** přes
  `authorizeUsing` — vyhýbá se double-invocation pasti (structural `canView` volá
  `isAuthorized(null)`). Auth per buňku: `->visibleForRecord(fn ($r) => auth()->user()->can('viewSalary', $r))`.
- **Testy:** `ColumnVisibleForRecordTest` (5 passed) + `test:table` 924 zelené;
  `analyse` No errors; `lint` passed. Docs: `docs/table/columns/index.md`.
- **Riziko:** nízké. **Hotovo.**

---

## 🔴 V2 — velké refaktory, BC breaky, ADR napřed

### V2-1 — `DataSource` kontrakt (odemčení read-cesty) — strategický strop
- **Co:** kontrakt `DataSource { query/paginate/count/resolveRecord }`, default
  `EloquentDataSource`; pipeline (filters/sort/search) cílí na kontrakt místo
  `Builder`. Umožní read modely / DTO / API zdroje / `Collection`.
- **Blast radius:** `QueryExecutor`, všechny `Core/Query/Pipes/*`,
  `TableQueryService`, metadata vrstva — BC-citlivé, hot files.
- **Prerekvizita:** ADR `architecture/decisions/0019-data-source-contract.md`
  (hranice kontraktu; co s metadaty/relacemi u non-Eloquent zdrojů). Navazuje na
  ADR 0013 (unified engine) a `v2-deferred-items.md` #2 (Metadata/Capabilities).

### V2-2 — Record abstrakce v akcích
- `Action::render(Model)` / `getRenderData(Model)` / `Table::canUpdate(EloquentModel)`
  → `RecordContract`/`getKey()`. Navazuje na V2-1; samostatně nemá smysl.

### V2-3 — State machine / workflow — implementace
- Podle ADR 0018 (N5). Zvážit i read-model pohled na stavy (souvisí s V2-1).

### V2-4..6 — Už na roadmapě (potvrzeno auditem jako reálné mezery, ne bugy)
- **Global search** (⌘K napříč resources) — CRM/CMS/SaaS standard.
- **Multi-tenancy** scoping — SaaS blocker. Doporučeno postavit na plugin hook
  `table.querying` s prioritou `-100` (viz `v2-deferred-items.md` #7C).
- **DB notifikace / notification center** — CRM/SaaS.

---

## Prioritizace (poměr hodnota/riziko)

| # | Položka | Kdy | Hodnota | Riziko | Odhad | Stav |
|---|---------|-----|---------|--------|-------|------|
| N1 | Split→Flex dokončit | teď | — | nízké | ½ d | ✅ |
| N2 | Optimistic locking | teď | 🔴 vysoká | nízké | 1 d | ✅ |
| N3 | Auth record-context | teď | 🟠 střední | nízké | 1 d | ✅ (zúženo) |
| F1 | Per-row cell auth (z N3) | 1.9 | 🟠 střední | nízké | 1 d | ✅ |
| N4 | `using()` cascade past | teď | 🟠 střední | nízké | ½ d | ✅ |
| N5 | State-machine ADR | teď | — (odemyká V2-3) | žádné | ½ d | ✅ |
| N6 | Hygiena (shims, Wizard) | teď/opt | nízká | nízké | ½ d | ✅ |
| V2-1 | DataSource kontrakt | V2 | 🔴 strategická | vysoké | velký |
| V2-2 | Record abstrakce akcí | V2 | střední | střední | střední |
| V2-3 | State-machine impl | V2 | 🔴 vysoká | střední | střední |
| V2-4 | Global search | V2 | vysoká | střední | střední |
| V2-5 | Multi-tenancy | V2 | 🔴 SaaS blocker | střední | střední |
| V2-6 | DB notifikace | V2 | střední | nízké | střední |

## Kritéria dokončení (per položka)
1. PHPStan čistý (`composer analyse`), Pint (`composer lint`).
2. Nový kód plně otestován (repo politika 100% coverage; pozor na shape coverage —
   nested/kolizní varianty, ne jen happy path — viz `architecture/audit.md`).
3. Aditivní API má opt-in default; BC break jen ve V2 s deprecation-first.
4. Docs + boost guidelines sync (stop hook to vynucuje).
