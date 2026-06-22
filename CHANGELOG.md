# Changelog

All notable changes to the Wire ecosystem will be documented in this file.

## [1.4.0]

### Added
- **PHP enum casts work everywhere a value is displayed.** When an Eloquent model casts an attribute to a PHP enum (`$casts = ['status' => Status::class]`), the raw enum instance now flows safely through every surface instead of fataling on `(string) $enum`. A single canonical owner, `Foundation\Support\EnumResolver`, normalizes any value — `scalar()` (backed → value, unit → case name), `label()` (human text), `color()` and `icon()` — and every downstream surface delegates to it rather than re-encoding `(string) $enum` or local `match` maps: table columns (`TextColumn`, `BadgeColumn`, `IconColumn`, `SelectColumn`, `TextInputColumn`, `PollColumn`), grouping/summary/filter indicators, all three exporters (CSV/Excel/PDF, via `ResolvesExportValue`), infolist entries (`TextEntry`, `IconEntry`, …), and form state (`StateManager::fill()` reduces enum-cast values to their scalar form so the wire-bound state stays safe and matches `<option>` values). `StateSerializer` now delegates its enum branch to the same owner. Non-enum values pass through untouched.
- **Opt-in enum contracts `Foundation\Contracts\Enum\{HasLabel, HasColor, HasIcon}`.** An enum used as a cast may implement these to carry its own display label, palette color, and icon (Nova/Filament-style). `BadgeColumn`, `IconColumn`, and `IconEntry` auto-resolve color and icon straight from the enum case when no explicit `colors()`/`icons()` map matches; display surfaces render `getLabel()` when present, falling back to the backing value / case name. Distinct from the builder-facing `Foundation\Contracts\HasLabel`/`HasIcon` (which carry fluent setters for components). Exports and group-by labels use the display label, matching what is shown on screen.
- **`EnumResolver::display()` – canonical display normalizer.** One entry point that turns any owner-provided value into a `(string)`-safe form: enum → label, array / JSON-cast attribute → compact JSON, scalars untouched. Used by the base column formatter, the shared `FormatsState` concern, infolist entries, and the exporters.

### Changed
- **`StateSerializer` enum serialization delegates to `EnumResolver`.** The wire-transfer serializer no longer carries its own `BackedEnum`/`UnitEnum` branch; it reuses the canonical owner so serialized and displayed enum values stay in lockstep. Behavior is unchanged.

### Fixed
- **Array / JSON-cast attributes no longer render as the literal `Array`.** A column, infolist entry, or export over an `array`/`json`-cast attribute previously hit `(string) $array`, raising an "Array to string conversion" warning and printing `Array`; it now renders compact JSON via `EnumResolver::display()`.
- **`NULLS FIRST/LAST` sorts no longer double-prefix the keyword.** A `SortClause` built with `'NULLS LAST'` combined with the `ApplySorting` pipe (which already prepends `NULLS`) would have emitted the invalid `NULLS NULLS LAST`. `SortClause` now stores the bare `FIRST`/`LAST` keyword (accepting either form on input).

### Security
- **Sort direction and `NULLS` position are normalized before reaching raw SQL.** `SortClause` now collapses `direction` to an `asc`/`desc` allow-list and `nullsPosition` to `FIRST`/`LAST`/null in its constructor — the single owner of a sort clause. Previously these flowed unnormalized into `orderByRaw` for SQL-expression and `NULLS` sorts; the URL query-string path already validated direction, but this hardens the sink itself (and stops a tampered direction from throwing on plain `orderBy`).
- **Search terms escape LIKE wildcards.** The `%`, `_` and `\` metacharacters in a user's search term are now escaped (shared `EscapesLikeTerm` concern) and every strategy pairs `LIKE`/`ILIKE` with an explicit `ESCAPE '\'` (consistent across MySQL, PostgreSQL and SQLite). Terms stay parameter-bound as before, so this closes a LIKE-wildcard-injection avenue (e.g. a `%%%%` term forcing a full scan), not a SQL-injection one.

##[1.3.0]

### Added
- **`BarChartWidget` – pure-CSS bar chart widget (no JS/Chart.js).** A dependency-free dashboard widget rendered entirely with Tailwind utilities, distinct from the existing JS `ChartWidget` (both coexist). Three visual modes selected from `type()` + `variant()`: vertical *finance* bars (value above, light max-height track, `MM / YYYY` caption below), vertical *system* bars (icon + label + percentage above a 0–100 % track with optional grid lines via `showGrid()`), and horizontal *system* progress bars (label left, value right). Fluent API mirrors the other widgets: `heading()`, `description()`, `type()` (vertical\|horizontal), `variant()` (finance\|system\|default), `items([ChartItem, …])`, `showGrid()`, `showMenu()`, `maxValue()`, `height()`, `rounded()`, `lazy()`. Each `ChartItem::make($label)` carries `value()`, `formattedValue()`, `color()`, `percentage(0–100)`, and `icon()`. Fill size is the only dynamic style, passed as a CSS variable (`style="--value: 72%"`) consumed by Tailwind arbitrary values (`h-[var(--value)]` / `w-[var(--value)]`). Inputs are validated (`type`/`variant` allow-lists throw on invalid values; per-item percentage clamped to 0–100), and colors map through a safe allow-list — no arbitrary class injection.
- **`HasColor::getGradientFillClasses()` / `getFillTextClasses()`** – canonical resolvers for bar/progress fills and their matching accent text, using literal chart hues (`blue` → `blue-500/600`, `green` → `green-500/600`, `gray` → `slate-400/500`).
- **Infolists – read-only, schema-driven display of a single record.** A new `wire-core` subsystem (alongside widgets) and the display counterpart of a form: `Infolist::make()->record($model)->schema([...])`. Binds an Eloquent model or a plain array, resolves each entry's value by name with dot notation (`data_get`), and reuses the canonical schema layout (`Section`, `Grid`, `Fieldset`) and Foundation concerns (label, icon, color, size, visibility, column span). Full entry set: `TextEntry` (money/numeric/date/dateTime/since via the shared `FormatsState` concern, plus `badge()`, `copyable()`, `limit()`, `weight()`, `prose()`, `listWithLineBreaks()`/`bulleted()`), `IconEntry` (`boolean()` and state→icon `icons()`/`colors()` maps), `ImageEntry` (`disk()`, `imageSize()`, `circular()`, `stacked()`, `defaultImageUrl()`), `ColorEntry`, `KeyValueEntry`, and `RepeatableEntry` (nested schema per relation/array item). `Infolist` is `Htmlable`, so it renders with a plain Blade echo. Host trait: `Infolists\Concerns\WithInfolists`.
- **`Action::infolist()` – open a record in a read-only modal.** `HasModal` gains `infolist(array|Infolist|Closure)` alongside `form()`: the action's record is bound automatically, the modal is not a confirmation, and it renders only a close button. `ViewAction::make()->slideOver()->infolist([...])` shows a record without a submit action; the table runtime resolves it lazily (stateless) and the action-modal partial renders it in both centered and slide-over variants.
- **`Foundation\Concerns\FormatsState`** – canonical numeric/money/date state formatting extracted from `TextColumn`, now shared by table columns **and** infolist text entries so a value formats identically wherever it is displayed.
- **`Foundation\Schema\{Section, Grid, Fieldset}`** – the schema layout is promoted into `wire-core` so forms and infolists share one owner. The `wire-forms` layout classes become thin subclasses (keeping their form-specific Blade chrome) for full backward compatibility; they are deprecated for removal in v2.0 in favor of the core schema layout.

##[1.2.0]

### Added
- **Poll change detection** – `Table::pollChangeDetection()` skips the full poll re-render (query + summaries + DOM morph) when a cheap checksum of the filtered data (`COUNT(*)` + `MAX(updated_at)` in one query) is unchanged since the last poll. Pass a closure (`->pollChangeDetection(fn ($query) => …)`) when parent timestamps don't capture relevant changes (e.g. child-table rollups). Opt-in; default behavior is unchanged.
- **Sub-row grand totals in the main footer** – a `query`-scoped summary on a sub-row column (`->summarizeSum('Celkem')`) renders the total of all children across all parents in the main table footer, computed in SQL over the child table. No parent rollup column needed; honours `Filter::subRows()`, `subRowQuery()`, the interactive sub-row filter bar, and the footer scope toggle (all / page / selection parents).
- **Row grouping with subtotals** – `Table::groupBy('customer')` keeps groups contiguous (group order is prepended to the active sort), renders a header row per group (`groupLabel(string|Closure)`), and adds per-group subtotal rows for every column with a summary (`groupSummaries(false)` to disable); the grand-total footer stays.
- **Summaries in exports** – CSV, Excel, and PDF exports append the `query`-scoped column summaries (the footer grand totals) after the data rows as `Label: value` cells; opt out with `TableExport::withSummaries(false)`. Custom PDF views receive a new `summaryRows` variable.
- **`SummaryType` enum** – summary aggregate types are a backed enum (`NyonCode\WireTable\Columns\SummaryType`): `->summarize(SummaryType::Median)` with full IDE completion. The enum owns the per-type semantics (default labels, count formatting, SQL portability, empty-set results). Strings stay accepted and are normalized to the enum; unknown type strings now throw an `InvalidArgumentException` instead of silently rendering an empty footer value.

### Changed
- **Rollup grand totals compute in SQL.** Summarizing a rollup column (`->sums()` + `->summarizeSum()`) previously loaded every filtered parent row into memory and summed floats in PHP; the rollup alias is now aggregated in SQL over a derived table — no row loading, database decimal precision.

### Performance
- **Row selection is client-side — a checkbox click no longer costs a server roundtrip.** Selection state lives in Alpine, entangled (deferred) with `tableState.selection.records`: checkboxes, the select-all header state, row highlight, and the selection bar (count, plural label, deselect) all react instantly without re-rendering the table. The server state syncs with the next request (bulk actions always see the current selection); tables with a summary footer auto-commit selection changes debounced (~350 ms) so selection-scope totals and the scope toggle stay correct. Server methods (`toggleRecordSelection()`, `selectAllRecords()`, …) remain for backwards compatibility.
- **`subRowsLimit()` no longer loads full child sets into memory.** The eager load now fetches only `limit` rows per parent (native per-parent eager-load limit, window function) plus one `loadCount` query for exact "show more" totals; parents flagged show-all still load their full set. Previously every expanded/flattened parent loaded *all* children just so the limit could be applied in memory. On Laravel 10 (no per-parent eager-load limit) the loader transparently falls back to the previous full-load + in-memory limit, so behaviour is identical there.
- **Footer summaries batch into a single SQL query.** Every `query`-scoped summary previously ran its own aggregate query on each Livewire render (a footer with 5 summaries = 5+ queries per interaction). All SQL-native summaries now compute in one aggregate query (plus one for rollup columns over the derived table); sub-row grand totals batch the same way over the child query. Closure summaries, statistical types (median, stddev, …), and `when()`-restricted summaries keep the per-summary fallback with identical results.
- **Package views no longer go through the deprecated magic properties.** `index.blade.php` and the sub-row partials read `$component->tableState` directly (precomputed once per render) instead of `$component->tableFilters` & co., which rebuilt the legacy property map on every access — per row, per column. The map itself is now memoized, so user code on the legacy properties is cheaper too.
- **Row loops reuse the precomputed visible-column set.** Header, filter row, body cells, and sub-row tables no longer re-evaluate `canView()` (which may hit the Gate) and `isColumnVisible()` per cell — visibility resolves once per render (once per parent for sub-rows).
- **Selected records are memoized per request** (selection-scope summaries, grand totals, and bulk modals previously each ran the same query) and **group subtotals partition the page once** instead of re-filtering all page records per group.
- **`StateContainer::get()` traverses the path once** instead of the previous `has()` + `resolve()` double walk — it runs hundreds of times per table render.
- Capability auto-resolution in `TableQueryService` is skipped when the metadata registry holds no column/accessor metadata — removes a no-op per-column walk from every query build.

### Fixed
- **Query cache no longer serves page 1 for every page.** Pagination is applied inside the cache callback, so the page number was missing from the cache key (`cacheQuery()`) — all pages shared one entry for the TTL. The current page (or cursor) is now part of the key, including with a custom `cacheQuery(key: …)`.
- **`subRowsLimit()` on Laravel 10 showed too few children.** The per-parent eager-load limit (a window function) only exists on Laravel 11+; on Laravel 10 it applied a single global `LIMIT` across all parents, so the first parent received fewer than `limit` rows. The loader now detects the framework capability and falls back to full-load + in-memory limiting on Laravel 10.


## [1.1.0]

### Added
- **Sub-row scoped table filters** – `Filter::subRows()` targets the child records of `Table::subRows()`: parents reduce to those with a matching child (`whereHas`), expanded panels show only matching children, and rollup aggregates (`->sums()`, `->counts()`, …) plus their footer grand totals count only the matching children.
- **`DateFilter::month()`** – Month/Year filtering (`whereYear` + `whereMonth`) rendered as a native month picker; combines with `subRows()` for filtering by the month of child records.
- **`DateTimePicker::asMonth()`** (wire-forms) – month mode rendering a native `<input type="month">`.
- **URL query-string persistence** – `Table::queryString()` keeps search, sort, per-page, and filter state in the URL (shareable/bookmarkable table views). Incoming values are validated against the table config; an optional prefix (`queryString('orders_')`) avoids collisions between multiple tables on one page.
- **Filter indicator chips** – active filters render as removable chips under the toolbar with per-type labels (option labels, range bounds, translated month names); customizable via `Filter::indicator(string|Closure)`, removable via the new `removeTableFilter()` action.

### Fixed
- Multiple-select filter (`SelectFilter::multiple()`) no longer crashes the select view when an array value is active, and renders the selected options correctly.
- **Action modal forms no longer lose typed text.** Action forms forced `wire:model.live` on every field, so each keystroke pause triggered a full component re-render whose DOM morph raced further typing and erased it. Fields now default to deferred `wire:model` (values are sent with the submit call), eliminating both the text loss and the per-keystroke server roundtrip + table re-render. Fields that drive reactive behavior opt in per field via `->live()`.
- **Rollup columns export their values.** Exporters resolved cell values only by column name, so a rollup column named differently from its aggregate attribute (e.g. `items_total` vs `items_sum_line_total`) exported empty cells; exporters now read the computed aggregate attribute.

## [1.0.0] – 2026-06-11

### Added
- **`wire-sortable` package** – drag & drop row sorting as a table plugin (`SortablePlugin`, `SortableTable`).
- **Plugin system** in `wire-core` (`PluginManager`) with string and typed hooks (e.g. `form.saving`) and plugin toolbar widgets.
- **Widget system** with toolbar widgets and workbench previews.
- **Audit subsystem** (`HasAuditable`) and **authorization trait** (`HasAuthorization`).
- **Table exports** with optional exporter drivers.
- **Bundled Heroicons** solid and outline sets (`outline:` prefix) in `DefaultIconSet`; `IconManager` is now resolved via DI.
- **Table summaries & subrows** – new summary types, numeric formatting with prefix/suffix, footer scope toggle, sortable subrows with actions and eager loading (N+1 fix).
- **Row polling** (`poll()`) and stable table identifiers for multi-table pages.
- **Macroable support** and `ActionMacros` cross-package integration.
- **Relation aggregate columns** (rollup attributes such as `items_sum_total`).
- **docs-site** with per-field live previews and six new guides: custom fields, testing, theming, cookbook, troubleshooting, upgrade.

### Changed
- **Design-system consolidation** – canonical color/size vocabulary now lives in `Foundation/Concerns` (`HasColor`, `HasSize`, `HasIcon`); table columns and forms delegate to it, inline SVGs replaced by `<x-wire::icon>`.
- Nine custom-UI table columns migrated from inline `renderCell()` to Blade partials via `HasView::renderView()`.
- Forms UI refreshed.

### Fixed
- Livewire `morph.updating` bug.
- `poll()` no longer accepts invalid polling interval values.
- `ImageColumn::size()` LSP violation that caused a fatal error.
- `ColorPicker::swatches()` `TypeError` when passing a `Closure` (missing import).
- Latent fatal in `HasColor` consumers – modal view components now define `getColor()`.

## [0.1.0] – 2026-04-18

### Added
- **Monorepo structure** with three packages: `wire-core`, `wire-forms`, `wire-table`.
- `wire-core` package with shared traits, actions, modals, notifications, icons, colors.
- `wire-forms` package with 20+ field types, layout components, standalone form support.
- GitHub Actions CI (tests matrix PHP 8.2/8.3/8.4 × Laravel 10/11/12, PHPStan, Pint).
- Monorepo split workflow for per-package releases.
- ADR documentation for architectural decisions.

### Breaking Changes

#### Namespace Changes

The following classes have moved to new namespaces. Update your `use` statements:

| Before | After |
|--------|-------|
| `NyonCode\WireTable\Actions\Action` | `NyonCode\WireCore\Actions\Action` |
| `NyonCode\WireTable\Actions\BulkAction` | `NyonCode\WireCore\Actions\BulkAction` |
| `NyonCode\WireTable\Actions\HeaderAction` | `NyonCode\WireCore\Actions\HeaderAction` |
| `NyonCode\WireTable\Actions\ActionGroup` | `NyonCode\WireCore\Actions\ActionGroup` |
| `NyonCode\WireTable\Actions\ActionHalt` | `NyonCode\WireCore\Actions\ActionHalt` |
| `NyonCode\WireTable\Actions\DeleteAction` | `NyonCode\WireCore\Actions\DeleteAction` |
| `NyonCode\WireTable\Actions\DeleteBulkAction` | `NyonCode\WireCore\Actions\DeleteBulkAction` |
| `NyonCode\WireTable\Actions\EditAction` | `NyonCode\WireCore\Actions\EditAction` |
| `NyonCode\WireTable\Actions\ViewAction` | `NyonCode\WireCore\Actions\ViewAction` |
| `NyonCode\WireTable\Actions\ModalStep` | `NyonCode\WireCore\Actions\ModalStep` |
| `NyonCode\WireTable\Actions\ModalFooterAction` | `NyonCode\WireCore\Actions\ModalFooterAction` |
| `NyonCode\WireTable\Notifications\TableNotification` | `NyonCode\WireCore\Notifications\TableNotification` |
| `NyonCode\WireTable\Notifications\TableNotificationManager` | `NyonCode\WireCore\Notifications\TableNotificationManager` |
| `NyonCode\WireTable\Notifications\Contracts\NotificationDriver` | `NyonCode\WireCore\Notifications\Contracts\NotificationDriver` |
| `NyonCode\WireTable\Notifications\Drivers\SessionDriver` | `NyonCode\WireCore\Notifications\Drivers\SessionDriver` |
| `NyonCode\WireTable\Notifications\Drivers\LivewireEventDriver` | `NyonCode\WireCore\Notifications\Drivers\LivewireEventDriver` |
| `NyonCode\WireTable\Notifications\Drivers\FlasherDriver` | `NyonCode\WireCore\Notifications\Drivers\FlasherDriver` |
| `NyonCode\WireTable\Forms\Fields\Field` | `NyonCode\WireForms\Components\Field` |
| `NyonCode\WireTable\Forms\Fields\TextInput` | `NyonCode\WireForms\Components\TextInput` |
| `NyonCode\WireTable\Forms\Fields\Textarea` | `NyonCode\WireForms\Components\Textarea` |
| `NyonCode\WireTable\Forms\Fields\Select` | `NyonCode\WireForms\Components\Select` |
| `NyonCode\WireTable\Forms\Fields\Checkbox` | `NyonCode\WireForms\Components\Checkbox` |
| `NyonCode\WireTable\Forms\Fields\CheckboxList` | `NyonCode\WireForms\Components\CheckboxList` |
| `NyonCode\WireTable\Forms\Fields\Radio` | `NyonCode\WireForms\Components\Radio` |
| `NyonCode\WireTable\Forms\Fields\Toggle` | `NyonCode\WireForms\Components\Toggle` |
| `NyonCode\WireTable\Forms\Fields\DatePicker` | `NyonCode\WireForms\Components\DateTimePicker` (use `->asDate()`) |
| `NyonCode\WireTable\Forms\Fields\DateTimePicker` | `NyonCode\WireForms\Components\DateTimePicker` |
| `NyonCode\WireTable\Forms\Fields\TimePicker` | `NyonCode\WireForms\Components\DateTimePicker` (use `->asTime()`) |
| `NyonCode\WireTable\Forms\Fields\ColorPicker` | `NyonCode\WireForms\Components\ColorPicker` |
| `NyonCode\WireTable\Forms\Fields\FileUpload` | `NyonCode\WireForms\Components\FileUpload` |
| `NyonCode\WireTable\Forms\Fields\RichEditor` | `NyonCode\WireForms\Components\RichEditor` |
| `NyonCode\WireTable\Forms\Fields\Hidden` | `NyonCode\WireForms\Components\Hidden` |
| `NyonCode\WireTable\Forms\Fields\Section` | `NyonCode\WireForms\Components\Layout\Section` |
| `NyonCode\WireTable\Forms\Fields\Fieldset` | `NyonCode\WireForms\Components\Layout\Fieldset` |
| `NyonCode\WireTable\Forms\Fields\Grid` | `NyonCode\WireForms\Components\Layout\Grid` |
| `NyonCode\WireTable\Forms\Fields\Placeholder` | `NyonCode\WireForms\Components\Display\Placeholder` |
| `NyonCode\WireTable\Forms\Fields\Alert` | `NyonCode\WireForms\Components\Display\Alert` |
| `NyonCode\WireTable\Forms\Fields\Html` | `NyonCode\WireForms\Components\Display\Html` |
| `NyonCode\WireTable\Forms\Fields\ViewField` | `NyonCode\WireForms\Components\Display\ViewField` |

#### Composer Changes
- `nyoncode/wire-table` now requires `nyoncode/wire-core` and `nyoncode/wire-forms`.
- `nyoncode/engine-core` dependency removed (absorbed into `wire-core`).

#### Config Changes
- Notification driver config keys in `wire-table.php` now reference `NyonCode\WireCore\Notifications\Drivers\*`.

### Migration Guide

1. Update `composer.json`:
   ```bash
   composer require nyoncode/wire-table:^0.1
   ```
   This will automatically install `wire-core` and `wire-forms`.

2. Find and replace namespaces in your codebase using the table above. Most common replacements:
   ```
   NyonCode\WireTable\Actions\ → NyonCode\WireCore\Actions\
   NyonCode\WireTable\Forms\Fields\ → NyonCode\WireForms\Components\
   NyonCode\WireTable\Notifications\ → NyonCode\WireCore\Notifications\
   ```

3. Layout components moved to sub-namespace:
   ```
   NyonCode\WireForms\Components\Layout\Section
   NyonCode\WireForms\Components\Layout\Grid
   NyonCode\WireForms\Components\Layout\Fieldset
   ```

4. Display components moved to sub-namespace:
   ```
   NyonCode\WireForms\Components\Display\Alert
   NyonCode\WireForms\Components\Display\Html
   NyonCode\WireForms\Components\Display\Placeholder
   NyonCode\WireForms\Components\Display\ViewField
   ```

5. DatePicker/TimePicker unified into `DateTimePicker`:
   ```php
   // Before:
   DatePicker::make('birth_date')
   TimePicker::make('start_time')

   // After:
   DateTimePicker::make('birth_date')->asDate()
   DateTimePicker::make('start_time')->asTime()
   ```

6. Blade render syntax changed:
   ```blade
   {{-- Before --}}
   {!! $this->getTable() !!}

   {{-- After --}}
   {{ $this->table }}
   ```

7. If you customized notification driver config, update class references to `NyonCode\WireCore\Notifications\Drivers\*`.
