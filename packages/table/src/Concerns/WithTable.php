<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Exception;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation as EloquentRelation;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionGroup;
use NyonCode\WireCore\Actions\ActionHalt;
use NyonCode\WireCore\Actions\BulkAction;
use NyonCode\WireCore\Actions\HeaderAction;
use NyonCode\WireCore\Actions\ModalFooterAction;
use NyonCode\WireCore\Actions\ModalStep;
use NyonCode\WireCore\Core\Actions\ActionContext;
use NyonCode\WireCore\Core\Actions\ActionPipeline;
use NyonCode\WireCore\Core\Actions\ActionResult;
use NyonCode\WireCore\Core\Events\ActionExecuted;
use NyonCode\WireCore\Core\Events\ActionExecuting;
use NyonCode\WireCore\Core\Events\CellUpdated;
use NyonCode\WireCore\Core\Events\CellUpdating;
use NyonCode\WireCore\Core\Events\TableFiltered;
use NyonCode\WireCore\Core\Events\TableFiltering;
use NyonCode\WireCore\Core\Events\TableRefreshed;
use NyonCode\WireCore\Core\Events\TableSearched;
use NyonCode\WireCore\Core\Events\TableSearching;
use NyonCode\WireCore\Core\Plugin\Hooks\ActionExecutedPayload;
use NyonCode\WireCore\Core\Plugin\Hooks\ActionExecutingPayload;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Core\State\StateContainer;
use NyonCode\WireCore\Core\Support\Deprecation;
use NyonCode\WireCore\Core\Validation\ValidationPipeline;
use NyonCode\WireCore\Infolists\Infolist;
use NyonCode\WireCore\Notifications\Notification;
use NyonCode\WireCore\Notifications\NotificationManager;
use NyonCode\WireForms\Concerns\DispatchesStateUpdates;
use NyonCode\WireForms\Concerns\InteractsWithFieldActions;
use NyonCode\WireForms\Concerns\InteractsWithRepeaters;
use NyonCode\WireForms\Concerns\InteractsWithSelectCreation;
use NyonCode\WireForms\Concerns\InteractsWithWizards;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Columns\SummaryBatch;
use NyonCode\WireTable\Export\ExportAction;
use NyonCode\WireTable\Export\ExportFormat;
use NyonCode\WireTable\Export\TableExport;
use NyonCode\WireTable\Filters\Filter;
use NyonCode\WireTable\Import\ImportAction;
use NyonCode\WireTable\Import\ImportResult;
use NyonCode\WireTable\Import\TableImport;
use NyonCode\WireTable\Table;
use ReflectionFunction;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** @phpstan-require-extends Component */
trait WithTable
{
    use DispatchesStateUpdates;
    use HasSqlDebug;
    use InteractsWithFieldActions;
    use InteractsWithRepeaters;
    use InteractsWithSelectCreation;
    use InteractsWithWizards;
    use WithPagination;
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

    /** @var Form|null Resolved Form instance for the current action modal */
    protected ?Form $actionModalFormInstance = null;

    /** @var Infolist|null Resolved Infolist instance for the current action modal */
    protected ?Infolist $actionModalInfolistInstance = null;

    /** @var Form|null Resolved Form instance for the halt modal */
    protected ?Form $haltModalFormInstance = null;

    /**
     * Previous modal form-data values captured in updatingTableState() so the
     * matching field's afterStateUpdated() callback receives `$old`.
     *
     * @var array<string, mixed>
     */
    protected array $modalStateBeforeUpdate = [];

    protected string $wireTableClass = Table::class;

    protected ?Table $tableInstance = null;

    /** @var array Modal config - not a public Livewire property */
    protected array $actionModalConfigCache = [];

    /** @var LengthAwarePaginator|Paginator|CursorPaginator|Collection|null Cached records for current request lifecycle */
    protected LengthAwarePaginator|Paginator|CursorPaginator|Collection|null $cachedRecords = null;

    /** @var Builder<Model>|null Cached query builder so summaries don't re-plan the query */
    protected ?Builder $cachedQuery = null;

    /** @var TableQueryService|null Shared query service instance */
    protected ?TableQueryService $queryService = null;

    /** @var Collection|null Memoized selected records — cleared when the selection mutates */
    protected ?Collection $cachedSelectedRecords = null;

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

        // Seed flatten mode from the table config (flattenSubRows()).
        if ($table->hasSubRows() && $table->isFlattenSubRows()) {
            $this->tableState->set('rows.flattenMode', true);
        }

        // Initialize filters with defaults (wrapped to match form-field state shape)
        $filters = [];
        foreach ($table->getFilters() as $filter) {
            $default = $filter->getDefault();
            if ($default !== null) {
                // Arr::set so dotted (relation) filter names nest the same way the
                // live wire:model binding writes them — keeps init and UI in sync.
                Arr::set($filters, $filter->getName(), $filter->wrapValue($default));
            }
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
     * Whether a tableState sub-path points at a field inside an open modal form.
     */
    private function isModalFormDataPath(string $path): bool
    {
        return str_starts_with($path, 'modal.action.formData.')
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
            $this->queryService = new TableQueryService;
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
        if ($this->tableState->get('modal.action.show') || $this->tableState->get('modal.halt.show')) {
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

        $query = $this->buildTableQuery();

        // Apply query caching if configured
        if ($table->isQueryCached()) {
            $this->cachedRecords = $this->executeWithCache($table, $query);
        } elseif ($table->isPaginated()) {
            $this->cachedRecords = $this->paginateQuery($table, $query);
        } else {
            $this->cachedRecords = $query->get();
        }

        // Eager-load sub-rows for the page in one query (avoids per-parent N+1).
        $this->eagerLoadSubRows($this->cachedRecords);

        return $this->cachedRecords;
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
        $key = $table->getQueryCacheKey() ?? $this->generateQueryCacheKey($query);

        // Pagination is applied inside the cache callback, so the page number is
        // not part of the SQL/bindings — without this suffix every page would
        // share one cache entry and serve page 1's results. Applies to custom
        // queryCacheKey() too, which would otherwise also collide across pages.
        if ($table->isPaginated()) {
            $key .= ':page:'.$this->getQueryCachePage();
        }

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
     * Generate a cache key from the query state.
     *
     * @param  Builder<Model>  $query
     */
    protected function generateQueryCacheKey(Builder $query): string
    {
        return 'wire_table:'.md5(
            $query->toSql().
            serialize($query->getBindings()).
            $this->tableState->get('pagination.perPage').
            $this->tableState->get('sort.column').
            $this->tableState->get('sort.direction')
        );
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
     * Find a column by name
     */
    protected function findColumn(string $name): ?Column
    {
        $table = $this->getTable();

        foreach ($table->getColumns() as $column) {
            if ($column->getName() === $name) {
                return $column;
            }
        }

        return null;
    }

    // ─── Sub-Rows ────────────────────────────────────────

    /**
     * Toggle expansion of a parent row to show/hide its sub-rows.
     */
    public function toggleRowExpansion(mixed $recordKey): void
    {
        $key = (string) $recordKey;
        $expanded = $this->tableState->get('rows.expanded', []);

        if (in_array($key, $expanded, true)) {
            $expanded = array_values(array_diff($expanded, [$key]));
        } else {
            $expanded[] = $key;
        }

        $this->tableState->set('rows.expanded', $expanded);
    }

    /**
     * Expand all rows to show sub-rows.
     */
    public function expandAllRows(): void
    {
        $table = $this->getTable();
        if (! $table->hasSubRows()) {
            return;
        }

        if ($table->isSubRowsDefaultExpanded()) {
            // Default expanded: clear the "collapsed" list
            $this->tableState->set('rows.expanded', []);
        } else {
            $records = $this->getTableRecords();
            $this->tableState->set('rows.expanded', $records->pluck($table->getPrimaryKey())
                ->map(fn ($k) => (string) $k)
                ->all());
        }
    }

    /**
     * Collapse all expanded rows.
     */
    public function collapseAllRows(): void
    {
        $table = $this->getTable();

        if ($table->hasSubRows() && $table->isSubRowsDefaultExpanded()) {
            // Default expanded: add all to "collapsed" list
            $records = $this->getTableRecords();
            $this->tableState->set('rows.expanded', $records->pluck($table->getPrimaryKey())
                ->map(fn ($k) => (string) $k)
                ->all());
        } else {
            $this->tableState->set('rows.expanded', []);
        }
    }

    /**
     * Check if a row is expanded.
     */
    public function isRowExpanded(mixed $recordKey): bool
    {
        $expanded = $this->tableState->get('rows.expanded', []);
        $isInList = in_array((string) $recordKey, $expanded, true);

        // When default expanded, the expandedRows list tracks *collapsed* rows
        if ($this->getTable()->isSubRowsDefaultExpanded()) {
            return ! $isInList;
        }

        return $isInList;
    }

    /**
     * Toggle flatten mode (show all sub-rows as regular rows).
     */
    public function toggleFlattenMode(): void
    {
        $this->tableState->set('rows.flattenMode', ! $this->tableState->get('rows.flattenMode'));
    }

    /**
     * Get sub-rows for a parent record.
     * Applies sub-row filters if enabled.
     * When no relation is set, returns the record itself as a single-item collection.
     */
    public function getSubRows(mixed $record): Collection
    {
        $table = $this->getTable();
        if (! $table->hasSubRows()) {
            return collect();
        }

        // No relation — detail row mode: show the record itself
        if ($table->getSubRowRelation() === null) {
            return collect([$record]);
        }

        // Resolve active sort and "show all" flag for this specific parent.
        $relation = $table->getSubRowRelation();
        $sort = $this->getSubRowSort();
        $parentKey = $record->getKey();
        $showAll = (bool) ($this->tableState->get('rows.subRowsShowAll', [])[$parentKey] ?? false);

        // Fast path: sub-rows were eager-loaded for the whole page in one query
        // (see eagerLoadSubRows). Read from memory instead of querying per parent.
        //
        // Only safe when no sub-row filters are active. The relation may also be
        // eager-loaded by the caller's base query (e.g. Invoice::with('items')),
        // in which case the loaded set is unfiltered — fall through to the query
        // path so active rows.subRowFilters are honoured.
        if ($record->relationLoaded($relation) && ! $this->hasActiveSubRowFilters()) {
            $items = $record->getRelation($relation);

            // A limited eager load (subRowsLimit) ships a loadCount alongside.
            // If show-all was enabled after loading, memory holds only `limit`
            // rows — fall through to the query for this parent's full set.
            $loadedCount = $record->getAttribute(Str::snake($relation).'_count');
            $isPartialLoad = $loadedCount !== null && $items->count() < (int) $loadedCount;

            if (! ($showAll && $isPartialLoad)) {
                if (! $showAll && $table->getSubRowsLimit()) {
                    $items = $items->take($table->getSubRowsLimit());
                }

                return $items->values();
            }
        }

        $query = $table->getSubRowsQuery($record, $sort, applyLimit: ! $showAll);

        // Main-table filters scoped to sub-rows (Filter::subRows()) constrain
        // the displayed children the same way they constrained the parents.
        $query = $this->applySubRowScopedFilters($query);

        // Apply sub-row filters
        $query = $this->applyInteractiveSubRowFilters($query);

        return $query->get();
    }

    /**
     * Eager-load sub-rows for the records that will actually render them
     * (expanded rows, or every row in flatten mode), in a single query —
     * replacing the per-parent N+1 queries.
     *
     * Skipped when sub-row filters are active, since per-parent filtering with
     * custom filter callbacks can't be expressed safely inside one eager-load
     * closure; those fall back to the per-parent query path in getSubRows().
     *
     * @param  LengthAwarePaginator<int, Model>|Paginator<int, Model>|CursorPaginator<int, Model>|Collection<int, Model>  $records
     */
    protected function eagerLoadSubRows(LengthAwarePaginator|Paginator|CursorPaginator|Collection $records): void
    {
        $table = $this->getTable();

        if (! $table->hasSubRows() || $table->getSubRowRelation() === null) {
            return;
        }

        // Don't eager-load when sub-row filters are active (correctness over speed).
        if ($this->hasActiveSubRowFilters()) {
            return;
        }

        $collection = $records instanceof Collection ? $records : $records->getCollection();
        if ($collection->isEmpty()) {
            return;
        }

        // Only load sub-rows that will be displayed.
        $flatten = (bool) $this->tableState->get('rows.flattenMode');
        $target = $flatten
            ? $collection
            : $collection->filter(fn ($record) => $this->isRowExpanded($record->getKey()));

        if ($target->isEmpty()) {
            return;
        }

        $relation = $table->getSubRowRelation();
        $sort = $this->getSubRowSort();
        $callback = $table->getSubRowQueryCallback();
        $limit = $table->getSubRowsLimit();

        $constrain = function ($query) use ($table, $sort, $callback) {
            if ($callback) {
                $query = $callback($query) ?? $query;
            }

            // Sub-row scoped main filters are global (same constraint for every
            // parent), so unlike interactive per-parent filters they are safe
            // to express inside the single eager-load closure.
            $this->applySubRowScopedFilters($query);

            $sortColumn = $sort['column'] ?? $table->getSubRowsDefaultSort();
            $sortDirection = $sort['direction'] ?? $table->getSubRowsDefaultSortDirection();

            if ($sortColumn !== null && $table->isSubRowColumnSortable($sortColumn)) {
                $query->orderBy($sortColumn, $sortDirection === 'desc' ? 'desc' : 'asc');
            }
        };

        // No display limit, or the framework can't limit an eager load per
        // parent (Laravel < 11): load the full sets in one query. getSubRows()
        // applies the display limit in memory and counts the loaded relation,
        // so behaviour stays correct — only the memory win is lost.
        if (! $limit || ! $this->supportsPerParentEagerLimit()) {
            $target->load([$relation => $constrain]);

            return;
        }

        // With a limit, loading full child sets just to count them wastes
        // memory on large relations. Parents flagged "show all" still need the
        // full set; the rest load only `limit` rows per parent (native
        // eager-load limit — window function) plus an exact count for the
        // "show more" affordance (read via getSubRowsTotalCount()).
        $showAll = $this->tableState->get('rows.subRowsShowAll', []);

        [$fullTargets, $limitedTargets] = $target->partition(
            fn ($record) => (bool) ($showAll[$record->getKey()] ?? false),
        );

        if ($fullTargets->isNotEmpty()) {
            $fullTargets->load([$relation => $constrain]);
        }

        if ($limitedTargets->isNotEmpty()) {
            $limitedTargets->load([$relation => function ($query) use ($constrain, $limit) {
                $constrain($query);
                $query->limit($limit);
            }]);

            // Counts ignore ordering — apply only the row constraints.
            $limitedTargets->loadCount([$relation => function ($query) use ($callback) {
                if ($callback) {
                    $query = $callback($query) ?? $query;
                }

                $this->applySubRowScopedFilters($query);
            }]);
        }
    }

    /**
     * Whether the framework can limit an eager load per parent.
     *
     * Per-parent eager-load limits (a window function under the hood) arrived
     * in Laravel 11 via Query\Builder::groupLimit(). On Laravel 10 calling
     * ->limit() inside an eager-load closure applies a single global LIMIT
     * across all parents, so the limited fast path must be skipped there.
     */
    protected function supportsPerParentEagerLimit(): bool
    {
        return method_exists(\Illuminate\Database\Query\Builder::class, 'groupLimit');
    }

    /**
     * Reset sub-row filters.
     */
    public function resetSubRowFilters(): void
    {
        $this->tableState->set('rows.subRowFilters', []);
    }

    /**
     * Main-table filters scoped to the sub-row relation (Filter::subRows())
     * paired with their active values. Empty when sub-rows are not relation-backed.
     *
     * @return array<int, array{0: Filter, 1: mixed}>
     */
    protected function getActiveSubRowScopedFilters(): array
    {
        $table = $this->getTable();

        if ($table->getSubRowRelation() === null) {
            return [];
        }

        $filterValues = $this->tableState->get('filters', []);
        $active = [];

        foreach ($table->getFilters() as $filter) {
            if (! $filter->appliesToSubRows() || ! $filter->canView()) {
                continue;
            }

            $raw = data_get($filterValues, $filter->getName());
            if ($raw === null || $raw === '' || $raw === []) {
                continue;
            }

            $value = $filter->extractValue($raw);
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $active[] = [$filter, $value];
        }

        return $active;
    }

    /**
     * Constrain a child query by the active sub-row scoped main filters —
     * the same constraint TableQueryService used to whereHas the parents.
     *
     * Accepts either an Eloquent Builder or a Relation (eager-load closures
     * receive the latter); filters always run against the Eloquent Builder.
     *
     * @template TQuery of Builder<Model>|EloquentRelation<Model, Model, mixed>
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    protected function applySubRowScopedFilters(Builder|EloquentRelation $query): Builder|EloquentRelation
    {
        $builder = $query instanceof EloquentRelation ? $query->getQuery() : $query;

        foreach ($this->getActiveSubRowScopedFilters() as [$filter, $value]) {
            $filter->apply($builder, $value);
        }

        return $query;
    }

    /**
     * Constrain a child query by the active interactive sub-row filter bar
     * values (subRowsFilterable()) — the per-column filters typed by the user.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function applyInteractiveSubRowFilters(Builder $query): Builder
    {
        $table = $this->getTable();
        $subRowFilters = $this->tableState->get('rows.subRowFilters', []);

        if (! $table->isSubRowsFilterable() || empty($subRowFilters)) {
            return $query;
        }

        foreach ($table->getSubRowColumns() as $column) {
            $filterValue = $subRowFilters[$column->getName()] ?? null;

            if ($filterValue !== null && $filterValue !== '' && $column->isFilterable()) {
                $query = $column->applyFilter($query, $filterValue);
            }
        }

        return $query;
    }

    /**
     * Whether the table has sub-row filtering enabled and at least one active
     * sub-row filter value. Used to disable eager-load / in-memory fast paths
     * that would otherwise bypass per-parent filtering.
     */
    protected function hasActiveSubRowFilters(): bool
    {
        if (! $this->getTable()->isSubRowsFilterable()) {
            return false;
        }

        $subRowFilters = $this->tableState->get('rows.subRowFilters', []);

        foreach ($subRowFilters as $value) {
            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Livewire hook for sub-row filter updates.
     */
    public function updatedSubRowFilters(): void
    {
        // Sub-row filters don't need pagination reset
    }

    /**
     * Current sub-row sort state, or null when none is active.
     *
     * @return array{column: string, direction: string}|null
     */
    public function getSubRowSort(): ?array
    {
        $sort = $this->tableState->get('rows.subRowSort');

        if (! is_array($sort) || empty($sort['column'])) {
            return null;
        }

        return [
            'column' => (string) $sort['column'],
            'direction' => ($sort['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc',
        ];
    }

    /**
     * Toggle sub-row sorting by a column. Clicking the active column flips the
     * direction; clicking a new column sorts it ascending.
     */
    public function sortSubRows(string $column): void
    {
        $table = $this->getTable();

        if (! $table->isSubRowColumnSortable($column)) {
            return;
        }

        $current = $this->getSubRowSort();

        if ($current !== null && $current['column'] === $column) {
            $direction = $current['direction'] === 'asc' ? 'desc' : 'asc';
        } else {
            $direction = 'asc';
        }

        $this->tableState->set('rows.subRowSort', ['column' => $column, 'direction' => $direction]);
    }

    /**
     * Reveal all sub-rows for a parent, bypassing the configured subRowsLimit.
     */
    public function showAllSubRows(string|int $parentKey): void
    {
        $showAll = $this->tableState->get('rows.subRowsShowAll', []);
        $showAll[$parentKey] = true;
        $this->tableState->set('rows.subRowsShowAll', $showAll);
    }

    /**
     * Whether a parent currently has its sub-rows fully expanded (show-all).
     */
    public function isSubRowsShowAll(string|int $parentKey): bool
    {
        return (bool) ($this->tableState->get('rows.subRowsShowAll', [])[$parentKey] ?? false);
    }

    /**
     * Total (unlimited) count of a parent's sub-rows, honouring sub-row filters.
     * Used to decide whether a "show more" affordance is needed.
     */
    public function getSubRowsTotalCount(mixed $record): int
    {
        $table = $this->getTable();

        if (! $table->hasSubRows() || $table->getSubRowRelation() === null) {
            return 0;
        }

        // Use the eager-loaded relation when present — no extra count query.
        // Skipped when sub-row filters are active: a caller-eager-loaded relation
        // (e.g. Invoice::with('items')) is unfiltered, so counting it would ignore
        // rows.subRowFilters and over-count the "show more" affordance.
        $relation = $table->getSubRowRelation();
        if ($record->relationLoaded($relation) && ! $this->hasActiveSubRowFilters()) {
            // Limited eager loads (subRowsLimit) ship an exact loadCount
            // alongside — prefer it; the loaded relation itself holds only
            // `limit` rows, so counting it would always cap at the limit.
            $loadedCount = $record->getAttribute(Str::snake($relation).'_count');

            if ($loadedCount !== null) {
                return (int) $loadedCount;
            }

            return $record->getRelation($relation)->count();
        }

        $query = $table->getSubRowsQuery($record, $this->getSubRowSort(), applyLimit: false);

        // Honour the same constraints used when listing.
        $query = $this->applySubRowScopedFilters($query);
        $query = $this->applyInteractiveSubRowFilters($query);

        return $query->count();
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
        $inMemoryRecords = match ($scope) {
            'page' => $this->getTableRecords(),
            'selection' => $this->getSelectedRecords(),
            default => collect(),
        };
        $query = ($scope === 'query') ? $this->buildTableQuery() : null;

        $columns = $table->getColumns();
        $summaries = [];

        // Batch all SQL-native query-scope aggregates into at most two queries
        // instead of one query per summary per column on every render.
        $batched = $query !== null ? SummaryBatch::compute($columns, $query) : [];

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
                $value = $table->getGroupValue($record);
                $matched = false;

                // Group values may be objects (enums, dates) — match strictly
                // instead of using them as array keys. No references needed:
                // 'records' is a Collection object, push() mutates in place.
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
        $batched = SummaryBatch::compute($subRowColumns, $childQuery, ['query']);

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
    }

    /**
     * Check if column is visible
     */
    public function isColumnVisible(string $column): bool
    {
        $hidden = $this->tableState->get('columns.hidden', []);

        return ! in_array($column, $hidden, true);
    }

    // ─── Record Selection ────────────────────────────────

    /**
     * Toggle record selection
     */
    public function toggleRecordSelection(string $key): void
    {
        $selected = $this->tableState->get('selection.records', []);
        $index = array_search($key, $selected, true);

        if ($index !== false) {
            unset($selected[$index]);
            $selected = array_values($selected);
        } else {
            $selected[] = $key;
        }

        $this->tableState->set('selection.records', $selected);
        $this->cachedSelectedRecords = null;
    }

    /**
     * Select all visible records
     */
    public function selectAllRecords(): void
    {
        $records = $this->getTableRecords();
        $primaryKey = $this->getTable()->getPrimaryKey();

        $selected = [];
        foreach ($records as $record) {
            $selected[] = (string) $record->{$primaryKey};
        }

        $this->tableState->set('selection.records', $selected);
        $this->cachedSelectedRecords = null;
    }

    /**
     * Check if record is selected
     */
    public function isRecordSelected(string $key): bool
    {
        $selected = $this->tableState->get('selection.records', []);

        return in_array($key, $selected, true);
    }

    /**
     * Get selected records count
     */
    public function getSelectedRecordsCount(): int
    {
        return count($this->tableState->get('selection.records', []));
    }

    /**
     * Check if some (but not all) visible records are selected
     */
    public function areSomeVisibleSelected(): bool
    {
        if (empty($this->tableState->get('selection.records', []))) {
            return false;
        }

        return ! $this->areAllVisibleSelected();
    }

    /**
     * Check if all visible records are selected
     */
    public function areAllVisibleSelected(): bool
    {
        $records = $this->getTableRecords();

        if ($records->isEmpty()) {
            return false;
        }

        $selected = $this->tableState->get('selection.records', []);
        $primaryKey = $this->getTable()->getPrimaryKey();

        foreach ($records as $record) {
            $key = (string) $record->{$primaryKey};
            if (! in_array($key, $selected, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get array of selected record keys
     */
    public function getSelectedRecordKeys(): array
    {
        return $this->tableState->get('selection.records', []);
    }

    /**
     * Deselect all records
     */
    public function deselectAllRecords(): void
    {
        $this->tableState->set('selection.records', []);
        $this->cachedSelectedRecords = null;
    }

    /**
     * Get Collection of selected records (fetched from database).
     *
     * Memoized per request — selection-scope summaries, grand totals, and bulk
     * modals may all ask for the set within one render. The memo is cleared
     * whenever the selection mutates or the table cache is invalidated.
     */
    public function getSelectedRecords(): Collection
    {
        if ($this->cachedSelectedRecords !== null) {
            return $this->cachedSelectedRecords;
        }

        $selectedKeys = $this->getSelectedRecordKeys();

        if (empty($selectedKeys)) {
            return collect();
        }

        $table = $this->getTable();

        $query = $table->getQuery()->whereIn($table->getPrimaryKey(), $selectedKeys);

        // Apply the same withCount/withSum aggregate subqueries that buildTableQuery()
        // adds, so aggregate columns (e.g. ->sums('items', 'line_total')) expose their
        // computed attribute on the selected models. Without this, selection-scope
        // summaries pluck a missing attribute and render as 0. Filters/sort are
        // intentionally not applied — selection is an explicit set of keys.
        foreach ($table->getColumns() as $column) {
            if (! $column->isAggregate()) {
                continue;
            }

            $relation = $column->getAggregateRelation();
            $aggregateCol = $column->getAggregateColumn();

            match ($column->getAggregateFunction()) {
                'count' => $query->withCount($relation),
                'sum' => $query->withSum($relation, $aggregateCol),
                'avg' => $query->withAvg($relation, $aggregateCol),
                'min' => $query->withMin($relation, $aggregateCol),
                'max' => $query->withMax($relation, $aggregateCol),
                default => null,
            };
        }

        return $this->cachedSelectedRecords = $query->get();
    }

    // ==========================================
    // Action System
    // ==========================================

    /**
     * Open action modal (confirmation or form)
     */
    public function openActionModal(string $recordKey, string $actionName): void
    {
        $action = $this->findAction($actionName);

        if (! $action || ! $action->hasModal()) {
            // No modal, execute directly
            $this->executeTableAction($recordKey, $actionName);

            return;
        }

        $table = $this->getTable();
        $record = $table->getQuery()->where($table->getPrimaryKey(), $recordKey)->first();

        if (! $record) {
            return;
        }

        $this->tableState->set('modal.action.name', $actionName);
        $this->tableState->set('modal.action.recordKey', $recordKey);
        $this->tableState->set('modal.action.isBulk', false);
        $this->tableState->set('modal.action.isHeaderAction', false);
        $this->tableState->set('modal.action.currentStep', 0);
        $this->actionModalConfigCache = $action->getModalConfig($record);
        $this->tableState->set('modal.action.formData', $action->getFormDefaults($record));
        $this->actionModalFormInstance = $this->buildModalActionFormInstance($action, $record);
        $this->tableState->set('modal.action.show', true);
    }

    /**
     * Find an action by name (including actions inside ActionGroups)
     */
    protected function findAction(string $name): ?Action
    {
        $table = $this->getTable();

        foreach ($table->getActions() as $action) {
            // Check if it's an ActionGroup
            if ($action instanceof ActionGroup) {
                foreach ($action->getActions() as $groupedAction) {
                    if ($groupedAction instanceof Action && $groupedAction->getName() === $name) {
                        return $groupedAction;
                    }
                }
            } elseif ($action instanceof Action && $action->getName() === $name) {
                return $action;
            }
        }

        return null;
    }

    /**
     * Execute table action
     */
    public function executeTableAction(string $recordKey, string $actionName, bool $confirmed = false): void
    {
        $action = $this->findAction($actionName);

        if (! $action) {
            return;
        }

        $table = $this->getTable();
        $record = $table->getQuery()->find($recordKey);

        if (! $record || ! $action->canExecute($record)) {
            return;
        }

        $this->executeActionPipeline($action, [
            'record' => $record,
            'data' => [],
        ], $recordKey, 'row', $confirmed);
    }

    protected function invokeActionCallback(callable $callback, array $payload): mixed
    {
        $reflection = new ReflectionFunction($callback);
        $arguments = [];

        foreach ($reflection->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $payload)) {
                $arguments[] = $payload[$name];
            } elseif ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
            }
        }

        return $reflection->invokeArgs($arguments);
    }

    /**
     * Generalized action execution pipeline.
     *
     * Delegates to Core ActionPipeline with adapter closures that bridge
     * ActionContext to the named-parameter reflection-based callbacks.
     *
     * @param  mixed  $action  The action to execute
     * @param  array  $payload  Named arguments for callbacks (record/records/data/etc.)
     * @param  string  $haltKey  Record key for halt modal ('__bulk__', '__header__', or record key)
     * @param  string  $actionType  'row', 'bulk', or 'header'
     * @param  bool  $confirmed  Whether this is a confirmed re-execution
     */
    protected function executeActionPipeline(
        mixed $action,
        array $payload,
        string $haltKey,
        string $actionType,
        bool $confirmed = false,
    ): void {
        $data = $payload['data'] ?? [];
        $tableId = static::class;

        // Collect record IDs for events
        $recordIds = [];
        if (isset($payload['record'])) {
            $pk = $this->getTable()->getPrimaryKey();
            $recordIds = [$payload['record']->{$pk}];
        } elseif (isset($payload['records'])) {
            $pk = $this->getTable()->getPrimaryKey();
            $recordIds = $payload['records']->pluck($pk)->all();
        }

        // Plugin hook: action.executing (hooks modify before event reports)
        if (app()->bound(PluginManager::class)) {
            $manager = app(PluginManager::class);

            $manager->runHook('action.executing', [
                'action' => $action,
                'actionName' => $action->getName(),
                'actionType' => $actionType,
                'recordIds' => $recordIds,
                'data' => $data,
                'component' => $this,
            ]);

            $preContext = $this->payloadToContext($payload, $action->getName());
            $manager->runTypedHook(
                'action.executing',
                new ActionExecutingPayload(
                    actionName: $action->getName(),
                    context: $preContext,
                    actionType: $actionType,
                    component: $this,
                ),
            );
        }

        // Dispatch ActionExecuting event (after hooks — reports final state)
        event(new ActionExecuting($tableId, $action->getName(), $recordIds));

        // Build ActionContext
        $context = $this->payloadToContext($payload, $action->getName());
        $context->set('confirmed', $confirmed);
        $context->set('actionType', $actionType);
        $context->set('haltKey', $haltKey);
        $context->set('component', $this);

        // Wrap before callbacks as adapter closures
        if (! $confirmed && $action->hasBeforeCallbacks()) {
            $wrappedBefore = [];
            foreach ($action->getBeforeCallbacks() as $i => $beforeCallback) {
                $wrappedBefore[] = function (ActionContext $ctx) use ($action, $beforeCallback, $i): mixed {
                    $this->invokeActionCallback($beforeCallback, array_merge(
                        $this->contextToPayload($ctx),
                        ['action' => $action, 'confirmed' => false, 'component' => $this],
                    ));

                    $pendingHalt = $action->consumePendingHalt();
                    if ($pendingHalt) {
                        $pendingHalt->source('before', $i);
                        $ctx->set('pendingHalt', $pendingHalt);

                        return false; // Signals BeforeCallbacksStage to halt
                    }

                    return true;
                };
            }
            $context->set('beforeCallbacks', $wrappedBefore);
        }

        // Wrap after callbacks
        if ($action->hasAfterCallbacks()) {
            $wrappedAfter = [];
            foreach ($action->getAfterCallbacks() as $i => $afterCallback) {
                $wrappedAfter[] = function (ActionContext $ctx, ActionResult $result) use ($action, $afterCallback, $i): void {
                    $this->invokeActionCallback($afterCallback, array_merge(
                        $this->contextToPayload($ctx),
                        ['action' => $action, 'result' => $result, 'confirmed' => $ctx->get('confirmed', false), 'component' => $this],
                    ));

                    $pendingHalt = $action->consumePendingHalt();
                    if ($pendingHalt) {
                        $pendingHalt->source('after', $i);
                        $ctx->set('pendingHalt', $pendingHalt);
                    }
                };
            }
            $context->set('afterCallbacks', $wrappedAfter);
        }

        // Main action closure for the pipeline
        $mainAction = function (ActionContext $ctx) use ($action): mixed {
            $callback = $action->getActionCallback();
            if (! $callback) {
                return ActionResult::success();
            }

            $halt = fn () => ActionHalt::make();
            $result = $this->invokeActionCallback($callback, array_merge(
                $this->contextToPayload($ctx),
                ['halt' => $halt, 'confirmed' => $ctx->get('confirmed', false), 'component' => $this],
            ));

            if ($result instanceof ActionHalt) {
                $result->source('action');
                $ctx->set('pendingHalt', $result);

                return ActionResult::halt();
            }

            return $result instanceof ActionResult ? $result : ActionResult::success();
        };

        // Execute through Core ActionPipeline
        $pipeline = app(ActionPipeline::class);
        $pipelineResult = $pipeline->execute($context, $mainAction);

        // Check for pending halt
        $pendingHalt = $context->get('pendingHalt');
        if ($pendingHalt instanceof ActionHalt) {
            $this->showHaltModal($haltKey, $action->getName(), $pendingHalt, $data, $actionType);

            return;
        }

        // Handle notification from pipeline
        $notification = $context->get('notification');
        if ($notification) {
            $this->sendNotification(
                // #TODO need fix
                Notification::make()
                    ->title($notification['message'])
                    ->type($notification['type'] ?? 'success'),
            );
        }

        // Handle redirect from pipeline
        $redirect = $context->get('redirect');
        if ($redirect) {
            $this->redirect($redirect);
        }

        // Post-action
        if ($actionType === 'bulk' && method_exists($action, 'shouldDeselectRecordsAfterCompletion') && $action->shouldDeselectRecordsAfterCompletion()) {
            $this->deselectAllRecords();
        }

        $this->handleActionSuccess($action, $payload['record'] ?? $payload['records'] ?? null);

        // Plugin hook: action.executed
        if (app()->bound(PluginManager::class)) {
            $manager = app(PluginManager::class);

            $manager->runHook('action.executed', [
                'action' => $action,
                'actionName' => $action->getName(),
                'actionType' => $actionType,
                'recordIds' => $recordIds,
                'result' => $pipelineResult,
                'component' => $this,
            ]);

            $manager->runTypedHook(
                'action.executed',
                new ActionExecutedPayload(
                    actionName: $action->getName(),
                    context: $context,
                    result: $pipelineResult,
                    actionType: $actionType,
                    component: $this,
                ),
            );
        }

        // Dispatch ActionExecuted event
        event(new ActionExecuted($tableId, $action->getName(), $recordIds, $pipelineResult->isSuccess()));
    }

    /**
     * Convert action payload to Core ActionContext.
     */
    private function payloadToContext(array $payload, string $actionName): ActionContext
    {
        return new ActionContext(
            record: $payload['record'] ?? null,
            records: isset($payload['records']) ? $payload['records'] : null,
            formData: $payload['data'] ?? [],
            actionName: $actionName,
        );
    }

    /**
     * Convert ActionContext back to named-parameter payload for reflection-based callbacks.
     */
    private function contextToPayload(ActionContext $ctx): array
    {
        $payload = [];

        if ($ctx->record !== null) {
            $payload['record'] = $ctx->record;
        }
        if ($ctx->records !== null) {
            $payload['records'] = $ctx->records;
        }
        $payload['data'] = $ctx->formData;

        return $payload;
    }

    /**
     * Show halt modal with dynamic configuration.
     */
    protected function showHaltModal(
        string $recordKey,
        string $actionName,
        ActionHalt $halt,
        array $formData = [],
        string $actionType = 'row',
    ): void {
        $this->tableState->set('modal.halt.recordKey', $recordKey);
        $this->tableState->set('modal.halt.actionName', $actionName);
        $this->tableState->set('modal.halt.config', $halt->toArray()['modal']);
        $this->tableState->set('modal.halt.formData', $halt->getModalFormData() ?? $formData);
        $this->tableState->set('modal.halt.actionType', $actionType);
        $this->tableState->set('modal.halt.context', $halt->toArray()['context'] ?? []);

        // Resolve Form instance for halt modal
        $formInstance = $halt->getFormInstance();
        if ($formInstance) {
            $formInstance->statePath('tableState.modal.halt.formData');
            $formInstance->livewire($this);
            $this->haltModalFormInstance = $formInstance;
            // Persist across Livewire re-renders; Form schema may contain non-serializable
            // closures (options callbacks, validation rules), so we swallow the exception.
            // If serialization fails the form won't survive polling re-renders, but
            // the halt modal stays open and the user can still submit on first render.
            try {
                session()->put('wire.halt_form_instance', serialize($formInstance));
            } catch (\Throwable) {
                // Non-serializable form — session fallback unavailable
            }
        }

        $this->tableState->set('modal.halt.show', true);
    }

    /**
     * Handle post-action success: table invalidation and redirects.
     */
    protected function handleActionSuccess(mixed $action, mixed $record = null): void
    {
        // Invalidate the cached table instance so re-render picks up DB changes
        $this->invalidateTable();

        // Success redirect
        if (method_exists($action, 'getSuccessRedirectUrl')) {
            $redirectUrl = $action->getSuccessRedirectUrl($record);
            if ($redirectUrl) {
                $this->redirect($redirectUrl);
            }
        }
    }

    /**
     * Invalidate cached table instance so that the next render fetches fresh data.
     */
    public function invalidateTable(): void
    {
        $this->tableInstance = null;
        $this->cachedRecords = null;
        $this->cachedQuery = null;
        $this->queryService = null;
        $this->cachedSelectedRecords = null;
        $this->cachedGroupPartitions = null;

        event(new TableRefreshed(static::class));
    }

    /**
     * Send a notification through the resolved notification driver.
     */
    public function sendNotification(Notification $notification): void
    {
        $driver = $this->getTable()->getNotificationDriver();

        NotificationManager::send($notification, $driver, $this);
    }

    /**
     * Open bulk action modal
     */
    public function openBulkActionModal(string $actionName): void
    {
        $action = $this->findBulkAction($actionName);

        if (! $action || ! $action->hasModal()) {
            // No modal, execute directly
            $this->executeBulkAction($actionName);

            return;
        }

        // Get selected records for dynamic form fields/defaults
        $selectedRecords = $this->getSelectedRecords();

        $this->tableState->set('modal.action.name', $actionName);
        $this->tableState->set('modal.action.recordKey', null);
        $this->tableState->set('modal.action.isBulk', true);
        $this->tableState->set('modal.action.isHeaderAction', false);
        $this->tableState->set('modal.action.currentStep', 0);
        $this->actionModalConfigCache = $action->getModalConfig($selectedRecords);
        $this->tableState->set('modal.action.formData', $action->getFormDefaults($selectedRecords));
        $this->actionModalFormInstance = $this->buildModalActionFormInstance($action, $selectedRecords);
        $this->tableState->set('modal.action.show', true);
    }

    /**
     * Find a bulk action by name
     */
    protected function findBulkAction(string $name): ?BulkAction
    {
        $table = $this->getTable();

        foreach ($table->getBulkActions() as $action) {
            if ($action->getName() === $name) {
                return $action;
            }
        }

        return null;
    }

    // ==========================================
    // Modal System
    // ==========================================

    /**
     * Execute bulk action
     */
    public function executeBulkAction(string $actionName, bool $confirmed = false): void
    {
        $action = $this->findBulkAction($actionName);

        if (! $action || ! $action->canExecute()) {
            return;
        }

        $selectedKeys = $this->getSelectedRecordKeys();

        if (empty($selectedKeys)) {
            return;
        }

        $table = $this->getTable();
        $records = $table->getQuery()->whereIn($table->getPrimaryKey(), $selectedKeys)->get();

        $this->executeActionPipeline($action, [
            'records' => $records,
            'data' => [],
        ], '__bulk__', 'bulk', $confirmed);
    }

    /**
     * Submit action modal (execute action with form data)
     */
    public function submitActionModal(): void
    {
        $isHeaderAction = (bool) $this->tableState->get('modal.action.isHeaderAction');
        $isBulkAction = (bool) $this->tableState->get('modal.action.isBulk');
        $actionName = $this->tableState->get('modal.action.name');
        $recordKey = $this->tableState->get('modal.action.recordKey');
        $formData = $this->tableState->get('modal.action.formData', []);

        if (! $actionName) {
            $this->closeActionModal();

            return;
        }

        $action = match (true) {
            $isHeaderAction => $this->findHeaderAction($actionName),
            $isBulkAction => $this->findBulkAction($actionName),
            default => $this->findAction($actionName),
        };

        if (! $action) {
            $this->closeActionModal();

            return;
        }

        $context = $isBulkAction ? ($this->getSelectedRecords()) : ($recordKey ? $this->getRecord($recordKey) : null);

        if ($action->hasMultipleSteps()) {
            // Validate every step's schema and rules against the shared form data
            // before executing. afterValidation hooks already ran while stepping
            // forward, so they are skipped here to avoid firing twice.
            for ($step = 0; $step < $action->getStepCount(); $step++) {
                $this->validateModalStep($action, $context, $step, runAfterValidation: false);
            }
        } else {
            // Re-resolve Form instance (not serialized between Livewire requests)
            if ($this->actionModalFormInstance === null) {
                $this->actionModalFormInstance = $action->getFormInstance($this, $context);
            }

            // Validate via Form instance
            if ($this->actionModalFormInstance !== null) {
                $this->actionModalFormInstance->validate();
            }
        }

        // Execute action
        if ($isHeaderAction) {
            $this->executeHeaderActionWithData($actionName, $formData);
        } elseif ($isBulkAction) {
            $this->executeBulkActionWithData($actionName, $formData);
        } else {
            $this->executeTableActionWithData(
                $recordKey,
                $actionName,
                $formData,
            );
        }

        $this->closeActionModal();
    }

    /**
     * Close action modal
     */
    public function closeActionModal(): void
    {
        $this->tableState->set('modal.action.show', false);
        $this->tableState->set('modal.action.name', null);
        $this->tableState->set('modal.action.recordKey', null);
        $this->tableState->set('modal.action.isBulk', false);
        $this->tableState->set('modal.action.isHeaderAction', false);
        $this->tableState->set('modal.action.formData', []);
        $this->tableState->set('modal.action.currentStep', 0);
        $this->actionModalFormInstance = null;
        $this->actionModalInfolistInstance = null;
        $this->actionModalConfigCache = [];

        // Invalidate table cache so next render fetches fresh data
        $this->invalidateTable();
    }

    /**
     * Run a custom modal footer action declared via Action::modalFooterActions().
     *
     * The callback receives the live form-data bag as `$data` plus a `$set`
     * writer for it, `$component`, and the modal's `$context`/`$record`/`$records`.
     * When the footer action opts into `submitsForm()`, the form is validated
     * first so validation errors surface before the callback runs.
     */
    public function callModalFooterAction(string $name): void
    {
        [$action, $context] = $this->resolveCurrentModalAction();

        if ($action === null) {
            return;
        }

        $footer = null;
        foreach ($action->getModalFooterActions() as $candidate) {
            if ($candidate instanceof ModalFooterAction && $candidate->getName() === $name) {
                $footer = $candidate;
                break;
            }
        }

        if ($footer === null) {
            return;
        }

        if ($footer->shouldSubmitForm()) {
            // Surfaces validation errors (throws ValidationException) before the callback.
            $this->getActionModalFormInstance()?->validate();
        }

        $callback = $footer->getActionCallback();

        if ($callback !== null) {
            $formData = $this->tableState->get('modal.action.formData', []);
            $isBulk = (bool) $this->tableState->get('modal.action.isBulk');
            $isHeader = (bool) $this->tableState->get('modal.action.isHeaderAction');

            $this->invokeActionCallback($callback, [
                'data' => is_array($formData) ? $formData : [],
                'set' => function (string $path, mixed $value): void {
                    $this->tableState->set('modal.action.formData.'.$path, $value);
                },
                'context' => $context,
                'record' => (! $isBulk && ! $isHeader) ? $context : null,
                'records' => $isBulk ? $context : null,
                'component' => $this,
            ]);
        }

        if ($footer->shouldCloseModal()) {
            $this->closeActionModal();
        }
    }

    /**
     * Find header action by name
     */
    protected function findHeaderAction(string $actionName): ?HeaderAction
    {
        $table = $this->getTable();

        foreach ($table->getHeaderActions() as $action) {
            if ($action->getName() === $actionName) {
                return $action;
            }
        }

        return null;
    }

    /**
     * Get a single record by its primary key
     */
    public function getRecord(mixed $key): ?object
    {
        if ($key === null) {
            return null;
        }

        $table = $this->getTable();

        return $table->getQuery()->where($table->getPrimaryKey(), $key)->first();
    }

    // ==========================================
    // Legacy Confirmation Modal (Backwards Compatibility)
    // ==========================================

    /**
     * Execute header action with form data
     */
    public function executeHeaderActionWithData(string $actionName, array $data = [], bool $confirmed = false): void
    {
        $action = $this->findHeaderAction($actionName);

        if (! $action || ! $action->canExecute()) {
            return;
        }

        $this->executeActionPipeline($action, [
            'data' => $data,
        ], '__header__', 'header', $confirmed);
    }

    /**
     * Execute bulk action with form data
     */
    public function executeBulkActionWithData(string $actionName, array $data = [], bool $confirmed = false): void
    {
        $action = $this->findBulkAction($actionName);

        if (! $action || ! $action->canExecute()) {
            return;
        }

        $selectedKeys = $this->getSelectedRecordKeys();

        if (empty($selectedKeys)) {
            return;
        }

        $table = $this->getTable();
        $records = $table->getQuery()->whereIn($table->getPrimaryKey(), $selectedKeys)->get();

        $this->executeActionPipeline($action, [
            'records' => $records,
            'data' => $data,
        ], '__bulk__', 'bulk', $confirmed);
    }

    /**
     * Execute table action with form data
     */
    public function executeTableActionWithData(
        string $recordKey,
        string $actionName,
        array $data = [],
        bool $confirmed = false,
    ): void {
        $action = $this->findAction($actionName);

        if (! $action) {
            return;
        }

        $table = $this->getTable();
        $record = $table->getQuery()->where($table->getPrimaryKey(), $recordKey)->first();

        if (! $record || ! $action->canExecute($record)) {
            return;
        }

        $this->executeActionPipeline($action, [
            'record' => $record,
            'data' => $data,
        ], $recordKey, 'row', $confirmed);
    }

    /**
     * Get current modal data for view
     */
    public function getActionModalData(): array
    {
        // If cache is empty but modal should be shown, regenerate it
        if (empty($this->actionModalConfigCache) && $this->tableState->get('modal.action.show') && $this->tableState->get('modal.action.name')) {
            $this->regenerateModalConfig();
        }

        return $this->actionModalConfigCache;
    }

    /**
     * Get the resolved Form instance for the current action modal, if any.
     * Re-resolves on demand since the Form instance is not serialized between Livewire requests.
     */
    public function getActionModalFormInstance(): ?Form
    {
        if ($this->actionModalFormInstance === null && $this->tableState->get('modal.action.show') && $this->tableState->get('modal.action.name')) {
            $this->resolveActionModalFormInstance();
        }

        return $this->actionModalFormInstance;
    }

    /**
     * Resolve the Form instance from the current action.
     */
    protected function resolveActionModalFormInstance(): void
    {
        $actionName = $this->tableState->get('modal.action.name');
        if (! $actionName) {
            return;
        }

        $action = null;
        $context = null;
        $recordKey = $this->tableState->get('modal.action.recordKey');

        if ($this->tableState->get('modal.action.isHeaderAction')) {
            $action = $this->findHeaderAction($actionName);
            // Header actions carry no record; expose the live form-data bag so a
            // wizard's later steps can build their schema from earlier values.
            $formData = $this->tableState->get('modal.action.formData', []);
            $context = is_array($formData) ? $formData : [];
        } elseif ($this->tableState->get('modal.action.isBulk')) {
            $action = $this->findBulkAction($actionName);
            $context = $this->getSelectedRecords();
        } else {
            $action = $this->findAction($actionName);
            $context = $recordKey ? $this->getRecord($recordKey) : null;
        }

        if ($action) {
            $this->actionModalFormInstance = $this->buildModalActionFormInstance($action, $context);
        }
    }

    /**
     * Resolve the Form instance for an action modal, honouring multi-step
     * wizards. A wizard renders only the current step's schema while all steps
     * share the same `modal.action.formData` state bag.
     */
    protected function buildModalActionFormInstance(Action|BulkAction|HeaderAction $action, mixed $context): ?Form
    {
        if ($action->hasMultipleSteps()) {
            $step = (int) $this->tableState->get('modal.action.currentStep', 0);

            return $action->getStepFormInstance($this, $context, $step);
        }

        return $action->getFormInstance($this, $context);
    }

    /**
     * Resolve the action backing the currently open modal together with its
     * record/selection context.
     *
     * @return array{0: Action|BulkAction|HeaderAction|null, 1: mixed}
     */
    protected function resolveCurrentModalAction(): array
    {
        $actionName = $this->tableState->get('modal.action.name');

        if (! $actionName) {
            return [null, null];
        }

        if ($this->tableState->get('modal.action.isHeaderAction')) {
            // Header actions have no record/selection context, so expose the live
            // form-data bag instead. This lets a multi-step wizard's later steps
            // build their schema (and validation) from values entered earlier.
            $formData = $this->tableState->get('modal.action.formData', []);

            return [$this->findHeaderAction($actionName), is_array($formData) ? $formData : []];
        }

        if ($this->tableState->get('modal.action.isBulk')) {
            return [$this->findBulkAction($actionName), $this->getSelectedRecords()];
        }

        $recordKey = $this->tableState->get('modal.action.recordKey');

        return [$this->findAction($actionName), $recordKey ? $this->getRecord($recordKey) : null];
    }

    /**
     * Advance the wizard to the next step after validating the current one.
     */
    public function nextActionModalStep(): void
    {
        [$action, $context] = $this->resolveCurrentModalAction();

        if (! $action || ! $action->hasMultipleSteps()) {
            return;
        }

        $current = (int) $this->tableState->get('modal.action.currentStep', 0);

        $this->validateModalStep($action, $context, $current);

        $next = min($current + 1, $action->getStepCount() - 1);

        $this->runModalStepBeforeCallback($action, $context, $next);

        $this->tableState->set('modal.action.currentStep', $next);
        $this->actionModalFormInstance = null;
    }

    /**
     * Step the wizard back one step. No validation runs when moving backwards.
     */
    public function prevActionModalStep(): void
    {
        $current = (int) $this->tableState->get('modal.action.currentStep', 0);

        $this->tableState->set('modal.action.currentStep', max(0, $current - 1));
        $this->actionModalFormInstance = null;
    }

    /**
     * Validate a single wizard step: the step's field schema via the Form
     * runtime, then any extra rules declared with ModalStep::validation(), then
     * the optional afterValidation() hook.
     */
    protected function validateModalStep(Action|BulkAction|HeaderAction $action, mixed $context, int $stepIndex, bool $runAfterValidation = true): void
    {
        $action->getStepFormInstance($this, $context, $stepIndex)?->validate();

        $step = $action->getModalStep($stepIndex);

        if (! $step instanceof ModalStep) {
            return;
        }

        $formData = $this->tableState->get('modal.action.formData', []);
        $formData = is_array($formData) ? $formData : [];

        $rules = $step->getValidation($context);

        if ($rules !== []) {
            Validator::make($formData, $rules, $step->getValidationMessages())->validate();
        }

        if ($runAfterValidation && ($callback = $step->getAfterValidationCallback())) {
            $callback($formData, $context);
        }
    }

    /**
     * Run a step's before() hook, letting it pre-fill form state before the step
     * is shown. The returned array (if any) is merged into the form data bag.
     */
    protected function runModalStepBeforeCallback(Action|BulkAction|HeaderAction $action, mixed $context, int $stepIndex): void
    {
        $step = $action->getModalStep($stepIndex);

        if (! $step instanceof ModalStep) {
            return;
        }

        $callback = $step->getBeforeCallback();

        if ($callback === null) {
            return;
        }

        $formData = $this->tableState->get('modal.action.formData', []);
        $formData = is_array($formData) ? $formData : [];

        $result = $callback($formData, $context);

        if (is_array($result)) {
            $this->tableState->set('modal.action.formData', array_merge($formData, $result));
        }
    }

    /**
     * Get the resolved Infolist instance for the current action modal, if any.
     * Re-resolves on demand since it is not serialized between Livewire requests.
     */
    public function getActionModalInfolistInstance(): ?Infolist
    {
        if ($this->actionModalInfolistInstance === null && $this->tableState->get('modal.action.show') && $this->tableState->get('modal.action.name')) {
            $this->resolveActionModalInfolistInstance();
        }

        return $this->actionModalInfolistInstance;
    }

    /**
     * Resolve the Infolist instance from the current action, bound to its record.
     */
    protected function resolveActionModalInfolistInstance(): void
    {
        $actionName = $this->tableState->get('modal.action.name');
        if (! $actionName) {
            return;
        }

        $action = null;
        $context = null;
        $recordKey = $this->tableState->get('modal.action.recordKey');

        if ($this->tableState->get('modal.action.isHeaderAction')) {
            $action = $this->findHeaderAction($actionName);
        } elseif ($this->tableState->get('modal.action.isBulk')) {
            $action = $this->findBulkAction($actionName);
            $context = $this->getSelectedRecords();
        } else {
            $action = $this->findAction($actionName);
            $context = $recordKey ? $this->getRecord($recordKey) : null;
        }

        if ($action) {
            $this->actionModalInfolistInstance = $action->getInfolistInstance($context);
        }
    }

    /**
     * Regenerate modal config from action
     */
    protected function regenerateModalConfig(): void
    {
        $actionName = $this->tableState->get('modal.action.name');
        if (! $actionName) {
            return;
        }

        $recordKey = $this->tableState->get('modal.action.recordKey');

        if ($this->tableState->get('modal.action.isHeaderAction')) {
            $action = $this->findHeaderAction($actionName);
            if ($action) {
                $this->actionModalConfigCache = $action->getModalConfig();
            }
        } elseif ($this->tableState->get('modal.action.isBulk')) {
            $action = $this->findBulkAction($actionName);
            if ($action) {
                $selectedRecords = $this->getSelectedRecords();
                $this->actionModalConfigCache = $action->getModalConfig($selectedRecords);
            }
        } else {
            $action = $this->findAction($actionName);
            if ($action) {
                $record = $recordKey ? $this->getRecord($recordKey) : null;
                $this->actionModalConfigCache = $action->getModalConfig($record);
            }
        }
    }

    /**
     * @deprecated Use halt modal system instead. Will be removed in v2.0.
     */
    public function confirmTableAction(string $recordKey, string $actionName): void
    {
        Deprecation::method('confirmTableAction', 'executeActionPipeline with halt');
    }

    /**
     * @deprecated Use halt modal system instead. Will be removed in v2.0.
     */
    public function executeConfirmedAction(): void
    {
        Deprecation::method('executeConfirmedAction', 'submitHaltModal');
    }

    /**
     * @deprecated Use halt modal system instead. Will be removed in v2.0.
     */
    public function closeConfirmationModal(): void
    {
        Deprecation::method('closeConfirmationModal', 'closeHaltModal');
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
    public function openHeaderActionModal(string $actionName): void
    {
        $action = $this->findHeaderAction($actionName);

        if (! $action || ! $action->hasModal()) {
            // No modal, execute directly
            $this->executeHeaderAction($actionName);

            return;
        }

        $this->tableState->set('modal.action.name', $actionName);
        $this->tableState->set('modal.action.recordKey', null);
        $this->tableState->set('modal.action.isBulk', false);
        $this->tableState->set('modal.action.isHeaderAction', true);
        $this->tableState->set('modal.action.currentStep', 0);
        $this->actionModalConfigCache = $action->getModalConfig();
        $this->tableState->set('modal.action.formData', $action->getFormDefaults());
        $this->actionModalFormInstance = $this->buildModalActionFormInstance($action, null);
        $this->tableState->set('modal.action.show', true);
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

        if (! $column->isEditable()) {
            return ['success' => false, 'message' => __('wire-table::messages.column_not_editable')];
        }

        // ── Permission checks (before transaction — read-only) ──
        if ($column->getPermission()) {
            $user = auth()->user();
            if (! $user) {
                return ['success' => false, 'message' => __('wire-table::messages.no_permission')];
            }
            if (method_exists($user, 'hasPermissionTo') && ! $user->hasPermissionTo($column->getPermission())) {
                return ['success' => false, 'message' => __('wire-table::messages.no_permission_view')];
            }
        }

        // ── Format & validate (before transaction — no DB writes) ──
        if (method_exists($column, 'formatForSave')) {
            $value = $column->formatForSave($value, null);
        }

        // Pre-validate without record context (basic rules)
        $rules = $column->getEditableRules(null);
        if (! empty($rules)) {
            $validationResult = app(ValidationPipeline::class)->validate(
                [$columnName => $value],
                [$columnName => $rules],
            );

            if ($validationResult->failed()) {
                $errors = $validationResult->getError($columnName) ?? [];

                return [
                    'success' => false,
                    'message' => $errors[0] ?? __('wire-table::messages.validation_failed'),
                    'errors' => $errors,
                ];
            }
        }

        // Dispatch CellUpdating event
        event(new CellUpdating(static::class, $columnName, $recordKey, $value));

        // ── Atomic update with optimistic locking ───────────────
        try {
            $result = DB::transaction(function () use ($table, $column, $columnName, $recordKey, $value, $recordVersion) {
                // Lock the row
                $record = $table->getQuery()
                    ->where($table->getPrimaryKey(), $recordKey)
                    ->lockForUpdate()
                    ->first();

                if (! $record) {
                    return ['success' => false, 'message' => __('wire-table::messages.record_not_found')];
                }

                // Capture old value for event
                $oldValue = $record->{$columnName};

                // ── Edit permission (record-aware) ──
                if (method_exists($column, 'canEdit') && ! $column->canEdit($record)) {
                    return ['success' => false, 'message' => __('wire-table::messages.no_permission_edit')];
                }

                // ── Optimistic locking ──
                if ($recordVersion !== null && $recordVersion !== '0' && $record->updated_at) {
                    $currentVersion = (string) $record->updated_at->getTimestamp();
                    if ($currentVersion !== $recordVersion) {
                        $currentValue = $record->{$columnName};
                        if (method_exists($column, 'formatAfterLoad')) {
                            $currentValue = $column->formatAfterLoad($currentValue, $record);
                        }

                        return [
                            'success' => false,
                            'message' => __('wire-table::messages.record_conflict'),
                            'conflict' => true,
                            'currentValue' => (string) ($currentValue ?? ''),
                            'currentVersion' => $currentVersion,
                        ];
                    }
                }

                // ── Re-format with record context ──
                if (method_exists($column, 'formatForSave')) {
                    $value = $column->formatForSave($value, $record);
                }

                // ── Validate with record context ──
                if (method_exists($column, 'validate')) {
                    $validation = $column->validate($value, $record);
                    if (! $validation['valid']) {
                        return [
                            'success' => false,
                            'message' => $validation['errors'][0] ?? __('wire-table::messages.validation_failed'),
                            'errors' => $validation['errors'],
                        ];
                    }
                }

                // ── Save ──
                // Custom save callback
                if (method_exists($column, 'getSaveCallback') && $column->getSaveCallback()) {
                    call_user_func($column->getSaveCallback(), $record, $value, $column);
                    $record->refresh();
                    $newVersion = $record->updated_at ? (string) $record->updated_at->getTimestamp() : null;

                    return ['success' => true, 'version' => $newVersion, 'record' => $record, 'value' => $value, 'oldValue' => $oldValue];
                }

                // Custom update callback (legacy)
                $editableCallback = $column->getEditableCallback();
                if ($editableCallback) {
                    call_user_func($editableCallback, $record, $value);
                    $record->refresh();
                    $newVersion = $record->updated_at ? (string) $record->updated_at->getTimestamp() : null;

                    return ['success' => true, 'version' => $newVersion, 'record' => $record, 'value' => $value, 'oldValue' => $oldValue];
                }

                // Pivot update
                if ($column->isPivot()) {
                    $attribute = $column->getRelationshipAttribute();
                    if ($record->pivot && $attribute !== null) {
                        $record->pivot->{$attribute} = $value;
                        $record->pivot->save();
                    }
                    $record->refresh();
                    $newVersion = $record->updated_at ? (string) $record->updated_at->getTimestamp() : null;

                    return ['success' => true, 'version' => $newVersion, 'record' => $record, 'value' => $value, 'oldValue' => $oldValue];
                }

                // Relation update
                if ($column->getRelation()) {
                    $relation = $column->getRelation();
                    $attribute = $column->getRelationshipAttribute();
                    $related = data_get($record, $relation);
                    if ($related instanceof Model && $attribute !== null) {
                        $related->{$attribute} = $value;
                        $related->save();
                    }
                    $record->refresh();
                    $newVersion = $record->updated_at ? (string) $record->updated_at->getTimestamp() : null;

                    return ['success' => true, 'version' => $newVersion, 'record' => $record, 'value' => $value, 'oldValue' => $oldValue];
                }

                // Direct update
                $record->{$columnName} = $value;
                $record->save();

                $newVersion = $record->updated_at ? (string) $record->updated_at->getTimestamp() : null;

                return ['success' => true, 'version' => $newVersion, 'record' => $record, 'value' => $value];
            });

            // ── Post-transaction callbacks (outside lock) ──
            if ($result['success'] ?? false) {
                $record = $result['record'] ?? null;
                $savedValue = $result['value'] ?? $value;
                $oldValue = $result['oldValue'] ?? null;

                if ($record && method_exists($column, 'getAfterStateUpdatedCallback') && $column->getAfterStateUpdatedCallback()) {
                    call_user_func($column->getAfterStateUpdatedCallback(), $record, $savedValue);
                }

                // Dispatch CellUpdated event
                event(new CellUpdated(static::class, $columnName, $recordKey, $oldValue, $savedValue));

                // Clean internal keys before returning to client
                unset($result['record'], $result['value'], $result['oldValue']);
            }

            return $result;

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

        // Apply formatters before validation (for TextInputColumn)
        if (method_exists($column, 'formatForSave')) {
            $value = $column->formatForSave($value, $record);
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
        $importConfig = null;
        foreach ($this->getTable()->getHeaderActions() as $action) {
            if ($action instanceof ImportAction) {
                $importConfig = $action->getImportConfig();
                break;
            }
        }

        $result = ($importConfig ?? TableImport::make())->import($filePath);

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
        $service = new TableQueryService;

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
