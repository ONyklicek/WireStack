# Changelog

All notable changes to the Wire ecosystem will be documented in this file.

## [1.5.1]

### Changed
- **Reusable view markup is owned in PHP, not duplicated in Blade.** A multi-threaded audit of the "Htmlable render" convention (reusable UI markup lives behind PHP htmlable getters / canonical resolvers; Blade only consumes) consolidated the remaining drift. The loading-spinner SVG, previously hand-inlined in four places (table column partial, `header-action`, `action-modal` ×2, forms `file-upload`), now has a single canonical owner `wire-core::partials.spinner` (`$class`, optional `$wireTarget`) that every site delegates to. The sortable drag handle is no longer a JS template string — its markup moved to `wire-sortable::partials.drag-handle`, rendered by the new `Table::getDragHandleHtml()` macro and injected into the Alpine component as config. The infolist column-span `match` (duplicated across six entry views) is now `HasColumnSpan::getColumnSpanClass($default)`; `StackedColumn` builds its stacked lines via `getLinesHtml(): Htmlable` (escaped) instead of a Blade closure; `BarChartWidget` exposes `getCardRadiusClass()` / `getPartialName()`; and the table's responsive stacked-layout classes resolve through `Table::getStackedTableHiddenClass()` / `getStackedCardsVisibleClass()`. `HeaderAction::getBadgeHtml()` now returns `Htmlable`, matching `ActionGroup`.
- **Forms colour palettes and modal/stat surfaces delegate to canonical resolvers.** The `Alert` and `Rating` Blade `match` maps and the unsafe `text-{$color}-…` string interpolation in `stats-overview` are gone. `Alert::getColorClasses()` delegates to a new `HasColor::getAlertColorClasses()` (soft banner surface — `bg-*-50` / `border-*-200` / `text-*-800`); `Rating::getColorClasses()` keeps its brighter `-500/-400` star scale as an owner-local `rating-active` surface (values unchanged). The action-modal submit button (two formerly inline `match` blocks, slide-over + centered) now resolves through `HasColor::getModalSubmitButtonClasses()`, surfaced via `HasModal::getModalConfig()['submitButtonClasses']`, so both footers stay in sync. `Stat` gains `getValueColorClass()` / `getDescriptionColorClass()` / `getChartColorClass()` and `TextEntry` gains `getTextColorClass()` / `getBadgeColorClass()`, all routed through the canonical (JIT-safe, allow-list) `HasColor` palette.

## [1.5.0]

### Added
- **Enum-sourced options auto-add an `in:` validation rule.** When a single-value `Select` or `Radio` takes its options from an enum class, the field is automatically constrained to those keys (`Rule::in([...])`) — a tampered or stale submission outside the enum is rejected without the owner restating the rule. The logic lives on `WireForms\Concerns\HasOptions` behind the new `WireForms\Contracts\ProvidesImplicitValidationRules`, and `HasFormValidation` merges it in. It is skipped when options are a plain array (the set may be dynamic), for multi-value fields (array state can't be a single `in:`), and when the owner already declared an `in:` / `Rule::in()` / `Rule::enum()` rule.
- **`TextInput::datalist()` accepts an enum class.** Passing an enum class uses its case labels as the input's suggestion list, mirroring the option-field shorthand.
- **Enum classes can populate field options directly (Filament-style).** Any option-based surface now accepts a backed/unit enum class-string instead of a `[value => label]` array — `->options(Status::class)` — and expands it to a map keyed by the backing value (or case name for unit enums). Enums implementing `Foundation\Contracts\Enum\HasLabel` render `getLabel()` as the option label; enums exposing a `label()` method use that; everything else falls back to a headline of the case name. The expansion is owned by a single canonical method, `EnumResolver::options()` (plus `isEnumClass()` and the pass-through `normalizeOptions()`), and every surface delegates to it: form `Select`, `Radio`, and `CheckboxList` (via the new shared `WireForms\Concerns\HasOptions` trait), table `SelectColumn`, and table `SelectFilter` (whose former package-local enum `match` map is removed in favour of the shared owner). The generic column-level APIs accept it too — `Column::editable(type: 'select', options: Status::class)`, `Column::filterable(type: 'select', options: Status::class)`, and `Column::filterAsSelect(Status::class)`. Closures returning an enum class are expanded too; arrays pass through untouched.

### Changed
- **A label-less enum now displays a headline of its case name everywhere, not its backing value.** `EnumResolver::label()` (and therefore every display surface — table columns, infolist entries, exports, grouping, filter chips) previously fell back to the raw scalar for an enum that does not implement `Enum\HasLabel` (`Status::Active = 'active'` rendered `active`). It now resolves through the same canonical order as option labels — `HasLabel::getLabel()`, then a `label()` method, then `Str::headline($case->name)` (`Active`, `InReview` → `In Review`) — so the same enum reads identically on a cell, an export, and a `<select>` option. Enums implementing `HasLabel` and non-enum values are unaffected; `scalar()` (used for keys, copy values, comparisons, serialization) still returns the backing value.

### Fixed
- **`Action::visible(Closure)` is honoured again.** The Actions `HasVisibility` trait implemented `visible()` as `hidden(! $visible)`; for a closure, `! $closure` coerced the object to `true`, so the callback was discarded and `hidden(false)` made the action **always visible** — silently bypassing per-record visibility logic such as `->visible(fn ($record) => $record->canEdit())`. The trait now branches on the closure and stores a negating wrapper, matching the Foundation `HasVisibility` behaviour.
- **Repeater state path keeps its form prefix.** A `Repeater` nested in a form (or any layout) never had its own state path set during `prepareChildren()`, so `getStatePath()` dropped the prefix (returned `contacts` instead of `data.contacts`) and the add/remove handlers and `wire:model` bindings wrote to the wrong location. `LayoutComponent` now records a resolved absolute path during preparation (idempotent — re-running never double-applies the prefix) and `Repeater::getStatePath()` reads it.
- **Repeater items are validated at the correct paths.** Repeater child fields were flattened and validated at the template path (`data.label`) instead of the per-item path, so per-item rules never matched real data, and the repeater's own `min/max-items` / `array` rules were never collected (the resolver called `getValidationRules()` while `Repeater` defined `getRules()`). The validation resolver now treats repeaters as opaque during flattening and emits container rules at the repeater path plus per-item **wildcard** rules (`data.contacts.*.label`); `required()` on a repeater adds `required` + `array`.
- **`<x-wire-actions::button>` no longer fatals for header/bulk actions.** The component's fallback (taken when an action has no `getRenderData()`, i.e. `HeaderAction` / `BulkAction`) called the `protected` `resolveSolidColorClasses()` / `resolveButtonSizeClasses()` with missing arguments, throwing `ArgumentCountError`. The fallback now uses a single public `BaseAction::getButtonClasses()` that assembles base + size + color via the canonical resolver.
- **Relation (dotted) filter names work from the UI.** A filter named `customer.status` is bound nested by `wire:model` (`filters.customer.status.value`) but was read back with a flat key, so the value never applied and the chip's remove button couldn't find it. Filter init, the query service, indicator chips, sub-row filters, and `removeTableFilter()` now read/write through `data_get` / `Arr::set` / `Arr::forget` (pruning empty parent nests), so dotted names round-trip end to end.
- **Multi-value field state is typed as an array.** `Select` (multiple) and `CheckboxList` inherited `getStateType() === 'string'`, so a stray scalar was left as a string the bound control couldn't use. Both now report `'array'` (Select only when `->multiple()`), matching `Tags` / `KeyValue` / `FileUpload`.
- **`DateTimePicker` stores a serializable date string, not a Carbon.** The field hydrated its state to a `Carbon` instance inside the Livewire array, which is fragile to serialize and unparseable by the Alpine/native picker. A new format-qualified hydrator type (`date:<format>`) parses any date-like input and returns a mode-appropriate **string** (`Y-m-d`, `Y-m`, `H:i[:s]`, `Y-m-d\TH:i[:s]`); the core `StateHydrator` owns the generic formatting while the field owns its format.
- **Column text filters ignore non-scalar values.** `applyTextFilter()` built `"%$value%"` without a type guard, so a crafted/stale array column-filter value raised an "Array to string conversion" warning and a garbage `LIKE`. Non-scalar values are now ignored.
- **Cleared range filters no longer count as active.** The toolbar derived active filters with a bare `array_filter($tableFilters)`, so a range filter typed then emptied (`['min' => '', 'max' => '']` — a truthy array) stayed "active", contradicting the (correctly empty) indicator chips. The active check now recurses and rejects all-empty values.
- **`Toggle` colours and icons render.** `onColor` / `offColor` / `onIcon` / `offIcon` were fully wired in PHP but the view hardcoded `bg-primary-600` / `bg-gray-200` and drew no icons. The toggle now resolves its track colours via `getOnColorClasses()` / `getOffColorClasses()` (purge-safe, toggle-specific) and renders the on/off icons.
- **Sortable column reordering supports all columns and non-integer user keys.** Non-sortable column `<th>`s emitted no `data-column`, so they couldn't be drag-reordered and were silently dropped from the saved order; every column header now carries `data-column`. The per-user column-order persistence typed the user id as `?int`, throwing on UUID/ULID auth keys — the model, concern, and migration now accept `int|string` (migration column type configurable via `wire-sortable.user_key_type`: `id` / `uuid` / `ulid`).
- **`SortableTable::alwaysReorderable()` actually enables reordering.** The method was missing on the subclass and fell through to a `Table::macro` writing a different property, so `isReorderable()` stayed `false` and reorder mode never engaged. It is now a real method (implying `reorderable()`), and `getOrderColumn()` reads the `wire-sortable.order_column` config default that was previously ignored.

### Changed
- **Bulk-action buttons delegate to the canonical colour owner.** `actions/bulk-button.blade.php` re-encoded the palette with an inline `match` map whose outlined branch only handled `primary` / `danger` (rendering `success` / `warning` / `info` grey); it now calls `getButtonClasses()`, so every hue and the solid/outlined split come from the shared `HasColor` resolver.
- **Toggle track colours delegate to the canonical palette.** The forms `Toggle` carried two package-local `match` maps for its on/off track fills (drifting from the shared palette, e.g. `info → cyan-600` instead of `cyan-500`); both now resolve through Foundation `HasColor` — `getOnColorClasses()` via the existing `getSolidBgClass()` and `getOffColorClasses()` via a new canonical soft-fill resolver `HasColor::getSoftBgClass()` (muted tinted background, same hue vocabulary, neutral-gray default). The table `ToggleColumn` is wired to the same owners: it already used `getSolidBgClass()` for the "on" track, and now honours `offColor()` through `getSoftBgClass()` instead of hardcoding `bg-gray-200` in the view (gray default keeps existing toggles visually unchanged).
- **Dead filter views removed.** `tables/filters/{date,number-range,text}.blade.php` were never resolved (filters render through `form-field`, `select`, or `ternary`) and one carried an inconsistent `wire:model` shape; they are deleted to prevent drift.

### Security
- **Repeater relationship saves no longer mass-assign a client-supplied primary key.** `RelationshipSaveHandler` passed the full item array (including its `id`) straight to `->create()` / `->update()`, letting client state set the primary key on create. The key is now stripped from the payload before persistence (it is still used to match existing rows).

## [1.4.2]

### Fixed
- **Table filters no longer fatal with `htmlspecialchars(): … array given`.** Single-value filter views echoed/cast their state value directly into a string context, so an array value reaching a single-value filter — via an array `default()`, a multiple/single-select mismatch, or stale URL/session state — raised a `TypeError` and took down the whole table render. The filter views are now defensive: `filters/select.blade.php` normalizes the current value into a list of comparable strings used uniformly for single and multiple selects (dropping the `(string) $currentValue` cast that blew up on arrays), `filters/form-field.blade.php`, `filters/text.blade.php`, and `filters/date.blade.php` guard their input value with `is_scalar()` before echoing, and the column-level `columns/partials/filter-select.blade.php` guards the value before its option comparison.

## [1.4.1]

### Fixed
- **`ActionGroup` dividers now render in every dropdown.** The generic `<x-wire-actions::group>` view rendered a bare `{{ $items }}` slot that `GroupComponent` never populated, so the dropdown came out empty — no items and no `Action::divider()` / `divided()` separators. Dropdown body rendering is now owned by `ActionGroup::getDropdownItemsHtml($record)` (sibling of `getBadgeHtml()`): it resolves auto- and manual dividers and renders each item through the canonical dropdown-item partial. Both the core group view and the table row-action group view consume the single method, and the group correctly collapses to one inline button when only a single executable action is visible.

### Changed
- **Action group rendering unified into one canonical view.** The duplicated core (`actions/group.blade.php`) and table (`tables/actions/action-group.blade.php`) dropdown markup is consolidated: the table view now `@include`s the core view, and item/divider/single-action rendering live on `ActionGroup` as htmlable getters (`getDropdownItemsHtml`, `getSingleActionHtml`, `countExecutableActions`) plus `Action::renderForDropdown()` / `Action::renderDivider()`. The dead, never-included `wire-core::actions.dropdown-item` partial was removed. Dropdown design polished to match the rest of the UI (`rounded-lg`, `ring-black/5`, `x-cloak`).

- **Table search no longer fails on MySQL / MariaDB.** A 1.4.0 hardening attempt wrapped every `LIKE`/`ILIKE` predicate with an explicit `ESCAPE '\'` clause to escape user-typed `%`/`_` wildcards. On MySQL/MariaDB the backslash is itself a string-literal escape, so `'\'` is an unterminated string and the query died with `SQLSTATE[42000] … syntax error … near '\')'` — any table search was unusable. The escaping (and the `EscapesLikeTerm` concern) is reverted; the search strategies are back to the proven `LIKE ?` / `ILIKE ?` form with the term bound as a parameter. (The wildcard-escaping behaviour will be reintroduced per-dialect, since `ESCAPE '\'` is not portable: MySQL/PostgreSQL already default to a backslash escape, only SQLite needs the explicit clause.)

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
