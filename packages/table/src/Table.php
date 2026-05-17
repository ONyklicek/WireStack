<?php

declare(strict_types=1);

namespace NyonCode\WireTable;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionGroup;
use NyonCode\WireCore\Core\Support\Deprecation;
use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Concerns\HasSqlDebug;
use NyonCode\WireTable\Filters\Filter;
use RuntimeException;

/** @phpstan-consistent-constructor */
class Table implements Htmlable
{
    use Concerns\HasSubRows;
    use HasSqlDebug;
    use Macroable;

    protected ?string $model = null;

    /** @var Builder<Model>|null */
    protected ?Builder $query = null;

    protected ?Closure $modifyQueryCallback = null;

    /** @var array<int, Column> */
    protected array $columns = [];

    /** @var array<int, Filter> */
    protected array $filters = [];

    /** @var array<int, Action|ActionGroup> */
    protected array $actions = [];

    /** @var array<int, Action> */
    protected array $bulkActions = [];

    /** @var array<int, Action> */
    protected array $headerActions = [];

    protected int $perPage = 10;

    /** @var array<int, int> */
    protected array $perPageOptions = [10, 25, 50, 100];

    protected bool $searchable = true;

    protected bool $sortable = true;

    protected bool $paginated = true;

    protected bool $selectable = false;

    protected ?string $defaultSort = null;

    protected string $defaultSortDirection = 'asc';

    protected ?string $emptyStateHeading = null;

    protected ?string $emptyStateDescription = null;

    protected ?string $emptyStateIcon = null;

    protected bool $striped = false;

    protected bool $hoverable = true;

    protected ?string $recordUrl = null;

    protected ?Closure $recordUrlCallback = null;

    protected mixed $livewireComponent = null;

    protected ?string $primaryKey = 'id';

    // Action positioning
    protected string $actionsPosition = 'end'; // 'start', 'end'

    protected string $actionsAlignment = 'right'; // 'left', 'center', 'right'

    protected ?string $actionsColumnLabel = null;

    protected ?string $actionsColumnWidth = null;

    // Table styling
    protected bool $compact = false;

    protected bool $bordered = false;

    protected ?string $tableClass = null;

    protected ?string $headerClass = null;

    protected ?string $rowClass = null;

    // Responsive layout
    protected bool $stackedOnMobile = false;

    protected string $stackedBreakpoint = 'md';

    // Lazy loading
    protected bool $lazy = false;

    protected ?string $lazyPlaceholder = null;

    // Polling
    protected bool $polling = false;

    protected ?string $pollingInterval = null;

    protected bool $pollingKeepAlive = false;

    protected ?Closure $pollingCondition = null;

    protected string $pollingMethod = 'refresh'; // 'refresh' | 'reload'

    protected bool $pollingVisible = true; // Only poll when tab is visible

    // Pagination mode: 'standard' | 'simple' | 'cursor'
    protected string $paginationMode = 'standard';

    // Query caching
    protected ?int $queryCacheTtl = null;

    protected ?string $queryCacheKey = null;

    // Notification driver
    protected ?NotificationDriver $notificationDriver = null;

    public static function make(): static
    {
        return new static; // @phpstan-ignore new.static
    }

    public function model(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Modify the base query using a callback.
     *
     * This allows you to add custom conditions, joins, eager loading, etc.
     * The callback receives the query builder and should return it.
     *
     * Example:
     * ->modifyQueryUsing(fn (Builder $query) => $query->where('active', true))
     * ->modifyQueryUsing(fn (Builder $query) => $query->with(['roles', 'permissions']))
     * ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('orders'))
     *
     * @param  Closure  $callback  Receives Builder, should return Builder
     */
    public function modifyQueryUsing(Closure $callback): static
    {
        $this->modifyQueryCallback = $callback;

        return $this;
    }

    /**
     * Get the callback for modifying the query.
     */
    public function getModifyQueryCallback(): ?Closure
    {
        return $this->modifyQueryCallback;
    }

    /**
     * Get raw SQL and bindings separately.
     *
     * @return array{sql: string, bindings: array<int, mixed>}
     */
    public function toRawSql(): array
    {
        $query = $this->getQuery();

        return [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
        ];
    }

    /**
     * @return Builder<Model>
     */
    public function getQuery(): Builder
    {
        $query = null;

        if ($this->query) {
            $query = clone $this->query;
        } elseif ($this->model) {
            $query = $this->model::query();
        } else {
            throw new RuntimeException('No model or query defined for table.');
        }

        // Apply query modification callback if set
        if ($this->modifyQueryCallback) {
            $query = call_user_func($this->modifyQueryCallback, $query) ?? $query;
        }

        return $query;
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function query(Builder $query): static
    {
        $this->query = $query;

        return $this;
    }

    /**
     * Get the SQL query string with bindings replaced.
     * Useful for debugging.
     *
     * @return string The SQL query with values
     */
    public function toSql(): string
    {
        return static::builderToSql($this->getQuery());
    }

    /**
     * Dump the query and continue execution.
     */
    public function dump(): static
    {
        dump([
            'sql' => $this->toSql(),
            'raw_sql' => $this->getQuery()->toSql(),
            'bindings' => $this->getQuery()->getBindings(),
        ]);

        return $this;
    }

    /**
     * Dump the query and stop execution.
     */
    public function dd(): never
    {
        dd([
            'sql' => $this->toSql(),
            'raw_sql' => $this->getQuery()->toSql(),
            'bindings' => $this->getQuery()->getBindings(),
        ]);
    }

    /**
     * Get column names only.
     *
     * @return array<int, string>
     */
    public function getColumnNames(): array
    {
        return array_map(fn ($c) => $c->getName(), $this->columns);
    }

    /**
     * Dump all columns info and continue.
     */
    public function dumpColumns(): static
    {
        dump([
            'defined_columns' => $this->getColumnsInfo(),
            'database_columns' => $this->getDatabaseColumns(),
        ]);

        return $this;
    }

    /**
     * Get all defined table columns with their configuration.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getColumnsInfo(): array
    {
        $info = [];
        foreach ($this->columns as $column) {
            $info[$column->getName()] = [
                'name' => $column->getName(),
                'label' => $column->getLabel(),
                'sortable' => $column->isSortable(),
                'searchable' => $column->isSearchable(),
                'toggleable' => $column->isToggleable(),
                'visible' => $column->canView(),
                'editable' => method_exists($column, 'isEditable') ? $column->isEditable() : false,
                'type' => class_basename($column),
            ];
        }

        return $info;
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    /**
     * Get database table columns (from the model's table).
     */
    /**
     * @return array<int, string>
     */
    public function getDatabaseColumns(): array
    {
        $query = $this->getQuery();
        $table = $query->getModel()->getTable();
        /** @var Connection $connection */
        $connection = $query->getConnection();

        return $connection->getSchemaBuilder()->getColumnListing($table);
    }

    /**
     * Dump all columns info and stop.
     */
    public function ddColumns(): never
    {
        dd([
            'defined_columns' => $this->getColumnsInfo(),
            'database_columns' => $this->getDatabaseColumns(),
            'database_columns_info' => $this->getDatabaseColumnsInfo(),
        ]);
    }

    /**
     * Get detailed database column information.
     */
    /**
     * @return array<string, array{name: string, type: string}>
     */
    public function getDatabaseColumnsInfo(): array
    {
        $query = $this->getQuery();
        $model = $query->getModel();
        $table = $model->getTable();
        /** @var Connection $connection */
        $connection = $query->getConnection();

        $columns = [];
        foreach ($connection->getSchemaBuilder()->getColumnListing($table) as $columnName) {
            $columns[$columnName] = [
                'name' => $columnName,
                'type' => $connection->getSchemaBuilder()->getColumnType($table, $columnName),
            ];
        }

        return $columns;
    }

    /**
     * Get debug information about the table configuration.
     *
     * @return array<string, mixed>
     */
    public function debug(): array
    {
        return [
            'model' => $this->model,
            'sql' => $this->toSql(),
            'raw_sql' => $this->getQuery()->toSql(),
            'bindings' => $this->getQuery()->getBindings(),
            'columns' => $this->getColumnsInfo(),
            'database_columns' => $this->getDatabaseColumns(),
            'filters' => array_map(fn ($f) => $f->getName(), $this->filters),
            'searchable' => $this->searchable,
            'sortable' => $this->sortable,
            'paginated' => $this->paginated,
            'per_page' => $this->perPage,
            'default_sort' => $this->defaultSort,
            'default_sort_direction' => $this->defaultSortDirection,
        ];
    }

    /**
     * Debug the QueryPlan for the current table configuration.
     *
     * Shows the planned joins, filters, search, sorting, eager loads, and aggregates
     * that the QueryPlanner would produce. Dev-only, disabled in production.
     *
     * @param  string|null  $search  Simulated search term
     * @param  array<string, mixed>  $filterValues  Simulated filter values
     * @param  string|null  $sortColumn  Simulated sort column
     * @param  string  $sortDirection  Simulated sort direction
     * @return array<string, mixed> QueryPlan debug info
     */
    public function debugQueryPlan(
        ?string $search = null,
        array $filterValues = [],
        ?string $sortColumn = null,
        string $sortDirection = 'asc',
    ): array {
        $service = new Concerns\TableQueryService;
        $baseQuery = $this->getQuery();

        // Build query to populate the plan
        $modifiedQuery = $service->buildQuery(
            baseQuery: $baseQuery,
            table: $this,
            search: $search,
            filterValues: $filterValues,
            sortColumn: $sortColumn ?? $this->defaultSort,
            sortDirection: $sortDirection,
        );

        $plan = $service->getLastPlan();

        if ($plan === null) {
            return ['error' => 'No QueryPlan generated'];
        }

        return [
            'query_plan' => [
                'joins' => array_map(fn ($j) => [
                    'table' => $j->table,
                    'alias' => $j->alias,
                    'type' => $j->type,
                    'first' => $j->firstColumn,
                    'operator' => $j->operator,
                    'second' => $j->secondColumn,
                ], $plan->joins),
                'eager_loads' => $plan->eagerLoads,
                'aggregates' => array_map(fn ($a) => [
                    'relation' => $a->relation,
                    'function' => $a->function,
                    'column' => $a->column,
                ], $plan->aggregates),
                'filters' => array_map(fn ($f) => [
                    'column' => $f->column,
                    'operator' => $f->operator,
                    'value' => $f->value,
                    'table_alias' => $f->tableAlias ?? null,
                    'is_relation' => $f->isRelation,
                ], $plan->filters),
                'search_clauses' => array_map(fn ($s) => [
                    'column' => $s->column,
                    'table_alias' => $s->tableAlias,
                    'is_relation' => $s->isRelation,
                ], $plan->searchClauses),
                'sort_clauses' => array_map(fn ($s) => [
                    'column' => $s->column,
                    'direction' => $s->direction,
                    'table_alias' => $s->tableAlias ?? null,
                    'is_relation' => $s->isRelation,
                ], $plan->sortClauses),
                'selected_columns' => $plan->selectedColumns,
                'scopes' => $plan->scopes,
                'with_soft_deletes' => $plan->withSoftDeletes,
            ],
            'final_sql' => static::builderToSql($modifiedQuery),
            'raw_sql' => $modifiedQuery->toSql(),
            'bindings' => $modifiedQuery->getBindings(),
        ];
    }

    /**
     * @param  array<int, Column>  $columns
     */
    public function columns(array $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * @return array<int, Column>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @param  array<int, Filter>  $filters
     */
    public function filters(array $filters): static
    {
        $this->filters = $filters;

        return $this;
    }

    /**
     * @return array<int, Filter>
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * @param  array<int, Action|ActionGroup>  $actions
     */
    public function actions(array $actions): static
    {
        $this->actions = $actions;

        return $this;
    }

    /**
     * Check if table has any actions (including ActionGroups)
     */
    public function hasActions(): bool
    {
        return ! empty($this->actions);
    }

    /**
     * Get flat list of all actions (expanding ActionGroups)
     *
     * @return array<int, Action>
     */
    public function getAllActions(): array
    {
        $allActions = [];

        foreach ($this->actions as $action) {
            if ($action instanceof ActionGroup) {
                $allActions = array_merge($allActions, $action->getActions());
            } else {
                $allActions[] = $action;
            }
        }

        return $allActions;
    }

    /**
     * @return array<int, Action|ActionGroup>
     */
    public function getActions(): array
    {
        return $this->actions;
    }

    /**
     * @param  array<int, Action>  $bulkActions
     */
    public function bulkActions(array $bulkActions): static
    {
        $this->bulkActions = $bulkActions;

        return $this;
    }

    /**
     * @return array<int, Action>
     */
    public function getBulkActions(): array
    {
        return $this->bulkActions;
    }

    /**
     * @param  array<int, Action>  $headerActions
     */
    public function headerActions(array $headerActions): static
    {
        $this->headerActions = $headerActions;

        return $this;
    }

    /**
     * @return array<int, Action>
     */
    public function getHeaderActions(): array
    {
        return $this->headerActions;
    }

    public function perPage(int $perPage): static
    {
        $this->perPage = $perPage;

        return $this;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * @param  array<int, int>  $options
     */
    public function perPageOptions(array $options): static
    {
        $this->perPageOptions = $options;

        return $this;
    }

    /**
     * @return array<int, int>
     */
    public function getPerPageOptions(): array
    {
        return $this->perPageOptions;
    }

    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;

        return $this;
    }

    public function sortable(bool $sortable = true): static
    {
        $this->sortable = $sortable;

        return $this;
    }

    public function paginated(bool $paginated = true): static
    {
        $this->paginated = $paginated;

        return $this;
    }

    public function isPaginated(): bool
    {
        return $this->paginated;
    }

    public function selectable(bool $selectable = true): static
    {
        $this->selectable = $selectable;

        return $this;
    }

    public function isSelectable(): bool
    {
        return $this->selectable || ! empty($this->bulkActions);
    }

    public function defaultSort(?string $column, string $direction = 'asc'): static
    {
        $this->defaultSort = $column;
        $this->defaultSortDirection = $direction;

        return $this;
    }

    public function getDefaultSort(): ?string
    {
        return $this->defaultSort;
    }

    public function getDefaultSortDirection(): string
    {
        return $this->defaultSortDirection;
    }

    public function emptyState(?string $heading = null, ?string $description = null, ?string $icon = null): static
    {
        $this->emptyStateHeading = $heading;
        $this->emptyStateDescription = $description;
        $this->emptyStateIcon = $icon;

        return $this;
    }

    public function getEmptyStateHeading(): ?string
    {
        return $this->emptyStateHeading ?? Trans::get('wire-table::messages.empty_heading');
    }

    public function getEmptyStateDescription(): ?string
    {
        return $this->emptyStateDescription ?? Trans::get('wire-table::messages.empty_description');
    }

    public function getEmptyStateIcon(): ?string
    {
        return $this->emptyStateIcon;
    }

    public function striped(bool $striped = true): static
    {
        $this->striped = $striped;

        return $this;
    }

    public function isStriped(): bool
    {
        return $this->striped;
    }

    public function hoverable(bool $hoverable = true): static
    {
        $this->hoverable = $hoverable;

        return $this;
    }

    public function isHoverable(): bool
    {
        return $this->hoverable;
    }

    public function recordUrl(string|Closure $url): static
    {
        if ($url instanceof Closure) {
            $this->recordUrlCallback = $url;
        } else {
            $this->recordUrl = $url;
        }

        return $this;
    }

    public function getRecordUrl(Model $record): ?string
    {
        if ($this->recordUrlCallback) {
            return call_user_func($this->recordUrlCallback, $record);
        }

        if ($this->recordUrl) {
            return Str::replace('{id}', $record->getKey(), $this->recordUrl);
        }

        return null;
    }

    public function primaryKey(string $primaryKey): static
    {
        $this->primaryKey = $primaryKey;

        return $this;
    }

    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    public function livewireComponent(mixed $component): static
    {
        $this->livewireComponent = $component;

        return $this;
    }

    public function getLivewireComponent(): mixed
    {
        return $this->livewireComponent;
    }

    // Action positioning methods

    /**
     * Set actions position ('start' or 'end')
     */
    public function actionsPosition(string $position): static
    {
        $this->actionsPosition = $position;

        return $this;
    }

    public function getActionsPosition(): string
    {
        return $this->actionsPosition;
    }

    /**
     * Set actions alignment ('left', 'center', 'right')
     */
    public function actionsAlignment(string $alignment): static
    {
        $this->actionsAlignment = $alignment;

        return $this;
    }

    public function getActionsAlignment(): string
    {
        return $this->actionsAlignment;
    }

    /**
     * Set the actions column label
     */
    public function actionsColumnLabel(?string $label): static
    {
        $this->actionsColumnLabel = $label;

        return $this;
    }

    public function getActionsColumnLabel(): ?string
    {
        return $this->actionsColumnLabel;
    }

    /**
     * Set the actions column width
     */
    public function actionsColumnWidth(?string $width): static
    {
        $this->actionsColumnWidth = $width;

        return $this;
    }

    public function getActionsColumnWidth(): ?string
    {
        return $this->actionsColumnWidth;
    }

    // Table styling methods

    /**
     * Set compact mode (smaller padding)
     */
    public function compact(bool $compact = true): static
    {
        $this->compact = $compact;

        return $this;
    }

    public function isCompact(): bool
    {
        return $this->compact;
    }

    /**
     * Set bordered mode
     */
    public function bordered(bool $bordered = true): static
    {
        $this->bordered = $bordered;

        return $this;
    }

    public function isBordered(): bool
    {
        return $this->bordered;
    }

    /**
     * Enable stacked/card layout on mobile devices
     *
     * @param  bool  $stacked  Whether to use stacked layout
     * @param  string  $breakpoint  Breakpoint below which to use stacked layout (sm, md, lg)
     */
    public function stackedOnMobile(bool $stacked = true, string $breakpoint = 'md'): static
    {
        $this->stackedOnMobile = $stacked;
        $this->stackedBreakpoint = $breakpoint;

        return $this;
    }

    public function isStackedOnMobile(): bool
    {
        return $this->stackedOnMobile;
    }

    public function getStackedBreakpoint(): string
    {
        return $this->stackedBreakpoint;
    }

    /**
     * Set custom table class
     */
    public function tableClass(?string $class): static
    {
        $this->tableClass = $class;

        return $this;
    }

    public function getTableClass(): ?string
    {
        return $this->tableClass;
    }

    /**
     * Set custom header class
     */
    public function headerClass(?string $class): static
    {
        $this->headerClass = $class;

        return $this;
    }

    public function getHeaderClass(): ?string
    {
        return $this->headerClass;
    }

    /**
     * Set custom row class (can use {record} placeholder)
     */
    public function rowClass(?string $class): static
    {
        $this->rowClass = $class;

        return $this;
    }

    public function getRowClass(): ?string
    {
        return $this->rowClass;
    }

    // Lazy loading methods

    /**
     * Enable lazy loading - table loads after page render
     */
    public function lazy(bool $lazy = true): static
    {
        $this->lazy = $lazy;

        return $this;
    }

    public function isLazy(): bool
    {
        return $this->lazy;
    }

    /**
     * Set custom placeholder text/HTML for lazy loading
     */
    public function lazyPlaceholder(?string $placeholder): static
    {
        $this->lazyPlaceholder = $placeholder;

        return $this;
    }

    public function getLazyPlaceholder(): ?string
    {
        return $this->lazyPlaceholder;
    }

    // ==========================================
    // Polling Methods
    // ==========================================

    /**
     * @deprecated Use poll() instead. Will be removed in v2.0.
     */
    public function polling(string $interval = '5s'): static
    {
        Deprecation::method('polling', 'poll');

        return $this->poll($interval);
    }

    /**
     * Enable polling with specified interval.
     *
     * @param  string  $interval  Interval in Livewire format (e.g., '5s', '10s', '30s', '1m')
     */
    public function poll(string $interval = '5s'): static
    {
        $this->polling = true;
        $this->pollingInterval = $interval;

        return $this;
    }

    /**
     * Set polling to keep connection alive (no timeout).
     */
    public function pollKeepAlive(bool $keepAlive = true): static
    {
        $this->pollingKeepAlive = $keepAlive;

        return $this;
    }

    /**
     * Set condition for when polling should be active.
     *
     * @param  Closure  $condition  Receives $livewire component, returns bool
     */
    public function pollWhen(Closure $condition): static
    {
        $this->pollingCondition = $condition;

        return $this;
    }

    /**
     * Set polling method.
     *
     * @param  string  $method  'refresh' (soft refresh) or 'reload' (full page reload)
     */
    public function pollMethod(string $method): static
    {
        $this->pollingMethod = $method;

        return $this;
    }

    /**
     * Poll only when browser tab is visible.
     */
    public function pollOnlyVisible(bool $onlyVisible = true): static
    {
        $this->pollingVisible = $onlyVisible;

        return $this;
    }

    /**
     * Check if polling is enabled.
     */
    public function isPolling(): bool
    {
        return $this->polling;
    }

    /**
     * Get polling interval.
     */
    public function getPollingInterval(): ?string
    {
        return $this->pollingInterval;
    }

    /**
     * Check if polling should keep connection alive.
     */
    public function isPollingKeepAlive(): bool
    {
        return $this->pollingKeepAlive;
    }

    /**
     * Get polling condition callback.
     */
    public function getPollingCondition(): ?Closure
    {
        return $this->pollingCondition;
    }

    /**
     * Get polling method.
     */
    public function getPollingMethod(): string
    {
        return $this->pollingMethod;
    }

    /**
     * Check if polling should only work when tab is visible.
     */
    public function isPollingOnlyVisible(): bool
    {
        return $this->pollingVisible;
    }

    /**
     * Get full polling config for view.
     *
     * @return array<string, mixed>
     */
    public function getPollingConfig(): array
    {
        return [
            'enabled' => $this->polling,
            'interval' => $this->pollingInterval,
            'keepAlive' => $this->pollingKeepAlive,
            'method' => $this->pollingMethod,
            'onlyVisible' => $this->pollingVisible,
            'directive' => $this->getPollingDirective(),
        ];
    }

    /**
     * Get wire:poll directive string.
     */
    public function getPollingDirective(): ?string
    {
        if (! $this->polling) {
            return null;
        }

        $directive = 'wire:poll';

        if ($this->pollingInterval) {
            $directive .= '.'.$this->pollingInterval;
        }

        if ($this->pollingKeepAlive) {
            $directive .= '.keep-alive';
        }

        if ($this->pollingVisible) {
            $directive .= '.visible';
        }

        return $directive;
    }

    // ==========================================
    // Pagination Mode
    // ==========================================

    /**
     * Use simple pagination (no total count query).
     *
     * More efficient for large datasets where you don't need to know
     * the total number of records.
     */
    public function simplePagination(): static
    {
        $this->paginationMode = 'simple';

        return $this;
    }

    /**
     * Use cursor-based pagination.
     *
     * Most efficient for very large datasets with sequential access.
     * Note: cursor pagination does not support jumping to arbitrary pages.
     */
    public function cursorPagination(): static
    {
        $this->paginationMode = 'cursor';

        return $this;
    }

    /**
     * Use standard pagination (default).
     */
    public function standardPagination(): static
    {
        $this->paginationMode = 'standard';

        return $this;
    }

    public function getPaginationMode(): string
    {
        return $this->paginationMode;
    }

    // ==========================================
    // Query Caching
    // ==========================================

    /**
     * Cache query results for the given TTL (seconds).
     *
     * Uses Laravel's query cache via remember().
     *
     * @param  int  $ttl  Cache duration in seconds
     * @param  string|null  $key  Custom cache key (auto-generated if null)
     */
    public function cacheQuery(int $ttl, ?string $key = null): static
    {
        $this->queryCacheTtl = $ttl;
        $this->queryCacheKey = $key;

        return $this;
    }

    public function getQueryCacheTtl(): ?int
    {
        return $this->queryCacheTtl;
    }

    public function getQueryCacheKey(): ?string
    {
        return $this->queryCacheKey;
    }

    public function isQueryCached(): bool
    {
        return $this->queryCacheTtl !== null;
    }

    // ==========================================
    // Chunking for Bulk Operations
    // ==========================================

    /**
     * Process all matching records in chunks.
     *
     * Useful for exports, bulk updates, or any operation that
     * needs to process all records without loading them all at once.
     *
     * @param  int  $chunkSize  Number of records per chunk
     * @param  Closure  $callback  Receives Collection of records per chunk
     */
    public function chunk(int $chunkSize, Closure $callback): bool
    {
        return $this->getQuery()->chunkById($chunkSize, $callback);
    }

    // ==========================================
    // Notification Driver
    // ==========================================

    /**
     * Set a per-table notification driver.
     *
     * Overrides the global default from NotificationManager.
     *
     * Example:
     *   $table->notificationDriver(new LivewireEventDriver('my-toast'));
     *   $table->notificationDriver(new FlasherDriver('toastr'));
     */
    public function notificationDriver(NotificationDriver $driver): static
    {
        $this->notificationDriver = $driver;

        return $this;
    }

    /**
     * Get the per-table notification driver (null = use global default).
     */
    public function getNotificationDriver(): ?NotificationDriver
    {
        return $this->notificationDriver;
    }

    /**
     * @return array<int, Column>
     */
    public function getSearchableColumns(): array
    {
        return array_values(array_filter($this->columns, fn (Column $column) => $column->isSearchable()));
    }

    /**
     * @return array<int, Column>
     */
    public function getSortableColumns(): array
    {
        return array_values(array_filter($this->columns, fn (Column $column) => $column->isSortable()));
    }

    public function __toString(): string
    {
        return $this->toHtml();
    }

    public function toHtml(): string
    {
        return view('wire-table::tables.index', ['table' => $this])->render();
    }
}
