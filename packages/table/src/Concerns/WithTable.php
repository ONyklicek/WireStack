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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\WithPagination;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionGroup;
use NyonCode\WireCore\Actions\ActionHalt;
use NyonCode\WireCore\Actions\BulkAction;
use NyonCode\WireCore\Actions\HeaderAction;
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
use NyonCode\WireCore\Core\Support\Deprecation;
use NyonCode\WireCore\Core\Validation\ValidationPipeline;
use NyonCode\WireCore\Notifications\Notification;
use NyonCode\WireCore\Notifications\NotificationManager;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Table;
use ReflectionFunction;

trait WithTable
{
    use HasSqlDebug;
    use WithPagination;

    public ?string $tableSearch = null;

    public string $tableSortColumn = '';

    public string $tableSortDirection = 'asc';

    public int $tablePerPage = 10;

    public array $tableFilters = [];

    public array $columnFilters = [];

    public array $selectedRecords = [];

    public array $hiddenColumns = []; // Array of hidden column names

    /** @var array Record keys that are currently expanded (showing sub-rows) */
    public array $expandedRows = [];

    /** @var bool If true, show all sub-rows inline (flatten mode) */
    public bool $flattenMode = false;

    /** @var array Filters for sub-rows (keyed by column name) */
    public array $subRowFilters = [];

    // Modal state
    public bool $showActionModal = false;

    public ?string $actionModalName = null;

    public ?string $actionModalRecordKey = null;

    public bool $actionModalIsBulk = false;

    public array $actionModalFormData = [];

    /** @var Form|null Resolved Form instance for the current action modal */
    protected ?Form $actionModalFormInstance = null;

    public bool $actionModalIsHeaderAction = false;

    // Dynamic halt modal state
    protected ?Form $haltModalFormInstance = null;

    public bool $showHaltModal = false;

    public ?string $haltActionName = null;

    public ?string $haltRecordKey = null;

    public array $haltModalConfig = [];

    public array $haltModalFormData = [];

    public bool $haltActionConfirmed = false;

    // Halt context - tracks where halt originated and how to resume
    public ?string $haltActionType = null;  // 'row', 'bulk', 'header'

    public array $haltContext = [];  // skipBeforeOnConfirm, source, index, redirectAfterConfirm

    // Lazy loading state
    public bool $tableReady = false;

    // Polling state
    public bool $tablePollingActive = true;

    protected string $wireTableClass = Table::class;

    protected ?Table $tableInstance = null;

    /** @var array Modal config - not a public Livewire property */
    protected array $actionModalConfigCache = [];

    /** @var LengthAwarePaginator|Paginator|CursorPaginator|Collection|null Cached records for current request lifecycle */
    protected LengthAwarePaginator|Paginator|CursorPaginator|Collection|null $cachedRecords = null;

    /** @var TableQueryService|null Shared query service instance */
    protected ?TableQueryService $queryService = null;

    /**
     * Initialize table state
     */
    public function mountWithTable(): void
    {
        $table = $this->getTable();

        // If lazy loading is enabled, don't load data yet
        if ($table->isLazy()) {
            $this->tableReady = false;
        } else {
            $this->tableReady = true;
        }

        if ($table->getDefaultSort()) {
            $this->tableSortColumn = $table->getDefaultSort();
            $this->tableSortDirection = $table->getDefaultSortDirection();
        }

        $this->tablePerPage = $table->getPerPage();

        // Initialize filters with defaults
        foreach ($table->getFilters() as $filter) {
            $default = $filter->getDefault();
            if ($default !== null) {
                $this->tableFilters[$filter->getName()] = $default;
            }
        }

        // Initialize hidden columns (columns that start hidden)
        foreach ($table->getColumns() as $column) {
            if ($column->isToggleable() && ! $column->isVisible()) {
                $this->hiddenColumns[] = $column->getName();
            }
        }
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

        // Simply re-render - Livewire will fetch new data
        // The table instance is recreated on each request
        $this->tableInstance = null;
    }

    /**
     * Check if polling should be active.
     */
    public function shouldPoll(): bool
    {
        if (! $this->tablePollingActive) {
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
        $this->tablePollingActive = false;
    }

    /**
     * Resume table polling.
     */
    public function resumeTablePolling(): void
    {
        $this->tablePollingActive = true;
    }

    /**
     * Toggle table polling.
     */
    public function toggleTablePolling(): void
    {
        $this->tablePollingActive = ! $this->tablePollingActive;
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

        return array_merge($table->getPollingConfig(), ['active' => $this->tablePollingActive && $this->shouldPoll()]);
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
        $this->tableReady = true;
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
        if ($table->isLazy() && ! $this->tableReady) {
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

        return $this->cachedRecords;
    }


    /**
     * Execute query with the appropriate pagination mode.
     *
     * @param  Builder<Model>  $query
     */
    protected function paginateQuery(Table $table, Builder $query): LengthAwarePaginator|Paginator|CursorPaginator
    {
        return match ($table->getPaginationMode()) {
            'simple' => $query->simplePaginate($this->tablePerPage),
            'cursor' => $query->cursorPaginate($this->tablePerPage),
            default => $query->paginate($this->tablePerPage),
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

        return Cache::remember($key, $ttl, function () use ($table, $query) {
            if ($table->isPaginated()) {
                return $this->paginateQuery($table, $query);
            }

            return $query->get();
        });
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
            $this->tablePerPage.
            $this->tableSortColumn.
            $this->tableSortDirection
        );
    }

    /**
     * Build the complete query with all modifications applied.
     *
     * Delegates to TableQueryService which uses the Core QueryPlanner
     * and QueryExecutor infrastructure. This replaces ~500 lines of
     * inline query building, accessor reflection, and metadata analysis.
     *
     * @return Builder<Model>
     */
    protected function buildTableQuery(): Builder
    {
        $table = $this->getTable();
        $baseQuery = $table->getQuery();
        $tableId = static::class;

        // Dispatch search event
        if ($this->tableSearch) {
            $searchableColumns = [];
            foreach ($table->getColumns() as $col) {
                if ($col->isSearchable()) {
                    $searchableColumns[] = $col->getName();
                }
            }
            event(new TableSearching($tableId, $this->tableSearch, $searchableColumns));
        }

        // Dispatch filter event
        $activeFilters = array_filter($this->tableFilters, fn ($v) => $v !== null && $v !== '' && $v !== []);
        if (! empty($activeFilters)) {
            event(new TableFiltering($tableId, $activeFilters));
        }

        $query = $this->getQueryService()->buildQuery(
            baseQuery: $baseQuery,
            table: $table,
            search: $this->tableSearch,
            filterValues: $this->tableFilters,
            sortColumn: ! empty($this->tableSortColumn) ? $this->tableSortColumn : null,
            sortDirection: $this->tableSortDirection,
            columnFilterValues: $this->columnFilters,
        );

        // Post-search event
        if ($this->tableSearch) {
            // Count is deferred — we dispatch with -1 as a signal that count is not yet known
            event(new TableSearched($tableId, $this->tableSearch, -1));
        }

        // Post-filter event
        if (! empty($activeFilters)) {
            event(new TableFiltered($tableId, $activeFilters, -1));
        }

        return $query;
    }

    /**
     * Check if table is ready to display data
     */
    public function isTableReady(): bool
    {
        return $this->tableReady;
    }

    // ==========================================
    // Sort, Search, Filter State Management
    // ==========================================

    /**
     * Sort table by column
     */
    public function sortTable(string $column): void
    {
        if ($this->tableSortColumn === $column) {
            $this->tableSortDirection = $this->tableSortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->tableSortColumn = $column;
            $this->tableSortDirection = 'asc';
        }

        $this->resetPage();
    }

    /**
     * Update per page setting
     */
    public function updatedTablePerPage(): void
    {
        $this->resetPage();
    }

    /**
     * Update search query
     */
    public function updatedTableSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Update filters
     */
    public function updatedTableFilters(): void
    {
        $this->resetPage();
    }

    /**
     * Update column filters
     */
    public function updatedColumnFilters(): void
    {
        $this->resetPage();
    }

    /**
     * Reset all filters
     */
    public function resetTableFilters(): void
    {
        $this->tableFilters = [];
        $this->columnFilters = [];
        $this->tableSearch = null;
        $this->resetPage();
    }

    /**
     * Reset column filters only
     */
    public function resetColumnFilters(): void
    {
        $this->columnFilters = [];
        $this->resetPage();
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

        if (in_array($key, $this->expandedRows, true)) {
            $this->expandedRows = array_values(array_diff($this->expandedRows, [$key]));
        } else {
            $this->expandedRows[] = $key;
        }
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
            $this->expandedRows = [];
        } else {
            $records = $this->getTableRecords();
            $this->expandedRows = $records->pluck($table->getPrimaryKey())
                ->map(fn ($k) => (string) $k)
                ->all();
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
            $this->expandedRows = $records->pluck($table->getPrimaryKey())
                ->map(fn ($k) => (string) $k)
                ->all();
        } else {
            $this->expandedRows = [];
        }
    }

    /**
     * Check if a row is expanded.
     */
    public function isRowExpanded(mixed $recordKey): bool
    {
        $isInList = in_array((string) $recordKey, $this->expandedRows, true);

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
        $this->flattenMode = ! $this->flattenMode;
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

        $query = $table->getSubRowsQuery($record);

        // Apply sub-row filters
        if ($table->isSubRowsFilterable() && ! empty($this->subRowFilters)) {
            foreach ($table->getSubRowColumns() as $column) {
                $colName = $column->getName();
                $filterValue = $this->subRowFilters[$colName] ?? null;

                if ($filterValue !== null && $filterValue !== '' && $column->isFilterable()) {
                    $query = $column->applyFilter($query, $filterValue);
                }
            }
        }

        return $query->get();
    }

    /**
     * Reset sub-row filters.
     */
    public function resetSubRowFilters(): void
    {
        $this->subRowFilters = [];
    }

    /**
     * Livewire hook for sub-row filter updates.
     */
    public function updatedSubRowFilters(): void
    {
        // Sub-row filters don't need pagination reset
    }

    // ─── Summaries ───────────────────────────────────────

    /**
     * Compute all column summaries.
     * Returns an array keyed by column name.
     *
     * @param  string  $scope  'page' for current page, 'query' for all filtered records, 'subRows' for sub-rows
     * @param  mixed  $parentRecord  Parent record (only for 'subRows' scope)
     * @return array [columnName => [['label' => ..., 'value' => ...], ...], ...]
     */
    public function computeTableSummaries(string $scope = 'query', mixed $parentRecord = null): array
    {
        $table = $this->getTable();
        $pageRecords = $this->getTableRecords();
        $query = ($scope === 'query') ? $this->buildTableQuery() : null;

        // For sub-rows scope, use sub-row records
        if ($scope === 'subRows' && $parentRecord !== null && $table->hasSubRows()) {
            $subRecords = $this->getSubRows($parentRecord);
            $columnsToSummarize = $table->getSubRowColumns();

            $summaries = [];
            foreach ($columnsToSummarize as $column) {
                if ($column->hasSummary()) {
                    $summaries[$column->getName()] = $column->computeSummaries($subRecords, null);
                }
            }

            return $summaries;
        }

        // For main table
        $columns = $table->getColumns();
        $summaries = [];

        foreach ($columns as $column) {
            if ($column->hasSummary()) {
                $summaries[$column->getName()] = $column->computeSummaries(
                    $scope === 'page' ? $pageRecords : collect(),
                    $query,
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

        return false;
    }

    // ─── Column Visibility ───────────────────────────────

    /**
     * Toggle column visibility
     */
    public function toggleColumn(string $column): void
    {
        $isHidden = in_array($column, $this->hiddenColumns, true);

        if ($isHidden) {
            // Show the column - remove from hidden
            $index = array_search($column, $this->hiddenColumns, true);
            if ($index !== false) {
                unset($this->hiddenColumns[$index]);
                $this->hiddenColumns = array_values($this->hiddenColumns);
            }
        } else {
            // Hide the column - but check if it's the last visible
            $visibleCount = 0;
            $table = $this->getTable();

            foreach ($table->getColumns() as $col) {
                if ($col->isToggleable() && $col->canView()) {
                    if (! in_array($col->getName(), $this->hiddenColumns, true)) {
                        $visibleCount++;
                    }
                }
            }

            // Don't allow hiding the last visible column
            if ($visibleCount <= 1) {
                return;
            }

            $this->hiddenColumns[] = $column;
        }
    }

    /**
     * Check if column is visible
     */
    public function isColumnVisible(string $column): bool
    {
        return ! in_array($column, $this->hiddenColumns, true);
    }

    // ─── Record Selection ────────────────────────────────

    /**
     * Toggle record selection
     */
    public function toggleRecordSelection(string $key): void
    {
        $index = array_search($key, $this->selectedRecords, true);

        if ($index !== false) {
            unset($this->selectedRecords[$index]);
            $this->selectedRecords = array_values($this->selectedRecords);
        } else {
            $this->selectedRecords[] = $key;
        }
    }

    /**
     * Select all visible records
     */
    public function selectAllRecords(): void
    {
        $records = $this->getTableRecords();
        $primaryKey = $this->getTable()->getPrimaryKey();

        $this->selectedRecords = [];

        foreach ($records as $record) {
            $this->selectedRecords[] = (string) $record->{$primaryKey};
        }
    }

    /**
     * Check if record is selected
     */
    public function isRecordSelected(string $key): bool
    {
        return in_array($key, $this->selectedRecords, true);
    }

    /**
     * Get selected records count
     */
    public function getSelectedRecordsCount(): int
    {
        return count($this->selectedRecords);
    }

    /**
     * Check if some (but not all) visible records are selected
     */
    public function areSomeVisibleSelected(): bool
    {
        if (empty($this->selectedRecords)) {
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

        $primaryKey = $this->getTable()->getPrimaryKey();

        foreach ($records as $record) {
            $key = (string) $record->{$primaryKey};
            if (! in_array($key, $this->selectedRecords, true)) {
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
        return $this->selectedRecords;
    }

    /**
     * Deselect all records
     */
    public function deselectAllRecords(): void
    {
        $this->selectedRecords = [];
    }

    /**
     * Get Collection of selected records (fetched from database)
     */
    public function getSelectedRecords(): Collection
    {
        $selectedKeys = $this->getSelectedRecordKeys();

        if (empty($selectedKeys)) {
            return collect();
        }

        $table = $this->getTable();

        return $table->getQuery()->whereIn($table->getPrimaryKey(), $selectedKeys)->get();
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

        $this->actionModalName = $actionName;
        $this->actionModalRecordKey = $recordKey;
        $this->actionModalIsBulk = false;
        $this->actionModalIsHeaderAction = false;
        $this->actionModalConfigCache = $action->getModalConfig($record);
        $this->actionModalFormData = $action->getFormDefaults($record);
        $this->actionModalFormInstance = $action->getFormInstance($this, $record);
        $this->showActionModal = true;
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

        // Dispatch ActionExecuting event
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
        $this->haltRecordKey = $recordKey;
        $this->haltActionName = $actionName;
        $this->haltModalConfig = $halt->toArray()['modal'];
        $this->haltModalFormData = $halt->getModalFormData() ?? $formData;
        $this->haltActionType = $actionType;
        $this->haltContext = $halt->toArray()['context'] ?? [];

        // Resolve Form instance for halt modal
        $formInstance = $halt->getFormInstance();
        if ($formInstance) {
            $formInstance->statePath('haltModalFormData');
            $formInstance->livewire($this);
            $this->haltModalFormInstance = $formInstance;
            // Store in session so it survives Livewire re-renders
            session()->put('wire.halt_form_instance', serialize($formInstance));
        }

        $this->showHaltModal = true;
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
        $this->queryService = null;

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

        $this->actionModalName = $actionName;
        $this->actionModalRecordKey = null;
        $this->actionModalIsBulk = true;
        $this->actionModalIsHeaderAction = false;
        $this->actionModalConfigCache = $action->getModalConfig($selectedRecords);
        $this->actionModalFormData = $action->getFormDefaults($selectedRecords);
        $this->actionModalFormInstance = $action->getFormInstance($this, $selectedRecords);
        $this->showActionModal = true;
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
        $isHeaderAction = $this->actionModalIsHeaderAction;
        $isBulkAction = $this->actionModalIsBulk;

        if (! $this->actionModalName) {
            $this->closeActionModal();

            return;
        }

        $action = match (true) {
            $isHeaderAction => $this->findHeaderAction($this->actionModalName),
            $isBulkAction => $this->findBulkAction($this->actionModalName),
            default => $this->findAction($this->actionModalName),
        };

        if (! $action) {
            $this->closeActionModal();

            return;
        }

        // Re-resolve Form instance (not serialized between Livewire requests)
        if ($this->actionModalFormInstance === null) {
            $context = $isBulkAction ? ($this->getSelectedRecords()) : ($this->actionModalRecordKey ? $this->getRecord($this->actionModalRecordKey) : null);
            $this->actionModalFormInstance = $action->getFormInstance($this, $context);
        }

        // Validate via Form instance
        if ($this->actionModalFormInstance !== null) {
            $this->actionModalFormInstance->validate();
        }

        // Execute action
        if ($isHeaderAction) {
            $this->executeHeaderActionWithData($this->actionModalName, $this->actionModalFormData);
        } elseif ($isBulkAction) {
            $this->executeBulkActionWithData($this->actionModalName, $this->actionModalFormData);
        } else {
            $this->executeTableActionWithData(
                $this->actionModalRecordKey,
                $this->actionModalName,
                $this->actionModalFormData,
            );
        }

        $this->closeActionModal();
    }

    /**
     * Close action modal
     */
    public function closeActionModal(): void
    {
        $this->showActionModal = false;
        $this->actionModalName = null;
        $this->actionModalRecordKey = null;
        $this->actionModalIsBulk = false;
        $this->actionModalIsHeaderAction = false;
        $this->actionModalFormData = [];
        $this->actionModalFormInstance = null;
        $this->actionModalConfigCache = [];

        // Invalidate table cache so next render fetches fresh data
        $this->invalidateTable();
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
        if (empty($this->actionModalConfigCache) && $this->showActionModal && $this->actionModalName) {
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
        if ($this->actionModalFormInstance === null && $this->showActionModal && $this->actionModalName) {
            $this->resolveActionModalFormInstance();
        }

        return $this->actionModalFormInstance;
    }

    /**
     * Resolve the Form instance from the current action.
     */
    protected function resolveActionModalFormInstance(): void
    {
        if (! $this->actionModalName) {
            return;
        }

        $action = null;
        $context = null;

        if ($this->actionModalIsHeaderAction) {
            $action = $this->findHeaderAction($this->actionModalName);
        } elseif ($this->actionModalIsBulk) {
            $action = $this->findBulkAction($this->actionModalName);
            $context = $this->getSelectedRecords();
        } else {
            $action = $this->findAction($this->actionModalName);
            $context = $this->actionModalRecordKey ? $this->getRecord($this->actionModalRecordKey) : null;
        }

        if ($action) {
            $this->actionModalFormInstance = $action->getFormInstance($this, $context);
        }
    }

    /**
     * Regenerate modal config from action
     */
    protected function regenerateModalConfig(): void
    {
        if (! $this->actionModalName) {
            return;
        }

        if ($this->actionModalIsHeaderAction) {
            $action = $this->findHeaderAction($this->actionModalName);
            if ($action) {
                $this->actionModalConfigCache = $action->getModalConfig();
            }
        } elseif ($this->actionModalIsBulk) {
            $action = $this->findBulkAction($this->actionModalName);
            if ($action) {
                $selectedRecords = $this->getSelectedRecords();
                $this->actionModalConfigCache = $action->getModalConfig($selectedRecords);
            }
        } else {
            $action = $this->findAction($this->actionModalName);
            if ($action) {
                $record = $this->actionModalRecordKey ? $this->getRecord($this->actionModalRecordKey) : null;
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
        return $this->haltModalConfig;
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

        if ($this->showHaltModal && session()->has('wire.halt_form_instance')) {
            $this->haltModalFormInstance = unserialize(session()->get('wire.halt_form_instance'));
            $this->haltModalFormInstance->livewire($this);
        }

        return $this->haltModalFormInstance;
    }

    /**
     * Submit halt modal (confirm and re-execute action).
     */
    public function submitHaltModal(array $formData = []): void
    {
        if (! $this->haltActionName) {
            $this->closeHaltModal();

            return;
        }

        // Validate form if present
        $validation = $this->haltModalConfig['formValidation'] ?? null;
        if ($validation && ! empty($formData)) {
            $result = app(ValidationPipeline::class)->validate(
                $formData,
                $validation,
                $this->haltModalConfig['formValidationMessages'] ?? [],
                $this->haltModalConfig['formValidationAttributes'] ?? [],
            );

            if ($result->failed()) {
                throw ValidationException::withMessages($result->errors());
            }
        }

        // Merge form data
        $data = array_merge($this->haltModalFormData, $formData);

        // Capture context before closing
        $actionName = $this->haltActionName;
        $recordKey = $this->haltRecordKey;
        $actionType = $this->haltActionType ?? 'row';
        $redirectAfterConfirm = $this->haltContext['redirectAfterConfirm'] ?? null;

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
        $this->showHaltModal = false;
        $this->haltActionName = null;
        $this->haltRecordKey = null;
        $this->haltModalConfig = [];
        $this->haltModalFormData = [];
        $this->haltModalFormInstance = null;
        session()->forget('wire.halt_form_instance');
        $this->haltActionType = null;
        $this->haltContext = [];

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

        $this->actionModalName = $actionName;
        $this->actionModalRecordKey = null;
        $this->actionModalIsBulk = false;
        $this->actionModalIsHeaderAction = true;
        $this->actionModalConfigCache = $action->getModalConfig();
        $this->actionModalFormData = $action->getFormDefaults();
        $this->actionModalFormInstance = $action->getFormInstance($this);
        $this->showActionModal = true;
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
                    if ($record->pivot) {
                        $record->pivot->{$attribute} = $value;
                        $record->pivot->save();
                    }

                    return ['success' => true, 'record' => $record, 'value' => $value, 'oldValue' => $oldValue];
                }

                // Relation update
                if ($column->getRelation()) {
                    $relation = $column->getRelation();
                    $attribute = $column->getRelationshipAttribute();
                    $related = data_get($record, $relation);
                    if ($related instanceof Model) {
                        $related->{$attribute} = $value;
                        $related->save();
                    }

                    return ['success' => true, 'record' => $record, 'value' => $value, 'oldValue' => $oldValue];
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

                $this->invalidateTable();

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
            'search' => $this->tableSearch ?? null,
            'filters' => $this->tableFilters ?? [],
            'column_filters' => $this->columnFilters ?? [],
            'sort_column' => $this->tableSortColumn,
            'sort_direction' => $this->tableSortDirection,
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
            'search' => $this->tableSearch ?? null,
            'filters' => $this->tableFilters ?? [],
            'column_filters' => $this->columnFilters ?? [],
            'sort_column' => $this->tableSortColumn,
            'sort_direction' => $this->tableSortDirection,
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
        $hiddenNames = $this->hiddenColumns ?? [];

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
        $hiddenNames = $this->hiddenColumns ?? [];

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
        // Invalidate cached records so next render fetches fresh data
        $this->cachedRecords = null;
    }
}
