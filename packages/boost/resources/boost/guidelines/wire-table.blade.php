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
`CheckboxColumn`, `PollColumn`, `SelectColumn`, `TextInputColumn`, `SplitColumn`, `StackedColumn`,
`ColorColumn`, `RatingColumn`, `TagsColumn`.

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

`SelectFilter`, `DateFilter`, `NumberRangeFilter`, `TernaryFilter`, `TrashedFilter`. A filter query callback must return the
Builder. It receives the value already normalized for its filter type — a `TernaryFilter` callback gets a real
`bool`, never the `'true'`/`'false'` option key, so branch with `$value ? … : …` and never compare to a
string. Use `->indicator()` for filter chips and `->subRows()` to scope sub-row filtering. `TrashedFilter` constrains no
column — it switches the soft-delete scope (`'with'` → `withTrashed()`, `'only'` → `onlyTrashed()`, cleared → live
records) and requires the model to use `SoftDeletes`.

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

### Search syntax

`Table::searchable()` matches the whole term as one substring across every searchable column
(`LIKE`/`ILIKE`), and a `%` or `_` the user types is escaped rather than acting as a wildcard.
Richer syntax is **opt-in per table** through `Table::search()` — nothing is interpreted unless
asked for, so an unconfigured table behaves exactly as before:

```php
use NyonCode\WireCore\Core\Query\Search\SearchConfig;

$table->search(fn (SearchConfig $s) => $s
    ->tokenize()    // spaces = AND; each word ORs across all columns; "quoted phrase" stays whole
    ->ranges()      // >100, >=100, <10, <=10, =42, 10..20, 10.., ..20, 2026-01-01..2026-03-31
    ->wildcards()   // nov* / a?b
);
```

Structured codes (`8866 01`, `8866 02` — shared series, zero-padded tail) get `Column::searchAs('code')`:
typing `8866 01..08` becomes one `BETWEEN '8866 01' AND '8866 08'`. The space inside the code also
splits the term, so the range carries the word typed before it and a code column completes both
bounds with it (write the series once); any other column ignores that word and reads `01..08` as the
plain range, so `praha 10..20` still works on the same table. The number must be stored padded and typed as stored
(`1..8` against stored `01 … 08` compares at the typed width and misses the padded series); a range
crossing a width boundary is completed — `8866 50..100` reads as `050..100`. `searchAs()` switches
nothing on by itself: a searchable column declaring a type while the table's search does not read
ranges is refused when the table renders, naming the missing `->search(...)` call.

`tokenize()` is what makes a first name in one column and a surname in another match together.
`ranges()` only asks a column that can answer — the value type comes from the model's casts, or
from `Column::searchAs('numeric'|'date')` where a cast cannot speak for the column; a comparison
no column can answer is searched as the literal text typed, never as an empty group that matches
everything. A typed date means its whole span (`2026-01-31` the day, `2026-01` the month, `2026`
the year). `Column::searchable(['first_name', 'last_name'])` searches exactly the columns listed;
`Column::searchUsing(fn (Builder $q, string $term) => ...)` OR-combines with the planned columns
and receives one token at a time when tokenizing.

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
menu, the `?` help and the Excel fill handle — is one layer, owned by `Support\TableGestures` and
configured with `Table::gestures()`. **It is opt-in.** Keyboard navigation, range selection and the drag
sweep are OFF for a table that never calls `gestures()`, because each changes how the table answers a
visitor who never meant to operate it (rows enter the tab order, an active row is marked, a press in the
checkbox column starts selecting a block, a Shift+click stops meaning a click). A selectable table starts
as checkboxes and nothing more and mounts no delegated controller at all. Right for a back office, wrong
for a public listing:

    ->gestures()                                                 // the desktop-app table
    ->gestures(false)                                            // not even the quiet capabilities
    ->gestures(fn (TableGestures $g) => $g
        ->keyboard()                                             // arrows, Enter, shortcuts …
        ->dragSelect(false))                                     // … but still no mouse sweep
    ->gestures(TableGestures::none()->contextMenu())             // a prepared set, shared across tables

Six capabilities, each its own setter and `allows*()` reader, with the default a table gets without asking:

| Capability | Default | Covers |
|---|---|---|
| `keyboard` | **off** | roving tabindex, arrows, Home/End, PageUp/PageDown, Enter / Shift+Enter, Space, every `keyboardShortcut()`/`onKey()` against the active row — and what makes the table an ARIA `grid` |
| `rangeSelection` | **off** | Shift / mod / mod+Shift click, Shift+arrow, Shift+Home/End |
| `dragSelect` | **off** | the checkbox-column sweep |
| `contextMenu` | on | `rowContextMenu()` and any `onContextMenu()` binding |
| `shortcutHelp` | on¹ | `?` |
| `fillHandle` | on² | the Excel-style handle on editable cells |

¹ reads the keyboard layer, so with the default it never opens. ² still needs `Table::fillHandle()`.

The three that stay allowed already need an invitation of their own — actions bound to the context menu,
`fillHandle()`, and (for the `?` help) the keyboard layer this default leaves off — so with the shipped
default a table offers only what it declared itself. The closure receives
this table's gestures and configures them **in place**; its return value is ignored, so a fluent chain and
a multi-line body both work. `TableGestures::defaults()` / `all()` / `none()` are the three starting
points: shipped default, everything, nothing.

Readers, all on `Table` and all consulted rather than re-derived: `usesGridSemantics()` (the single owner
of "is this an ARIA grid"), `keyboardNavEnabled()`, `usesRangeSelection()`, `usesDragSelect()`,
`usesShortcutHelp()`, `usesActiveRowMarker()`, `mountsRecordActionController()`, `getGestureConfig()`
(the `{sweep, ranges}` the client controller consumes), `getRecordActionKeyboardConfig()`, `getTableRole()`,
`hasRowContextMenu()`, `isFillHandleEnabled()`, `getGestures()` (the raw permissions).

**A capability is a permission, never a trigger.** Switching one on never conjures the thing it governs:
a sweep still needs `selectable()`, ranges still need a selection to grow in, `fillHandle` still needs
`Table::fillHandle()` plus editable columns, and `shortcutHelp` cannot outlive the keyboard layer that
listens for the key. `keyboard` is the one **three-state** switch (`false` = the shipped default; `null` = the table decides,
which it takes for record actions or a selectable table — this is what `gestures()` sets, deliberately
NOT `true`, since a table with neither has nothing for the arrows to do; `true` = force it on regardless);
the other five are plain booleans.

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

The project-wide default is `config('wire-table.defaults.gestures')` — `null`/absent keeps the shipped
default above, `true` turns the whole layer on for **every** table (what a back-office project sets once),
`false` allows nothing, and a map (`['keyboard' => true, 'drag_select' => false]`) mixes on top of the
shipped default; keys match loosely (`drag_select` = `drag-select` = `dragSelect`), and an **unknown key
throws** `TableConfigurationException` rather than quietly doing nothing. A per-table `gestures()` always
wins. Consequence for fixtures and tests: anything asserting `role="grid"`, a roving tabindex, arrow
navigation, a Shift+click range, a sweep or the `?` help must call `->gestures()` first.

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

The card renders a **copy** with the keyboard shortcut stripped (`HasKeyboardShortcut::withoutKeyboardShortcut()`,
new in wire-core). A rendered action button binds its `keyboardShortcut()` as a **window** listener
(`x-on:keydown.{key}.window` in `wire-core::actions.button`) and the stacked cards are in the document at
every width — so without this one `Delete` press ran an `onKey('Delete')` action once per card behind the
desktop table. General rule: **never render the same shortcut-carrying action on two surfaces.**

### JavaScript assets

The table's Alpine controllers (`wireRecordSelection`, `wireRecordActions`, plus core's
`wireDropdown`/`wireContextMenu`/`wireEditableCell`/`wireFillHandle`) ship as pre-built bundles
served from each package's own asset route — nothing to publish, nothing to build.

**The primary delivery path is `@@wireStackScripts` in the app's layout `<head>`.** The
per-surface `@@include`s of `partials/selection-assets.blade.php` & co. are now a **fallback**:
they still work, and they dedupe against the directive, but tell an app to add the directive.
Only a bundle already in the initial document survives Livewire's cached Back/Forward path,
which does not await newly injected head scripts — that is what `wireRecordSelection is not
defined` after a `wire:navigate` (dead dropdowns, a grey sheet-backdrop scrim over the table)
comes from. `@@wireStackScripts('wire-table')` narrows it to one package.

Registration rules live in the wire-core guidelines: register unconditionally, never only from
`alpine:init`; lazy the heavy bodies, never the registrators.

### More

- Summaries: per-column `->summarize(...)` with footer scope toggles; grand totals computed in SQL.
- Sub-rows: expandable child records via `->subRows('relation')` + `->subRowColumns([...])`, with per-parent subtotals, `->subRowsLimit()` ("show more"), and an interactive filter bar (`->subRowsFilterable()`, filters the **children**). Expansion is one baseline, not a per-row list: `->subRowsDefaultExpanded()` sets where rows start, the master chevron in the expander column header (or `toggleAllRowExpansion()`) moves it, and it survives pagination + is stored per user with `rememberColumns()`. `flattenSubRows()`/`toggleFlattenMode()` are **deprecated** aliases of the default-expanded baseline — they never flattened anything. `subRows()` is table-wide, so **every** row gets a chevron unless `->subRowsVisible(fn ($record) => ...)` says which records can have children at all — rejected records lose the chevron, the panel and their share of the eager load, but keep an empty expander cell so the columns stay aligned (the result is memoized per record, so the callback may query — prefer a `withCount` attribute). It is not "has none right now" — that case is `->subRowsHideWhenEmpty()`, which is **not** the same closure written by hand: it makes the table's own query carry a constrained presence count (its own alias, so a rollup count column keeps `{relation}_count`), so the per-row check is an attribute read rather than a `COUNT` per row. That count honours `subRowQuery()` and `Filter::subRows()` but deliberately **not** the interactive `subRowsFilterable()` bar, whose values change per parent. Both conditions compose, cheap one first.
- **A large selection is a query, not a `Collection`.** Besides the keyed selection, the user can "select all matching the filter" (`selectAllMatchingRecords()` / the bulk-bar escalation), stored as a mode whose list holds the *exclusions* — a filter/search change drops it back to explicit keys. A bulk-action callback still receives a `Collection`, but `Table::bulkMaxRecords()` (default 1000) caps what one action loads and the action **refuses out loud** past it. For an action that must handle any size, walk it: `->eachSelectedRecord(fn (Model $r) => ..., chunk: 500)` or `selectedRecordsQuery()` — never expand it into keys.
- Grouping with subtotals, and exports (`withSummaries`).
- Inline editing via `TextInputColumn` / `ToggleColumn` / `SelectColumn`. All three share one canonical Alpine component (`wireEditableCell`): the cell updates **optimistically**, rolls back on failure, and carries the row version for **optimistic-lock** conflict detection (conflict shown inline on the cell; opt-in toast via `Table::notifyEditConflicts()`). The version is `RecordVersion` — the model's own `updated_at` column, so `const UPDATED_AT` is honoured; do not hand-roll the stamp, a literal `->updated_at` read renders the `'0'` sentinel for such a model and `conflicts()` reads `'0'` as "the client never had a version", leaving every edit on it unguarded. Edits the **same request** made are not a conflict: Livewire bundles calls issued in one tick into one request, so tabbing out of one cell straight into another on the same row sends both holding the version from their shared render — `RecordVersion` remembers the stamp each record carried when the request opened and accepts it however many times that request has written since, while a version matching neither the current stamp nor that baseline is still refused. The save (`updateTableCell`) **renders**, and the cell reconciles from a sync node rather than being reset by the morph (see gotchas). Server-side `canEdit(Model $record)` enforces per-record `disabled()`/permission — client `disabled()` is cosmetic only.
- **Fill (Excel-style), server side.** `Table::fillHandle()` opts a table in to writing one value across many rows in **one** request (`fillTableCells`); `Column::fillable(false)` excludes a column that is otherwise editable (a unique code, an invoice number), and `Table::fillMaxRecords(int)` caps a single request (default 500). Each record still goes through the full per-record path — `canEdit()`, its own rules, its own optimistic-lock version — so a fill is deliberately **not** all-or-nothing: one row losing its race is reported as a per-record failure while the rest land. Records are resolved through the table's own query, so a key outside it is never written. The endpoint refuses outright unless `fillHandle()` is on. Per-cell `CellUpdating`/`CellUpdated` fire exactly as for a single edit — there is no separate bulk event. The payload is a **list** of `{column, value, records}` entries where `records` maps record key to the optimistic-lock version the client holds (a map, not a bare list of keys — PHP casts a numeric string array key to an int, so `{"15": "…"}` and `["…"]` would be indistinguishable). Driving `fillTableCells` repeatedly means sending the versions the previous call **returned**, never the ones you started with — that is what a later request needs, and a fill in a request of its own is refused without it. (Two fills bundled into the *same* request are forgiven by the baseline rule above; do not rely on it, since which calls share a request is Livewire's decision, not yours.) The version is `updated_at` to the second, so two writes inside one second are indistinguishable and a stale version is not caught there.
- Conditional row styling: `Table::rowColor(string|Closure|null)` tints a whole row with a semantic/hue color resolved by the canonical `HasColor` owner (return `null` from the Closure for no tint; a tinted row gets a same-hue hover and drops the neutral hover/striping). `Table::rowClass(string|Closure|null)` adds arbitrary classes (the Closure receives the record). Prefer `rowColor()` over hand-written `bg-*` classes; combine both for e.g. a danger tint + `font-semibold`.
- Per-user column memory: `Table::rememberColumns('key')` loads each user's saved hidden-column set on mount and persists it on every toggle, scoped to `auth()->user()` (one key serves all users; stale column names are ignored). Storage is a driver chosen in `config('wire-table.preferences')` — `null` (default, no persistence), `session`, or `database` (publish `wire-table::migrations` → `table_preferences` table). `Table::preferenceDriver($driver)` overrides per table; a "Reset columns" control clears the saved layout. Implement `TablePreferenceDriver` for a custom store.
- **Record actions (whole-row interaction), a distinct group from `->actions()`/`->bulkActions()`/`->headerActions()`.** `Table::recordActions([...])` / `recordAction(string|Action|RecordAction)` bind an action to a row gesture: `Action::make('edit')->onDoubleClick()` (also `->onClick()`, `->onContextMenu()`, `->onKey('Delete')`, `->on('custom')`). Those fluent triggers are `Action` macros that **return a `RecordAction`** (a table-owned wrapper — the shared `Action` class stays clean); it belongs in `recordActions()`, and `->actions()` rejects it out loud. A bare name (`recordAction('edit')`) references an action already in `->actions()`. Execution reuses `openActionModal`/`executeTableAction` (auth, confirmation, forms unchanged) — no second pipeline. **Behaviour-only by default** (no button — this is what makes a table feel like an app); `->alsoInRowActions()` also renders it in the column, `->behaviorOnly()` states the default. **One delegated Alpine controller (`wireRecordActions`) on the `<tbody>`** — never per-row — resolves the row from `data-row-key` and ignores clicks on any interactive element inside the row (buttons/checkboxes/links/editable cells/dropdowns) with no `stopPropagation()` needed. `onContextMenu()` feeds the row context menu (a single delegated menu, positioned at the cursor; closes on outside-click/Escape/scroll). When selectable, the default trigger is **double-click**, leaving the single click free for selection work — a *plain* click only marks the row (active row + range anchor) and never ticks the checkbox, while a **modified** click is a selection gesture and never runs a bound action (see the selection-gestures bullet). Keyboard nav needs `gestures()` **and** a table the keyboard can drive row by row — record actions **or** `selectable()`/`bulkActions()` (`Table::usesGridSemantics()` is the single owner of that decision; see the gesture-layer bullet): `role="grid"`, roving `tabindex`, ↑/↓ move the active row, Enter/Shift+Enter run the primary/secondary, Menu key **and Shift+F10** open the context menu, `?` opens the shortcut help, and each action's `keyboardShortcut()` fires against the active row. **Pointer and keyboard share one active row**: a click marks the row (an Alpine `:class`/`:tabindex` binding, so the marker and the tabstop survive the Livewire morph every update triggers, follow the record through a re-sort, and fall back to the first row when it leaves the page), the active row drops its hover tint so `hover:bg-*` cannot paint over the marker, keys reach the grid only when a **row itself** has the focus (a keystroke inside a row button/editable cell/dropdown is that element's), the grid is inert while a dialog is open, and closing a modal hands the focus back to the active row. Style with `recordActionHover('primary')` (else neutral) and `activeRowClass(...)`. Desktop pointer + keyboard feature; touch cards and sub-rows are excluded by design.
- **Selection gestures (mouse + keyboard), one shared Alpine component.** Every selection surface — the checkboxes, both select-all toggles, the bulk bar, the mobile cards, the keyboard — drives one `wireRecordSelection` component reached via `[data-selection-root]` (optimistic; no per-keystroke roundtrip). Mouse: the **whole selection cell** toggles (`[data-select-cell]`, not just the 16px box, and the cell is registered interactive so the click never reaches a record action), Shift+click ranges from the anchor, mod+click toggles one row anywhere on it, mod+Shift+click adds a block, and **dragging down the checkbox column sweeps** rows in (additive only, mouse only, engages on the first row-changing move so a plain click stays a click). Keyboard: Space toggles + anchors, Shift+↑/↓ ranges, Shift+Home/End and mod+Shift+↑/↓ range to the edge, Home/End/PageUp/PageDown navigate, mod+A selects the page. **A range writes `base ∪ range`, not the range alone** — the snapshot minus the contiguous block around the anchor, so rows selected elsewhere survive; a range gesture **never rewrites `mode`**, which means in "all matching" mode (where the stored list is the *exclusions*) a range **deselects** and mod+A stands down. The anchor is one-shot and invisible; with no anchor of its own the range grows from the far edge of the block the active row sits in. Drop single rows with Space or mod+click, not a range. Keys reach the grid only when a **row itself** has the focus. **The grid reserves the keys it navigates with** — `->onKey()` on Enter/Space/arrows/Home/End/PageUp/PageDown/ContextMenu/F10/`?` throws a `TableConfigurationException` at configuration time rather than dropping the binding silently (a `keyboardShortcut()` on the action itself is only skipped); `Backspace` is deliberately not reserved and acts as a JS-side alias of `Delete`. ARIA: `aria-rowcount`, `aria-multiselectable`, `aria-rowindex` counted through the **whole result set** (not the page), bound `aria-selected` per row, and a polite live region that is in the DOM from the first paint and empty until the first change. The active-row marker is a tint **plus** a leading stripe (`activeRowClass()` replaces both halves) — the tint alone is ~1.1:1, under the 3:1 non-text contrast floor. `Table::shortcutLegend()` returns the same gesture list as data (`ShortcutHint` value objects) for rendering elsewhere.
- **Empty-state actions — the way out of an empty table.** `Table::emptyStateActions([...])` renders actions inside the empty state (`->emptyState()` only sets heading/description/icon), typically "create the first record". It accepts a row `Action` **or** a `HeaderAction`, and the empty state is a **record-less** surface: both kinds execute through the header-action host methods (`executeHeaderAction` / `openHeaderActionModal`), never the row pipeline, so `->form()`, `->requiresConfirmation()` and the modal stack all work unchanged while `findHeaderAction()` searches both surfaces. Three rules follow from having no record: only a **static** `->url('/posts/create')` resolves (a per-record `->url(fn ($record) => …)` closure stays unresolved and the action renders as a plain button); an empty-state action needs a **name of its own**, because sharing one with a header action renders both when the table is empty (duplicate `data-testid`, and a `keyboardShortcut()` window listener bound twice); and they are **not** shown when a *filter* emptied the table — that state keeps offering the filter reset, since the records exist behind the filter. Under `stackedOnMobile()` the card empty state renders the same actions from a shortcut-stripped copy, so a browser-test selector matches twice (one per layout) and only one binds the shortcut.
- `Table::rowContextMenu([...actions])` is **deprecated** (removed in v2.0) — a thin alias that still feeds the same context menu. Prefer `recordAction(Action::make('edit')->onContextMenu())`.
- **Mobile (`Table::stackedOnMobile()`).** Below the breakpoint each row becomes a card whose hierarchy is five derived slots — title (first column), metric (last right-aligned, e.g. `money()`), meta (badge columns), subtitle, and a label/value grid for the rest — overridable per column (`->mobileMetric()`, `->mobileMeta()`, …) or per table (`->mobileCard(fn (MobileCardConfig $c) => $c->title('number')->metric('total'))`). The header row is hidden, so its controls move into the card view: an always-visible select-all strip, a sort control, sub-row children with their subtotal, the summary totals, and the **empty state** — which is the same canonical surface as the desktop one, so a custom icon/description, the filter-empty reset and `emptyStateActions()` all render on a phone. `->collapseActionsOnMobile()` folds row actions into one dropdown. **Record actions fall back to buttons here**: every record trigger is a desktop one, so a behaviour-only record action renders as an ordinary button on the card (and only there) — one declaration, a gesture on the desktop and a button on a phone. It never doubles an action already in `->actions()`, one referenced by name, or one promoted with `->alsoInRowActions()`, and the fallback buttons count towards `collapseActionsOnMobile()`; the card's copy drops the action's `keyboardShortcut()` (`HasKeyboardShortcut::withoutKeyboardShortcut()`), because a rendered button binds its shortcut as a *window* listener and the cards are in the document at every width. Opt out with `Table::recordActionButtonsOnMobile(false)`. **The toolbar folds too, separately**: `Table::collapseHeaderActionsOnMobile(bool $collapse = true, int $threshold = 2)` collapses the *header* actions into one `ActionGroup` dropdown — no `stackedOnMobile()` needed (the toolbar is the same at every width), switched on the table's `mobileBreakpoint()` (`sm` by default) rather than the stacking breakpoint, counting only actions the viewer may run, and rendering the folded copy shortcut-less for the same window-listener reason.
- `Table::queryString()` persists state to the URL.
- Browser-testing hooks: every active part carries a stable `data-testid` — `table-search`, `table-filters-trigger`, `table-filter-reset`, `filter-chip-{name}`, `column-filter-chip-{name}`, `table-column-toggle`, `table-per-page`, `table-page-prev|next|{n}`, `table-sort-{col}`, `table-filter-{col}`, `table-cell-{col}`, `table-editable-{col}`, `table-row` (+ `data-row-key`; mobile `table-card`), `table-select-all` / `table-row-select`, `table-row-expand`, `table-bulk-bar` / `table-deselect`, and `action-{name}` / `header-action-{name}` / `bulk-action-{name}` / `menu-action-{name}` (all with `aria-label`) — so Pest v4 Browser Testing targets them at the user level. An **empty-state action reuses the testid of its kind**, and under `stackedOnMobile()` it matches twice (desktop table + card layout), so select the visible one. Actions and filter options are also reachable by visible text. Column-static render metadata is resolved once per column (`$columnMeta`) instead of per cell.

### Performance

The table renders each cell and each action **once per row**, so per-row cost scales with
rows × columns (× actions). Keep the per-row work cheap and lean on the levers the package
already gives you:

- **A page size is what bounds a table's memory, and `'all'` removes it.** `Table::perPageOptions([10, 25, 50, 'all'])` adds a "show everything on one page" option (`Table::perPage('all')` makes it the default). It is stored as the integer `Table::PER_PAGE_ALL` (`-1`) — the word never survives configuration, because the select, the `per_page` query-string parameter and the query cache key all compare page sizes strictly as integers — and it is paginated by counting first, since a negative limit would give the paginator a negative page count. Deliberately **not** among the shipped `[10, 25, 50, 100]`: the host clamps any page size the table does not offer back to `perPage()`, which is the same guard that stops a forged `perPage: 500000`, so a table only reads its whole source into memory when it said `'all'` itself. There is **no** ceiling behind it (unlike `bulkMaxRecords()`), so it belongs on a table whose row count is known, not on one over an unbounded source.
- **Defer off-screen tables.** `Table::lazy()` returns no rows and runs no query until the
  table scrolls into view (optional `->lazyPlaceholder(...)`). Use it for tables below the fold
  or in tabs. It defers the JS too: the table's Alpine bundles ship with the *deferred* render,
  which is safe because every bundle registers unconditionally (not only from `alpine:init`)
  and Livewire runs a response's new `@@assets` to completion before morphing it in. So
  `lazy()` is a lever for query cost, render cost **and** first-paint script weight.
- **Defer action-group menus.** `ActionGroup::make([...])->lazyMenu()` ships only the trigger plus
  a serialized item spec per row and builds the menu client-side on first open — zero per-row menu
  Blade renders (an eager group renders one view per item per row). Opt-in; the default is eager.
  Trade-offs: keyboard shortcuts and `wire:click` modifiers on menu items are not wired in lazy
  mode. Reach for it on large tables whose every row carries a multi-item action dropdown.
- **An inline edit re-renders the table, and the cell survives it.** `TextInputColumn` /
  `ToggleColumn` / `SelectColumn` commit through `updateTableCell`, which renders — everything
  derived from the written value (summaries, rollups, a badge computed from the same column, the
  row's position under the current sort) is stale otherwise. The cell keeps its own Alpine state
  because its root carries `wire:ignore.self`, which is also why the fresh value cannot reach it
  through that root: Livewire stops updating an ignored element's own attributes after the first
  render. It arrives on a **sync node**, a child element the morph does update and the cell
  watches. Never move `data-server-value` / `data-record-version` back onto the ignored root, and
  never write one without the other — that wakes the observer against a frozen partner and the
  edit vanishes from the screen a second after it reached the database.
  `Table::refreshAfterEdit(false)` opts back out for a table where the render is expensive and
  nothing on screen depends on the edited value.
- **Every skip goes through `WithTable::skipTableRender()`, every view change through
  `markTableViewChanged()`.** Never call `skipRender()` directly. Livewire merges everything queued
  for one component into a single request, so a cell save, a fill or a poll tick can arrive
  together with the user changing the page size, search, a filter, the sort, the page or which
  columns are visible. Skipping there answers with no HTML at all — the new state lands in the
  snapshot while the browser keeps the old rows until the user does something else. Note the
  asymmetry that makes `markTableViewChanged()` more than a flag: property updates (the per-page
  select) always reach the component before any call, but `setPage()` is itself a *call*, and the
  browser queues the cell edit **first** — the pagination click blurs the input on its way — so the
  skip is already granted by then and has to be taken back.
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
- **Column-static metadata is already resolved once per column** — `Support\ColumnRenderPlan`
  owns it and the table view reads it as `$columnMeta`, keyed by column name and carrying each
  column's compiled `<td>` skeleton. Hidden columns / non-executable actions short-circuit to an
  empty render. You get all of that for free; never re-derive it per cell.

Use `describe-table` on an existing component to see its resolved columns, filters and actions.
