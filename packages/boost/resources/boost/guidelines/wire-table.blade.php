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
Builder. Use `->indicator()` for filter chips and `->subRows()` to scope sub-row filtering.

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
- Sub-rows, grouping with subtotals, and exports (`withSummaries`).
- Inline editing via `TextInputColumn` / `ToggleColumn` / `SelectColumn`. All three share one canonical Alpine component (`wireEditableCell`): the save (`updateTableCell`) `skipRender()`s the table, so the cell updates **optimistically**, rolls back on failure, and carries the row version for **optimistic-lock** conflict detection (conflict shown inline on the cell; opt-in toast via `Table::notifyEditConflicts()`). Server-side `canEdit(Model $record)` enforces per-record `disabled()`/permission — client `disabled()` is cosmetic only.
- Conditional row styling: `Table::rowColor(string|Closure|null)` tints a whole row with a semantic/hue color resolved by the canonical `HasColor` owner (return `null` from the Closure for no tint; a tinted row gets a same-hue hover and drops the neutral hover/striping). `Table::rowClass(string|Closure|null)` adds arbitrary classes (the Closure receives the record). Prefer `rowColor()` over hand-written `bg-*` classes; combine both for e.g. a danger tint + `font-semibold`.
- Per-user column memory: `Table::rememberColumns('key')` loads each user's saved hidden-column set on mount and persists it on every toggle, scoped to `auth()->user()` (one key serves all users; stale column names are ignored). Storage is a driver chosen in `config('wire-table.preferences')` — `null` (default, no persistence), `session`, or `database` (publish `wire-table::migrations` → `table_preferences` table). `Table::preferenceDriver($driver)` overrides per table; a "Reset columns" control clears the saved layout. Implement `TablePreferenceDriver` for a custom store.
- `Table::rowContextMenu([...actions])` lets users right-click a row to open a menu of actions at the cursor. The actions are declared **separately** from `->actions()` (not a mirror of the toolbar — pass the same objects to match); action-group menu styling, groups flattened, only visible actions shown. Only one menu open at a time; closes on outside-click/Escape/scroll/choose. Desktop pointer feature; the actions column stays for touch.
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
