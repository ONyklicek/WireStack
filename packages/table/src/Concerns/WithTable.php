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
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
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
use NyonCode\WireCore\Core\Events\CellUpdating;
use NyonCode\WireCore\Core\Events\TableFiltered;
use NyonCode\WireCore\Core\Events\TableFiltering;
use NyonCode\WireCore\Core\Events\TableSearched;
use NyonCode\WireCore\Core\Events\TableSearching;
use NyonCode\WireCore\Core\State\StateContainer;
use NyonCode\WireCore\Core\Support\Deprecation;
use NyonCode\WireCore\Core\Validation\ValidationPipeline;
use NyonCode\WireCore\Foundation\Contracts\DehydratesState;
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
use NyonCode\WireTable\Export\ExportAction;
use NyonCode\WireTable\Export\ExportFormat;
use NyonCode\WireTable\Export\TableExport;
use NyonCode\WireTable\Filters\Filter;
use NyonCode\WireTable\Import\ImportAction;
use NyonCode\WireTable\Import\ImportResult;
use NyonCode\WireTable\Import\TableImport;
use NyonCode\WireTable\Preferences\Contracts\TablePreferenceDriver;
use NyonCode\WireTable\Preferences\TablePreferenceManager;
use NyonCode\WireTable\Services\CellEditPipeline;
use NyonCode\WireTable\Services\SummaryBatch;
use NyonCode\WireTable\Services\TableQueryCacheKey;
use NyonCode\WireTable\Services\TableQueryService;
use NyonCode\WireTable\Support\CellEditOutcome;
use NyonCode\WireTable\Table;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** @phpstan-require-extends Component */
trait WithTable
{
    use CanExpandSubRows;
    use CanFillCells;
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
        InteractsWithTableActions::haltModalFormStatePath insteadof InteractsWithActionForms;
        InteractsWithTableActions::afterActionExecuted insteadof InteractsWithActions;
        InteractsWithTableActions::resolveActionRecordIds insteadof InteractsWithActions;
        InteractsWithTableActions::sendActionNotification insteadof InteractsWithActions;
    }
    use InteractsWithFieldActions;
    use InteractsWithFileUploads;
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
     * Whether a result-shaping state path (per-page, search, filters, sort)
     * was written in this request. Livewire pools commits fired in the same
     * tick, so a wire:poll tick can share a request with the user's change —
     * and the poll's "nothing changed, skip the render" verdict would then
     * throw away the render that change was made for.
     */
    protected bool $tableStateChangedThisRequest = false;

    protected string $wireTableClass = Table::class;

    protected ?Table $tableInstance = null;

    /** @var LengthAwarePaginator|Paginator|CursorPaginator|Collection|null Cached records for current request lifecycle */
    protected LengthAwarePaginator|Paginator|CursorPaginator|Collection|null $cachedRecords = null;

    /** @var Builder<Model>|null Cached query builder so summaries don't re-plan the query */
    protected ?Builder $cachedQuery = null;

    /** @var TableQueryService|null Shared query service instance */
    protected ?TableQueryService $queryService = null;

    // $cachedSelectedRecords comes from CanSelectRecords.

    /**
     * Memoized page records partitioned by group value, in page order.
     * Each entry: ['value' => mixed, 'records' => Collection].
     *
     * @var array<int, array{value: mixed, records: Collection<int, Model>}>|null
     */
    protected ?array $cachedGroupPartitions = null;

    /**
     * Initialize table state via StateContainer.
     */
    public function mountWithTable(): void
    {
        $this->tableState = new StateContainer(TableStateSchema::defaults());

        $table = $this->getTable();

        // If lazy loading is enabled, don't load data yet
        $this->tableState->set('ready', ! $table->isLazy());

        if ($table->getDefaultSort()) {
            $this->tableState->set('sort.column', $table->getDefaultSort());
            $this->tableState->set('sort.direction', $table->getDefaultSortDirection());
        }

        $this->tableState->set('pagination.perPage', $table->getPerPage());

        // Initialize filters with defaults (wrapped to match form-field state shape).
        // Every *rendered* filter gets a slot, not only the ones with a default:
        // non-native filters bind through $wire.entangle(), and Livewire's entangle
        // silently no-ops when the path is undefined at render, so a filter without
        // a default would never reach the server. A null value stays inactive
        // everywhere — apply() ignores it and it is not counted as an active filter.
        $filters = [];
        foreach ($table->getFilters() as $filter) {
            $default = $filter->getDefault();

            // A hidden filter renders no control to bind, so it only needs a slot
            // when a default actually forces a value into the query.
            if ($default === null && ! $filter->canView()) {
                continue;
            }

            // Arr::set so dotted (relation) filter names nest the same way the
            // live wire:model binding writes them — keeps init and UI in sync.
            Arr::set($filters, $filter->getName(), $filter->wrapValue($default));
        }
        if ($filters !== []) {
            $this->tableState->set('filters', $filters);
        }

        // Initialize hidden columns (columns that start hidden)
        $hidden = [];
        foreach ($table->getColumns() as $column) {
            if ($column->isToggleable() && ! $column->isVisible()) {
                $hidden[] = $column->getName();
            }
        }
        if ($hidden !== []) {
            $this->tableState->set('columns.hidden', $hidden);
        }

        // Every column filter needs a state slot up front, for the same reason the
        // panel filters above do: the header controls entangle their path, and an
        // undefined path makes Livewire's entangle a silent no-op.
        //
        // Multi-select filters must specifically start as an *array* so Livewire
        // treats their header checkboxes as an array group (toggle membership)
        // rather than replacing a scalar on each click.
        foreach ($table->getColumns() as $column) {
            if (! $column->isFilterable()) {
                continue;
            }

            $path = 'columnFilters.'.$column->getName();
            $current = $this->tableState->get($path);

            if ($column->filterExpectsArray()) {
                if (! is_array($current)) {
                    $this->tableState->set($path, []);
                }

                continue;
            }

            if ($current === null) {
                $this->tableState->set($path, null);
            }
        }

        // Sub-row filter columns need the same up-front slot, for the same
        // entangle-no-op reason: an interactive sub-row filter bar binds each
        // control to rows.subRowFilters.<name>, and a select/multi-select there
        // entangles that path.
        if ($table->isSubRowsFilterable()) {
            foreach ($table->getSubRowColumns() as $column) {
                if (! $column->isFilterable()) {
                    continue;
                }

                $path = 'rows.subRowFilters.'.$column->getName();
                $current = $this->tableState->get($path);

                if ($column->filterExpectsArray()) {
                    if (! is_array($current)) {
                        $this->tableState->set($path, []);
                    }
                } elseif ($current === null) {
                    $this->tableState->set($path, null);
                }
            }
        }

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
        $resetPaths = [
            'pagination.perPage',
            'search',
            'filters',
            'columnFilters',
            'sort.column',
            'sort.direction',
        ];

        foreach ($resetPaths as $resetPath) {
            if ($path === $resetPath || str_starts_with($path, $resetPath.'.')) {
                if ($path === 'pagination.perPage') {
                    $this->normalizePerPage();
                }

                // The view this render must produce is not the one the poll
                // checksum was taken for — see refreshTable().
                $this->tableStateChangedThisRequest = true;

                // "Everything the filter matches" is defined by the filter that
                // was on screen. Narrowing the set while that selection stands
                // would silently redefine what a bulk action is about to touch.
                if ($path !== 'sort.column' && $path !== 'sort.direction' && $path !== 'pagination.perPage') {
                    $this->resetSelectionScope();
                }

                $this->resetPage();

                return;
            }
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
            if (method_exists($this, 'skipRender')) {
                $this->skipRender();
            }

            return;
        }

        // Simply re-render - Livewire will fetch new data
        // The table instance is recreated on each request
        $this->tableInstance = null;
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

        $model = $query->getModel();

        if (! $model->usesTimestamps() || $model->getUpdatedAtColumn() === null) {
            return null;
        }

        $updatedAt = $query->getQuery()->getGrammar()->wrap(
            $query->qualifyColumn($model->getUpdatedAtColumn()),
        );

        $base = $query->toBase();
        $base->select([]);
        $base->selectRaw("COUNT(*) as wt_count, MAX({$updatedAt}) as wt_max");
        $row = $base->first();

        return ($row->wt_count ?? 0).'|'.($row->wt_max ?? '');
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
     * Render the table view.
     */
    public function getTableProperty(): View
    {
        $table = $this->getTable();

        $viewName = method_exists($this, 'getTableView')
            ? $this->getTableView()
            : (method_exists($table, 'getViewName') ? $table->getViewName() : 'wire-table::tables.index');

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

        return $query->get();
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

        return match ($table->getPaginationMode()) {
            'simple' => $query->simplePaginate($perPage),
            'cursor' => $query->cursorPaginate($perPage),
            default => $query->paginate($perPage),
        };
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
        );

        return Cache::remember($key, $ttl, function () use ($table, $query) {
            if ($table->isPaginated()) {
                return $this->paginateQuery($table, $query);
            }

            return $query->get();
        });
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

        // Dispatch search event
        if ($search) {
            $searchableColumns = [];
            foreach ($table->getColumns() as $col) {
                if ($col->isSearchable()) {
                    $searchableColumns[] = $col->getName();
                }
            }
            event(new TableSearching($tableId, $search, $searchableColumns));
        }

        // Dispatch filter event
        $activeFilters = array_filter($filters, fn ($v) => $v !== null && $v !== '' && $v !== []);
        if (! empty($activeFilters)) {
            event(new TableFiltering($tableId, $activeFilters));
        }

        $query = $this->getQueryService()->buildQuery(
            baseQuery: $baseQuery,
            table: $table,
            search: $search,
            filterValues: $filters,
            sortColumn: ! empty($sortColumn) ? $sortColumn : null,
            sortDirection: $sortDirection,
            columnFilterValues: $columnFilters,
        );

        // Post-search event
        if ($search) {
            // Count is deferred — we dispatch with -1 as a signal that count is not yet known
            event(new TableSearched($tableId, $search, -1));
        }

        // Post-filter event
        if (! empty($activeFilters)) {
            event(new TableFiltered($tableId, $activeFilters, -1));
        }

        $query = $this->applyGroupOrdering($query);

        $this->cachedQuery = $query;

        return $query;
    }

    /**
     * Keep groups contiguous: prepend an order on the group column so every
     * other sort applies within a group. Skipped when the user explicitly
     * sorts by the group column — that sort already keeps groups together.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function applyGroupOrdering(Builder $query): Builder
    {
        $table = $this->getTable();
        $groupColumn = $table->getGroupColumn();

        if ($groupColumn === null) {
            return $query;
        }

        if ($this->tableState->get('sort.column', '') === $groupColumn) {
            return $query;
        }

        $base = $query->getQuery();
        $base->orders = array_merge(
            [['column' => $query->qualifyColumn($groupColumn), 'direction' => 'asc']],
            $base->orders ?? [],
        );

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
        $table = $this->getTable();

        // For sub-rows scope, use sub-row records
        if ($scope === 'subRows' && $parentRecord !== null && $table->hasSubRows()) {
            // Reuse already-fetched sub-rows when provided; only query otherwise.
            $subRecords ??= $this->getSubRows($parentRecord);
            $columnsToSummarize = $table->getSubRowColumns();

            $summaries = [];
            foreach ($columnsToSummarize as $column) {
                if ($column->hasSummary()) {
                    $summaries[$column->getName()] = $column->computeSummaries($subRecords, null);
                }
            }

            return $summaries;
        }

        // For main table — resolve the in-memory record set per scope.
        // The page scope is unwrapped: getTableRecords() hands back a paginator
        // whenever the table is paginated, and computeSummaries() takes a
        // Collection — so "this page" was a TypeError on every paginated table,
        // hidden only because summaries are usually exercised unpaginated.
        $pageRecords = $this->getTableRecords();

        $inMemoryRecords = match ($scope) {
            'page' => $pageRecords instanceof Collection ? $pageRecords : collect($pageRecords->items()),
            'selection' => $this->getSelectedRecords(),
            default => collect(),
        };
        $query = ($scope === 'query') ? $this->buildTableQuery() : null;

        $columns = $table->getColumns();
        $summaries = [];

        // Batch all SQL-native query-scope aggregates into at most two queries
        // instead of one query per summary per column on every render.
        $batched = $query !== null ? app(SummaryBatch::class)->compute($columns, $query) : [];

        foreach ($columns as $column) {
            if ($column->hasSummary()) {
                $summaries[$column->getName()] = $column->computeSummaries(
                    $inMemoryRecords,
                    $query,
                    null,
                    $batched[$column->getName()] ?? [],
                );
            }
        }

        return $summaries;
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
     * Whether group subtotal rows should render: grouping is active, enabled,
     * and at least one column has a summary to subtotal.
     */
    public function tableHasGroupSummaries(): bool
    {
        $table = $this->getTable();

        if (! $table->hasGrouping() || ! $table->hasGroupSummaries()) {
            return false;
        }

        foreach ($table->getColumns() as $column) {
            if ($column->hasSummary()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Per-group subtotals, computed in memory over the group's records on the
     * current page (groups crossing a page boundary subtotal per page).
     *
     * @return array<string, array<int, array<string, mixed>>> [columnName => [['label' => …, 'value' => …], …]]
     */
    public function computeGroupSummaries(mixed $groupValue): array
    {
        $table = $this->getTable();

        if (! $table->hasGrouping()) {
            return [];
        }

        $groupRecords = $this->getGroupRecords($groupValue);

        $summaries = [];

        foreach ($table->getColumns() as $column) {
            if (! $column->hasSummary()) {
                continue;
            }

            // In-memory over the group's rows; selection/subRows scopes don't
            // describe a group, so only query/page declarations subtotal.
            $summaries[$column->getName()] = $column->computeSummaries(
                $groupRecords,
                null,
                ['query', 'page'],
            );
        }

        return $summaries;
    }

    /**
     * Records of one group on the current page. The page is partitioned once
     * per request — group subtotals are rendered per group, and re-filtering
     * the whole page for each of them is O(groups × page size).
     *
     * @return Collection<int, Model>
     */
    protected function getGroupRecords(mixed $groupValue): Collection
    {
        if ($this->cachedGroupPartitions === null) {
            $table = $this->getTable();
            $records = $this->getTableRecords();
            $records = $records instanceof Collection ? $records : collect($records->items());

            $partitions = [];

            foreach ($records as $record) {
                // Normalised scalar key: the raw value may be a date/object cast
                // (a fresh Carbon per record), so a strict compare of the raw value
                // would never match and every row would form its own group. The
                // caller (computeGroupSummaries) is handed the same key by the view.
                $value = $table->getGroupComparisonKey($record);
                $matched = false;

                // 'records' is a Collection object, push() mutates it in place.
                foreach ($partitions as $partition) {
                    if ($partition['value'] === $value) {
                        $partition['records']->push($record);
                        $matched = true;
                        break;
                    }
                }

                if (! $matched) {
                    $partitions[] = ['value' => $value, 'records' => collect([$record])];
                }
            }

            $this->cachedGroupPartitions = $partitions;
        }

        foreach ($this->cachedGroupPartitions as $partition) {
            if ($partition['value'] === $groupValue) {
                return $partition['records'];
            }
        }

        return collect();
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
        $relationName = $table->getSubRowRelation();

        if ($relationName === null) {
            return null;
        }

        $relation = $table->getQuery()->getModel()->{$relationName}();

        if (! $relation instanceof HasOneOrMany) {
            return null;
        }

        $childQuery = $relation->getRelated()->newQuery();

        if ($relation instanceof MorphOneOrMany) {
            $childQuery->where($relation->getQualifiedMorphType(), $relation->getMorphClass());
        }

        $foreignKey = $relation->getQualifiedForeignKeyName();
        $localKey = $relation->getLocalKeyName();

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

        if (($key = $table->getRememberColumnsKey()) !== null) {
            $this->resolvePreferenceDriver($table)->forget($key, $this->preferenceUser());
        }
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
            } catch (\Throwable) {
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
        // Prevent Livewire from re-rendering the table in this response.
        // Re-rendering causes DOM morphing that destroys Alpine component state
        // (success indicator, saving flag, etc.). The Alpine MutationObserver
        // on each cell handles syncing new values, and polling refreshes the
        // table on the next cycle.
        if (method_exists($this, 'skipRender')) {
            $this->skipRender();
        }

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
     * Validate a cell value without saving.
     *
     * @param  mixed  $recordKey  The primary key of the record
     * @param  string  $columnName  The name of the column
     * @param  mixed  $value  The value to validate
     * @return array{valid: bool, errors?: array}
     */
    public function validateTableCell(mixed $recordKey, string $columnName, mixed $value): array
    {
        if (method_exists($this, 'skipRender')) {
            $this->skipRender();
        }

        $table = $this->getTable();
        $column = $this->findColumn($columnName);

        if (! $column) {
            return ['valid' => false, 'errors' => [__('wire-table::messages.column_not_found')]];
        }

        $record = $table->getQuery()->find($recordKey);

        if (! $record) {
            return ['valid' => false, 'errors' => [__('wire-table::messages.record_not_found')]];
        }

        // Apply the column's own dehydration before validating, so rules see the
        // value that would actually be stored.
        if ($column instanceof DehydratesState) {
            $value = $column->dehydrateState($value, $record);
        }

        // Use column's validate method (for TextInputColumn)
        if (method_exists($column, 'validate')) {
            return $column->validate($value, $record);
        }

        // Validate using editable rules
        $rules = $column->getEditableRules($record);
        if (! empty($rules)) {
            $validationResult = app(ValidationPipeline::class)->validate(
                [$columnName => $value],
                [$columnName => $rules],
            );

            if ($validationResult->failed()) {
                return [
                    'valid' => false,
                    'errors' => $validationResult->getError($columnName) ?? [],
                ];
            }
        }

        return ['valid' => true, 'errors' => []];
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
        $this->cachedGroupPartitions = null;
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
        $exportFormat = ExportFormat::from($format);
        $table = $this->getTable();

        // Find ExportAction config if defined
        $exportConfig = null;
        foreach ($table->getHeaderActions() as $action) {
            if ($action instanceof ExportAction) {
                $exportConfig = $action->getExportConfig();
                break;
            }
        }

        $export = ($exportConfig ?? TableExport::make())->format($exportFormat);

        // Use current filtered query
        $query = $this->getFilteredTableQuery();

        // Use visible columns
        $columns = array_values(array_filter(
            $export->getColumns() ?? $table->getColumns(),
            fn (Column $col) => $col->canView() && ! in_array($col->getName(), $this->tableState->get('columns.hidden', []), true),
        ));

        return $export->download($query, $columns);
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
        $this->cachedGroupPartitions = null;

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
