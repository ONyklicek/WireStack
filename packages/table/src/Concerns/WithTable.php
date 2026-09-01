<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\Concerns\InteractsWithActions;
use NyonCode\WireCore\Core\Data\PagingRequest;
use NyonCode\WireCore\Core\Events\CellUpdating;
use NyonCode\WireCore\Core\Query\QueryPlan;
use NyonCode\WireCore\Core\State\StateContainer;
use NyonCode\WireCore\Core\Support\Deprecation;
use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireCore\Core\Validation\ValidationPipeline;
use NyonCode\WireCore\Foundation\Concerns\InteractsWithPartials;
use NyonCode\WireCore\Notifications\Notification;
use NyonCode\WireForms\Concerns\DispatchesStateUpdates;
use NyonCode\WireForms\Concerns\InteractsWithActionForms;
use NyonCode\WireForms\Concerns\InteractsWithFieldActions;
use NyonCode\WireForms\Concerns\InteractsWithFileUploads;
use NyonCode\WireForms\Concerns\InteractsWithRepeaters;
use NyonCode\WireForms\Concerns\InteractsWithSelectCreation;
use NyonCode\WireForms\Concerns\InteractsWithWizards;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Data\EloquentDataSource;
use NyonCode\WireTable\Events\TableRecordsChanged;
use NyonCode\WireTable\Export\ExportAction;
use NyonCode\WireTable\Export\ExportFormat;
use NyonCode\WireTable\Export\Jobs\RunExportJob;
use NyonCode\WireTable\Export\TableExport;
use NyonCode\WireTable\Filters\Filter;
use NyonCode\WireTable\Import\ImportAction;
use NyonCode\WireTable\Import\ImportResult;
use NyonCode\WireTable\Import\Jobs\RunImportJob;
use NyonCode\WireTable\Import\TableImport;
use NyonCode\WireTable\Preferences\Contracts\TablePreferenceDriver;
use NyonCode\WireTable\Preferences\TablePreferenceManager;
use NyonCode\WireTable\Preferences\TableViewPayload;
use NyonCode\WireTable\Services\CellEditPipeline;
use NyonCode\WireTable\Services\SubRowQuery;
use NyonCode\WireTable\Services\SummaryBatch;
use NyonCode\WireTable\Services\SummarySet;
use NyonCode\WireTable\Services\TableQueryCacheKey;
use NyonCode\WireTable\Services\TableQueryEvents;
use NyonCode\WireTable\Services\TableQueryService;
use NyonCode\WireTable\Services\WriteGeneration;
use NyonCode\WireTable\Support\CellEditOutcome;
use NyonCode\WireTable\Support\RowRenderer;
use NyonCode\WireTable\Support\RowStamps;
use NyonCode\WireTable\Support\StateInvalidation;
use NyonCode\WireTable\Support\TablePartials;
use NyonCode\WireTable\Support\TableRenderPlan;
use NyonCode\WireTable\Table;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

use function Livewire\store;

/** @phpstan-require-extends Component */
trait WithTable
{
    use CanExpandSubRows;
    use CanFillCells;
    use CanGroupRecords;
    use CanSelectRecords;
    use DispatchesStateUpdates;
    use HasSqlDebug;

    // Shared, form-agnostic action engine (wire-core) + the form-hosting bridge
    // (wire-forms). WithTable keeps thin, record-scoped wrappers on top; the
    // bridge overrides the engine's form extension points.
    // The last four lines were silent overrides while these methods lived in this
    // trait's own body: a method defined here beats an imported trait's without
    // any insteadof, and without any trace. Splitting them into
    // InteractsWithTableActions turned them into real collisions, which is an
    // improvement — the table's answers now say out loud that they replace the
    // engine's defaults.
    use InteractsWithActionForms, InteractsWithActions, InteractsWithTableActions {
        InteractsWithActionForms::validateMountedActionForm insteadof InteractsWithActions;
        InteractsWithActionForms::resolveHaltModalForm insteadof InteractsWithActions;
        InteractsWithActionForms::getActionModalFormInstance insteadof InteractsWithActions;
        InteractsWithActionForms::getActionModalFormInstanceForDepth insteadof InteractsWithActions;
        InteractsWithTableActions::haltModalFormStatePath insteadof InteractsWithActionForms;
        InteractsWithTableActions::afterActionExecuted insteadof InteractsWithActions;
        InteractsWithTableActions::resolveActionRecordIds insteadof InteractsWithActions;
        InteractsWithTableActions::sendActionNotification insteadof InteractsWithActions;
    }
    use InteractsWithFieldActions;
    use InteractsWithFileUploads;

    // Lets a write render the regions it touched instead of the whole table. The
    // engine is here; the table's own anchors and the flag that turns them on are
    // the next slice — nothing calls renderPartial() yet.
    use InteractsWithPartials;
    use InteractsWithRepeaters;
    use InteractsWithSelectCreation;
    use InteractsWithTableModals;
    use InteractsWithWizards;

    // Aliased so setPage() below can drop the record memo and then delegate:
    // WithPagination is a trait, so there is no parent:: to call through.
    use WithPagination {
        setPage as protected paginatorSetPage;
    }
    use WithTableQueryString;

    /**
     * Unified state container replacing 26 individual public properties.
     *
     * All table state (sort, filters, selection, modal, halt, polling, etc.)
     * is stored in this single container and synchronized via TableStateSynthesizer.
     *
     * Access state via: $this->tableState->get('sort.column')
     * Legacy property access ($this->tableSortColumn) is supported via __get/__set.
     *
     * @see TableStateSchema for path definitions and defaults
     */
    public StateContainer $tableState;

    // $actionModalFormInstance and $haltModalFormInstance come from
    // InteractsWithActionForms; $actionModalInfolistInstance and
    // $actionModalConfigCache from InteractsWithActions. This trait used to
    // redeclare all four identically — legal, because PHP only rejects an
    // *incompatible* redeclaration, and therefore invisible.

    /**
     * Previous modal form-data values captured in updatingTableState() so the
     * matching field's afterStateUpdated() callback receives `$old`.
     *
     * @var array<string, mixed>
     */
    protected array $modalStateBeforeUpdate = [];

    /**
     * Whether something that shapes what the table renders (per-page, search,
     * filters, sort, the page, which columns are visible) changed in this
     * request. Livewire merges everything queued for one component into a single
     * commit, so a wire:poll tick or an inline-edit call can share a request with
     * the user's change — and their "leave the DOM alone" verdict would then
     * throw away the render that change was made for. Every skip goes through
     * {@see skipTableRender()}, every change through {@see markTableViewChanged()}.
     */
    protected bool $tableStateChangedThisRequest = false;

    protected string $wireTableClass = Table::class;

    protected ?Table $tableInstance = null;

    /** @var LengthAwarePaginator|Paginator|CursorPaginator|Collection|null Cached records for current request lifecycle */
    protected LengthAwarePaginator|Paginator|CursorPaginator|Collection|null $cachedRecords = null;

    /**
     * @var TableRenderPlan|null What this render resolved, shared by the view and
     *                           any island body. Held for the length of ONE
     *                           render — see tableRenderPlan().
     */
    protected ?TableRenderPlan $renderPlan = null;

    /**
     * Totals resolved so far this render, keyed by scope.
     *
     * @var array<string, array<string, array<int, array<string, mixed>>>>
     */
    protected array $summaryMemo = [];

    /**
     * Sub-row grand totals resolved so far this render, keyed by scope.
     *
     * @var array<string, array<string, array<int, array<string, mixed>>>>
     */
    protected array $subRowGrandTotalMemo = [];

    /** @var Builder<Model>|null Cached query builder so summaries don't re-plan the query */
    protected ?Builder $cachedQuery = null;

    /** @var TableQueryService|null Shared query service instance */
    protected ?TableQueryService $queryService = null;

    // $cachedSelectedRecords comes from CanSelectRecords.

    /**
     * Initialize table state via StateContainer.
     */
    public function mountWithTable(): void
    {
        // Seeded with the bare defaults before getTable(), because a consumer's
        // table() may read state while it configures — then replaced with what
        // this table's own configuration decides.
        $this->tableState = new StateContainer(TableStateSchema::defaults());

        $table = $this->getTable();

        $this->tableState->replace(TableStateSchema::initialFor($table));

        // Per-user view layout (columns, sub-row expansion): a saved preference
        // (if any) overrides the configured defaults above.
        $this->loadViewPreferences($table);

        // Query-string persistence: seed state from the URL (URL wins over
        // the defaults applied above) and register URL-tracking attributes.
        $this->initializeTableQueryString($table);
    }

    // ==========================================
    // Backward Compatibility (Deprecated Properties)
    // ==========================================

    /**
     * Magic getter for backward compatibility with legacy property names.
     *
     * @deprecated Access state via $this->tableState->get() instead.
     */
    public function __get($name): mixed
    {
        $map = TableStateSchema::legacyPropertyMap();

        if (isset($map[$name]) && isset($this->tableState)) {
            Deprecation::property(static::class, $name, "tableState->get('{$map[$name]}')");

            return $this->tableState->get($map[$name]);
        }

        // Let parent __get handle it (Livewire trait magic)
        if (is_subclass_of(static::class, Component::class)) {
            return parent::__get($name);
        }

        return null;
    }

    /**
     * Magic setter for backward compatibility with legacy property names.
     *
     * @deprecated Access state via $this->tableState->set() instead.
     */
    public function __set($name, $value): void
    {
        $map = TableStateSchema::legacyPropertyMap();

        if (isset($map[$name])) {
            if (! isset($this->tableState)) {
                // tableState not yet initialised — mountWithTable() hasn't run.
                // This write will be overwritten when mount runs (filter/sort/pagination
                // defaults are applied there). Move legacy writes into mountWithTable().
                $this->tableState = new StateContainer(TableStateSchema::defaults());
            }

            Deprecation::property(static::class, $name, "tableState->set('{$map[$name]}', \$value)");
            $this->tableState->set($map[$name], $value);

            return;
        }

        // Let parent __set handle it (Livewire trait magic)
        if (is_subclass_of(static::class, Component::class) && method_exists(get_parent_class(static::class), '__set')) {
            parent::__set($name, $value);
        }
    }

    /**
     * Magic isset for backward compatibility with legacy property names.
     */
    public function __isset($name): bool
    {
        $map = TableStateSchema::legacyPropertyMap();

        if (isset($map[$name])) {
            return $this->tableState->has($map[$name]);
        }

        if (is_subclass_of(static::class, Component::class)) {
            return parent::__isset($name);
        }

        return false;
    }

    /**
     * Livewire lifecycle hook for StateContainer property updates.
     *
     * Called by Livewire when any nested path on $tableState changes.
     * Handles page resets for search/filter/sort/perPage changes.
     */
    /**
     * Livewire hook: snapshot a modal form field's previous value before it
     * changes, so updatedTableState() can pass `$old` to afterStateUpdated().
     */
    public function updatingTableState(mixed $value, string $path): void
    {
        if ($this->isModalFormDataPath($path)) {
            $this->modalStateBeforeUpdate[$path] = $this->tableState->get($path);
        }
    }

    public function updatedTableState(mixed $value, string $path): void
    {
        // `mode` decides what `selection.records` means — an entangled write
        // outside the two known shapes is corruption ({@see normalizeSelectionMode}).
        if ($path === 'selection.mode') {
            $this->normalizeSelectionMode();
        }

        // What the write invalidates is a decision about the path; doing the
        // resetting is this host's job. See StateInvalidation for the rules,
        // including why a re-sort leaves the selection alone and a filter does
        // not.
        $invalidated = StateInvalidation::forPath($path);

        if ($invalidated !== null) {
            if ($invalidated->normalisesPerPage) {
                $this->normalizePerPage();
            }

            if ($invalidated->marksViewChanged) {
                $this->markTableViewChanged();
            }

            if ($invalidated->resetsSelectionScope) {
                $this->resetSelectionScope();
            }

            if ($invalidated->resetsPage) {
                $this->resetPage();
            }

            if ($invalidated->clearsCursor) {
                $this->tableState->set('pagination.cursor', null);
            }

            return;
        }

        // A field inside an action/halt modal form changed — run its reactive
        // afterStateUpdated() callback against the live form-data bag.
        if ($this->isModalFormDataPath($path)) {
            $old = $this->modalStateBeforeUpdate[$path] ?? null;
            unset($this->modalStateBeforeUpdate[$path]);

            $forms = array_filter([
                $this->getActionModalFormInstance(),
                $this->getHaltModalFormInstance(),
            ]);

            $this->dispatchAfterStateUpdated($forms, 'tableState.'.$path, $old);
            $this->dispatchLiveValidation($forms, 'tableState.'.$path);
        }
    }

    /**
     * Coerce the page size the client just sent back into something the table
     * actually offers.
     *
     * The select posts its value as a numeric *string*, which would otherwise
     * travel all the way into the cache key and the query-string "except"
     * comparison as `"25" !== 25`. And nothing stops a crafted payload from
     * writing `perPage: 500000` over the wire, which is a page-sized read of
     * the whole table — so anything outside the offered options falls back to
     * the configured default.
     *
     * That clamp is also the whole gate on {@see Table::PER_PAGE_ALL}: the
     * sentinel is a legal page size only on a table that listed `'all'` among
     * its options, and a forged one falls back like any other.
     */
    protected function normalizePerPage(): void
    {
        $table = $this->getTable();
        $value = $this->tableState->get('pagination.perPage');

        $perPage = is_numeric($value) ? (int) $value : 0;

        if (! in_array($perPage, $table->getPerPageOptions(), true)) {
            $perPage = $table->getPerPage();
        }

        $this->tableState->set('pagination.perPage', $perPage);
    }

    /**
     * Whether a tableState sub-path points at a field inside an open modal form.
     */
    private function isModalFormDataPath(string $path): bool
    {
        return (str_starts_with($path, 'modal.actions.') && str_contains($path, '.data.'))
            || str_starts_with($path, 'modal.halt.formData.');
    }

    // ==========================================
    // Table Configuration & Query Building
    // ==========================================

    /**
     * Get the configured table instance
     */
    public function getTable(): Table
    {
        if ($this->tableInstance === null) {
            $this->tableInstance = $this->table(($this->wireTableClass)::make());
            $this->tableInstance->livewireComponent($this);
        }

        return $this->tableInstance;
    }

    /**
     * Abstract method - must be implemented in the component
     */
    abstract public function table(Table $table): Table;

    /**
     * Get or create the TableQueryService.
     */
    protected function getQueryService(): TableQueryService
    {
        if ($this->queryService === null) {
            // Resolved, not shared: the service memoises the last query plan, so
            // a container singleton would leak one table's plan into the next.
            $this->queryService = app(TableQueryService::class);
        }

        return $this->queryService;
    }

    // ==========================================
    // Table Polling
    // ==========================================

    /**
     * Refresh table data (called by wire:poll).
     */
    public function refreshTable(): void
    {
        // Check if polling should be active
        if (! $this->shouldPoll()) {
            return;
        }

        // Don't re-render while any modal with a form is open. A poll re-render
        // hits the server before debounced wire:model.live values are synced,
        // causing morph to overwrite whatever the user has typed. Keeping
        // wire:poll in the DOM means polling resumes automatically on the next
        // tick once the modal closes — no extra work needed.
        if ($this->actionFrameCount() > 0 || $this->tableState->get('modal.halt.show')) {
            return;
        }

        // Opt-in change detection: skip the full render (query + summaries +
        // DOM morph) when a cheap checksum of the filtered data is unchanged.
        if ($this->shouldSkipPollRender()) {
            $this->skipTableRender();

            return;
        }

        // The table instance is recreated on each request, so dropping it is what
        // makes the poll fetch new data.
        $this->tableInstance = null;

        // …and where the table asked for row partials, send the rows that moved
        // rather than the page they sit in. This is the freshness half of the ERP
        // case: while several people edit one table, a colleague's write should
        // repaint their row and leave everything else — including whatever the
        // reader has half-typed in a cell of their own — untouched.
        $this->queueChangedRowPartials();
    }

    /**
     * Queue a partial for each row whose data moved since the last poll.
     *
     * Which rows changed is worked out **server-side, from this component's own
     * page**, and deliberately not carried on the broadcast: the channel is
     * scoped to a model class rather than to a viewer, so putting record keys on
     * it would tell every listener which records exist and change — including the
     * ones their own query would never return. The event stays a bare "something
     * moved" signal and each listener answers it for its own rows.
     *
     * A row is "the same row" by key and "unchanged" by a hash of its own
     * attributes. Deliberately not the `updated_at` the optimistic lock uses:
     * that column is stored to the second, so two writes inside one second look
     * identical and the second one would never be sent. Hashing what the record
     * holds costs nothing extra — the page is already in memory — and sees every
     * change to the record itself.
     *
     * It shares the blind spot of `pollChangeDetection()`'s default: a change
     * that never touches the parent row (a child-table rollup, a computed column)
     * is invisible to it, and a table that renders one should say so with a
     * `pollChangeDetection()` closure, which decides whether the poll renders at
     * all before this is reached.
     *
     * The hashes live in the poll state, which costs the snapshot a few bytes a
     * row and is why this only runs where row partials are on.
     *
     * **The key SET changing means a full render**, and that is not a shortcut: a
     * row that arrived, left, or moved under the sort is a change no per-row
     * partial can express — the page's shape moved, not a row's contents.
     */
    protected function queueChangedRowPartials(): void
    {
        $table = $this->getTable();

        if (! $table->usesRowPartials() || ! method_exists($this, 'renderPartial')) {
            return;
        }

        $key = $table->getPrimaryKey();

        $ordered = [];

        foreach ($this->getTableRecords() as $record) {
            $ordered[(string) $record->{$key}] = $record;
        }

        $stamps = RowStamps::of($ordered, $key);
        $changed = RowStamps::changed($this->tableState->get('polling.rows'), $stamps);

        $this->tableState->set('polling.rows', $stamps);

        // Null, not empty: the page holds different rows than last time, so no
        // per-row partial can describe what happened and the full render stands.
        if ($changed === null) {
            return;
        }

        // Nothing moved, and this knew it per row rather than from a checksum over
        // the set — so the poll can answer with nothing at all. That is the common
        // case on a table nobody is editing, and the cheapest answer there is.
        if ($changed === []) {
            $this->skipTableRender();

            return;
        }

        $partials = TablePartials::for($table, $this, $this->tableRenderPlan());
        $moved = array_intersect_key($ordered, array_flip($changed));

        foreach ($partials->rows($ordered, $changed) as $name => $html) {
            $this->renderPartial($name, $html);
        }

        foreach ($partials->satellites($moved) as $name => $html) {
            $this->renderPartial($name, $html);
        }
    }

    /**
     * Suppress this request's re-render — unless the request also changed what
     * the table renders.
     *
     * Several endpoints want the DOM left exactly as it is: a poll that found
     * nothing new, and every inline-edit call, whose optimistic cell state a
     * morph would reset. None of them may skip *unconditionally*, because
     * Livewire merges everything queued for one component into one commit: the
     * per-page select's update and an in-flight `updateTableCell()` arrive
     * together, updates applied first. Skipping there answers with no HTML at
     * all — the new page size is in the snapshot, so the server looks correct,
     * while the browser keeps the old rows until whatever the user does next
     * forces a render.
     *
     * The cell state a skip protects is moot in that case anyway: the rows it
     * belongs to are being replaced.
     */
    protected function skipTableRender(): void
    {
        if ($this->tableStateChangedThisRequest) {
            return;
        }

        if (method_exists($this, 'skipRender')) {
            $this->skipRender();
        }
    }

    /**
     * Record that this request changed what the table renders, and take back any
     * skip already granted for it.
     *
     * The taking-back is the point, and it is why this cannot be a bare flag.
     * A property update always reaches the component before any method call, so
     * the per-page select could be handled by having `skipTableRender()` consult
     * a flag. A *page* change cannot: `setPage()` is a method call like
     * `updateTableCell()`, they are ordered by when the browser queued them, and
     * the browser queues the edit FIRST — the input's blur fires on the way to
     * the pagination link that is about to be clicked. The skip was therefore
     * already applied by the time the page changed, and a flag consulted earlier
     * had nothing left to prevent.
     *
     * Same store `skipRender()` itself writes to, so this un-does exactly what
     * that did and nothing else — Livewire reads the value once, at render.
     */
    protected function markTableViewChanged(): void
    {
        $this->tableStateChangedThisRequest = true;

        // Guarded like skipTableRender(): WithTable is exercised on plain hosts
        // that are not Livewire components at all.
        if (method_exists($this, 'skipRender') && function_exists('Livewire\store')) {
            store($this)->set('skipRender', false);
        }
    }

    /**
     * The same verdict for a request that WRITES a cell — one the table usually
     * wants to render.
     *
     * An inline edit is rarely only about the cell it touched: summaries, group
     * rollups, footer totals and any column derived from the edited value all go
     * stale the moment the write lands, and skipping the render leaves them
     * showing the previous value until something else re-renders the page.
     *
     * The render used to be skipped to protect the cell's own optimistic state
     * from the morph. That protection is now the cell's: `wire:ignore.self` keeps
     * its root out of the morph, and it reconciles through its sync node, which
     * will not touch a value being typed or a write still in flight. So the
     * default flipped, and {@see Table::refreshAfterEdit()} is the way back for a
     * table where the extra query per edit is not worth it.
     */
    protected function skipTableRenderAfterWrite(): void
    {
        if ($this->getTable()->shouldRefreshAfterEdit()) {
            return;
        }

        $this->skipTableRender();
    }

    /**
     * Compare the poll checksum with the previous one; true = data unchanged.
     *
     * The new checksum is stored in state either way, so the next poll
     * compares against the latest observed data.
     */
    protected function shouldSkipPollRender(): bool
    {
        $detector = $this->getTable()->getPollChangeDetection();

        if ($detector === false) {
            return false;
        }

        // The user changed the view in this same pooled request. The data may
        // well be unchanged, but the rendering of it is not — skipping here
        // would swallow their per-page/sort/filter change until the next
        // roundtrip.
        if ($this->tableStateChangedThisRequest) {
            return false;
        }

        $checksum = $this->computePollChecksum($detector);

        // No checksum available (e.g. model without timestamps) — always render.
        if ($checksum === null) {
            return false;
        }

        $previous = $this->tableState->get('polling.checksum');
        $this->tableState->set('polling.checksum', $checksum);

        return $previous !== null && $previous === $checksum;
    }

    /**
     * Checksum of the current filtered data set.
     *
     * Default (true): COUNT(*) + MAX(updated_at) in one query. This misses
     * changes that don't touch the parent row (e.g. child-table rollups) —
     * pass a closure to pollChangeDetection() for those cases.
     */
    protected function computePollChecksum(bool|callable $detector): ?string
    {
        $query = (clone $this->buildTableQuery())->reorder();

        if ($detector !== true) {
            return (string) $detector($query);
        }

        // The COUNT/MAX half belongs to whatever the rows come from, so it is
        // asked of the source rather than assembled here. Built over *this*
        // query — the narrowed one — because that is the set being polled, not
        // the table's base query.
        $token = (new EloquentDataSource($query))->changeToken(new QueryPlan);

        if ($token === null) {
            return null;
        }

        // The write generation is the third term, and it is what makes this
        // usable. `updated_at` is stored to the second, so an edit landing in the
        // same second as the tick that took the last checksum is indistinguishable
        // from no edit at all — and the change is then missed for good, not merely
        // shown late, because the next tick compares against that same second. The
        // counter moves on every write through a table whatever the clock says,
        // while COUNT and MAX still catch a write that never went through one.
        // It is the half no data source can answer for: it is about this
        // application's writes, not about the dataset.
        $generation = app(WriteGeneration::class)->current($this->queryCacheScope($this->getTable()));

        return $token.'|'.$generation;
    }

    /**
     * Check if polling should be active.
     */
    public function shouldPoll(): bool
    {
        if (! $this->tableState->get('polling.active')) {
            return false;
        }

        $table = $this->getTable();

        if (! $table->isPolling()) {
            return false;
        }

        $condition = $table->getPollingCondition();

        if ($condition) {
            return call_user_func($condition, $this);
        }

        return true;
    }

    /**
     * Pause table polling.
     */
    public function pauseTablePolling(): void
    {
        $this->tableState->set('polling.active', false);
    }

    /**
     * Resume table polling.
     */
    public function resumeTablePolling(): void
    {
        $this->tableState->set('polling.active', true);
    }

    /**
     * Toggle table polling.
     */
    public function toggleTablePolling(): void
    {
        $this->tableState->set('polling.active', ! $this->tableState->get('polling.active'));
    }

    /**
     * Get polling configuration for view.
     */
    public function getTablePollingConfig(): array
    {
        $table = $this->getTable();

        if (! $table->isPolling()) {
            return ['enabled' => false];
        }

        return array_merge($table->getPollingConfig(), ['active' => $this->tableState->get('polling.active') && $this->shouldPoll()]);
    }

    /**
     * Get wire:poll attribute for table container.
     */
    public function getTablePollingAttribute(): ?string
    {
        $table = $this->getTable();

        if (! $table->isPolling() || ! $this->shouldPoll()) {
            return null;
        }

        $directive = $table->getPollingDirective();

        if (! $directive) {
            return null;
        }

        // Add method name
        return $directive.'="refreshTable"';
    }

    /**
     * Load the table data (called when lazy loading is ready)
     */
    public function loadTable(): void
    {
        $this->tableState->set('ready', true);
    }

    /**
     * What this render resolved — see {@see TableRenderPlan}.
     *
     * Deliberately NOT memoised across renders. The plan reads table state, and
     * a request is free to write state before it renders (a filter, a cell edit,
     * a page change); a memo living longer than one render would hand the second
     * render the first one's answers. {@see getTableProperty()} therefore drops
     * it as each render begins, and this rebuilds on demand — which is exactly
     * what the view's `@php` block used to do, so it costs nothing new.
     *
     * The memo is what makes it shareable WITHIN a render: the main view and
     * every island body resolve the same instance rather than each rebuilding.
     *
     * **Resolved on first use, not handed to the view.** That is load-bearing
     * rather than lazy for its own sake: a view is allowed to reconfigure the
     * table before the part that reads the plan renders, and one does —
     * `wire-sortable`'s table view applies the user's persisted column order by
     * calling `$table->columns(...)` in its own `@php` block, ahead of including
     * wire-table's view. A plan built in this method, when the innermost view
     * asks for it, sees that order; a plan built here in `getTableProperty()` saw
     * the order the component declared and silently undid the reorder.
     */
    public function tableRenderPlan(): TableRenderPlan
    {
        // The records are sourced here rather than required as an argument so an
        // island body can ask for the plan with nothing but the component. They
        // are memoised by getTableRecords() for every path except lazy-not-ready,
        // which returns a fresh empty collection each call — value-identical, so
        // the plan agrees with the view either way.
        return $this->renderPlan ??= TableRenderPlan::build(
            $this->getTable(),
            $this,
            $this->getTableRecords(),
        );
    }

    /**
     * Render the table view.
     */
    public function getTableProperty(): View
    {
        $table = $this->getTable();

        $viewName = method_exists($this, 'getTableView')
            ? $this->getTableView()
            : (method_exists($table, 'getViewName') ? $table->getViewName() : 'wire-table::tables.index');

        // A new render, so a new plan. Dropped rather than built: the view
        // resolves it when it first needs it — see tableRenderPlan().
        $this->renderPlan = null;
        $this->summaryMemo = [];
        $this->subRowGrandTotalMemo = [];

        return view($viewName, [
            'table' => $table,
            'records' => $this->getTableRecords(),
            'component' => $this,
        ]);
    }

    // ==========================================
    // Query Building (delegated to TableQueryService)
    // ==========================================

    /**
     * Get paginated records for the table.
     *
     * Delegates query building to TableQueryService which uses
     * QueryPlanner + QueryExecutor from wire-core.
     *
     * Results are cached within the current request lifecycle to prevent
     * duplicate queries when areAllVisibleSelected() or selectAllRecords()
     * call this method after the initial render.
     */
    public function getTableRecords(): LengthAwarePaginator|Paginator|CursorPaginator|Collection
    {
        if ($this->cachedRecords !== null) {
            return $this->cachedRecords;
        }

        // Allow plugin traits to intercept record fetching (e.g. reorder mode)
        if (method_exists($this, 'interceptTableRecords')) {
            $intercepted = $this->interceptTableRecords();
            if ($intercepted !== null) {
                $this->cachedRecords = $intercepted;

                // An intercepted set is still a page of records, and its sub-rows
                // still have to be batched. Returning without this sent reorder
                // mode down the per-parent N+1 the eager load exists to remove —
                // on the one mode that also drops pagination, so with the most
                // parents on the page. Pinned by wire-sortable's
                // ReorderSubRowLoadTest.
                $this->eagerLoadSubRows($this->cachedRecords);

                return $this->cachedRecords;
            }
        }

        $table = $this->getTable();

        // If lazy loading is enabled and not ready, return empty collection
        if ($table->isLazy() && ! $this->tableState->get('ready')) {
            return collect();
        }

        $records = $this->fetchTableRecords($table);

        // The stored page can point past the end of the result set — a shared
        // ?page=5 URL, a filter that shrank the set, or rows deleted by someone
        // else since the page was opened. Re-anchor to the last populated page
        // and fetch it, so an out-of-range page never renders as "no records".
        if ($this->rehomeOutOfRangePage($records)) {
            $records = $this->fetchTableRecords($table);
        }

        $this->cachedRecords = $records;

        // Eager-load sub-rows for the page in one query (avoids per-parent N+1).
        $this->eagerLoadSubRows($this->cachedRecords);

        return $this->cachedRecords;
    }

    /**
     * Livewire's page setter, with the record memo dropped.
     *
     * Paging normally arrives as a fresh request, where the memo is empty anyway.
     * Called within one request, though — which is what a "select this page"
     * after a programmatic setPage() does — the memo would still hold the
     * previous page and the caller would act on the wrong rows.
     */
    public function setPage($page, $pageName = 'page'): void
    {
        $this->paginatorSetPage($page, $pageName);

        // Every way to change page funnels through here — gotoPage(), nextPage(),
        // previousPage() and resetPage() all call it — so this is the one place
        // that has to say the rows on screen are no longer the right ones.
        $this->markTableViewChanged();

        $this->cachedRecords = null;
    }

    /**
     * Move a cursor-paginated table, which nothing else can do for it.
     *
     * Livewire's pagination is page-based — `previousPage()`, `nextPage()`,
     * `gotoPage()` — and offers no cursor equivalent, so a `CursorPaginator` had
     * no control that could drive it: the rows paged correctly but only through a
     * `cursor` query parameter nobody was setting. The cursor therefore lives in
     * table state, where the rest of this table's paging already lives, and the
     * pagination partial hands back the encoded cursor the paginator itself
     * produced.
     *
     * `null` returns to the first page, which is what the paginator means by an
     * absent cursor.
     */
    public function setTableCursor(?string $cursor = null): void
    {
        $this->tableState->set('pagination.cursor', $cursor);

        // Same reasoning as setPage(): the rows on screen are no longer the ones
        // a selection or a poll checksum was taken against.
        $this->markTableViewChanged();

        $this->cachedRecords = null;
    }

    /**
     * Run the table query for the current page, honouring the cache config.
     */
    protected function fetchTableRecords(Table $table): LengthAwarePaginator|Paginator|CursorPaginator|Collection
    {
        $query = $this->buildTableQuery();

        if ($table->isQueryCached()) {
            return $this->executeWithCache($table, $query);
        }

        if ($table->isPaginated()) {
            return $this->paginateQuery($table, $query);
        }

        return $this->readAll($query);
    }

    /**
     * Move the paginator back into range, reporting whether it moved.
     *
     * Only length-aware pagination can compute a last page; simple and cursor
     * modes have no total to clamp against, so the instanceof guard leaves
     * them alone. Page 1 always exists (even when empty), so an empty first
     * page is not a clamp.
     */
    protected function rehomeOutOfRangePage(mixed $records): bool
    {
        if (! $records instanceof LengthAwarePaginator) {
            return false;
        }

        if ((int) $this->getPage() <= 1) {
            return false;
        }

        $lastPage = max(1, $records->lastPage());

        if ($records->currentPage() <= $lastPage) {
            return false;
        }

        $this->setPage($lastPage);

        return true;
    }

    /**
     * Re-anchor the paginator when the current page no longer exists.
     *
     * Kept as the explicit post-mutation hook (a delete that empties the
     * current page re-anchors before the records are re-read), but the rule
     * itself now lives in getTableRecords(): every fetch clamps, so a page
     * that went out of range for any other reason — a shared ?page=5 URL, a
     * filter that shrank the set, a concurrent delete — is caught too.
     */
    public function clampPageToBounds(): void
    {
        $this->getTableRecords();
    }

    /**
     * Execute query with the appropriate pagination mode.
     *
     * @param  Builder<Model>  $query
     */
    protected function paginateQuery(Table $table, Builder $query): LengthAwarePaginator|Paginator|CursorPaginator
    {
        $perPage = (int) $this->tableState->get('pagination.perPage', 10);

        // The PER_PAGE_ALL sentinel is resolved by the source, which is where
        // the count it needs lives; it arrives here as a negative perPage and
        // leaves as one honest page.
        $paging = match ($table->getPaginationMode()) {
            'simple' => PagingRequest::simple($perPage),
            // The cursor comes from table state rather than the request:
            // Livewire's pagination is page-based and has no cursor of its own
            // to read.
            'cursor' => PagingRequest::cursor($perPage, $this->tableState->get('pagination.cursor')),
            default => PagingRequest::lengthAware($perPage),
        };

        // Built over this query — already searched, filtered and sorted — rather
        // than the table's own source, which wraps the base query. Same reason
        // the poll token is: what gets paged is this set, not the table's.
        $source = new EloquentDataSource($query);

        // The plan the query was built from, so a source that has to honour it
        // itself can. EloquentDataSource does not need it — this query is
        // already narrowed — but the contract is the same for every source, and
        // handing it an empty plan would be a lie about what was asked for.
        return $source->paginate(
            $this->getQueryService()->getLastPlan() ?? new QueryPlan,
            $paging,
        );
    }

    /**
     * Execute query with caching.
     *
     * @param  Builder<Model>  $query
     */
    protected function executeWithCache(Table $table, Builder $query): LengthAwarePaginator|Paginator|CursorPaginator|Collection
    {
        $ttl = $table->getQueryCacheTtl();

        // The namespace says which table; the state fingerprint says which
        // view of it. A caller-supplied cacheQuery() key replaces the former
        // only — it can never opt out of the latter, or the table would freeze
        // on whichever view happened to warm the entry.
        $key = app(TableQueryCacheKey::class)->build(
            $table->getQueryCacheKey() ?? $this->generateQueryCacheKey($query),
            $this->queryCacheState($table),
            app(WriteGeneration::class)->current($this->queryCacheScope($table)),
        );

        // The cache sits *above* the source, not inside it. What makes an entry
        // stale here is the write generation and the table's own view state —
        // facts about this host, not about the dataset — so a source that knew
        // how to cache would be caching the wrong thing.
        return Cache::remember($key, $ttl, function () use ($table, $query) {
            if ($table->isPaginated()) {
                return $this->paginateQuery($table, $query);
            }

            return $this->readAll($query);
        });
    }

    /**
     * Every matching row, unpaginated — the "no pagination" table and the
     * cached form of it.
     *
     * Goes through the source for the same reason paging does: one owner for
     * how rows are read, so a custom source is asked here too rather than only
     * on the paged path.
     *
     * @param  Builder<Model>  $query
     * @return Collection<int, mixed>
     */
    protected function readAll(Builder $query): Collection
    {
        return (new EloquentDataSource($query))->get(
            $this->getQueryService()->getLastPlan() ?? new QueryPlan,
        );
    }

    /**
     * Current page for the query cache key suffix.
     *
     * Cursor pagination encodes its position in the cursor parameter rather
     * than a page number, so the raw request value is used there.
     */
    protected function getQueryCachePage(): string
    {
        if ($this->getTable()->getPaginationMode() === 'cursor') {
            return (string) request()->query('cursor', '');
        }

        return (string) $this->getPage();
    }

    /**
     * State that shapes the cached slice and therefore belongs in the key.
     *
     * Search, filters and sort already reach the generated key through the
     * SQL and bindings, but a caller-supplied cacheQuery() key knows none of
     * them — and `perPage`/`page` reach neither key, because pagination is
     * applied inside the cache callback. Listing them all here keeps one
     * answer for both key flavours.
     *
     * @return array<string, mixed>
     */
    protected function queryCacheState(Table $table): array
    {
        $state = [
            'search' => $this->tableState->get('search'),
            'filters' => $this->tableState->get('filters', []),
            'columnFilters' => $this->tableState->get('columnFilters', []),
            'sort' => $this->tableState->get('sort', []),
        ];

        if (! $table->isPaginated()) {
            return $state;
        }

        return $state + [
            'perPage' => $this->tableState->get('pagination.perPage'),
            'page' => $this->getQueryCachePage(),
        ];
    }

    /**
     * The cache namespace for this table when cacheQuery() supplied no key.
     *
     * Override to scope entries by tenant, user or anything else the SQL does
     * not carry. The per-view state fingerprint is appended to whatever this
     * returns, so an override cannot accidentally collapse two views into one
     * entry.
     *
     * @param  Builder<Model>  $query
     */
    protected function generateQueryCacheKey(Builder $query): string
    {
        return app(TableQueryCacheKey::class)->namespaceFor($query);
    }

    /**
     * What a write invalidates: every cached slice sharing this scope.
     *
     * The model class, so two components listing the same records retire each
     * other's entries — a row edited in one table is stale in the other. A table
     * built from a bare `query()` has no model to name and falls back to its own
     * component, which is narrower but still correct for its own writes.
     *
     * Override to widen or narrow it (a tenant id, a parent record).
     */
    protected function queryCacheScope(Table $table): string
    {
        return $table->getModelClass() ?? static::class;
    }

    /**
     * Move this table's write generation on, because something wrote.
     *
     * Two readers, one counter (see {@see WriteGeneration}): it retires every
     * cached slice of the table at once, and it is the term that lets poll
     * change detection see a write that landed inside the same second as the
     * last checksum.
     */
    protected function recordTableWrite(): void
    {
        $table = $this->getTable();

        // Only the two readers care, and a bump is a write to the shared cache
        // store: a table that neither caches nor polls has nobody to tell.
        if (! $table->isQueryCached() && ! $table->isPolling()) {
            return;
        }

        app(WriteGeneration::class)->bump($this->queryCacheScope($table));
    }

    /**
     * Everything that has to happen elsewhere because this request wrote.
     *
     * The two are one decision, not two: the cached slices this table serves are
     * stale, and so is every other session's screen. Splitting them is how they
     * would come apart — a caller remembering one and not the other.
     */
    protected function announceTableWrite(): void
    {
        $this->recordTableWrite();

        $table = $this->getTable();

        if (! $table->shouldBroadcastChanges()) {
            return;
        }

        try {
            event(new TableRecordsChanged($this->queryCacheScope($table)));
        } catch (Throwable $e) {
            // The write has already committed. Every caller of this runs inside a
            // try/catch that turns a throw into "the save failed" — so a
            // broadcaster having a bad day would report a landed write as failed,
            // and the cell would roll itself back to a value the database no
            // longer holds. The user retypes an edit that was never lost.
            //
            // Made likelier by ShouldBroadcastNow, which puts the broadcaster's
            // HTTP call inline in that same try. The push is an optimisation with
            // a working fallback — polling — so it is never worth a wrong answer
            // about whether the write landed.
            //
            // Reported rather than swallowed: this belongs in the log, it just
            // does not belong in the response.
            report($e);
        }
    }

    /**
     * The channel other sessions are told about this table's writes on.
     *
     * Null unless `live(broadcast: true)` is on — the view uses it to decide
     * whether to subscribe at all, so a table without the opt-in ships no
     * listener and needs no channel authorization.
     */
    public function getTableLiveChannel(): ?string
    {
        $table = $this->getTable();

        return $table->shouldBroadcastChanges()
            ? TableRecordsChanged::channelFor($this->queryCacheScope($table))
            : null;
    }

    /**
     * Build the complete query with all modifications applied.
     *
     * Delegates to TableQueryService which uses the Core QueryPlanner
     * and QueryExecutor infrastructure. This replaces ~500 lines of
     * inline query building, accessor reflection, and metadata analysis.
     *
     * The resulting Builder is cached within the request lifecycle so that
     * computeTableSummaries() can reuse it without triggering a second full
     * planning pass (metadata registry + QueryPlanner + QueryExecutor).
     *
     * @return Builder<Model>
     */
    protected function buildTableQuery(): Builder
    {
        if ($this->cachedQuery !== null) {
            return clone $this->cachedQuery;
        }

        $table = $this->getTable();
        $baseQuery = $table->getQuery();
        $tableId = static::class;

        $search = $this->tableState->get('search');
        $filters = $this->tableState->get('filters', []);
        // Fall back to the configured default sort when no explicit sort is set, so
        // the rendered table and the export (getFilteredTableQuery) order identically.
        $sortColumn = $this->tableState->get('sort.column', '') ?: ($table->getDefaultSort() ?? '');
        $sortDirection = $this->tableState->get('sort.direction', '') ?: ($table->getDefaultSortDirection() ?? 'asc');
        $columnFilters = $this->tableState->get('columnFilters', []);

        // The four search/filter events bracket the build, so their two halves
        // cannot come apart — see TableQueryEvents.
        $query = app(TableQueryEvents::class)->around(
            $tableId,
            $table,
            $search,
            $filters,
            fn (): Builder => $this->getQueryService()->buildQuery(
                baseQuery: $baseQuery,
                table: $table,
                search: $search,
                filterValues: $filters,
                sortColumn: ! empty($sortColumn) ? $sortColumn : null,
                sortDirection: $sortDirection,
                columnFilterValues: $columnFilters,
            ),
        );

        $query = $this->applyGroupOrdering($query);

        $this->cachedQuery = $query;

        return $query;
    }

    /**
     * Check if table is ready to display data
     */
    public function isTableReady(): bool
    {
        return (bool) $this->tableState->get('ready', false);
    }

    // ==========================================
    // Sort, Search, Filter State Management
    // ==========================================

    /**
     * Sort table by column
     */
    public function sortTable(string $column): void
    {
        if ($this->tableState->get('sort.column') === $column) {
            $this->tableState->set('sort.direction', $this->tableState->get('sort.direction') === 'asc' ? 'desc' : 'asc');
        } else {
            $this->tableState->set('sort.column', $column);
            $this->tableState->set('sort.direction', 'asc');
        }

        $this->resetPage();
    }

    /**
     * Reset all filters
     */
    public function resetTableFilters(): void
    {
        $this->tableState->set('filters', []);
        $this->tableState->set('columnFilters', []);
        $this->tableState->set('search', null);
        $this->resetPage();
    }

    /**
     * Reset column filters only
     */
    public function resetColumnFilters(): void
    {
        $this->tableState->set('columnFilters', []);
        $this->resetPage();
    }

    /**
     * Clear a single filter (used by the indicator chips' remove buttons).
     */
    public function removeTableFilter(string $name): void
    {
        $filters = $this->tableState->get('filters', []);
        Arr::forget($filters, $name);

        // Prune empty parent nests left behind by a dotted (relation) filter name
        // so cleared filters don't accumulate stale [] containers in the state.
        if (str_contains($name, '.')) {
            $parent = substr($name, 0, strrpos($name, '.'));
            if (Arr::get($filters, $parent) === []) {
                Arr::forget($filters, $parent);
            }
        }

        $this->tableState->set('filters', $filters);
        $this->resetPage();
    }

    /**
     * Indicator labels for active filters, keyed by filter name.
     *
     * Drives the indicator chips rendered under the table toolbar.
     *
     * @return array<string, string>
     */
    public function getActiveFilterIndicators(): array
    {
        $filters = $this->tableState->get('filters', []);
        $indicators = [];

        foreach ($this->getTable()->getFilters() as $filter) {
            if (! $filter->canView()) {
                continue;
            }

            $indicator = $filter->getIndicator(data_get($filters, $filter->getName()));

            if ($indicator !== null) {
                $indicators[$filter->getName()] = $indicator;
            }
        }

        return $indicators;
    }

    /**
     * Indicator labels for active column header filters, keyed by column name.
     *
     * Column filter state is stored unwrapped under columnFilters.<name>, which
     * Filter::getIndicator() consumes directly (a scalar/keyed-array passes
     * through extractValue()), so header filters reuse the same chip pipeline as
     * the panel filters.
     *
     * @return array<string, string>
     */
    public function getActiveColumnFilterIndicators(): array
    {
        $values = $this->tableState->get('columnFilters', []);
        $indicators = [];

        foreach ($this->getTable()->getColumns() as $column) {
            $filter = $column->getFilter();
            if ($filter === null || ! $filter->canView()) {
                continue;
            }

            $indicator = $filter->getIndicator($values[$column->getName()] ?? null);

            if ($indicator !== null) {
                $indicators[$column->getName()] = $indicator;
            }
        }

        return $indicators;
    }

    /**
     * Clear a single column header filter (used by its indicator chip's remove
     * button), mirroring removeTableFilter() for panel filters.
     */
    public function removeColumnFilter(string $name): void
    {
        $columnFilters = $this->tableState->get('columnFilters', []);
        Arr::forget($columnFilters, $name);

        if (str_contains($name, '.')) {
            $parent = substr($name, 0, (int) strrpos($name, '.'));
            if (Arr::get($columnFilters, $parent) === []) {
                Arr::forget($columnFilters, $parent);
            }
        }

        $this->tableState->set('columnFilters', $columnFilters);
        $this->resetPage();
    }

    /**
     * Find a column by name
     */
    protected function findColumn(string $name): ?Column
    {
        return $this->getTable()->findColumn($name);
    }

    // ─── Summaries ───────────────────────────────────────

    /**
     * Compute all column summaries.
     * Returns an array keyed by column name.
     *
     * @param  string  $scope  'page' (current page), 'query' (all filtered),
     *                         'selection' (selected rows), or 'subRows'
     * @param  mixed  $parentRecord  Parent record (only for 'subRows' scope)
     * @param  Collection<int, mixed>|null  $subRecords  Pre-fetched sub-rows (avoids a
     *                                                   second query when the caller already has them)
     * @return array [columnName => [['label' => ..., 'value' => ...], ...], ...]
     */
    public function computeTableSummaries(string $scope = 'query', mixed $parentRecord = null, ?Collection $subRecords = null): array
    {
        // The desktop `<tfoot>` and the mobile card footer are two renderings of
        // one set of totals in the same document — both halves are always in it,
        // only CSS decides which is shown — so this ran the whole aggregate batch
        // twice per render of a stacked table, producing byte-identical SQL. The
        // memo is per render, so the second reading is free.
        //
        // Only the main-table scopes are memoised. The sub-rows scope is asked per
        // parent record and has no single answer to remember.
        if ($parentRecord === null && $subRecords === null) {
            return $this->summaryMemo[$scope] ??= $this->resolveTableSummaries($scope);
        }

        return $this->resolveTableSummaries($scope, $parentRecord, $subRecords);
    }

    /**
     * @param  Collection<int, mixed>|null  $subRecords
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function resolveTableSummaries(string $scope, mixed $parentRecord = null, ?Collection $subRecords = null): array
    {
        $table = $this->getTable();
        $set = app(SummarySet::class);

        // Sub-rows summarise a parent's children, over the sub-row columns.
        // Already-fetched children are reused; only an unprovided set queries.
        if ($scope === 'subRows' && $parentRecord !== null && $table->hasSubRows()) {
            return $set->build(
                $table->getSubRowColumns(),
                $subRecords ?? $this->getSubRows($parentRecord),
            );
        }

        // What a scope *means* is the host's to answer — only it knows which
        // rows are on the page and which are selected. The page scope is
        // unwrapped: getTableRecords() hands back a paginator whenever the table
        // is paginated, and the summaries take a Collection, so "this page" was
        // a TypeError on every paginated table — hidden only because summaries
        // are usually exercised unpaginated.
        $pageRecords = $this->getTableRecords();

        $records = match ($scope) {
            'page' => $pageRecords instanceof Collection ? $pageRecords : collect($pageRecords->items()),
            'selection' => $this->getSelectedRecords(),
            default => collect(),
        };

        return $set->build(
            $table->getColumns(),
            $records,
            // Only the query scope hands the batcher something to batch.
            $scope === 'query' ? $this->buildTableQuery() : null,
        );
    }

    /**
     * Check if any column has a summary defined.
     */
    public function tableHasSummaries(): bool
    {
        $table = $this->getTable();

        foreach ($table->getColumns() as $column) {
            if ($column->hasSummary()) {
                return true;
            }
        }

        return $this->tableHasSubRowGrandTotals();
    }

    /**
     * Whether any sub-row column declares a 'query'-scoped summary — a grand
     * total of children across all parents, rendered in the main footer.
     */
    public function tableHasSubRowGrandTotals(): bool
    {
        $table = $this->getTable();

        if ($table->getSubRowRelation() === null) {
            return false;
        }

        foreach ($table->getSubRowColumns() as $column) {
            if ($column->hasSummaryInScope('query')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Grand totals of sub-row columns across parents, for the main footer.
     *
     * A sub-row column opts in with summarize(..., scope: 'query') (the default
     * scope). The aggregate runs in SQL over the child table constrained to the
     * current parent set — 'query' = all filtered parents, 'page' = parents on
     * the current page, 'selection' = selected parents — and honours sub-row
     * scoped main filters, the subRowQuery() callback, and the interactive
     * sub-row filter bar, so the total always matches the displayed children.
     *
     * @return array<string, array<int, array<string, mixed>>> [columnName => [['label' => …, 'value' => …], …]]
     */
    public function computeSubRowGrandTotals(string $scope = 'query'): array
    {
        // Asked once for the desktop footer and once for the mobile one, same as
        // the column totals above.
        return $this->subRowGrandTotalMemo[$scope] ??= $this->resolveSubRowGrandTotals($scope);
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function resolveSubRowGrandTotals(string $scope): array
    {
        if (! $this->tableHasSubRowGrandTotals()) {
            return [];
        }

        $childQuery = $this->buildSubRowGrandTotalQuery($scope);

        if ($childQuery === null) {
            return [];
        }

        $totals = [];
        $subRowColumns = $this->getTable()->getSubRowColumns();

        // The child query is identical for every sub-row column — batch the
        // SQL-native aggregates into one query instead of one per summary.
        $batched = app(SummaryBatch::class)->compute($subRowColumns, $childQuery, ['query']);

        foreach ($subRowColumns as $column) {
            if (! $column->hasSummaryInScope('query')) {
                continue;
            }

            $totals[$column->getName()] = $column->computeSummaries(
                collect(),
                clone $childQuery,
                ['query'],
                $batched[$column->getName()] ?? [],
            );
        }

        return $totals;
    }

    /**
     * Build the child query for sub-row grand totals: all children whose parent
     * is in the current parent set, under the same constraints the displayed
     * sub-rows use. Only direct parent→child relations (HasMany/HasOne and
     * their morph variants) are supported; other relation types yield null.
     *
     * @return Builder<Model>|null
     */
    protected function buildSubRowGrandTotalQuery(string $scope = 'query'): ?Builder
    {
        $table = $this->getTable();
        $relation = app(SubRowQuery::class)->open($table);

        if ($relation === null) {
            return null;
        }

        $childQuery = $relation->children;
        $foreignKey = $relation->foreignKey;
        $localKey = $relation->localKey;

        if ($scope === 'page') {
            // Paginators forward collection calls, so pluck() works on both.
            $childQuery->whereIn($foreignKey, $this->getTableRecords()->pluck($localKey));
        } elseif ($scope === 'selection') {
            $childQuery->whereIn($foreignKey, $this->getSelectedRecords()->pluck($localKey));
        } else {
            // buildTableQuery() may hand back its cached instance — clone before
            // stripping orders/selects for the parent-id subquery.
            $parents = (clone $this->buildTableQuery())->reorder();
            $childQuery->whereIn($foreignKey, $parents->select($parents->qualifyColumn($localKey)));
        }

        if ($callback = $table->getSubRowQueryCallback()) {
            $childQuery = $callback($childQuery) ?? $childQuery;
        }

        $childQuery = $this->applySubRowScopedFilters($childQuery);

        return $this->applyInteractiveSubRowFilters($childQuery);
    }

    /**
     * Active footer summary scope: 'page', 'query', or 'selection'.
     *
     * Falls back to 'query' when 'selection' is active but nothing is selected,
     * so the footer never shows an empty selection total.
     */
    public function getSummaryScope(): string
    {
        $scope = $this->tableState->get('summary.scope', 'query');

        if ($scope === 'selection' && $this->getSelectedRecordsCount() === 0) {
            return 'query';
        }

        return in_array($scope, ['page', 'query', 'selection'], true) ? $scope : 'query';
    }

    /**
     * Set the footer summary scope (ignores unknown values).
     */
    public function setSummaryScope(string $scope): void
    {
        if (in_array($scope, ['page', 'query', 'selection'], true)) {
            $this->tableState->set('summary.scope', $scope);
        }
    }

    /**
     * Scope options to offer in the footer toggle. 'selection' only appears
     * when rows are actually selected.
     *
     * @return array<int, string>
     */
    public function getSummaryScopeOptions(): array
    {
        $options = ['query', 'page'];

        if ($this->getSelectedRecordsCount() > 0) {
            $options[] = 'selection';
        }

        return $options;
    }

    // ─── Column Visibility ───────────────────────────────

    /**
     * Toggle column visibility
     */
    public function toggleColumn(string $column): void
    {
        $hidden = $this->tableState->get('columns.hidden', []);
        $isHidden = in_array($column, $hidden, true);

        if ($isHidden) {
            // Show the column - remove from hidden
            $hidden = array_values(array_diff($hidden, [$column]));
        } else {
            // Hide the column - but check if it's the last visible
            $visibleCount = 0;
            $table = $this->getTable();

            foreach ($table->getColumns() as $col) {
                if ($col->isToggleable() && $col->canView()) {
                    if (! in_array($col->getName(), $hidden, true)) {
                        $visibleCount++;
                    }
                }
            }

            // Don't allow hiding the last visible column
            if ($visibleCount <= 1) {
                return;
            }

            $hidden[] = $column;
        }

        $this->tableState->set('columns.hidden', $hidden);
        $this->markTableViewChanged();
        $this->persistViewPreferences();
    }

    /**
     * Check if column is visible
     */
    public function isColumnVisible(string $column): bool
    {
        $hidden = $this->tableState->get('columns.hidden', []);

        return ! in_array($column, $hidden, true);
    }

    /**
     * Reset every toggleable column back to its configured default visibility,
     * clearing any saved per-user preference.
     */
    public function resetColumns(): void
    {
        $table = $this->getTable();

        $hidden = [];
        foreach ($table->getColumns() as $column) {
            if ($column->isToggleable() && ! $column->isVisible()) {
                $hidden[] = $column->getName();
            }
        }

        $this->tableState->set('columns.hidden', $hidden);
        $this->markTableViewChanged();

        if (($key = $table->getRememberColumnsKey()) !== null) {
            $this->resolvePreferenceDriver($table)->forget($key, $this->preferenceUser());
        }
    }

    // ==========================================
    // Saved views
    // ==========================================

    /**
     * Save the current view under a name, replacing one of the same name.
     *
     * A Livewire endpoint, so the body lives in
     * {@see TableViewPayload}: what a view is
     * has to be one answer shared with {@see applyTableView()}, or a view would
     * restore something other than what was saved.
     */
    public function saveTableView(string $name): void
    {
        $name = trim($name);
        $key = $this->getTable()->getSavedViewsKey();

        // An empty name is the unnamed current layout, which is not a saved view
        // — accepting it here would let "Save" overwrite the live layout with
        // itself and put an entry with no label in the switcher.
        if ($key === null || $name === '') {
            return;
        }

        $this->resolvePreferenceDriver($this->getTable())->save(
            $key,
            $this->preferenceUser(),
            TableViewPayload::capture($this->tableState),
            $name,
        );
    }

    /**
     * Restore a saved view onto the current table state.
     */
    public function applyTableView(string $name): void
    {
        $table = $this->getTable();
        $key = $table->getSavedViewsKey();

        if ($key === null || $name === '') {
            return;
        }

        $payload = $this->resolvePreferenceDriver($table)->load($key, $this->preferenceUser(), $name);

        if ($payload === []) {
            return;
        }

        TableViewPayload::applyTo($payload, $this->tableState, $table);

        // A restored view changes which records are on screen, so the page it
        // was saved on is not this view's page any more.
        $this->resetPage();
        $this->markTableViewChanged();
    }

    /**
     * Delete a saved view. The current layout is untouched.
     */
    public function deleteTableView(string $name): void
    {
        $key = $this->getTable()->getSavedViewsKey();

        if ($key === null || $name === '') {
            return;
        }

        $this->resolvePreferenceDriver($this->getTable())->forget($key, $this->preferenceUser(), $name);
    }

    /**
     * The names of this user's saved views, for the switcher.
     *
     * @return array<int, string>
     */
    public function getTableViews(): array
    {
        $key = $this->getTable()->getSavedViewsKey();

        if ($key === null) {
            return [];
        }

        return $this->resolvePreferenceDriver($this->getTable())->views($key, $this->preferenceUser());
    }

    /**
     * Seed the per-user view layout from the saved preference, if the table
     * opted in with rememberColumns() and something has actually been stored.
     *
     * Covers the hidden-column set and the sub-row expansion baseline — both
     * are "how I like to look at this table", so they share one stored payload
     * and one opt-in. Stale column names (columns that no longer exist or are no
     * longer toggleable) are dropped so a renamed/removed column can never hide
     * the wrong thing.
     */
    protected function loadViewPreferences(Table $table): void
    {
        $key = $table->getRememberColumnsKey();

        if ($key === null) {
            return;
        }

        $preferences = $this->resolvePreferenceDriver($table)->load($key, $this->preferenceUser());

        $storedExpandAll = $preferences['rows']['expandAll'] ?? null;
        if (is_bool($storedExpandAll) && $table->hasSubRows()) {
            $this->tableState->set('rows.expandAll', $storedExpandAll);
        }

        // Nothing saved yet → keep the configured defaults.
        if (! array_key_exists('columns', $preferences) || ! is_array($preferences['columns'])) {
            return;
        }

        $storedHidden = $preferences['columns']['hidden'] ?? [];
        if (! is_array($storedHidden)) {
            return;
        }

        $toggleable = [];
        foreach ($table->getColumns() as $column) {
            if ($column->isToggleable() && $column->canView()) {
                $toggleable[] = $column->getName();
            }
        }

        $this->tableState->set(
            'columns.hidden',
            array_values(array_intersect($storedHidden, $toggleable)),
        );
    }

    /**
     * Persist the current view layout for the current user, when enabled.
     */
    protected function persistViewPreferences(): void
    {
        $table = $this->getTable();
        $key = $table->getRememberColumnsKey();

        if ($key === null) {
            return;
        }

        $this->resolvePreferenceDriver($table)->save($key, $this->preferenceUser(), [
            'columns' => [
                'hidden' => array_values($this->tableState->get('columns.hidden', [])),
            ],
            'rows' => [
                'expandAll' => $this->tableState->get('rows.expandAll'),
            ],
        ]);
    }

    /**
     * Resolve the preference driver for this table (per-table override > global
     * config), picking the guest driver when no user is authenticated.
     */
    protected function resolvePreferenceDriver(Table $table): TablePreferenceDriver
    {
        return TablePreferenceManager::resolve(
            $table->getPreferenceDriver(),
            $this->preferenceUser() !== null,
        );
    }

    /**
     * The user whose preferences we read/write (null for a guest).
     */
    protected function preferenceUser(): ?Authenticatable
    {
        return Auth::user();
    }

    // ==========================================
    // Halt Modal System (Dynamic Confirmation)
    // ==========================================

    /**
     * Get halt modal configuration for view.
     */
    public function getHaltModalData(): array
    {
        return $this->tableState->get('modal.halt.config', []);
    }

    /**
     * Get the resolved Form instance for the halt modal.
     * Re-hydrates from session since it's not serialized between Livewire requests.
     */
    public function getHaltModalFormInstance(): ?Form
    {
        if ($this->haltModalFormInstance !== null) {
            return $this->haltModalFormInstance;
        }

        if ($this->tableState->get('modal.halt.show') && session()->has('wire.halt_form_instance')) {
            try {
                $restored = unserialize(session()->get('wire.halt_form_instance'));
                if ($restored instanceof Form) {
                    $restored->livewire($this);
                    $this->haltModalFormInstance = $restored;
                }
            } catch (Throwable) {
                // Corrupt or non-restorable session data — close the modal cleanly
                $this->tableState->set('modal.halt.show', false);
                session()->forget('wire.halt_form_instance');
            }
        }

        return $this->haltModalFormInstance;
    }

    /**
     * Submit halt modal (confirm and re-execute action).
     */
    public function submitHaltModal(array $formData = []): void
    {
        $haltActionName = $this->tableState->get('modal.halt.actionName');
        if (! $haltActionName) {
            $this->closeHaltModal();

            return;
        }

        $haltConfig = $this->tableState->get('modal.halt.config', []);

        // Validate form if present
        $validation = $haltConfig['formValidation'] ?? null;
        if ($validation && ! empty($formData)) {
            $result = app(ValidationPipeline::class)->validate(
                $formData,
                $validation,
                $haltConfig['formValidationMessages'] ?? [],
                $haltConfig['formValidationAttributes'] ?? [],
            );

            if ($result->failed()) {
                throw ValidationException::withMessages($result->errors());
            }
        }

        // Merge form data
        $data = array_merge($this->tableState->get('modal.halt.formData', []), $formData);

        // Capture context before closing
        $actionName = $haltActionName;
        $recordKey = $this->tableState->get('modal.halt.recordKey');
        $actionType = $this->tableState->get('modal.halt.actionType') ?? 'row';
        $haltContext = $this->tableState->get('modal.halt.context', []);
        $redirectAfterConfirm = $haltContext['redirectAfterConfirm'] ?? null;

        $this->closeHaltModal();

        // Re-execute via correct method based on action type
        match ($actionType) {
            'bulk' => $this->executeBulkActionWithData($actionName, $data, confirmed: true),
            'header' => $this->executeHeaderActionWithData($actionName, $data, confirmed: true),
            default => $recordKey !== null
                ? $this->executeTableActionWithData($recordKey, $actionName, $data, confirmed: true)
                : null,
        };

        // Redirect after successful confirm
        if ($redirectAfterConfirm) {
            $this->redirect($redirectAfterConfirm);
        }
    }

    /**
     * Close halt modal.
     */
    public function closeHaltModal(): void
    {
        $this->tableState->set('modal.halt.show', false);
        $this->tableState->set('modal.halt.actionName', null);
        $this->tableState->set('modal.halt.recordKey', null);
        $this->tableState->set('modal.halt.config', []);
        $this->tableState->set('modal.halt.formData', []);
        $this->haltModalFormInstance = null;
        session()->forget('wire.halt_form_instance');
        $this->tableState->set('modal.halt.actionType', null);
        $this->tableState->set('modal.halt.context', []);

        // Invalidate table cache so next render fetches fresh data
        $this->invalidateTable();
    }

    /**
     * @deprecated Use halt modal system instead. Will be removed in v2.0.
     */
    public function confirmBulkAction(string $actionName): void
    {
        Deprecation::method('confirmBulkAction', 'executeBulkAction with halt');
    }

    /**
     * Open header action modal
     */
    /**
     * @param  array<string, mixed>  $arguments  Exposed to callbacks as `$arguments`.
     */
    public function openHeaderActionModal(string $actionName, array $arguments = []): void
    {
        $action = $this->findHeaderAction($actionName);

        if (! $action || ! $action->hasModal()) {
            // No modal, execute directly
            $this->executeHeaderAction($actionName);

            return;
        }

        // Stack a new live frame on top instead of replacing the current modal
        // (refused only at the runaway safety depth cap).
        if (! $this->canMountAnotherActionFrame()) {
            return;
        }

        $this->pushActionFrame([
            'name' => $actionName,
            'recordKey' => null,
            'isBulk' => false,
            'isHeaderAction' => true,
            'currentStep' => 0,
            'arguments' => $arguments,
            'data' => $action->getFormDefaults(),
        ]);

        $this->actionModalConfigCache = $action->getModalConfig();
        $this->actionModalFormInstance = $this->buildModalActionFormInstance($action, null);
    }

    /**
     * Execute header action
     */
    public function executeHeaderAction(string $actionName, bool $confirmed = false): void
    {
        $this->executeHeaderActionWithData($actionName, [], $confirmed);
    }

    // ==========================================
    // Inline Editing
    // ==========================================

    /**
     * Update a single cell in the table.
     *
     * Supports optimistic locking via $recordVersion parameter.
     *
     * @param  mixed  $recordKey  The primary key of the record
     * @param  string  $columnName  The name of the column
     * @param  mixed  $value  The new value
     * @param  string|null  $recordVersion  The updated_at timestamp when the client loaded the value (optimistic lock)
     * @return array{success: bool, message?: string, errors?: array, conflict?: bool, currentValue?: mixed, currentVersion?: string, version?: string}
     */
    public function updateTableCell(mixed $recordKey, string $columnName, mixed $value, ?string $recordVersion = null): array
    {
        // Render unless the table opted out: everything derived from this value
        // — summaries, rollups, a badge two columns over — is stale the instant
        // the write lands. The cell's own optimistic state survives the morph on
        // its own (see skipTableRenderAfterWrite).
        $this->skipTableRenderAfterWrite();

        $table = $this->getTable();
        $column = $this->findColumn($columnName);

        if (! $column) {
            return ['success' => false, 'message' => __('wire-table::messages.column_not_found')];
        }

        $pipeline = app(CellEditPipeline::class);

        // ── Column-level refusals (before any transform — read-only) ──
        if ($failure = $pipeline->guard($column)) {
            return $failure->toArray();
        }

        // ── Format & validate (before transaction — no DB writes) ──
        // Hold on to the state the client sent. The record-aware pass inside the
        // transaction dehydrates from this, never from the output below.
        $state = $value;
        $value = $pipeline->dehydrate($column, $state);

        if ($failure = $pipeline->validateWithoutRecord($column, $columnName, $value)) {
            return $failure->toArray();
        }

        // Dispatch CellUpdating event
        event(new CellUpdating(static::class, $columnName, $recordKey, $value));

        // ── Atomic update with optimistic locking ───────────────
        try {
            $outcome = DB::transaction(function () use ($table, $pipeline, $column, $columnName, $recordKey, $state, $recordVersion): CellEditOutcome {
                // Lock the row
                $record = $table->getQuery()
                    ->where($table->getPrimaryKey(), $recordKey)
                    ->lockForUpdate()
                    ->first();

                if (! $record) {
                    return CellEditOutcome::rejected(__('wire-table::messages.record_not_found'));
                }

                return $pipeline->commit($column, $columnName, $record, $state, $recordVersion);
            });

            // ── Post-transaction callbacks (outside lock) ──
            $pipeline->settle($outcome, $column, static::class, $columnName, $recordKey);

            if ($outcome->success) {
                $this->announceTableWrite();
                $this->queueRowPartial($recordKey, $columnName);
            }

            // The conflict is always shown inline on the cell; a table can opt in
            // to *also* raise a (more prominent) notification for it.
            if ($outcome->conflict && $table->shouldNotifyEditConflicts()) {
                $this->sendNotification(Notification::warning(
                    $outcome->message ?? __('wire-table::messages.record_conflict')
                ));
            }

            return $outcome->toArray();

        } catch (Exception $e) {
            return ['success' => false, 'message' => __('wire-table::messages.save_error', ['error' => $e->getMessage()])];
        }
    }

    /**
     * Answer a successful write with the row it changed, if the table asked.
     *
     * Opt-in through {@see Table::rowPartials()}, and refused for the three
     * shapes that keep numbers outside a row — see
     * {@see Table::usesRowPartials()}. Without it this is a no-op and the write
     * renders whatever it rendered before.
     *
     * Two things have to happen together. The row goes into the partial queue
     * ({@see InteractsWithPartials}),
     * and the island render is declined: an editable cell targets the
     * `data-region` island, and letting both answer would render the region AND
     * the row — the region would win the morph and the row would be wasted work.
     *
     * A record that is no longer on the page renders nothing, which is correct:
     * the client finds no anchor for it and leaves the page alone.
     */
    protected function queueRowPartial(mixed $recordKey, ?string $columnName = null): void
    {
        $table = $this->getTable();

        if (! $table->usesRowPartials() || ! method_exists($this, 'renderPartial')) {
            return;
        }

        // Editing the column a table groups BY moves the record to another group:
        // the page's shape changes, and no set of regions can describe that.
        if ($columnName !== null && $table->getGroupColumn() === $columnName) {
            return;
        }

        $records = $this->getTableRecords();
        $key = $table->getPrimaryKey();

        foreach ($records as $index => $record) {
            if ((string) $record->{$key} !== (string) $recordKey) {
                continue;
            }

            $renderer = RowRenderer::for($table, $this, $this->tableRenderPlan());

            $this->skipIslandsRender();
            $this->renderPartial(
                'row-'.$recordKey,
                fn (): string => $renderer->render($record, (int) $index),
            );

            // The one row is rendered here rather than through TablePartials
            // because its position is known already; everything the write moves
            // *around* it goes through the same owner as the poll path.
            $satellites = TablePartials::for($table, $this, $this->tableRenderPlan())
                ->satellites([$recordKey => $record]);

            foreach ($satellites as $name => $html) {
                $this->renderPartial($name, $html);
            }

            return;
        }
    }

    /**
     * Validate a cell value without saving.
     *
     * @param  mixed  $recordKey  The primary key of the record
     * @param  string  $columnName  The name of the column
     * @param  mixed  $value  The value to validate
     * @return array{valid: bool, errors?: array}
     */
    public function validateTableCell(mixed $recordKey, string $columnName, mixed $value): array
    {
        $this->skipTableRender();

        $table = $this->getTable();
        $column = $this->findColumn($columnName);

        if (! $column) {
            return ['valid' => false, 'errors' => [__('wire-table::messages.column_not_found')]];
        }

        $record = $table->getDataSource()->resolveRecord($recordKey)?->unwrap();

        if (! $record) {
            return ['valid' => false, 'errors' => [__('wire-table::messages.record_not_found')]];
        }

        // Dehydration and the rules live with the commit path, so this check and
        // the save it predicts cannot drift apart.
        return app(CellEditPipeline::class)->validateAgainstRecord($column, $columnName, $value, $record);
    }

    /**
     * @deprecated Use halt modal system instead. Will be removed in v2.0.
     */
    public function getConfirmationModalData(): array
    {
        Deprecation::method('getConfirmationModalData', 'getHaltModalData');

        return [
            'title' => __('wire-table::messages.confirm_heading'),
            'description' => __('wire-table::messages.confirm_description'),
            'confirmLabel' => __('wire-table::messages.confirm_submit'),
            'cancelLabel' => __('wire-table::messages.confirm_cancel'),
        ];
    }

    // ==========================================
    // Debug & SQL Inspection
    // ==========================================

    /**
     * Get raw SQL and bindings for the table query.
     *
     * @return array{sql: string, bindings: array}
     */
    public function getTableRawSql(): array
    {
        $query = $this->buildTableQuery();

        return [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
        ];
    }

    /**
     * Get the final SQL query with all filters, search, and sorting applied.
     *
     * @return string The complete SQL query
     */
    public function getTableSql(): string
    {
        return static::builderToSql($this->buildTableQuery());
    }

    /**
     * Dump the complete table query and continue.
     */
    public function dumpTableQuery(): void
    {
        dump([
            'complete_sql' => $this->getTableSql(),
            'raw_sql' => $this->buildTableQuery()->toSql(),
            'bindings' => $this->buildTableQuery()->getBindings(),
            'search' => $this->tableState->get('search'),
            'filters' => $this->tableState->get('filters', []),
            'column_filters' => $this->tableState->get('columnFilters', []),
            'sort_column' => $this->tableState->get('sort.column'),
            'sort_direction' => $this->tableState->get('sort.direction'),
        ]);
    }

    /**
     * Dump the complete table query and stop execution.
     */
    public function ddTableQuery(): never
    {
        dd([
            'complete_sql' => $this->getTableSql(),
            'raw_sql' => $this->buildTableQuery()->toSql(),
            'bindings' => $this->buildTableQuery()->getBindings(),
            'search' => $this->tableState->get('search'),
            'filters' => $this->tableState->get('filters', []),
            'column_filters' => $this->tableState->get('columnFilters', []),
            'sort_column' => $this->tableState->get('sort.column'),
            'sort_direction' => $this->tableState->get('sort.direction'),
        ]);
    }

    /**
     * Get all defined table columns with their names.
     */
    public function getTableColumnNames(): array
    {
        return $this->getTable()->getColumnNames();
    }

    /**
     * Dump columns info and continue.
     */
    public function dumpTableColumns(): void
    {
        $allColumnNames = $this->getTable()->getColumnNames();
        $hiddenNames = $this->tableState->get('columns.hidden', []);

        dump([
            'defined_columns' => $this->getTableColumnsInfo(),
            'visible_columns' => array_values(array_diff($allColumnNames, $hiddenNames)),
            'hidden_columns' => $hiddenNames,
            'database_columns' => $this->getTableDatabaseColumns(),
        ]);
    }

    /**
     * Get all defined table columns with full info.
     */
    public function getTableColumnsInfo(): array
    {
        return $this->getTable()->getColumnsInfo();
    }

    /**
     * Get database columns for the model.
     */
    public function getTableDatabaseColumns(): array
    {
        return $this->getTable()->getDatabaseColumns();
    }

    /**
     * Dump columns info and stop.
     */
    public function ddTableColumns(): never
    {
        $allColumnNames = $this->getTable()->getColumnNames();
        $hiddenNames = $this->tableState->get('columns.hidden', []);

        dd([
            'defined_columns' => $this->getTableColumnsInfo(),
            'visible_columns' => array_values(array_diff($allColumnNames, $hiddenNames)),
            'hidden_columns' => $hiddenNames,
            'database_columns' => $this->getTableDatabaseColumns(),
            'database_columns_info' => $this->getTableDatabaseColumnsInfo(),
        ]);
    }

    /**
     * Get detailed database column info.
     */
    public function getTableDatabaseColumnsInfo(): array
    {
        return $this->getTable()->getDatabaseColumnsInfo();
    }

    /**
     * Refresh a specific row in the table.
     * This is called by PollColumn for row-level polling.
     *
     * @param  mixed  $recordKey  The primary key of the record to refresh
     */
    public function refreshRow(mixed $recordKey): void
    {
        // Invalidate cached records so next render fetches fresh data.
        // cachedQuery is intentionally kept — the query plan doesn't change,
        // only the row data; re-running the planner would be wasted work.
        $this->cachedRecords = null;
    }

    // ==========================================
    // Export
    // ==========================================

    /**
     * Export the current table data.
     *
     * Uses the current filtered/sorted query and visible columns.
     */
    public function exportTable(string $format = 'csv'): StreamedResponse
    {
        [$export, $query, $columns] = $this->buildTableExport(ExportFormat::from($format));

        return $export->download($query, $columns);
    }

    /**
     * The export this table would produce: its config, its filtered query and
     * its visible columns.
     *
     * Public and separate because a queued export needs exactly this and cannot
     * get it from a response. {@see RunExportJob}
     * rebuilds the host and calls it, so a download and a queued file are the
     * same export delivered two ways rather than two exports that happen to
     * agree today.
     *
     * @return array{0: TableExport, 1: Builder<Model>, 2: array<int, Column>}
     */
    public function buildTableExport(ExportFormat $format): array
    {
        $table = $this->getTable();

        // Find ExportAction config if defined
        $exportConfig = null;
        foreach ($table->getHeaderActions() as $action) {
            if ($action instanceof ExportAction) {
                $exportConfig = $action->getExportConfig();
                break;
            }
        }

        $export = ($exportConfig ?? TableExport::make())->format($format);

        // Use current filtered query
        $query = $this->getFilteredTableQuery();

        // Use visible columns
        $columns = array_values(array_filter(
            $export->getColumns() ?? $table->getColumns(),
            fn (Column $col) => $col->canView() && ! in_array($col->getName(), $this->tableState->get('columns.hidden', []), true),
        ));

        return [$export, $query, $columns];
    }

    /**
     * Hand the export to a worker instead of streaming it now.
     *
     * For the case a download cannot serve: an export whose query would outlast
     * the request. The file lands on a disk and a notification says where —
     * which needs a notification that survives the request, hence the database
     * driver.
     */
    public function queueTableExport(string $format = 'csv', ?string $disk = null, string $directory = 'exports'): void
    {
        // The state travels with it: without that the worker mounts fresh and a
        // user who filtered to twenty rows would receive all ten thousand.
        RunExportJob::dispatch(static::class, $format, $disk, $directory, $this->tableState->all());

        $this->sendNotification(Notification::info(
            Trans::get('wire-table::messages.export_queued')
        ));
    }

    /**
     * Hand an uploaded file to a worker and return immediately.
     *
     * Takes a **disk path**, not the temp upload's real path: the worker may be
     * another machine, and a Livewire temp file will not be there when it looks.
     * Store the upload first — `$file->store('imports')` — and pass what that
     * returns.
     *
     * The import itself is unchanged; see
     * {@see RunImportJob} for why this needed
     * no second copy of anything, unlike the export.
     */
    public function queueTableImport(string $path, ?string $disk = null): void
    {
        RunImportJob::dispatch(static::class, $path, $disk);

        $this->sendNotification(Notification::info(
            Trans::get('wire-table::messages.import_queued')
        ));
    }

    /**
     * Import rows from an uploaded file into the table's model.
     *
     * Resolves the {@see ImportAction} config declared in the table's header
     * actions (mirroring exportTable()), runs the import over the given file path
     * (typically an uploaded temp file's real path), invalidates cached records so
     * the new rows render, and returns the per-row {@see ImportResult}.
     */
    public function importTable(string $filePath): ImportResult
    {
        $importAction = null;
        foreach ($this->getTable()->getHeaderActions() as $action) {
            if ($action instanceof ImportAction) {
                $importAction = $action;
                break;
            }
        }

        // Enforce the ImportAction's authorization server-side. importTable is a
        // public Livewire endpoint, so a client can invoke it directly — without
        // this, an ->authorize()/->hidden() guard declared on the action would be
        // bypassed and an arbitrary server-readable path fed to the importer.
        if ($importAction !== null && ! $importAction->canExecute()) {
            return new ImportResult;
        }

        $result = ($importAction?->getImportConfig() ?? TableImport::make())->import($filePath);

        // New rows changed the dataset — drop cached records/partitions so the
        // next render reflects the import.
        $this->cachedRecords = null;

        return $result;
    }

    /**
     * Get the filtered (but not paginated) query for the current table state.
     *
     * @return Builder<Model>
     */
    protected function getFilteredTableQuery(): Builder
    {
        $table = $this->getTable();
        $service = app(TableQueryService::class);

        return $this->applyGroupOrdering($service->buildQuery(
            baseQuery: $table->getQuery(),
            table: $table,
            search: $this->tableState->get('search', ''),
            filterValues: $this->tableState->get('filters', []),
            sortColumn: $this->tableState->get('sort.column') ?: $table->getDefaultSort(),
            sortDirection: $this->tableState->get('sort.direction') ?: $table->getDefaultSortDirection(),
            columnFilterValues: $this->tableState->get('columnFilters', []),
        ));
    }
}
