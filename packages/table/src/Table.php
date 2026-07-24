<?php

declare(strict_types=1);

namespace NyonCode\WireTable;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionGroup;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Core\Support\Deprecation;
use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireCore\Foundation\Concerns\HasColor;
use NyonCode\WireCore\Foundation\Concerns\HasSheetOnMobile;
use NyonCode\WireCore\Foundation\Enums\Alignment;
use NyonCode\WireCore\Foundation\Enums\Breakpoint;
use NyonCode\WireCore\Foundation\Icons\Icon;
use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireTable\Actions\RecordActionResolver;
use NyonCode\WireTable\Actions\TableActionClickResolver;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Concerns\CanSelectRecords;
use NyonCode\WireTable\Concerns\HasSqlDebug;
use NyonCode\WireTable\Exceptions\TableConfigurationException;
use NyonCode\WireTable\Exceptions\TableHasNoDataSourceException;
use NyonCode\WireTable\Filters\Filter;
use NyonCode\WireTable\Preferences\Contracts\TablePreferenceDriver;
use NyonCode\WireTable\Services\TableQueryService;
use NyonCode\WireTable\Support\MobileCard;
use NyonCode\WireTable\Support\RecordAction;

/** @phpstan-consistent-constructor */
#[\AllowDynamicProperties]
class Table implements Htmlable
{
    use Concerns\HasGrouping;
    use Concerns\HasSubRows;
    use HasSheetOnMobile;
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

    protected bool $queryString = false;

    protected string $queryStringPrefix = '';

    protected bool $sortable = true;

    protected bool $paginated = true;

    protected bool $selectable = false;

    // Policy-based authorization
    protected bool $usePolicy = false;

    protected bool|Closure|null $authorizeCreate = null;

    protected bool|Closure|null $authorizeUpdate = null;

    protected bool|Closure|null $authorizeDelete = null;

    protected bool|Closure|null $authorizeView = null;

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

    /** Row-action presentation: 'solid' (default, filled buttons) or 'quiet' (neutral at rest, color on hover/focus). */
    protected string $actionsStyle = 'solid';

    // Table styling
    protected bool $compact = false;

    protected bool $bordered = false;

    protected ?string $tableClass = null;

    protected ?string $headerClass = null;

    /** @var string|Closure|null Extra row class(es); a Closure receives the record. */
    protected string|Closure|null $rowClass = null;

    /** @var string|Closure|null Semantic/hue color tint for a whole row; a Closure receives the record. */
    protected string|Closure|null $rowColor = null;

    // Responsive layout
    protected bool $stackedOnMobile = false;

    /** Explicit stacked-card slot assignment; null derives from the columns. */
    protected ?Closure $mobileCardCallback = null;

    private ?MobileCard $resolvedMobileCard = null;

    private ?string $resolvedMobileCardSignature = null;

    protected string $stackedBreakpoint = 'md';

    /** Collapse row actions into a single dropdown group in the mobile stacked-card view. */
    protected bool $collapseActionsOnMobile = false;

    /** Minimum number of row actions before the mobile card collapses them into a dropdown. */
    protected int $collapseActionsOnMobileThreshold = 3;

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

    /**
     * Poll change detection: false = always re-render (default), true = skip
     * the render when COUNT(*) + MAX(updated_at) of the filtered query are
     * unchanged, Closure = custom checksum fn (Builder $query): string.
     */
    protected bool|Closure $pollingChangeDetection = false;

    // Pagination mode: 'standard' | 'simple' | 'cursor'
    protected string $paginationMode = 'standard';

    // Query caching
    protected ?int $queryCacheTtl = null;

    protected ?string $queryCacheKey = null;

    // Notification driver
    protected ?NotificationDriver $notificationDriver = null;

    // Per-user column preferences: stable key (null = disabled) + optional driver.
    protected ?string $rememberColumnsKey = null;

    protected ?TablePreferenceDriver $preferenceDriver = null;

    /** @var array<int, Action|ActionGroup> Dedicated actions for the row right-click menu. */
    protected array $rowContextMenuActions = [];

    /** @var array<int, string|Action|RecordAction> Row-level record-action bindings (click/dblclick/etc.). */
    protected array $recordActions = [];

    /** Opt-in hover color for rows carrying a record action; null keeps the neutral default. */
    protected ?string $recordActionHover = null;

    /** Extra class(es) for the keyboard-active row (null keeps the built-in active style). */
    protected ?string $activeRowClass = null;

    /** Keyboard navigation: null = auto (on when record actions exist), true/false = forced. */
    protected ?bool $recordActionKeyboard = null;

    /** Memoized resolver over the record-action bindings; cleared when they (or selection) change. */
    private ?RecordActionResolver $recordActionResolver = null;

    // Also send a notification (toast) when an inline edit hits an optimistic-lock
    // conflict. Off by default — the conflict is always shown inline on the cell,
    // so this needs no notification setup; opt in for a more prominent toast.
    protected bool $notifyEditConflicts = false;

    // Excel-style fill handle on editable cells. Opt-in: dragging it overwrites
    // rows, which would be a silent behaviour change for every existing table
    // with an editable column.
    protected bool $fillHandle = false;

    // Ceiling on the rows one fill may write. A vertical drag can only reach
    // rendered rows, so this normally sits far above any real fill — it is a
    // bound on a forged request, not a UX limit.
    protected int $fillMaxRecords = 500;

    // Ceiling on the rows one bulk action may load at once. "Select all matching"
    // can mean a hundred thousand records; materialising those into models is an
    // out-of-memory error, so past this the action refuses out loud instead.
    // null lifts the cap for a table whose actions are known to stream.
    protected ?int $bulkMaxRecords = 1000;

    public static function make(): static
    {
        return new static;
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
            throw TableHasNoDataSourceException::make();
        }

        // Apply query modification callback if set
        if ($this->modifyQueryCallback) {
            $query = ($this->modifyQueryCallback)($query) ?? $query;
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
                'editable' => $column->isEditable(),
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
        $service = app(TableQueryService::class);
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
     * The column with this name, or null. The canonical lookup — the Livewire
     * host and the fill writer both resolve a client-supplied column name here
     * rather than scanning getColumns() themselves.
     */
    public function findColumn(string $name): ?Column
    {
        foreach ($this->columns as $column) {
            if ($column->getName() === $name) {
                return $column;
            }
        }

        return null;
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
     * @param  array<int, Action|ActionGroup|RecordAction>  $actions  A RecordAction
     *                                                                is rejected — `Action::make()->onDoubleClick()` returns one, and it
     *                                                                belongs in `recordActions()`, not here; it is accepted in the type
     *                                                                only so the mistake is caught with a clear message rather than a
     *                                                                fatal further down.
     */
    public function actions(array $actions): static
    {
        foreach ($actions as $action) {
            // A RecordAction is a row-interaction binding, not a toolbar action.
            // `Action::make()->onDoubleClick()` returns one; catch the mistake of
            // dropping it into the actions column with a clear message.
            if ($action instanceof RecordAction) {
                throw TableConfigurationException::recordActionInRowActions();
            }
        }

        $this->actions = $actions;

        return $this;
    }

    /**
     * Check if table has any actions (including ActionGroups), counting record
     * actions promoted into the column via `alsoInRowActions()`.
     */
    public function hasActions(): bool
    {
        return ! empty($this->actions) || $this->recordActionResolver()->rowActionButtons() !== [];
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
     * The page sizes the per-page select offers.
     *
     * The configured perPage() is always one of them. Without this a table
     * declaring perPage(3) against the default [10, 25, 50, 100] renders a
     * select whose displayed value (10) contradicts the 3 rows on screen, and
     * whose "10" option cannot be chosen because the control already claims to
     * be on it.
     *
     * @return array<int, int>
     */
    public function getPerPageOptions(): array
    {
        if (in_array($this->perPage, $this->perPageOptions, true)) {
            return $this->perPageOptions;
        }

        $options = [...$this->perPageOptions, $this->perPage];
        sort($options);

        return $options;
    }

    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;

        return $this;
    }

    /**
     * Persist search, sort, per-page, and filter state in the URL query string.
     *
     * Pass a string to prefix every parameter name — required when multiple
     * tables with query-string persistence render on the same page:
     *
     *     $table->queryString();          // ?search=…&sort=…&filter_status=…
     *     $table->queryString('orders_'); // ?orders_search=…&orders_sort=…
     *
     * The current page is already tracked by Livewire's WithPagination.
     */
    public function queryString(bool|string $enabled = true): static
    {
        if (is_string($enabled)) {
            $this->queryString = true;
            $this->queryStringPrefix = $enabled;
        } else {
            $this->queryString = $enabled;
        }

        return $this;
    }

    public function hasQueryString(): bool
    {
        return $this->queryString;
    }

    public function getQueryStringPrefix(): string
    {
        return $this->queryStringPrefix;
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
        // The default record-action trigger is selection-aware, so the resolver's
        // memo must be dropped when selection changes.
        $this->recordActionResolver = null;

        return $this;
    }

    public function isSelectable(): bool
    {
        return $this->selectable || ! empty($this->bulkActions);
    }

    /**
     * Enable model policy auto-resolution.
     *
     * When enabled, create/update/delete/view permissions are resolved
     * automatically from the model's Laravel Policy.
     */
    public function authorize(bool $usePolicy = true): static
    {
        $this->usePolicy = $usePolicy;

        return $this;
    }

    public function usesPolicy(): bool
    {
        return $this->usePolicy;
    }

    /**
     * Override create authorization (hides "New" button when denied).
     */
    public function authorizeCreate(bool|Closure $authorize = true): static
    {
        $this->authorizeCreate = $authorize;

        return $this;
    }

    /**
     * Override update authorization (per-row, hides edit action when denied).
     */
    public function authorizeUpdate(bool|Closure $authorize = true): static
    {
        $this->authorizeUpdate = $authorize;

        return $this;
    }

    /**
     * Override delete authorization (per-row, hides delete action when denied).
     */
    public function authorizeDelete(bool|Closure $authorize = true): static
    {
        $this->authorizeDelete = $authorize;

        return $this;
    }

    /**
     * Override view authorization (per-row, hides view action when denied).
     */
    public function authorizeView(bool|Closure $authorize = true): static
    {
        $this->authorizeView = $authorize;

        return $this;
    }

    /**
     * Check if the current user can create a new record.
     */
    public function canCreate(): bool
    {
        if ($this->authorizeCreate !== null) {
            return $this->authorizeCreate instanceof Closure
                ? (bool) ($this->authorizeCreate)()
                : $this->authorizeCreate;
        }

        if ($this->usePolicy && $this->model) {
            return Gate::allows('create', $this->model);
        }

        return true;
    }

    /**
     * Check if the current user can update the given record.
     */
    public function canUpdate(EloquentModel $record): bool
    {
        if ($this->authorizeUpdate !== null) {
            return $this->authorizeUpdate instanceof Closure
                ? (bool) ($this->authorizeUpdate)($record)
                : $this->authorizeUpdate;
        }

        if ($this->usePolicy) {
            return Gate::allows('update', $record);
        }

        return true;
    }

    /**
     * Check if the current user can delete the given record.
     */
    public function canDelete(EloquentModel $record): bool
    {
        if ($this->authorizeDelete !== null) {
            return $this->authorizeDelete instanceof Closure
                ? (bool) ($this->authorizeDelete)($record)
                : $this->authorizeDelete;
        }

        if ($this->usePolicy) {
            return Gate::allows('delete', $record);
        }

        return true;
    }

    /**
     * Check if the current user can view the given record.
     */
    public function canView(EloquentModel $record): bool
    {
        if ($this->authorizeView !== null) {
            return $this->authorizeView instanceof Closure
                ? (bool) ($this->authorizeView)($record)
                : $this->authorizeView;
        }

        if ($this->usePolicy) {
            return Gate::allows('view', $record);
        }

        return true;
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

    public function emptyState(?string $heading = null, ?string $description = null, string|Icon|null $icon = null): static
    {
        $this->emptyStateHeading = $heading;
        $this->emptyStateDescription = $description;
        $this->emptyStateIcon = $icon instanceof Icon ? $icon->value() : $icon;

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
            return ($this->recordUrlCallback)($record);
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
    public function actionsAlignment(string|Alignment $alignment): static
    {
        $this->actionsAlignment = $alignment instanceof Alignment ? $alignment->value : $alignment;

        return $this;
    }

    public function getActionsAlignment(): string
    {
        return $this->actionsAlignment;
    }

    /**
     * Canonical literal `text-*` class for the actions column header alignment.
     */
    public function getActionsAlignmentClass(): string
    {
        return Alignment::resolve($this->actionsAlignment)->textClass();
    }

    /**
     * Canonical literal `justify-*` class for the actions row (flex main axis).
     */
    public function getActionsJustifyClass(): string
    {
        return Alignment::resolve($this->actionsAlignment)->justifyClass();
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

    /**
     * Set the row-action presentation style.
     *
     * - 'solid' (default): filled, always-colored buttons — the current look.
     * - 'quiet': neutral text at rest, semantic color on hover/focus, so a row
     *   of actions stops competing with the data. Destructive actions stay
     *   legible (red at rest); mark one action ->solid() to keep it prominent.
     */
    public function actionsStyle(string $style): static
    {
        $this->actionsStyle = $style;

        return $this;
    }

    public function getActionsStyle(): string
    {
        return $this->actionsStyle;
    }

    /**
     * Canonical owner of row-action presentation: returns the configured actions
     * with the current style applied, so both actions-cell positions render
     * identically. Applying quiet is idempotent (the same Action instance already
     * renders for every row).
     *
     * @return array<int, Action|ActionGroup>
     */
    public function getRowActionsForDisplay(): array
    {
        $actions = array_values($this->actions);

        // Record actions flagged alsoInRowActions() also render as toolbar
        // buttons. Skip any whose name already appears — a reference to an
        // existing row action must not double it.
        $seen = [];
        foreach ($actions as $action) {
            if ($action instanceof Action) {
                $seen[$action->getName()] = true;
            }
        }

        foreach ($this->recordActionResolver()->rowActionButtons() as $button) {
            if (! isset($seen[$button->getName()])) {
                $actions[] = $button;
                $seen[$button->getName()] = true;
            }
        }

        if ($this->actionsStyle === 'quiet') {
            foreach ($actions as $action) {
                if ($action instanceof Action && ! $action->isDivider()) {
                    $action->quiet();
                }
            }
        }

        return $actions;
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
     * Cap the rows a single bulk action may load into memory.
     *
     * A "select all matching" selection is a query, not a list, so it can be
     * arbitrarily large. Past this cap the action refuses and says so, rather
     * than materialising the set and dying halfway through it. Pass null to lift
     * the cap for a table whose bulk actions stream via
     * {@see CanSelectRecords::eachSelectedRecord()}.
     */
    public function bulkMaxRecords(?int $max): static
    {
        $this->bulkMaxRecords = $max === null ? null : max(1, $max);

        return $this;
    }

    public function getBulkMaxRecords(): ?int
    {
        return $this->bulkMaxRecords;
    }

    /**
     * Shape the stacked mobile card: which column is the title, which is the
     * supporting line, which is the figure set right, and what sits beside them
     * as status.
     *
     *   ->mobileCard(fn (MobileCardConfig $card) => $card
     *       ->title('number')->subtitle('customer')->metric('total')->meta('status'))
     *
     * Slots left unnamed are derived from the columns, so this is an override,
     * never a requirement.
     */
    public function mobileCard(Closure $callback): static
    {
        $this->mobileCardCallback = $callback;
        $this->resolvedMobileCard = null;

        return $this;
    }

    /**
     * The card resolved for a set of visible columns, memoized per column set —
     * the stacked view would otherwise resolve it once per record.
     *
     * @param  array<int, Column>  $visibleColumns
     */
    public function getMobileCard(array $visibleColumns): MobileCard
    {
        $signature = implode('|', array_map(fn (Column $c): string => $c->getName(), $visibleColumns));

        if ($this->resolvedMobileCard === null || $this->resolvedMobileCardSignature !== $signature) {
            $this->resolvedMobileCard = MobileCard::resolve($visibleColumns, $this->mobileCardCallback);
            $this->resolvedMobileCardSignature = $signature;
        }

        return $this->resolvedMobileCard;
    }

    /**
     * Enable stacked/card layout on mobile devices
     *
     * @param  bool  $stacked  Whether to use stacked layout
     * @param  string|Breakpoint  $breakpoint  Breakpoint below which to use stacked layout (sm, md, lg)
     */
    public function stackedOnMobile(bool $stacked = true, string|Breakpoint $breakpoint = Breakpoint::Md): static
    {
        $this->stackedOnMobile = $stacked;
        $this->stackedBreakpoint = $breakpoint instanceof Breakpoint ? $breakpoint->value : $breakpoint;

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
     * Responsive class that hides the full table below the stacked breakpoint.
     *
     * Owns the breakpoint → Tailwind class mapping in PHP (literal class names so
     * the JIT scanner sees them); the view only consumes the result. Returns no
     * hiding class when mobile stacking is disabled.
     */
    public function getStackedTableHiddenClass(): string
    {
        if (! $this->stackedOnMobile) {
            return '';
        }

        return Breakpoint::resolve($this->stackedBreakpoint)->blockFromClass();
    }

    /**
     * Responsive class that shows the mobile cards only below the stacked
     * breakpoint. Companion to {@see getStackedTableHiddenClass()}; defaults to a
     * fully hidden cards layout when mobile stacking is disabled.
     */
    public function getStackedCardsVisibleClass(): string
    {
        if (! $this->stackedOnMobile) {
            return 'hidden';
        }

        return Breakpoint::resolve($this->stackedBreakpoint)->hiddenAtClass();
    }

    /**
     * Collapse the row actions into one dropdown group in the mobile stacked-card
     * view, so a card header shows a single "⋮" trigger instead of several inline
     * buttons. No effect on the desktop table, and only meaningful together with
     * {@see stackedOnMobile()}.
     *
     * The collapse only kicks in once a row has at least `$threshold` actions
     * (default 3); with fewer actions the card keeps them inline. Pass a lower
     * threshold to collapse sooner, or 1 to always collapse.
     */
    public function collapseActionsOnMobile(bool $collapse = true, int $threshold = 3): static
    {
        $this->collapseActionsOnMobile = $collapse;
        $this->collapseActionsOnMobileThreshold = max(1, $threshold);

        return $this;
    }

    public function getCollapseActionsOnMobileThreshold(): int
    {
        return $this->collapseActionsOnMobileThreshold;
    }

    /**
     * Whether the mobile card should collapse its row actions: the feature is
     * enabled and the row carries at least the configured threshold of actions.
     * The count flattens nested groups and ignores dividers, matching what the
     * dropdown would actually contain.
     */
    public function shouldCollapseActionsOnMobile(): bool
    {
        return $this->collapseActionsOnMobile
            && count($this->flattenMobileRowActions()) >= $this->collapseActionsOnMobileThreshold;
    }

    /**
     * Flatten the configured row actions into a single list, expanding nested
     * {@see ActionGroup}s and dropping dividers. Shared by the collapse threshold
     * check and {@see getMobileActionGroup()} so both count the same actions.
     *
     * @return array<int, Action>
     */
    protected function flattenMobileRowActions(): array
    {
        $flat = [];

        foreach ($this->getRowActionsForDisplay() as $action) {
            if ($action instanceof ActionGroup) {
                foreach ($action->getActions() as $inner) {
                    if ($inner instanceof Action && $inner->isDivider()) {
                        continue;
                    }

                    $flat[] = $inner;
                }

                continue;
            }

            if ($action->isDivider()) {
                continue;
            }

            $flat[] = $action;
        }

        return $flat;
    }

    /**
     * Canonical builder for the mobile card's collapsed action dropdown: wraps the
     * row actions in a single {@see ActionGroup}, flattening any existing groups so
     * everything lands under one trigger. The group inherits the table's mobile
     * bottom-sheet settings and collapses to a lone inline button when only one
     * action is visible (handled by ActionGroup itself).
     */
    public function getMobileActionGroup(): ActionGroup
    {
        return $this->buildMobileActionGroup($this->flattenMobileRowActions());
    }

    /**
     * The same collapsed dropdown for a sub-row's actions.
     *
     * Child actions collapse on a phone unconditionally, unlike row actions
     * (which honour {@see collapseActionsOnMobile()}): a child line is narrower
     * than the card that holds it, and two labelled buttons there crush the
     * product name to an ellipsis. There is no width at which they fit.
     */
    public function getMobileSubRowActionGroup(): ActionGroup
    {
        $flat = [];

        foreach ($this->getSubRowActions() as $action) {
            if ($action instanceof ActionGroup) {
                foreach ($action->getActions() as $inner) {
                    if ($inner instanceof Action && $inner->isDivider()) {
                        continue;
                    }

                    $flat[] = $inner;
                }

                continue;
            }

            if ($action instanceof Action && $action->isDivider()) {
                continue;
            }

            $flat[] = $action;
        }

        return $this->buildMobileActionGroup($flat);
    }

    /**
     * @param  array<int, Action|ActionGroup>  $actions
     */
    private function buildMobileActionGroup(array $actions): ActionGroup
    {
        return ActionGroup::make($actions)
            ->sheetOnMobile($this->usesSheetOnMobile())
            ->mobileBreakpoint($this->getMobileBreakpoint());
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
     * Add extra class(es) to every row, or per-record via a Closure.
     *
     * The Closure receives the record and returns a class string (or null):
     * `->rowClass(fn ($record) => $record->is_flagged ? 'font-semibold' : null)`.
     */
    public function rowClass(string|Closure|null $class): static
    {
        $this->rowClass = $class;

        return $this;
    }

    /**
     * Resolve the custom row class for a record.
     *
     * Backwards compatible: with no record (or a static string) it returns the
     * plain string; a Closure is only invoked when a record is supplied.
     */
    public function getRowClass(?Model $record = null): ?string
    {
        if ($this->rowClass instanceof Closure) {
            return $record === null ? null : ($this->rowClass)($record);
        }

        return $this->rowClass;
    }

    /**
     * Tint a whole row with a semantic role or raw Tailwind hue, statically or
     * per-record via a Closure returning a color name (or null for no tint):
     * `->rowColor(fn ($record) => $record->isOverdue() ? 'danger' : null)`.
     *
     * The tint is resolved by the canonical {@see HasColor::getRowTintClasses()}
     * owner and replaces the neutral hover + zebra striping for that row.
     */
    public function rowColor(string|Closure|null $color): static
    {
        $this->rowColor = $color;

        return $this;
    }

    /**
     * Resolve the row color name for a record (null = no tint).
     */
    public function getRowColor(?Model $record = null): ?string
    {
        $color = $this->rowColor instanceof Closure
            ? ($record === null ? null : ($this->rowColor)($record))
            : $this->rowColor;

        return $color === null || $color === '' ? null : (string) $color;
    }

    /**
     * Compose the full `<tr>` class string for a record: row tint (if any),
     * otherwise the neutral (or opt-in record-action) hover + zebra striping, a
     * `cursor-pointer` when the row is clickable, plus any custom row class.
     *
     * Centralizing this keeps the row view free of layered conditionals and lets
     * a colored row correctly suppress the gray hover / striping it would clash
     * with. A colored row still receives its own same-hue hover from the tint.
     */
    public function getRowClasses(?Model $record, int $rowIndex): string
    {
        $tint = $record === null ? null : $this->getRowColor($record);
        $clickable = $this->hasRecordActionPointer();

        if ($tint !== null) {
            // A tinted row keeps its own same-hue hover; the record-action hover
            // override applies only to otherwise-neutral rows.
            $base = HasColor::getRowTintClasses($tint);
        } else {
            $recordHover = $this->getRecordActionHover();

            if ($clickable && $recordHover !== null) {
                $hover = HasColor::getRowHoverClasses($recordHover);
            } else {
                $hover = $this->isHoverable() ? 'hover:bg-gray-50 dark:hover:bg-gray-700/30' : '';
            }

            $stripe = $this->isStriped() && $rowIndex % 2 === 1 ? 'bg-gray-50/50 dark:bg-gray-800/30' : '';
            $base = trim("{$hover} {$stripe}");
        }

        $cursor = $clickable ? 'cursor-pointer' : '';

        return trim("{$base} {$cursor} ".((string) $this->getRowClass($record)));
    }

    /**
     * Whether the table carries a whole-row pointer record action (click or
     * double-click) — the rows are clickable and should read as such.
     */
    public function hasRecordActionPointer(): bool
    {
        return $this->getRecordActionBindings() !== [];
    }

    /**
     * Force keyboard navigation on or off (null = auto: on when the table has any
     * record action). Keyboard nav gives the rows a roving tabindex, arrow-key
     * movement, Enter/Shift+Enter for the primary/secondary action, and the
     * record actions' own keyboard shortcuts against the active row.
     */
    public function recordActionKeyboard(?bool $enabled = true): static
    {
        $this->recordActionKeyboard = $enabled;

        return $this;
    }

    /**
     * Whether keyboard navigation is active for this table.
     */
    public function keyboardNavEnabled(): bool
    {
        return $this->recordActionKeyboard ?? $this->hasRecordActions();
    }

    /**
     * ARIA role for the table element: `grid` only when keyboard navigation is
     * on, so a plain data table is never given grid semantics it does not use
     * (see ADR / plan decision — role is conditional, not always applied).
     */
    public function getTableRole(): ?string
    {
        return $this->keyboardNavEnabled() ? 'grid' : null;
    }

    /**
     * The client config the keyboard layer of `wireRecordActions` consumes:
     * the Enter/Shift+Enter targets, the shortcut map, whether Space toggles
     * selection, and the class marking the active row.
     *
     * @return array<string, mixed>
     */
    public function getRecordActionKeyboardConfig(): array
    {
        $resolver = $this->recordActionResolver();

        return [
            'primary' => $resolver->primaryActionName(),
            'secondary' => $resolver->secondaryActionName(),
            'shortcuts' => $resolver->shortcuts(),
            'selectable' => $this->isSelectable(),
            'activeClass' => $this->getActiveRowClass() ?? 'bg-primary-100 dark:bg-primary-900/30',
        ];
    }

    /**
     * Companion of {@see getRowClasses()} for the mobile stacked-card view: the
     * row tint (or the default white card background) plus the card border and
     * any custom row class, so a colored row reads the same on phone and desktop.
     */
    public function getRowCardClasses(?Model $record): string
    {
        $tint = $record === null ? null : $this->getRowColor($record);
        $background = $tint !== null
            ? HasColor::getRowTintClasses($tint)
            : 'bg-white dark:bg-gray-800';

        return trim("{$background} border-b border-gray-200 dark:border-gray-700 ".((string) $this->getRowClass($record)));
    }

    /**
     * Remember each user's column layout under a stable key.
     *
     * When set, the table loads the user's saved hidden-column set on mount and
     * persists it whenever a column is toggled, via the configured
     * {@see TablePreferenceDriver} (see `config('wire-table.preferences')`). The
     * key identifies this table across the app — use a distinct, stable string
     * per table (e.g. `'users-index'`). Different users are scoped by the driver,
     * so one key serves everyone.
     */
    public function rememberColumns(string $key): static
    {
        $this->rememberColumnsKey = $key;

        return $this;
    }

    /**
     * The preferences key set by {@see rememberColumns()} (null = disabled).
     */
    public function getRememberColumnsKey(): ?string
    {
        return $this->rememberColumnsKey;
    }

    /**
     * Persist this table's preferences through a specific driver, overriding the
     * configured default (e.g. force the database driver for one critical table).
     */
    public function preferenceDriver(?TablePreferenceDriver $driver): static
    {
        $this->preferenceDriver = $driver;

        return $this;
    }

    /**
     * The per-table preference driver override, if any.
     */
    public function getPreferenceDriver(): ?TablePreferenceDriver
    {
        return $this->preferenceDriver;
    }

    /**
     * Define a dedicated right-click context menu for each row.
     *
     * @deprecated Superseded by record actions. Bind an action to the right-click
     *             trigger instead: `->recordAction(Action::make('edit')->onContextMenu())`.
     *             Kept as a thin alias — it still populates the same context menu
     *             (see {@see getContextMenuActions()}) — and will be removed in v2.0.
     *
     * @param  array<int, Action|ActionGroup>  $actions
     */
    public function rowContextMenu(array $actions): static
    {
        Deprecation::method('rowContextMenu', 'recordAction()->onContextMenu', '2.0');

        $this->rowContextMenuActions = $actions;

        return $this;
    }

    /**
     * Whether a row context menu exists — a dedicated `rowContextMenu()` list, or
     * a record action bound to the right-click trigger via `onContextMenu()`.
     */
    public function hasRowContextMenu(): bool
    {
        return $this->getContextMenuActions() !== [];
    }

    /**
     * @return array<int, Action|ActionGroup>
     */
    public function getRowContextMenuActions(): array
    {
        return $this->rowContextMenuActions;
    }

    /**
     * The full context-menu action list: the dedicated `rowContextMenu()` actions
     * plus any record action bound with `onContextMenu()`. This is the single
     * owner of "what the right-click menu shows" — the record-action layer feeds
     * the existing menu rather than standing up a second one.
     *
     * @return array<int, Action|ActionGroup>
     */
    public function getContextMenuActions(): array
    {
        return array_merge(
            array_values($this->rowContextMenuActions),
            $this->recordActionResolver()->contextMenuActions(),
        );
    }

    /**
     * Render a record's context-menu items (same markup as the ActionGroup
     * dropdown). Returns empty HTML when the row has no visible action, so the
     * view can skip the menu entirely.
     */
    public function getRowContextMenuHtml(Model $record): Htmlable
    {
        $html = '';
        $click = new TableActionClickResolver;

        foreach ($this->getContextMenuActions() as $action) {
            $html .= $action instanceof ActionGroup
                ? $action->getDropdownItemsHtml($record, $click)->toHtml()
                : $action->renderForDropdown($record, $click);
        }

        return new HtmlString($html);
    }

    // Record actions (row-level interaction: click, double-click, right-click, keys)

    /**
     * Bind an action to a whole-row interaction — a click, double-click,
     * right-click or key over the empty part of the row runs it, desktop-app
     * style. Separate from `->actions()` (toolbar buttons), `->bulkActions()`
     * and `->headerActions()`.
     *
     * Accepts an {@see Action} (or a {@see RecordAction} with an explicit
     * trigger), or the *name* of an action already declared in `->actions()` to
     * reference it without redefining. Each call appends; call it more than once,
     * or pass a list to {@see recordActions()}.
     */
    public function recordAction(string|Action|RecordAction $action): static
    {
        $this->recordActions[] = $action;
        $this->recordActionResolver = null;

        return $this;
    }

    /**
     * Replace the record-action bindings with the given list.
     *
     * @param  array<int, string|Action|RecordAction>  $actions
     */
    public function recordActions(array $actions): static
    {
        $this->recordActions = array_values($actions);
        $this->recordActionResolver = null;

        return $this;
    }

    /**
     * @return array<int, string|Action|RecordAction>
     */
    public function getRecordActions(): array
    {
        return $this->recordActions;
    }

    public function hasRecordActions(): bool
    {
        return $this->recordActions !== [];
    }

    /**
     * The memoized resolver over the record-action bindings. Cleared by the
     * record-action and selection setters, since the default trigger is
     * selection-aware.
     */
    protected function recordActionResolver(): RecordActionResolver
    {
        return $this->recordActionResolver ??= new RecordActionResolver($this);
    }

    /**
     * Pointer-trigger → action-name map for the JS controller / Blade x-data
     * (click, double-click and custom gestures; not context-menu or key).
     *
     * @return array<string, string>
     */
    public function getRecordActionBindings(): array
    {
        return $this->recordActionResolver()->pointerMap();
    }

    /**
     * Find a registered row action by name (flattening action groups). The
     * canonical name lookup a record-action reference resolves against — a
     * `recordAction('edit')` reuses the very `Action` declared in `->actions()`.
     */
    public function findRegisteredAction(string $name): ?Action
    {
        foreach ($this->getAllActions() as $action) {
            if ($action->getName() === $name) {
                return $action;
            }
        }

        return null;
    }

    /**
     * The wrapped action instances a record action carries in its own right
     * (not name references) — the fallback pool the execution endpoints search so
     * a behaviour-only record action with its own callback still runs.
     *
     * @return array<int, Action>
     */
    public function getRecordActionInstances(): array
    {
        $out = [];

        foreach ($this->recordActions as $entry) {
            if ($entry instanceof RecordAction && $entry->getAction() !== null) {
                $out[] = $entry->getAction();
            } elseif ($entry instanceof Action) {
                $out[] = $entry;
            }
        }

        return $out;
    }

    /**
     * Tint a record-action row on hover with a semantic role or hue instead of
     * the neutral default (e.g. `->recordActionHover('primary')`). Null keeps the
     * existing neutral hover, so enabling record actions never silently restyles
     * an existing table.
     */
    public function recordActionHover(?string $color): static
    {
        $this->recordActionHover = $color === '' ? null : $color;

        return $this;
    }

    public function getRecordActionHover(): ?string
    {
        return $this->recordActionHover;
    }

    /**
     * Override the class(es) applied to the keyboard-active row (null keeps the
     * built-in active style).
     */
    public function activeRowClass(?string $class): static
    {
        $this->activeRowClass = $class === '' ? null : $class;

        return $this;
    }

    public function getActiveRowClass(): ?string
    {
        return $this->activeRowClass;
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
        if (! preg_match('/^\d+(ms|s|m|h)$/', $interval)) {
            throw TableConfigurationException::invalidPollInterval();
        }

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
     * Skip the poll re-render when the underlying data has not changed.
     *
     * With `true`, a cheap checksum (COUNT(*) + MAX(updated_at) of the
     * filtered query) is compared between polls; an unchanged checksum
     * skips the full query + render cycle. Models without timestamps fall
     * back to always rendering.
     *
     * Pass a closure for a custom checksum when parent timestamps don't
     * capture relevant changes (e.g. rollup sums over child rows):
     *
     *   ->pollChangeDetection(fn ($query) => (string) $query->max('synced_at'))
     */
    public function pollChangeDetection(bool|Closure $detector = true): static
    {
        $this->pollingChangeDetection = $detector;

        return $this;
    }

    /**
     * Get the poll change detection setting (false = disabled).
     */
    public function getPollChangeDetection(): bool|Closure
    {
        return $this->pollingChangeDetection;
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
     * Also surface an optimistic-lock edit conflict as a notification (toast),
     * on top of the inline message shown on the cell. Opt-in — requires the
     * notification system to be wired up (e.g. a toast container).
     *
     * Example:
     *   $table->notifyEditConflicts();
     */
    public function notifyEditConflicts(bool $condition = true): static
    {
        $this->notifyEditConflicts = $condition;

        return $this;
    }

    public function shouldNotifyEditConflicts(): bool
    {
        return $this->notifyEditConflicts;
    }

    /**
     * Show the Excel-style fill handle on editable cells, so a value can be
     * dragged down over the rows below it.
     *
     * Opt-in, and the server honours it: `fillTableCells()` refuses outright
     * unless this is on, so the endpoint cannot be driven by a forged request
     * against a table that never offered the affordance.
     *
     * Example:
     *   $table->fillHandle();
     */
    public function fillHandle(bool $condition = true): static
    {
        $this->fillHandle = $condition;

        return $this;
    }

    public function isFillHandleEnabled(): bool
    {
        return $this->fillHandle;
    }

    /** Cap the number of rows a single fill may write (default 500). */
    public function fillMaxRecords(int $max): static
    {
        $this->fillMaxRecords = $max;

        return $this;
    }

    public function getFillMaxRecords(): int
    {
        return $this->fillMaxRecords;
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

    // ==========================================
    // Plugin Type Resolution
    // ==========================================

    /**
     * Resolve a custom column class registered by a plugin.
     *
     * @return class-string|null
     */
    public static function resolveColumnType(string $type): ?string
    {
        if (! app()->bound(PluginManager::class)) {
            return null;
        }

        $types = app(PluginManager::class)->getColumnTypes();

        return $types[$type] ?? null;
    }

    /**
     * Resolve a custom filter class registered by a plugin.
     *
     * @return class-string|null
     */
    public static function resolveFilterType(string $type): ?string
    {
        if (! app()->bound(PluginManager::class)) {
            return null;
        }

        $types = app(PluginManager::class)->getFilterTypes();

        return $types[$type] ?? null;
    }

    /**
     * Resolve a custom action class registered by a plugin.
     *
     * @return class-string|null
     */
    public static function resolveActionType(string $type): ?string
    {
        if (! app()->bound(PluginManager::class)) {
            return null;
        }

        $types = app(PluginManager::class)->getActionTypes();

        return $types[$type] ?? null;
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
