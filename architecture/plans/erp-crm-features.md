---
name: ERP/CRM Features Plan
description: 6 klíčových features pro ERP/CRM použitelnost Wire ekosystému
type: plan
---

## ERP/CRM Features — Implementation Plan

Tento dokument je feature inventory / implementation checklist.
Nevyjadřuje cílovou architekturu produktu.

Pro cílový model “Filament-like DX, Nova-like ownership, ERP-safe execution”
viz [ADR 0017](../decisions/0017-erp-crm-application-architecture.md).

Stavíme na hotovém Unified Engine (Phase 0–5). Každá feature je samostatná fáze, může se dělat nezávisle (kromě závislostí uvedených níže).

### Stav implementace (aktualizováno 2026-05-24)

| Feature | Stav | Kompletnost |
|---------|------|-------------|
| 1. Relace & Lookup fieldy | HOTOVO | 100% |
| 2. Bulk akce na tabulkách | HOTOVO | 100% |
| 3. Dashboard widgety | HOTOVO | 100% |
| 4. Export | HOTOVO | 100% |
| 5. Permissions integrace | HOTOVO | 100% |
| 6. Audit log | HOTOVO | 100% |

---

## FEATURE 1 — Relace & Lookup fieldy — HOTOVO

**Cíl:** BelongsTo select, HasMany inline tabulky, relationship fieldy ve forms i tabulkách

### 1.1 BelongsToSelect field (wire-forms)

- `Components/BelongsToSelect.php` extends Select
- `->relationship('company', 'name')` — auto-load options z relace
- `->searchable()` — AJAX search (Livewire endpoint, debounce 300ms)
- `->preload()` — načíst všechny options na mount
- `->createOptionForm([...])` — inline modal pro vytvoření nového záznamu
- `->editOptionAction()` — odkaz na editaci
- Používá existující `Core\Metadata\RelationMetadata` pro resolve FK, related model

### 1.2 HasMany Repeater field (wire-forms)

- `Components/Repeater.php` — inline tabulka/seznam related záznamů
- `->relationship('contacts')` — auto-bind na HasMany
- `->schema([TextInput::make('name'), ...])` — fieldy pro každý řádek
- `->addable()`, `->deletable()`, `->reorderable()` (integrace sortable plugin)
- `->collapsed()`, `->collapsible()` — collapse jednotlivé itemy
- `->minItems(1)`, `->maxItems(10)`
- State management: nested state paths v StateContainer (`contacts.0.name`, `contacts.1.email`)
- Save: cascade save v SaveHandler — parent save, pak child upsert/delete

### 1.3 MorphTo select (wire-forms)

- `Components/MorphToSelect.php`
- `->types([User::class => 'Users', Company::class => 'Companies'])`
- Dva selecty: typ + ID (dynamicky options podle vybraného typu)
- Používá `Core\Relations\MorphSegment`

### 1.4 Relationship columns (wire-table)

- Už existuje základ v Column + RelationPath
- Rozšířit: `->relationship('company.country', 'name')` — auto eager load
- BelongsTo inline select v tabulce (SelectColumn s relationship)
- Aggregate columns: `TextColumn::make('orders_count')->counts('orders')`

### 1.5 Testy

- Unit: BelongsToSelect options loading, search, create
- Unit: Repeater add/remove/reorder, nested validation
- Integration: Repeater save cascade (parent + children)

**Závislosti:** Žádné (staví na hotovém Metadata + RelationPath)
**Odhad složitosti:** Nejvyšší — Repeater je komplexní

---

## FEATURE 2 — Bulk akce na tabulkách — HOTOVO

**Cíl:** Select řádky, provést hromadné akce (export, změna stavu, delete)

### 2.1 Row selection (wire-table)

- `WithTable` — přidání `$selectedRows` state (array IDs)
- Checkbox column na začátku tabulky (select all / individual)
- "Select all on page" vs "Select all matching filter" (count badge)
- `->selectable()` na Table config
- Blade: checkbox v header + per-row, Alpine.js pro select-all toggle

### 2.2 BulkAction class (wire-core)

- `Actions/BulkAction.php` extends BaseAction
- `->action(fn (Collection $records) => ...)` — callback s kolekcí vybraných záznamů
- `->deselectRecordsAfterCompletion()` — auto reset selection
- `->requiresConfirmation()` — confirmation modal (existující modal system)
- Integrace s existujícím ActionPipeline (ActionContext už má `bulk records`)

### 2.3 Built-in bulk akce

- `ForceDeleteBulkAction::make()` — force delete s potvrzením
- `RestoreBulkAction::make()` — restore soft-deleted s potvrzením
- Custom: `BulkAction::make('approve')->action(fn ($records) => $records->each->approve())`

### 2.4 Bulk action v Table configu

- `Table::make()->bulkActions([...])` — registrace
- Toolbar dropdown s bulk akcemi (zobrazuje se jen při selectu)
- Badge s počtem vybraných

### 2.5 Testy

- Unit: BulkAction creation, pipeline execution
- Feature: select rows, execute bulk action, verify result

**Závislosti:** Existující ActionPipeline + Modal system
**Odhad složitosti:** Střední

---

## FEATURE 3 — Dashboard widgety — HOTOVO

**Cíl:** Stats karty, grafy, KPI, custom widgety pro dashboardy

### 3.1 Widget base (wire-core — nový modul)

- `Widgets/Widget.php` — base Livewire component
- `Widgets/Concerns/HasPolling.php` — `->pollingInterval('30s')`
- Reusuje `Foundation/Concerns/HasColumnSpan.php` — grid layout (1–12 columns)
- `Widgets/Contracts/HasWidgets.php` — interface pro pages

### 3.2 StatsOverview widget

- `Widgets/StatsOverviewWidget.php`
- `Stat::make('Total Revenue', '$45,231')` — label + value
- `->description('12% increase')`, `->descriptionIcon('arrow-up')`
- `->color('success')`, `->chart([7, 3, 4, 5, 6, 3, 5])` — sparkline
- `->extraAttributes([...])` — custom styling
- Blade: grid layout, stat karty s optional sparkline (SVG, ne knihovna)

### 3.3 ChartWidget

- `Widgets/ChartWidget.php`
- `->type('line' | 'bar' | 'pie' | 'doughnut')` — Chart.js integrace
- `getDatasets()`, `getLabels()` — methods k overridu
- `->filter('year')` — dropdown filter na widgetu
- Minimální JS: Alpine + Chart.js CDN nebo npm

### 3.4 TableWidget

- `Widgets/TableWidget.php` — embeddovaná wire-table v dashboardu
- `->table(fn (Table $table) => $table->columns([...])->query(Order::query()))`
- Kompaktní layout, bez full toolbar

### 3.5 Custom widget

- `Widgets/CustomWidget.php` — wrapper pro custom Blade view
- `->view('dashboard.custom-widget')` — uživatelský Blade

### 3.6 Dashboard layout

- `WithWidgets` trait — `getWidgets()`, `getWidgetColumns()`
- Grid layout: 1–4 sloupce, responsive
- Blade: `<x-wire::widget-grid :widgets="$this->getWidgets()" />`

### 3.7 Testy

- Unit: Stat creation, Chart data, Widget rendering
- Feature: Dashboard mount, widget polling

**Závislosti:** Žádné (standalone modul v core)
**Odhad složitosti:** Střední

---

## FEATURE 4 — Export — HOTOVO

**Cíl:** CSV, Excel, PDF export z tabulek (s filtry)

### 4.1 Export base (wire-table)

- `Export/TableExport.php` — base export třída
- `->columns([...])` — které sloupce exportovat (default: všechny viditelné)
- `->query()` — použije aktuální filtered query z TableQueryService
- `->fileName('orders-2026')` — název souboru
- `->withHeadings()` — hlavička v prvním řádku

### 4.2 CSV export

- `Export/CsvExporter.php` — nativní PHP `fputcsv()`, žádná závislost
- `->delimiter(',')`, `->enclosure('"')`
- Streaming response: `StreamedResponse` pro velké datasety

### 4.3 Excel export (optional dependency)

- `Export/ExcelExporter.php` — vyžaduje `maatwebsite/excel` nebo `openspout/openspout`
- Auto-detect: `class_exists()` check
- Fallback na CSV pokud není závislost
- Styling: header bold, auto-width

### 4.4 PDF export (optional dependency)

- `Export/PdfExporter.php` — vyžaduje `barryvdh/laravel-dompdf` nebo `spatie/laravel-pdf`
- Template-based: Blade view → PDF
- `->orientation('landscape')`, `->paperSize('A4')`

### 4.5 Export action v Table

- `Table::make()->headerActions([ExportAction::make()])` — toolbar button
- ExportAction → modal s výběrem formátu + sloupců
- Background export pro velké datasety: Laravel Queue job + notification po dokončení
- `->formats([ExportFormat::Csv, ExportFormat::Excel])` — omezení formátů

### 4.6 Testy

- Unit: CSV generation, column mapping
- Feature: Export s filtry, streaming response

**Závislosti:** TableQueryService (hotový)
**Odhad složitosti:** Střední — CSV jednoduchý, Excel/PDF optional

---

## FEATURE 5 — Permissions integrace — HOTOVO

**Cíl:** Konzistentní auth API napříč všemi komponentami. Kompatibilní se třemi permission systémy:
- **Laravel Gate/Policy** — vestavěný, žádná závislost
- **spatie/laravel-permission** — `hasPermissionTo()`, `hasRole()`
- **nyoncode/laravel-permission-extended** — wildcard permissions (`admin.*`), Super Admin gate bypass, `WithPermissions` trait pro Livewire

### Klíčový princip

`nyoncode/laravel-permission-extended` registruje Super Admin bypass přímo na úrovni Laravel Gate (`Gate::before()`). To znamená, že **jakýkoli `Gate::allows()` volání automaticky projde pro Super Admina** — komponenty to nemusí řešit samy. Hardcoded `$user->hasRole('Super Admin')` check v Actions a Columns je redundantní a měl by být odstraněn. Stejně tak wildcard permissions (`admin.*`) fungují transparentně přes Gate — stačí volat `Gate::allows()` a balíček se postará o matching.

Wire ekosystém **nesmí mít hard dependency** na žádný z těchto balíčků. Detekce přes `class_exists()` / `method_exists()`.

### Stav implementace (aktualizováno 2026-05-23)

Všechny komponenty používají sdílený `HasAuthorization` trait. Hardcoded Super Admin bypass odstraněn. Vše jde čistě přes `Gate::allows()`.

| Komponenta | `permission()` | `authorize()` | `authorizeUsing()` | Hardcoded Super Admin | Poznámka |
|------------|:-:|:-:|:-:|:-:|:--|
| Actions (HasVisibility) | ✅ | ✅ | ✅ | ❌ odstraněn | via `HasAuthorization` trait |
| Column | ✅ | ✅ | ✅ | ❌ odstraněn | via `HasAuthorization` trait |
| Table (policy) | — | ✅ | ✅ (override) | ❌ | Gate-based policies |
| Form (policy) | — | ✅ | ✅ | ❌ | `authorizeUsing()` doplněno |
| Filter | ✅ | ✅ | ✅ | ❌ | via `HasAuthorization` trait |
| Foundation fields | ✅ | ✅ | ✅ | ❌ | via `HasAuthorization` v `HasVisibility` |
| Widget | ✅ | ✅ | ✅ | ❌ | via `HasAuthorization` v `HasVisibility` |

### 5.1 Extrakce sdíleného auth traitu — `Foundation/Concerns/HasAuthorization`

Aktuálně mají Actions HasVisibility a Column.php identickou auth logiku duplikovanou. Extrahovat do jednoho traitu:

**Nový soubor:** `packages/core/src/Foundation/Concerns/HasAuthorization.php`

```php
trait HasAuthorization
{
    protected ?string $permission = null;
    protected ?string $gateAbility = null;
    protected ?Closure $authorizeCallback = null;

    public function permission(?string $permission): static;
    public function authorize(?string $ability): static;
    public function authorizeUsing(?Closure $callback): static;
    public function isAuthorized(mixed $context = null): bool;
}
```

**Rozhodovací logika `isAuthorized()`:**
1. Pokud nic není nastaveno (`permission`, `gateAbility`, `authorizeCallback`) → `true`
2. Uživatel neexistuje → `false`
3. Custom callback → `(bool) callback($user)`
4. Gate ability → `Gate::forUser($user)->allows($ability, $context)`
5. Permission string → `Gate::forUser($user)->allows($permission)` (Gate fallback — funguje s Laravel, Spatie i permission-extended)

**Odstranit:**
- Hardcoded `$user->hasRole('Super Admin')` check — `laravel-permission-extended` to řeší přes `Gate::before()`, čistý Laravel přes policies, Spatie přes `Gate::before()` registraci
- Přímé `$user->hasPermissionTo()` volání — `Gate::allows()` fallback pokryje Spatie i permission-extended transparentně (oba balíčky registrují své permissions do Gate)

**Použít v:**
- `Actions/Concerns/HasVisibility.php` — reusuje `HasAuthorization` místo vlastní logiky
- `Columns/Column.php` — reusuje `HasAuthorization` místo duplicitního kódu
- `Foundation/Concerns/HasVisibility.php` — přidat `use HasAuthorization` (fieldy, widgety)
- `Filters/Filter.php` — přidat `use HasAuthorization` místo vlastního `permission` property

### 5.2 Column authorization — rozšíření

`authorizeInline()` zůstává na Column (specifické pro inline editing):
- `->authorizeInline('edit-salary')` → `Gate::allows('edit-salary')` (bez hardcoded Super Admin)
- Wildcard support přes permission-extended: `->authorizeInline('salaries.*')` funguje automaticky

### 5.3 Model policy integrace (wire-table) — HOTOVO ✅

`Table::make()->authorize()` zapne policy mode. Využívá čistě `Gate::allows()`:
- `->authorizeCreate(bool|Closure)` → `Gate::allows('create', Model::class)`
- `->authorizeUpdate(bool|Closure)` → per-row `Gate::allows('update', $record)`
- `->authorizeDelete(bool|Closure)` → per-row `Gate::allows('delete', $record)`
- `->authorizeView(bool|Closure)` → per-row `Gate::allows('view', $record)`
- Override (bool/Closure) má přednost před policy

Kompatibilita: `Gate::allows()` automaticky projde přes Super Admin gate (`permission-extended`), Spatie policies, i čistý Laravel.

### 5.4 Form policy integrace (wire-forms) — HOTOVO ✅

Implementováno: `authorize()`, `canSave()`, `isReadOnly()`, `authorizeUsing()`.

### 5.5 Filter authorization — HOTOVO ✅

Filter používá `HasAuthorization` trait. Podporuje `permission()`, `authorize()`, `authorizeUsing()`, `canView()`.

### 5.6 Foundation fields + Widget — HOTOVO ✅

`HasAuthorization` je integrován do `Foundation/Concerns/HasVisibility.php`. Fieldy i widgety podporují `permission()`, `authorize()`, `authorizeUsing()`. `isVisible()` kontroluje i `isAuthorized()`.

### 5.7 Kompatibilita s permission systémy

| Feature | Laravel Gate/Policy | spatie/laravel-permission | nyoncode/laravel-permission-extended |
|---------|:---:|:---:|:---:|
| `->authorize('ability')` | ✅ `Gate::allows()` | ✅ (Spatie registruje do Gate) | ✅ (registruje do Gate + wildcard) |
| `->permission('name')` | ✅ `Gate::allows()` fallback | ✅ (registruje permissions do Gate) | ✅ (wildcard matching `admin.*`) |
| `->authorizeUsing(fn)` | ✅ custom callback | ✅ custom callback | ✅ custom callback |
| Super Admin bypass | Policy vrací `true` | `Gate::before()` registrace | ✅ automatický `Gate::before()` |
| Table `->authorize()` | ✅ `Gate::allows('update', $record)` | ✅ | ✅ |
| Wildcard `admin.*` | ❌ (potřeba vlastní Gate) | ❌ | ✅ transparentně |

**Klíč:** Wire nikdy nevolá Spatie/permission-extended API přímo. Vše jde přes `Gate::allows()`. Oba balíčky se do Gate registrují samy — Wire jen používá standardní Laravel auth API.

### 5.8 Implementační kroky

1. **Vytvořit `HasAuthorization` trait** v `Foundation/Concerns/` — sdílená auth logika, čistě přes `Gate::allows()`
2. **Refaktorovat Actions HasVisibility** — odstranit duplicitní auth kód, reusovat `HasAuthorization`
3. **Refaktorovat Column** — odstranit duplicitní auth kód, reusovat `HasAuthorization`
4. **Rozšířit Foundation HasVisibility** — přidat `use HasAuthorization`, napojit na `isVisible()`
5. **Rozšířit Filter** — nahradit vlastní `$permission` za `use HasAuthorization`
6. **Rozšířit Form** — přidat `authorizeUsing()`, propojit `isReadOnly()` → auto-disable fields
7. **Odstranit hardcoded Super Admin bypass** z Actions i Columns — nahradit čistým `Gate::allows()`
8. **Testy** — aktualizovat existující, přidat nové pro Filter, Foundation, cross-cutting

### 5.9 Testy

Existující (aktualizovat po refaktoringu):
- `packages/core/tests/Unit/Actions/HasVisibilityTest.php`
- `packages/table/tests/Unit/TableAuthorizationTest.php`
- `packages/table/tests/Unit/Columns/ColumnAuthorizationTest.php`
- `packages/forms/tests/Unit/FormAuthorizationTest.php`

Nové:
- `packages/core/tests/Unit/Foundation/HasAuthorizationTest.php` — sdílený trait
- `packages/table/tests/Unit/Filters/FilterAuthorizationTest.php`
- `packages/core/tests/Unit/Widgets/WidgetAuthorizationTest.php`
- Integration: Gate + Spatie + permission-extended kompatibilita (mock `Gate::before()`)

**Závislosti:** Existující HasVisibility + Laravel Gate/Policy (žádná hard dependency na Spatie ani permission-extended)
**Odhad složitosti:** Střední — refaktoring existujícího kódu + rozšíření na zbývající komponenty

---

## FEATURE 6 — Audit log — HOTOVO

**Cíl:** Kdo co kdy změnil — integrace s existujícím event systémem

### Stav implementace (aktualizováno 2026-05-24)

Kompletní audit log systém implementován v `packages/core/src/Audit/`.

### 6.1 Audit event system (wire-core) — HOTOVO ✅

- `Audit/Contracts/AuditableEvent.php` — interface pro všechny audit eventy
- `Audit/Events/RecordCreated.php` — nový záznam vytvořen
- `Audit/Events/RecordUpdated.php` — záznam upraven (old → new values)
- `Audit/Events/RecordDeleted.php` — záznam smazán
- `Audit/Events/BulkActionExecuted.php` — hromadná akce (action name, record IDs)
- `Audit/Events/InlineCellUpdated.php` — inline edit v tabulce (column, old → new)
- Každý event implementuje `AuditableEvent` a obsahuje: model type/ID, old/new values, metadata

### 6.2 AuditLogger + AuditEntry (wire-core) — HOTOVO ✅

- `Audit/AuditLogger.php` — centrální logger, listens na `AuditableEvent`
    - `log(AuditableEvent)` — zapíše entry, respektuje config (enabled, events, exclude_columns)
    - `withoutAuditing(fn)` — disable pro seedy/importy (static, nestable, exception-safe)
    - `prune()` — smaže záznamy starší než `retention_days`
    - Auto-resolve: user ID, IP, user agent z requestu
- `Audit/AuditEntry.php` — Eloquent model (`audit_logs` tabulka)
    - `auditable()` — MorphTo polymorphic relace
    - `user()` — BelongsTo s konfigurovatelným user modelem
    - Scopes: `forRecord()`, `forEvent()`, `byUser()`, `olderThan()`
    - `getChanges()` — computed diff (old → new)
- `Audit/AuditEventSubscriber.php` — Laravel event subscriber, propojuje eventy s loggerem
- Migration: `database/migrations/create_audit_logs_table.php`

### 6.3 Konfigurace — HOTOVO ✅

Přidáno do `config/wire-core.php` → `audit` sekce:
- `audit.enabled` — global on/off (env `WIRE_AUDIT_ENABLED`)
- `audit.model` — custom AuditEntry model class
- `audit.user_model` — user model pro BelongsTo relaci
- `audit.events` — whitelist event typů (null = all)
- `audit.exclude_columns` — columns to never log (default: `password`, `remember_token`)
- `audit.retention_days` — auto-prune (null = no pruning)

### 6.4 Audit trail UI — HOTOVO ✅

- `Audit/Actions/AuditTrailAction.php` — row action → slide-over s historií
    - Extends `Action`, default: icon `clock`, color `gray`, slideOver, stickyHeader, width `lg`
- `resources/views/audit/trail.blade.php` — timeline Blade view
    - Event-specific ikony a barvy (created=zelená, updated=modrá, deleted=červená, bulk=amber)
    - Diff tabulka (old → new) pro každý entry
    - User + relative timestamp (`diffForHumans`)
    - Metadata (IP adresa)
    - Empty state
- Překlady: `resources/lang/en/audit.php` + `resources/lang/cs/audit.php`

### 6.5 HasAuditable trait — HOTOVO ✅

- `Audit/Concerns/HasAuditable.php` — trait pro Eloquent modely
    - Boot: auto-registrace `created`, `updated`, `deleted` model event listenerů
    - `audits()` — `morphMany` relace na AuditEntry
    - `getAuditExclude()` — blacklist sloupců (override v modelu)
    - `getAuditInclude()` — whitelist sloupců (override v modelu)
    - `filterAuditAttributes()` — aplikuje include/exclude filtry

### 6.6 Testy — HOTOVO ✅ (35 testů, 74 assertions)

- `tests/Unit/Audit/AuditEntryTest.php` — model, casts, getChanges() diff logika
- `tests/Unit/Audit/AuditEventsTest.php` — všech 5 event tříd, interface, metadata
- `tests/Unit/Audit/AuditLoggerTest.php` — withoutAuditing, config enabled/events
- `tests/Unit/Audit/AuditTrailActionTest.php` — factory, slideOver, modal config
- `tests/Unit/Audit/HasAuditableTest.php` — include/exclude filtry, boot, audits relace

**Závislosti:** Existující Event system, Modal system (SlideOver)
**Odhad složitosti:** Střední

---

## Doporučené pořadí implementace

```
Feature 5 (Permissions)   ✅ HOTOVO
    ↓
Feature 2 (Bulk akce)     ✅ HOTOVO
    ↓
Feature 1 (Relace)        ✅ HOTOVO
    ↓
Feature 4 (Export)         ✅ HOTOVO
    ↓
Feature 3 (Dashboard)     ✅ HOTOVO
    ↓
Feature 6 (Audit log)     ✅ HOTOVO
```

## Pravidla

1. Každá feature končí zeleným CI
2. Optional dependencies (Excel, PDF, Chart.js) — nikdy hard dependency
3. Používat existující infrastrukturu (ActionPipeline, Events, Modals, QueryPlanner)
4. Každá nová třída má unit testy
5. Blade views: minimální JS, Alpine.js pro interaktivitu
