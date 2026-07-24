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

### More

- Summaries: per-column `->summarize(...)` with footer scope toggles; grand totals computed in SQL.
- Sub-rows: expandable child records via `->subRows('relation')` + `->subRowColumns([...])`, with per-parent subtotals, `->subRowsLimit()` ("show more"), and an interactive filter bar (`->subRowsFilterable()`, filters the **children**). Expansion is one baseline, not a per-row list: `->subRowsDefaultExpanded()` sets where rows start, the master chevron in the expander column header (or `toggleAllRowExpansion()`) moves it, and it survives pagination + is stored per user with `rememberColumns()`. `flattenSubRows()`/`toggleFlattenMode()` are **deprecated** aliases of the default-expanded baseline — they never flattened anything.
- **A large selection is a query, not a `Collection`.** Besides the keyed selection, the user can "select all matching the filter" (`selectAllMatchingRecords()` / the bulk-bar escalation), stored as a mode whose list holds the *exclusions* — a filter/search change drops it back to explicit keys. A bulk-action callback still receives a `Collection`, but `Table::bulkMaxRecords()` (default 1000) caps what one action loads and the action **refuses out loud** past it. For an action that must handle any size, walk it: `->eachSelectedRecord(fn (Model $r) => ..., chunk: 500)` or `selectedRecordsQuery()` — never expand it into keys.
- Grouping with subtotals, and exports (`withSummaries`).
- Inline editing via `TextInputColumn` / `ToggleColumn` / `SelectColumn`. All three share one canonical Alpine component (`wireEditableCell`): the save (`updateTableCell`) `skipRender()`s the table, so the cell updates **optimistically**, rolls back on failure, and carries the row version for **optimistic-lock** conflict detection (conflict shown inline on the cell; opt-in toast via `Table::notifyEditConflicts()`). Server-side `canEdit(Model $record)` enforces per-record `disabled()`/permission — client `disabled()` is cosmetic only.
- **Fill (Excel-style), server side.** `Table::fillHandle()` opts a table in to writing one value across many rows in **one** request (`fillTableCells`); `Column::fillable(false)` excludes a column that is otherwise editable (a unique code, an invoice number), and `Table::fillMaxRecords(int)` caps a single request (default 500). Each record still goes through the full per-record path — `canEdit()`, its own rules, its own optimistic-lock version — so a fill is deliberately **not** all-or-nothing: one row losing its race is reported as a per-record failure while the rest land. Records are resolved through the table's own query, so a key outside it is never written. The endpoint refuses outright unless `fillHandle()` is on. Per-cell `CellUpdating`/`CellUpdated` fire exactly as for a single edit — there is no separate bulk event. The payload is a **list** of `{column, value, records}` entries where `records` maps record key to the optimistic-lock version the client holds (a map, not a bare list of keys — PHP casts a numeric string array key to an int, so `{"15": "…"}` and `["…"]` would be indistinguishable). Driving `fillTableCells` repeatedly means sending the versions the previous call **returned**, never the ones you started with; the version is `updated_at` to the second, so two writes inside one second are indistinguishable and a stale version is not caught there.
- Conditional row styling: `Table::rowColor(string|Closure|null)` tints a whole row with a semantic/hue color resolved by the canonical `HasColor` owner (return `null` from the Closure for no tint; a tinted row gets a same-hue hover and drops the neutral hover/striping). `Table::rowClass(string|Closure|null)` adds arbitrary classes (the Closure receives the record). Prefer `rowColor()` over hand-written `bg-*` classes; combine both for e.g. a danger tint + `font-semibold`.
- Per-user column memory: `Table::rememberColumns('key')` loads each user's saved hidden-column set on mount and persists it on every toggle, scoped to `auth()->user()` (one key serves all users; stale column names are ignored). Storage is a driver chosen in `config('wire-table.preferences')` — `null` (default, no persistence), `session`, or `database` (publish `wire-table::migrations` → `table_preferences` table). `Table::preferenceDriver($driver)` overrides per table; a "Reset columns" control clears the saved layout. Implement `TablePreferenceDriver` for a custom store.
- **Record actions (whole-row interaction), a distinct group from `->actions()`/`->bulkActions()`/`->headerActions()`.** `Table::recordActions([...])` / `recordAction(string|Action|RecordAction)` bind an action to a row gesture: `Action::make('edit')->onDoubleClick()` (also `->onClick()`, `->onContextMenu()`, `->onKey('Delete')`, `->on('custom')`). Those fluent triggers are `Action` macros that **return a `RecordAction`** (a table-owned wrapper — the shared `Action` class stays clean); it belongs in `recordActions()`, and `->actions()` rejects it out loud. A bare name (`recordAction('edit')`) references an action already in `->actions()`. Execution reuses `openActionModal`/`executeTableAction` (auth, confirmation, forms unchanged) — no second pipeline. **Behaviour-only by default** (no button — this is what makes a table feel like an app); `->alsoInRowActions()` also renders it in the column, `->behaviorOnly()` states the default. **One delegated Alpine controller (`wireRecordActions`) on the `<tbody>`** — never per-row — resolves the row from `data-row-key` and ignores clicks on any interactive element inside the row (buttons/checkboxes/links/editable cells/dropdowns) with no `stopPropagation()` needed. `onContextMenu()` feeds the row context menu (a single delegated menu, positioned at the cursor; closes on outside-click/Escape/scroll). When selectable, the default trigger is **double-click** so a single click still selects. Keyboard nav auto-on when any record action exists: `role="grid"`, roving `tabindex`, ↑/↓ move the active row, Enter/Shift+Enter run the primary/secondary, the Menu key opens the context menu, and each action's `keyboardShortcut()` fires against the active row (`recordActionKeyboard(false)` forces off). Keyboard **selection** shares the one selection component the checkboxes/bulk bar use (reached via `data-selection-root`, optimistic — no per-keystroke roundtrip): Space toggles the active row + sets an anchor, Shift+↑/↓ extends a contiguous range from the anchor, mod+A selects the page. Style with `recordActionHover('primary')` (else neutral) and `activeRowClass(...)`. Desktop pointer + keyboard feature; touch cards and sub-rows are excluded by design.
- `Table::rowContextMenu([...actions])` is **deprecated** (removed in v2.0) — a thin alias that still feeds the same context menu. Prefer `recordAction(Action::make('edit')->onContextMenu())`.
- **Mobile (`Table::stackedOnMobile()`).** Below the breakpoint each row becomes a card whose hierarchy is five derived slots — title (first column), metric (last right-aligned, e.g. `money()`), meta (badge columns), subtitle, and a label/value grid for the rest — overridable per column (`->mobileMetric()`, `->mobileMeta()`, …) or per table (`->mobileCard(fn (MobileCardConfig $c) => $c->title('number')->metric('total'))`). The header row is hidden, so its controls move into the card view: an always-visible select-all strip, a sort control, sub-row children with their subtotal, and the summary totals. `->collapseActionsOnMobile()` folds row actions into one dropdown.
- `Table::queryString()` persists state to the URL.
- Browser-testing hooks: every active part carries a stable `data-testid` — `table-search`, `table-filters-trigger`, `table-filter-reset`, `filter-chip-{name}`, `column-filter-chip-{name}`, `table-column-toggle`, `table-per-page`, `table-page-prev|next|{n}`, `table-sort-{col}`, `table-filter-{col}`, `table-cell-{col}`, `table-editable-{col}`, `table-row` (+ `data-row-key`; mobile `table-card`), `table-select-all` / `table-row-select`, `table-row-expand`, `table-bulk-bar` / `table-deselect`, and `action-{name}` / `header-action-{name}` / `bulk-action-{name}` / `menu-action-{name}` (all with `aria-label`) — so Pest v4 Browser Testing targets them at the user level. Actions and filter options are also reachable by visible text. Column-static render metadata is resolved once per column (`$columnMeta`) instead of per cell.

### Performance

The table renders each cell and each action **once per row**, so per-row cost scales with
rows × columns (× actions). Keep the per-row work cheap and lean on the levers the package
already gives you:

- **Defer off-screen tables.** `Table::lazy()` returns no rows and runs no query until the
  table scrolls into view (optional `->lazyPlaceholder(...)`). Use it for tables below the fold
  or in tabs.
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
