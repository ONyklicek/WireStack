## wire-table

Build a data table inside a Livewire component using the `WithTable` trait and a `table()` method:

    use NyonCode\WireTable\Concerns\WithTable;
    use NyonCode\WireTable\Table;

    class Users extends Component
    {
        use WithTable;

        public function table(Table $table): Table
        {
            return $table
                ->query(User::query())
                ->columns([
                    TextColumn::make('name')->sortable()->searchable(),
                    BadgeColumn::make('status')->colorUsing(fn ($state) => $state === 'active' ? 'success' : 'gray'),
                    BooleanColumn::make('is_admin'),
                ])
                ->filters([
                    SelectFilter::make('status')->options(Status::class),
                ])
                ->actions([EditAction::make(), DeleteAction::make()])
                ->headerActions([HeaderAction::make('create')])
                ->bulkActions([DeleteBulkAction::make()]);
        }
    }

### Columns

`TextColumn`, `BadgeColumn`, `BooleanColumn`, `IconColumn`, `ImageColumn`, `ButtonColumn`, `ToggleColumn`,
`PollColumn`, `SelectColumn`, `TextInputColumn`, `SplitColumn`, `StackedColumn`.

`BadgeColumn` (and `IconColumn`) color/icon resolution — pick by intent:
- one fixed color for every row: `->color('success')` (takes `string|Color|null`, never a Closure);
- a static state → color map: `->colors(['active' => 'success', 'draft' => 'gray'])`;
- a value computed per row: `->colorUsing(fn ($state) => …)` (the Closure receives the cell state);
- nothing at all when the state is an enum implementing `HasColor` — the color resolves automatically.
The same four-way choice applies to icons: `->icon()`, `->icons([...])`, `->iconUsing(fn ($state) => …)`, or an enum with `HasIcon`.

Dot-notation relation columns (`TextColumn::make('company.name')`) resolve by mechanism, not one JOIN for
everything. **Display** always eager-loads the relation (`with()`) — every relation type, including nested chains
like `company.country.name` — so a shown relation value never triggers an N+1 and never depends on a join.
**Sorting** by a singular relation (`belongsTo`/`hasOne`/`hasOneThrough`) uses a real `LEFT JOIN`; the joined
side is a scoped subquery that honours the related model's global scopes and any `->where()` on the relation.
**Filtering** by a relation column uses Eloquent's native `whereHas()` (an `EXISTS` subquery), so it works for
any relation type — including `hasMany`/`belongsToMany` — and honours the relation's keys, scopes, and
constraints automatically.

### Filters

`SelectFilter`, `DateFilter`, `NumberRangeFilter`, `TernaryFilter`. A filter query callback must return the
Builder. It receives the value already normalized for its filter type — a `TernaryFilter` callback gets a real
`bool`, never the `'true'`/`'false'` option key, so branch with `$value ? … : …` and never compare to a
string. Use `->indicator()` for filter chips and `->subRows()` to scope sub-row filtering.

Filtering by a relation aggregate uses the `orders->count()` / `orders->exists()` path syntax
(`Filter::make('orders->count()')`). It is applied as a `WHERE` over the aggregate subquery via Eloquent's
native `whereHas($relation, null, $operator, $count)` / `whereDoesntHave` — **never `HAVING`**, which
PostgreSQL rejects without a `GROUP BY`. `sum`/`avg`/`min`/`max` aggregate filters have no native primitive
and are not applied (skipped, not errored).

Per-column header filters are a **placement of the same canonical `Filter`** in the header cell (not a
separate engine): `->filterable()` (text, with `->filterOperator()`), `->filterAsSelect()` (single),
`->filterAsMultiSelect()` (several values → `whereIn`), `->filterAsBoolean()`, `->filterAsDate()`,
`->filterAsDateRange()`, `->filterAsNumberRange()` — thin factories over `TextFilter` / `SelectFilter` /
`DateFilter` / `NumberRangeFilter` / `TernaryFilter`. Or pass a ready filter with `->filter(SelectFilter::make(...))`.
Options accept an array or enum class. `filterAsSelect`/`filterAsMultiSelect` render the **canonical
searchable combobox** (the same `searchable-select` used by wire-forms `Select` and the table `SelectFilter`)
— search is on by default; `->filterSearchable(false)` drops it. All controls share one style owner
(`Support\FilterControl`) that mirrors the wire-forms field look. They write to the `columnFilters` state
(separate from table `filters`), are planned through the same `QueryPlanner` as panel filters (date/boolean
fall back to `Filter::apply()`), and inherit authorization, **indicator chips** (removable, alongside panel
chips), and **query-string persistence** (`Table::queryString()`, under a `col_<column>` URL parameter).

### Relation managers

A relationship-scoped table as a standalone Livewire component. Extend `RelationManagers\RelationManager`,
set `protected string $relationship` (and optional `protected ?string $title`), and define `table()` exactly
as in any `WithTable` component — columns, filters, actions, exports, search and sorting all work. The base
class pins `query()` to the owner record's relationship, so a subclass cannot widen it. Render with
`@@livewire(PostsRelationManager::class, ['ownerRecord' => $author])`.

Any relationship type can be listed; for belongs-to-many the query selects `related.*` so pivot columns
cannot overwrite related attributes or the row key. Create/attach/detach actions call the base helpers —
`$this->createRelatedRecord([...])` (sets the FK; creates + attaches for belongs-to-many),
`$this->attachRelated($id, [...pivot])` and `$this->detachRelated($id)` (belongs-to-many only, `null`
detaches all). Using one against an unsupported relationship type throws a clear `RuntimeException`.

### Gesture layer

The desktop behaviour — keyboard grid navigation, Shift/mod ranges, the drag sweep, the right-click row
menu, the `?` help and the Excel fill handle — is one switchable layer, owned by `Support\TableGestures`
and configured with `Table::gestures()`. Right for a back office, usually wrong for a public listing:

    ->gestures(false)                                            // an ordinary web table
    ->gestures(fn (TableGestures $g) => $g
        ->keyboard()                                             // arrows, Enter, shortcuts
        ->dragSelect(false)                                      // but no mouse sweep
        ->rangeSelection())                                      // Shift+click still ranges
    ->gestures(TableGestures::none()->contextMenu())             // a prepared set, shared across tables

Six capabilities, each its own setter and `allows*()` reader: **`keyboard`** (roving tabindex, arrows,
Home/End, PageUp/PageDown, Enter / Shift+Enter, Space, every `keyboardShortcut()`/`onKey()`, and what makes
the table an ARIA `grid`), **`rangeSelection`** (Shift/mod/mod+Shift click, Shift+arrow, Shift+Home/End),
**`dragSelect`** (the checkbox-column sweep), **`contextMenu`** (`rowContextMenu()` and any
`onContextMenu()`), **`shortcutHelp`** (`?`), **`fillHandle`**. The closure receives this table's gestures
and configures them **in place** — its return value is ignored, so a fluent chain and a multi-line body
both work.

**A capability is a permission, never a trigger.** Switching one on never conjures the thing it governs:
a sweep still needs `selectable()`, ranges still need a selection to grow in, `fillHandle` still needs
`Table::fillHandle()` plus editable columns, and `shortcutHelp` cannot outlive the keyboard layer that
listens for the key. `keyboard` is the one **three-state** switch (`null` = the table decides — on for
record actions or a selectable table; `true` = force on for a table that would not have qualified;
`false` = off); the other five are plain booleans.

**What the layer does not govern: an explicitly declared record action.** `RecordAction::make('view')->onClick()`
is a deliberate statement about that table, not an affordance the table turned on for itself, so it keeps
firing with `gestures(false)`. The exception is `->onKey()`, which needs the keyboard layer to listen with.
Selection is likewise untouched — checkboxes, both select-all controls and the bulk bar work with every
gesture off; you lose the shortcuts to them, not the feature.

**It is off on the server, not just ignored on the client.** The delegated Alpine controllers are not
rendered (a table with nothing but the gestures off renders no controller at all and requests no bundle),
`role="grid"`/`role="row"`/roving tabindex go away, rows stop being focusable so a click steals no focus,
the `fillTableCells` endpoint **refuses**, and `shortcutLegend()` drops the rows that no longer apply —
with ranges off, Shift+arrow is not listed in the `?` help because it does not work. The legend is
generated from what the table actually does, so it cannot drift from reality.

The project-wide default is `config('wire-table.defaults.gestures')` — `true`/absent for everything,
`false` for nothing, or a map (`['keyboard' => true, 'drag_select' => false]`) for a mixed default; keys
match loosely (`drag_select` = `drag-select` = `dragSelect`), and an **unknown key throws**
`TableConfigurationException` rather than quietly doing nothing. A per-table `gestures()` always wins.

The active-row marker appears only when something needs an anchor to grow from — grid semantics, range
selection or the sweep (`usesActiveRowMarker()`). A table left with nothing but a declared click action
marks nothing: the click opens the record and moves on, and a highlight left behind would be an
application affordance on a page that asked for none.

**Phones get buttons instead of gestures.** There is no double click, no right click and no hover to
discover either, so a behaviour-only record action would be *unreachable* on a stacked mobile card — it is
therefore rendered as an ordinary button there, and only there (`getMobileRowActionsForDisplay()` vs
`getRowActionsForDisplay()`). The fallback never doubles anything: row actions keep their order with the
record actions appended after them, a `recordAction('edit')` that only *references* an action already in
`->actions()` yields one button, an action promoted with `->alsoInRowActions()` is already a button and is
left alone, and the fallback buttons count towards `->collapseActionsOnMobile()`. Turn it off with
`->recordActionButtonsOnMobile(false)`.

### More

- Summaries: per-column `->summarize(...)` with footer scope toggles; grand totals computed in SQL.
- Sub-rows: expandable child records via `->subRows('relation')` + `->subRowColumns([...])`, with per-parent subtotals, `->subRowsLimit()` ("show more"), and an interactive filter bar (`->subRowsFilterable()`, filters the **children**). Expansion is one baseline, not a per-row list: `->subRowsDefaultExpanded()` sets where rows start, the master chevron in the expander column header (or `toggleAllRowExpansion()`) moves it, and it survives pagination + is stored per user with `rememberColumns()`. `flattenSubRows()`/`toggleFlattenMode()` are **deprecated** aliases of the default-expanded baseline — they never flattened anything.
- **A large selection is a query, not a `Collection`.** Besides the keyed selection, the user can "select all matching the filter" (`selectAllMatchingRecords()` / the bulk-bar escalation), stored as a mode whose list holds the *exclusions* — a filter/search change drops it back to explicit keys. A bulk-action callback still receives a `Collection`, but `Table::bulkMaxRecords()` (default 1000) caps what one action loads and the action **refuses out loud** past it. For an action that must handle any size, walk it: `->eachSelectedRecord(fn (Model $r) => ..., chunk: 500)` or `selectedRecordsQuery()` — never expand it into keys.
- Grouping with subtotals, and exports (`withSummaries`).
- Inline editing via `TextInputColumn` / `ToggleColumn` / `SelectColumn`. All three share one canonical Alpine component (`wireEditableCell`): the save (`updateTableCell`) `skipRender()`s the table, so the cell updates **optimistically**, rolls back on failure, and carries the row version for **optimistic-lock** conflict detection (conflict shown inline on the cell; opt-in toast via `Table::notifyEditConflicts()`). Server-side `canEdit(Model $record)` enforces per-record `disabled()`/permission — client `disabled()` is cosmetic only.
- **Fill (Excel-style), server side.** `Table::fillHandle()` opts a table in to writing one value across many rows in **one** request (`fillTableCells`); `Column::fillable(false)` excludes a column that is otherwise editable (a unique code, an invoice number), and `Table::fillMaxRecords(int)` caps a single request (default 500). Each record still goes through the full per-record path — `canEdit()`, its own rules, its own optimistic-lock version — so a fill is deliberately **not** all-or-nothing: one row losing its race is reported as a per-record failure while the rest land. Records are resolved through the table's own query, so a key outside it is never written. The endpoint refuses outright unless `fillHandle()` is on. Per-cell `CellUpdating`/`CellUpdated` fire exactly as for a single edit — there is no separate bulk event. The payload is a **list** of `{column, value, records}` entries where `records` maps record key to the optimistic-lock version the client holds (a map, not a bare list of keys — PHP casts a numeric string array key to an int, so `{"15": "…"}` and `["…"]` would be indistinguishable). Driving `fillTableCells` repeatedly means sending the versions the previous call **returned**, never the ones you started with; the version is `updated_at` to the second, so two writes inside one second are indistinguishable and a stale version is not caught there.
- Conditional row styling: `Table::rowColor(string|Closure|null)` tints a whole row with a semantic/hue color resolved by the canonical `HasColor` owner (return `null` from the Closure for no tint; a tinted row gets a same-hue hover and drops the neutral hover/striping). `Table::rowClass(string|Closure|null)` adds arbitrary classes (the Closure receives the record). Prefer `rowColor()` over hand-written `bg-*` classes; combine both for e.g. a danger tint + `font-semibold`.
- Per-user column memory: `Table::rememberColumns('key')` loads each user's saved hidden-column set on mount and persists it on every toggle, scoped to `auth()->user()` (one key serves all users; stale column names are ignored). Storage is a driver chosen in `config('wire-table.preferences')` — `null` (default, no persistence), `session`, or `database` (publish `wire-table::migrations` → `table_preferences` table). `Table::preferenceDriver($driver)` overrides per table; a "Reset columns" control clears the saved layout. Implement `TablePreferenceDriver` for a custom store.
- **Record actions (whole-row interaction), a distinct group from `->actions()`/`->bulkActions()`/`->headerActions()`.** `Table::recordActions([...])` / `recordAction(string|Action|RecordAction)` bind an action to a row gesture: `Action::make('edit')->onDoubleClick()` (also `->onClick()`, `->onContextMenu()`, `->onKey('Delete')`, `->on('custom')`). Those fluent triggers are `Action` macros that **return a `RecordAction`** (a table-owned wrapper — the shared `Action` class stays clean); it belongs in `recordActions()`, and `->actions()` rejects it out loud. A bare name (`recordAction('edit')`) references an action already in `->actions()`. Execution reuses `openActionModal`/`executeTableAction` (auth, confirmation, forms unchanged) — no second pipeline. **Behaviour-only by default** (no button — this is what makes a table feel like an app); `->alsoInRowActions()` also renders it in the column, `->behaviorOnly()` states the default. **One delegated Alpine controller (`wireRecordActions`) on the `<tbody>`** — never per-row — resolves the row from `data-row-key` and ignores clicks on any interactive element inside the row (buttons/checkboxes/links/editable cells/dropdowns) with no `stopPropagation()` needed. `onContextMenu()` feeds the row context menu (a single delegated menu, positioned at the cursor; closes on outside-click/Escape/scroll). When selectable, the default trigger is **double-click**, leaving the single click free for selection work — a *plain* click only marks the row (active row + range anchor) and never ticks the checkbox, while a **modified** click is a selection gesture and never runs a bound action (see the selection-gestures bullet). Keyboard nav is auto-on for any table the keyboard drives row by row — record actions **or** `selectable()`/`bulkActions()` (`Table::usesGridSemantics()` is the single owner of that decision; the gesture layer forces it either way — see the gesture-layer bullet): `role="grid"`, roving `tabindex`, ↑/↓ move the active row, Enter/Shift+Enter run the primary/secondary, Menu key **and Shift+F10** open the context menu, `?` opens the shortcut help, and each action's `keyboardShortcut()` fires against the active row. **Pointer and keyboard share one active row**: a click marks the row (an Alpine `:class`/`:tabindex` binding, so the marker and the tabstop survive the Livewire morph every update triggers, follow the record through a re-sort, and fall back to the first row when it leaves the page), the active row drops its hover tint so `hover:bg-*` cannot paint over the marker, keys reach the grid only when a **row itself** has the focus (a keystroke inside a row button/editable cell/dropdown is that element's), the grid is inert while a dialog is open, and closing a modal hands the focus back to the active row. Style with `recordActionHover('primary')` (else neutral) and `activeRowClass(...)`. Desktop pointer + keyboard feature; touch cards and sub-rows are excluded by design.
- **Selection gestures (mouse + keyboard), one shared Alpine component.** Every selection surface — the checkboxes, both select-all toggles, the bulk bar, the mobile cards, the keyboard — drives one `wireRecordSelection` component reached via `[data-selection-root]` (optimistic; no per-keystroke roundtrip). Mouse: the **whole selection cell** toggles (`[data-select-cell]`, not just the 16px box, and the cell is registered interactive so the click never reaches a record action), Shift+click ranges from the anchor, mod+click toggles one row anywhere on it, mod+Shift+click adds a block, and **dragging down the checkbox column sweeps** rows in (additive only, mouse only, engages on the first row-changing move so a plain click stays a click). Keyboard: Space toggles + anchors, Shift+↑/↓ ranges, Shift+Home/End and mod+Shift+↑/↓ range to the edge, Home/End/PageUp/PageDown navigate, mod+A selects the page. **A range writes `base ∪ range`, not the range alone** — the snapshot minus the contiguous block around the anchor, so rows selected elsewhere survive; a range gesture **never rewrites `mode`**, which means in "all matching" mode (where the stored list is the *exclusions*) a range **deselects** and mod+A stands down. The anchor is one-shot and invisible; with no anchor of its own the range grows from the far edge of the block the active row sits in. Drop single rows with Space or mod+click, not a range. Keys reach the grid only when a **row itself** has the focus. **The grid reserves the keys it navigates with** — `->onKey()` on Enter/Space/arrows/Home/End/PageUp/PageDown/ContextMenu/F10/`?` throws a `TableConfigurationException` at configuration time rather than dropping the binding silently (a `keyboardShortcut()` on the action itself is only skipped); `Backspace` is deliberately not reserved and acts as a JS-side alias of `Delete`. ARIA: `aria-rowcount`, `aria-multiselectable`, `aria-rowindex` counted through the **whole result set** (not the page), bound `aria-selected` per row, and a polite live region that is in the DOM from the first paint and empty until the first change. The active-row marker is a tint **plus** a leading stripe (`activeRowClass()` replaces both halves) — the tint alone is ~1.1:1, under the 3:1 non-text contrast floor. `Table::shortcutLegend()` returns the same gesture list as data (`ShortcutHint` value objects) for rendering elsewhere.
- `Table::rowContextMenu([...actions])` is **deprecated** (removed in v2.0) — a thin alias that still feeds the same context menu. Prefer `recordAction(Action::make('edit')->onContextMenu())`.
- **Mobile (`Table::stackedOnMobile()`).** Below the breakpoint each row becomes a card whose hierarchy is five derived slots — title (first column), metric (last right-aligned, e.g. `money()`), meta (badge columns), subtitle, and a label/value grid for the rest — overridable per column (`->mobileMetric()`, `->mobileMeta()`, …) or per table (`->mobileCard(fn (MobileCardConfig $c) => $c->title('number')->metric('total'))`). The header row is hidden, so its controls move into the card view: an always-visible select-all strip, a sort control, sub-row children with their subtotal, and the summary totals. `->collapseActionsOnMobile()` folds row actions into one dropdown. **Record actions fall back to buttons here**: every record trigger is a desktop one, so a behaviour-only record action renders as an ordinary button on the card (and only there) — one declaration, a gesture on the desktop and a button on a phone. It never doubles an action already in `->actions()`, one referenced by name, or one promoted with `->alsoInRowActions()`, and the fallback buttons count towards `collapseActionsOnMobile()`; the card's copy drops the action's `keyboardShortcut()` (`HasKeyboardShortcut::withoutKeyboardShortcut()`), because a rendered button binds its shortcut as a *window* listener and the cards are in the document at every width. Opt out with `Table::recordActionButtonsOnMobile(false)`.
- **The desktop gesture layer is switchable — `Table::gestures()`, the single owner.** Keyboard grid navigation, `Shift`/`mod` ranges, the drag sweep down the checkbox column, the right-click row menu, the `?` help and the fill handle are six capabilities of one layer: `->gestures(false)` leaves an ordinary web table (nothing bound, nothing marked, the delegated controllers not rendered), `->gestures(fn (TableGestures $g) => $g->keyboard()->dragSelect(false))` mixes them, and `config('wire-table.defaults.gestures')` sets the project-wide default (`true` / `false` / a map like `['keyboard' => true, 'drag_select' => false]`; an unknown capability throws). `NyonCode\WireTable\Support\TableGestures` owns the vocabulary (`all()`, `none()`; `keyboard()` is three-state — `null` = the table decides). Each capability is a **permission, not a trigger** (the sweep still needs `selectable()`, the fill handle still needs `fillHandle()`), and the switch does **not** govern an explicitly declared record action — a bound `onClick()`/`onDoubleClick()` survives `gestures(false)`; only `onKey()` needs the keyboard layer. Enforced server-side too: no `role="grid"`, no roving `tabindex`, no context-menu panels, `fillTableCells()` refused, and the `?` legend lists only what the table actually answers to.
- `Table::queryString()` persists state to the URL.
- Browser-testing hooks: every active part carries a stable `data-testid` — `table-search`, `table-filters-trigger`, `table-filter-reset`, `filter-chip-{name}`, `column-filter-chip-{name}`, `table-column-toggle`, `table-per-page`, `table-page-prev|next|{n}`, `table-sort-{col}`, `table-filter-{col}`, `table-cell-{col}`, `table-editable-{col}`, `table-row` (+ `data-row-key`; mobile `table-card`), `table-select-all` / `table-row-select`, `table-row-expand`, `table-bulk-bar` / `table-deselect`, and `action-{name}` / `header-action-{name}` / `bulk-action-{name}` / `menu-action-{name}` (all with `aria-label`) — so Pest v4 Browser Testing targets them at the user level. Actions and filter options are also reachable by visible text. Column-static render metadata is resolved once per column (`$columnMeta`) instead of per cell.

### Performance

The table renders each cell and each action **once per row**, so per-row cost scales with
rows × columns (× actions). Keep the per-row work cheap and lean on the levers the package
already gives you:

- **Defer off-screen tables.** `Table::lazy()` returns no rows and runs no query until the
  table scrolls into view (optional `->lazyPlaceholder(...)`). Use it for tables below the fold
  or in tabs. It defers the query and the markup, **not** the JS: the table's Alpine bundles
  ship with the placeholder render, because they register from `alpine:init` and that fires
  once, at boot — a bundle arriving with the deferred markup would register nothing. So
  `lazy()` is a lever for query and render cost, not for first-paint script weight.
- **Defer action-group menus.** `ActionGroup::make([...])->lazyMenu()` ships only the trigger plus
  a serialized item spec per row and builds the menu client-side on first open — zero per-row menu
  Blade renders (an eager group renders one view per item per row). Opt-in; the default is eager.
  Trade-offs: keyboard shortcuts and `wire:click` modifiers on menu items are not wired in lazy
  mode. Reach for it on large tables whose every row carries a multi-item action dropdown.
- **Inline edits skip the table render.** `TextInputColumn` / `ToggleColumn` / `SelectColumn`
  commit through `updateTableCell`, which `skipRender()`s the table — the edited cell updates
  optimistically without re-rendering every other row. Do not wrap the whole table in your own
  `wire:model` polling that would defeat this.
- **Relation display never N+1s.** Dot-notation columns (`TextColumn::make('company.name')`)
  eager-load via `with()` for display — never add a manual per-row query in `displayUsing`.
- **Eager-load closure relations.** A relation dereferenced ONLY inside a closure —
  `->displayUsing(fn ($s, $r) => $r->company->name)`, `->url(fn ($r) => route('x', $r->team))`,
  `->color(fn ($s, $r) => $r->status->tint)` — has no column path, so the planner cannot
  discover it and it lazy-loads once per row (a large N+1). Add the hint
  `->loadRelations('company')` (or `->loadRelations(['company', 'team'])`) on the column,
  or eager-load on the base query (`->query(User::with('company'))`). The hint flattens
  the query count regardless of row count.
- **Summaries and rollups are one SQL query**, not one per column (`SummaryBatch`); grand totals
  compute in SQL. Prefer `->summarize(...)` over counting in PHP.
- **Keep per-row closures cheap — they run for every row.** `displayUsing`, `colorUsing`,
  `iconUsing`, `visibleForRecord`, `rowColor` and `rowClass` closures execute per record. Pick
  the cheapest form: a fixed `->color('success')` or a static `->colors([...])` / `->icons([...])`
  map over a `->colorUsing(fn ...)` when the mapping does not actually depend on runtime state; an
  enum implementing `HasColor`/`HasIcon` resolves with no closure at all. Never open a DB query or
  resolve a container binding inside one of these closures.
- **Do not render Blade per row inside your own code.** A custom `->view(...)` or `displayUsing`
  is already invoked once per cell by the engine — do not nest another `view(...)->render()` or a
  Blade include of a primitive (spinner, icon, divider) inside it. Return a value or a prebuilt
  `Htmlable`; let the cell partial place it. Resolve icons through the `IconManager` (an enum
  `HasIcon`, `->icon('check')`), never a hardcoded `<svg>` — the manager output is what gets reused,
  and hardcoded SVG also breaks theming.
- **Split a heavy screen into a child component.** `WithTable` and `WithActions` on the same
  Livewire component both re-render on every interaction; for a large table with its own action
  workflow, put the table in a dedicated child component so an action elsewhere on the page does
  not re-render the whole grid.
- **Column-static metadata is already resolved once per column** (`$columnMeta`), and hidden
  columns / non-executable actions short-circuit to an empty render — you get those for free.

Use `describe-table` on an existing component to see its resolved columns, filters and actions.
