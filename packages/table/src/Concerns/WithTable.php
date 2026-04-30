<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\WithPagination;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionGroup;
use NyonCode\WireCore\Actions\ActionHalt;
use NyonCode\WireCore\Actions\BulkAction;
use NyonCode\WireCore\Actions\HeaderAction;
use NyonCode\WireCore\Notifications\Notification;
use NyonCode\WireCore\Notifications\NotificationManager;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Table;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;

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

    public bool $actionModalIsHeaderAction = false;

    // Dynamic halt modal state
    public bool $showHaltModal = false;

    public ?string $haltActionName = null;

    public ?string $haltRecordKey = null;

    public array $haltModalConfig = [];

    public array $haltModalFormData = [];

    public bool $haltActionConfirmed = false;

    // Halt context - tracks where halt originated and how to resume
    public ?string $haltActionType = null;  // 'row', 'bulk', 'header'

    public array $haltContext = [];  // skipBeforeOnConfirm, source, index, redirectAfterConfirm

    // Legacy confirmation modal (for backwards compatibility)
    public bool $showConfirmationModal = false;

    public ?string $confirmActionName = null;

    public ?string $confirmRecordKey = null;

    public bool $isBulkAction = false;

    // Lazy loading state
    public bool $tableReady = false;

    // Polling state
    public bool $tablePollingActive = true;

    protected ?Table $tableInstance = null;

    /** @var array Modal config - not a public Livewire property */
    protected array $actionModalConfigCache = [];

    /** @var array Cached column metadata for accessors */
    protected array $columnMetadata = [];

    /** @var array Cached filter metadata for accessors */
    protected array $filterMetadata = [];

    /** @var array Cached database columns */
    protected ?array $databaseColumns = null;

    /** @var LengthAwarePaginator|Collection|null Cached records for current request lifecycle */
    protected LengthAwarePaginator|Collection|null $cachedRecords = null;

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
    // Table Polling
    // ==========================================

    /**
     * Get the configured table instance
     */
    public function getTable(): Table
    {
        if ($this->tableInstance === null) {
            $this->tableInstance = $this->table(Table::make());
            $this->tableInstance->livewireComponent($this);

            // Auto-detect accessors and configure virtual columns
            $this->configureVirtualColumns();
        }

        return $this->tableInstance;
    }

    /**
     * Abstract method - must be implemented in the component
     */
    abstract public function table(Table $table): Table;

    /**
     * Analyze columns and build metadata for accessors
     */
    protected function configureVirtualColumns(): void
    {
        $table = $this->tableInstance;
        $model = $this->getTableModel();

        if (! $model) {
            return;
        }

        $modelInstance = new $model;
        $this->databaseColumns = $this->getModelTableColumns($modelInstance);

        // Configure column metadata
        foreach ($table->getColumns() as $column) {
            $columnName = $column->getName();

            // If column has explicit searchColumns or a custom search callback,
            // use those directly (Filament-style: searchable(['first_name', 'last_name']))
            $explicitSearchColumns = $column->getSearchColumns();
            $hasSearchCallback = $column->getSearchCallback() !== null;

            if (! empty($explicitSearchColumns) || $hasSearchCallback) {
                $this->columnMetadata[$columnName] = [
                    'isAccessor' => ! in_array($columnName, $this->databaseColumns),
                    'isDbColumn' => in_array($columnName, $this->databaseColumns),
                    'isRelation' => $column->getRelation() !== null,
                    'searchColumns' => $explicitSearchColumns,
                    'sortColumn' => ! empty($explicitSearchColumns) ? $explicitSearchColumns[0] : $columnName,
                    'hasSearchCallback' => $hasSearchCallback,
                    'hasSortCallback' => $column->getSortCallback() !== null,
                ];

                continue;
            }

            // Handle relation columns (contains dot)
            if ($column->getRelation()) {
                $relation = $column->getRelation();
                $attribute = $column->getRelationshipAttribute();

                // Get related model and its columns
                $relatedModel = $this->getRelatedModel($modelInstance, $relation);
                $relatedColumns = $this->getRelatedModelColumns($modelInstance, $relation);

                if (in_array($attribute, $relatedColumns)) {
                    // It's a real DB column on related model
                    $this->columnMetadata[$columnName] = [
                        'isAccessor' => false,
                        'isDbColumn' => true,
                        'isRelation' => true,
                        'searchColumns' => [$columnName],
                        'sortColumn' => $columnName,
                    ];
                } else {
                    // Attribute is accessor on related model - use reflection
                    $searchCols = $relatedModel
                        ? $this->resolveAccessorColumns($relatedModel, $attribute, $relatedColumns)
                        : $this->guessColumnsFromName($attribute, $relatedColumns);

                    // Prefix with relation name
                    $searchCols = array_map(fn ($col) => "{$relation}.{$col}", $searchCols);

                    $this->columnMetadata[$columnName] = [
                        'isAccessor' => true,
                        'isDbColumn' => false,
                        'isRelation' => true,
                        'searchColumns' => $searchCols,
                        'sortColumn' => ! empty($searchCols) ? $searchCols[0] : null,
                    ];
                }

                continue;
            }

            // Check if column exists in database
            if (in_array($columnName, $this->databaseColumns)) {
                $this->columnMetadata[$columnName] = [
                    'isAccessor' => false,
                    'isDbColumn' => true,
                    'isRelation' => false,
                    'searchColumns' => [$columnName],
                    'sortColumn' => $columnName,
                ];

                continue;
            }

            // Check if it's an accessor - use reflection to find columns
            if ($this->isAccessor($modelInstance, $columnName)) {
                $searchCols = $this->resolveAccessorColumns($modelInstance, $columnName, $this->databaseColumns);

                $this->columnMetadata[$columnName] = [
                    'isAccessor' => true,
                    'isDbColumn' => false,
                    'isRelation' => false,
                    'searchColumns' => $searchCols,
                    'sortColumn' => ! empty($searchCols) ? $searchCols[0] : null,
                ];
            } else {
                // Unknown column - might be added via state() callback
                $this->columnMetadata[$columnName] = [
                    'isAccessor' => false,
                    'isDbColumn' => false,
                    'isRelation' => false,
                    'searchColumns' => [],
                    'sortColumn' => null,
                ];
            }
        }

        // Configure filter metadata
        foreach ($table->getFilters() as $filter) {
            $filterName = $filter->getName();

            // Handle relation filters (contains dot)
            if (method_exists($filter, 'getRelation') && $filter->getRelation()) {
                $relation = $filter->getRelation();
                $attribute = $filter->getRelationshipAttribute();

                // Get related model and its columns
                $relatedModel = $this->getRelatedModel($modelInstance, $relation);
                $relatedColumns = $this->getRelatedModelColumns($modelInstance, $relation);

                if (in_array($attribute, $relatedColumns)) {
                    // It's a real DB column on related model
                    $this->filterMetadata[$filterName] = [
                        'isAccessor' => false,
                        'isDbColumn' => true,
                        'isRelation' => true,
                        'filterColumns' => [$attribute],
                    ];
                } else {
                    // Attribute is accessor on related model
                    $filterCols = $relatedModel
                        ? $this->resolveAccessorColumns($relatedModel, $attribute, $relatedColumns)
                        : $this->guessColumnsFromName($attribute, $relatedColumns);

                    $this->filterMetadata[$filterName] = [
                        'isAccessor' => true,
                        'isDbColumn' => false,
                        'isRelation' => true,
                        'filterColumns' => $filterCols,
                    ];
                }

                continue;
            }

            // Check if filter column exists in database
            if (in_array($filterName, $this->databaseColumns)) {
                $this->filterMetadata[$filterName] = [
                    'isAccessor' => false,
                    'isDbColumn' => true,
                    'isRelation' => false,
                    'filterColumns' => [$filterName],
                ];

                continue;
            }

            // Check if it's an accessor - use reflection to find columns
            if ($this->isAccessor($modelInstance, $filterName)) {
                $filterCols = $this->resolveAccessorColumns($modelInstance, $filterName, $this->databaseColumns);

                $this->filterMetadata[$filterName] = [
                    'isAccessor' => true,
                    'isDbColumn' => false,
                    'isRelation' => false,
                    'filterColumns' => $filterCols,
                ];
            } else {
                // Unknown filter or has custom query callback
                $this->filterMetadata[$filterName] = [
                    'isAccessor' => false,
                    'isDbColumn' => false,
                    'isRelation' => false,
                    'filterColumns' => [],
                ];
            }
        }
    }

    /**
     * Get the model class for the table
     */
    protected function getTableModel(): ?string
    {
        $table = $this->tableInstance;

        // Try to get model from table configuration
        $query = $table->getQuery();

        return get_class($query->getModel());
    }

    /**
     * Get database table columns for a model
     */
    protected function getModelTableColumns($model): array
    {
        try {
            $table = $model->getTable();
            $connection = $model->getConnection();

            return $connection->getSchemaBuilder()->getColumnListing($table);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get related model instance for a relation
     */
    protected function getRelatedModel($model, string $relation)
    {
        try {
            $parts = explode('.', $relation);
            $current = $model;

            foreach ($parts as $part) {
                $methodName = Str::camel($part);
                if (! method_exists($current, $methodName)) {
                    return null;
                }
                $relationInstance = $current->{$methodName}();
                $current = $relationInstance->getRelated();
            }

            return $current;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Get database columns for a related model
     */
    protected function getRelatedModelColumns($model, string $relation): array
    {
        try {
            // Handle nested relations (e.g., "user.role")
            $parts = explode('.', $relation);
            $current = $model;

            foreach ($parts as $part) {
                $methodName = Str::camel($part);
                if (! method_exists($current, $methodName)) {
                    return [];
                }
                $relationInstance = $current->{$methodName}();
                $current = $relationInstance->getRelated();
            }

            return $current->getConnection()->getSchemaBuilder()->getColumnListing($current->getTable());
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Resolve columns used by an accessor - primary method using reflection
     */
    protected function resolveAccessorColumns($model, string $accessorName, array $tableColumns): array
    {
        // 1. Try reflection-based extraction from source code
        $columns = $this->extractColumnsFromAccessor($model, $accessorName, $tableColumns);

        if (! empty($columns)) {
            return $columns;
        }

        // 2. Fallback: pattern-based guessing for common names
        return $this->guessColumnsFromName($accessorName, $tableColumns);
    }

    /**
     * Extract database columns used by an accessor via reflection and source analysis
     */
    protected function extractColumnsFromAccessor($model, string $accessorName, array $tableColumns): array
    {
        // Try new-style Attribute accessor (Laravel 9+)
        $methodName = Str::camel($accessorName);
        if (method_exists($model, $methodName)) {
            $columns = $this->extractColumnsFromMethodSource($model, $methodName, $tableColumns);
            if (! empty($columns)) {
                return $columns;
            }
        }

        // Try old-style accessor (getXxxAttribute)
        $oldStyleMethod = 'get'.Str::studly($accessorName).'Attribute';
        if (method_exists($model, $oldStyleMethod)) {
            $columns = $this->extractColumnsFromMethodSource($model, $oldStyleMethod, $tableColumns);
            if (! empty($columns)) {
                return $columns;
            }
        }

        return [];
    }

    /**
     * Extract column references from a method's source code using reflection
     */
    protected function extractColumnsFromMethodSource($model, string $methodName, array $tableColumns): array
    {
        try {
            $reflection = new ReflectionMethod($model, $methodName);
            $filename = $reflection->getFileName();
            $startLine = $reflection->getStartLine();
            $endLine = $reflection->getEndLine();

            if (! $filename || ! $startLine || ! $endLine) {
                return [];
            }

            // Read the method source code
            $lines = file($filename);
            $methodSource = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

            return $this->findColumnsInSource($methodSource, $tableColumns);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Find column names in source code by matching against known table columns
     */
    protected function findColumnsInSource(string $source, array $tableColumns): array
    {
        $foundColumns = [];

        foreach ($tableColumns as $column) {
            // Match various patterns where column might be referenced:
            // $this->column_name, $this->columnName
            // $this->first_name, $this->firstName
            // "first_name", 'first_name'
            // $record->column_name
            $patterns = [
                '/\$this->'.preg_quote($column, '/')."\b/",
                '/\$this->'.preg_quote(Str::camel($column), '/')."\b/",
                '/["\']'.preg_quote($column, '/').'["\']/',
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $source)) {
                    $foundColumns[] = $column;
                    break;
                }
            }
        }

        return array_unique($foundColumns);
    }

    /**
     * Fallback: guess columns from accessor name for common patterns
     */
    protected function guessColumnsFromName(string $accessorName, array $tableColumns): array
    {
        $searchColumns = [];
        $snakeName = Str::snake($accessorName);

        // Common patterns for composite columns
        $patterns = [
            'full_name' => ['first_name', 'last_name', 'name'],
            'name' => ['first_name', 'last_name'],
            'full_address' => ['street', 'city', 'zip', 'address', 'address_line_1', 'address_line_2'],
            'address' => ['street', 'city', 'zip', 'address_line_1'],
            'contact' => ['email', 'phone', 'name'],
            'contact_info' => ['email', 'phone', 'mobile'],
            'full_paid' => ['paid_amount', 'total_amount'],
            'fully_paid' => ['paid_amount', 'total_amount'],
            'is_paid' => ['paid_amount', 'total_amount'],
        ];

        // Check if accessor matches a known pattern
        if (isset($patterns[$snakeName])) {
            foreach ($patterns[$snakeName] as $col) {
                if (in_array($col, $tableColumns)) {
                    $searchColumns[] = $col;
                }
            }
            if (! empty($searchColumns)) {
                return $searchColumns;
            }
        }

        // Try to find columns containing parts of accessor name
        $parts = explode('_', $snakeName);

        foreach ($tableColumns as $col) {
            foreach ($parts as $part) {
                if (strlen($part) > 2 && Str::contains($col, $part)) {
                    $searchColumns[] = $col;
                    break;
                }
            }
        }

        return array_unique($searchColumns);
    }

    /**
     * Check if a column name corresponds to a Laravel Attribute accessor
     */
    protected function isAccessor($model, string $name): bool
    {
        // Check for new-style Attribute accessor (Laravel 9+)
        $methodName = Str::camel($name);

        if (method_exists($model, $methodName)) {
            $reflection = new ReflectionMethod($model, $methodName);
            $returnType = $reflection->getReturnType();

            if ($returnType && $returnType->getName() === "Illuminate\Database\Eloquent\Casts\Attribute") {
                return true;
            }
        }

        // Check for old-style accessor (getXxxAttribute)
        $oldStyleMethod = 'get'.Str::studly($name).'Attribute';
        if (method_exists($model, $oldStyleMethod)) {
            return true;
        }

        // Check if it's in model's appends array (usually means it's an accessor)
        $appends = $model->getAppends();
        if (in_array($name, $appends) || in_array(Str::snake($name), $appends)) {
            return true;
        }

        return false;
    }

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
     * Render the table as HTML
     */
    public function getTableProperty(): string
    {
        return view('tables.index', [
            'table' => $this->getTable(),
            'records' => $this->getTableRecords(),
            'component' => $this,
        ])->render();
    }

    /**
     * Get paginated records for the table.
     *
     * Results are cached within the current request lifecycle to prevent
     * duplicate queries when areAllVisibleSelected() or selectAllRecords()
     * call this method after the initial render.
     */
    public function getTableRecords(): LengthAwarePaginator|Collection
    {
        if ($this->cachedRecords !== null) {
            return $this->cachedRecords;
        }

        $table = $this->getTable();

        // If lazy loading is enabled and not ready, return empty collection
        if ($table->isLazy() && ! $this->tableReady) {
            return collect();
        }

        $query = $table->getQuery();

        // Apply eager loading for relations
        $query = $this->applyEagerLoading($query);

        // Apply global search
        $query = $this->applySearch($query);

        // Apply filters
        $query = $this->applyFilters($query);

        // Apply sorting
        $query = $this->applySorting($query);

        // Return paginated or all
        if ($table->isPaginated()) {
            $this->cachedRecords = $query->paginate($this->tablePerPage);
        } else {
            $this->cachedRecords = $query->get();
        }

        return $this->cachedRecords;
    }

    /**
     * Apply eager loading for relation columns
     */
    protected function applyEagerLoading(Builder $query): Builder
    {
        $table = $this->getTable();
        $relations = [];

        foreach ($table->getColumns() as $column) {
            $relation = $column->getRelation();
            if ($relation && ! in_array($relation, $relations)) {
                $relations[] = $relation;
            }
        }

        if (! empty($relations)) {
            $query->with($relations);
        }

        return $query;
    }

    /**
     * Apply global search to query
     */
    protected function applySearch(Builder $query): Builder
    {
        if (empty($this->tableSearch)) {
            return $query;
        }

        $table = $this->getTable();
        $searchableColumns = $table->getSearchableColumns();

        if (empty($searchableColumns)) {
            return $query;
        }

        $search = $this->tableSearch;

        return $query->where(function (Builder $query) use ($searchableColumns, $search) {
            foreach ($searchableColumns as $column) {
                $columnName = $column->getName();

                // 1. Custom search callback takes highest priority (Filament-style)
                $searchCallback = $column->getSearchCallback();
                if ($searchCallback !== null) {
                    $query->orWhere(function (Builder $q) use ($searchCallback, $search) {
                        call_user_func($searchCallback, $q, $search);
                    });

                    continue;
                }

                // 2. Explicit searchColumns (Column: searchable(['col1', 'col2']), StackedColumn: auto-detected)
                $searchCols = $column->getSearchColumns();
                if (! empty($searchCols)) {
                    foreach ($searchCols as $searchCol) {
                        $this->applySearchToColumn($query, $searchCol, $search);
                    }

                    continue;
                }

                // 3. Use metadata (auto-detected via reflection)
                $metadata = $this->getColumnMetadata($columnName);

                // Check if it's a real DB column
                $isRealDbColumn =
                    $metadata['isDbColumn'] ||
                    ($this->databaseColumns !== null && in_array($columnName, $this->databaseColumns));

                if ($isRealDbColumn) {
                    // Search directly by column name
                    $this->applySearchToColumn($query, $columnName, $search);
                } elseif (! empty($metadata['searchColumns'])) {
                    // Search by mapped columns (from reflection or metadata)
                    foreach ($metadata['searchColumns'] as $searchCol) {
                        $this->applySearchToColumn($query, $searchCol, $search);
                    }
                }
                // else: skip - non-DB column without searchColumns and no callback
            }
        });
    }

    /**
     * Apply search to a single column
     */
    protected function applySearchToColumn(Builder $query, string $columnName, string $search): void
    {
        // Check if it's a relation column (contains dot)
        if (str_contains($columnName, '.')) {
            $parts = explode('.', $columnName);
            $attribute = array_pop($parts);
            $relation = implode('.', $parts);

            $query->orWhereHas($relation, function (Builder $q) use ($attribute, $search) {
                $q->where($attribute, 'like', "%$search%");
            });
        } else {
            $query->orWhere($columnName, 'like', "%{$search}%");
        }
    }

    /**
     * Get metadata for a column
     */
    protected function getColumnMetadata(string $columnName): array
    {
        return $this->columnMetadata[$columnName] ?? [
            'isAccessor' => false,
            'isDbColumn' => false,
            'isRelation' => false,
            'searchColumns' => [],
            'sortColumn' => null,
            'hasSearchCallback' => false,
            'hasSortCallback' => false,
        ];
    }

    /**
     * Apply filters to query
     */
    protected function applyFilters(Builder $query): Builder
    {
        $table = $this->getTable();

        // Apply global filters
        foreach ($table->getFilters() as $filter) {
            $filterName = $filter->getName();
            $value = $this->tableFilters[$filterName] ?? null;

            if (! $filter->canView()) {
                continue;
            }

            // Skip if no value
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            // If filter has custom query callback, use it
            if (method_exists($filter, 'getQueryCallback') && $filter->getQueryCallback()) {
                $query = call_user_func($filter->getQueryCallback(), $query, $value);

                continue;
            }

            // Get filter metadata
            $metadata = $this->getFilterMetadata($filterName);

            // Handle relation filters
            if (method_exists($filter, 'getRelation') && $filter->getRelation()) {
                $relation = $filter->getRelation();
                $attribute = $filter->getRelationshipAttribute();

                $query = $query->whereHas($relation, function ($q) use ($attribute, $value, $filter, $metadata) {
                    if ($metadata['isAccessor'] && ! empty($metadata['filterColumns'])) {
                        // Accessor on related model - filter by underlying columns
                        $q->where(function ($subQuery) use ($metadata, $value, $filter) {
                            foreach ($metadata['filterColumns'] as $col) {
                                if (method_exists($filter, 'isMultiple') && $filter->isMultiple() && is_array($value)) {
                                    $subQuery->orWhereIn($col, $value);
                                } else {
                                    $subQuery->orWhere($col, 'like', "%$value%");
                                }
                            }
                        });
                    } else {
                        // Real DB column on related model
                        if (method_exists($filter, 'isMultiple') && $filter->isMultiple() && is_array($value)) {
                            $q->whereIn($attribute, $value);
                        } else {
                            $q->where($attribute, $value);
                        }
                    }
                });

                continue;
            }

            // Handle local accessors
            if ($metadata['isAccessor'] && ! empty($metadata['filterColumns'])) {
                // Special handling for boolean/comparison accessors with exactly 2 columns
                if (
                    count($metadata['filterColumns']) === 2 &&
                    in_array($value, ['true', 'false', '1', '0', true, false, 1, 0], true)
                ) {
                    $cols = $metadata['filterColumns'];

                    // Try to intelligently determine which column is "paid" vs "total"
                    // Common patterns: paid_amount vs total_amount, current vs target, etc.
                    $paidCol = null;
                    $totalCol = null;

                    foreach ($cols as $col) {
                        if (str_contains($col, 'paid')) {
                            $paidCol = $col;
                        } elseif (str_contains($col, 'total')) {
                            $totalCol = $col;
                        }
                    }

                    // If we found both paid and total columns, use comparison logic
                    if ($paidCol && $totalCol) {
                        $isTrueValue = in_array($value, ['true', '1', true, 1], true);

                        if ($isTrueValue) {
                            // For "true": paid >= total (fully paid)
                            $query = $query->whereRaw("$paidCol >= $totalCol");
                        } else {
                            // For "false": paid < total (not fully paid)
                            $query = $query->whereRaw("$paidCol < $totalCol");
                        }
                    } else {
                        // Fallback: use first column for comparison (less reliable)
                        $col1 = $cols[0];
                        $col2 = $cols[1];
                        $isTrueValue = in_array($value, ['true', '1', true, 1], true);

                        if ($isTrueValue) {
                            $query = $query->whereRaw("$col1 >= $col2");
                        } else {
                            $query = $query->whereRaw("$col1 < $col2");
                        }
                    }
                } else {
                    // Standard accessor filtering - search across all underlying columns
                    $query = $query->where(function ($q) use ($metadata, $value, $filter) {
                        foreach ($metadata['filterColumns'] as $col) {
                            if (method_exists($filter, 'isMultiple') && $filter->isMultiple() && is_array($value)) {
                                $q->orWhereIn($col, $value);
                            } else {
                                $q->orWhere($col, 'like', "%$value%");
                            }
                        }
                    });
                }

                continue;
            }

            // Standard filter application for DB columns
            $query = $filter->apply($query, $value);
        }

        // Apply column filters (row filters)
        foreach ($table->getColumns() as $column) {
            if ($column->isFilterable()) {
                $columnName = $column->getName();
                $value = $this->columnFilters[$columnName] ?? null;

                if ($value !== null && $value !== '') {
                    $query = $this->applyColumnFilter($query, $column, $value);
                }
            }
        }

        return $query;
    }

    /**
     * Get metadata for a filter
     */
    protected function getFilterMetadata(string $filterName): array
    {
        return $this->filterMetadata[$filterName] ?? [
            'isAccessor' => false,
            'isDbColumn' => false,
            'isRelation' => false,
            'filterColumns' => [],
        ];
    }

    /**
     * Apply filter for a single column using metadata
     */
    protected function applyColumnFilter(Builder $query, Column $column, mixed $value): Builder
    {
        // Empty array values (e.g. date_range with both empty) should be skipped
        if (is_array($value) && empty(array_filter($value, fn ($v) => $v !== null && $v !== ''))) {
            return $query;
        }

        // Custom filter callback takes priority
        if ($column->getFilterQueryCallback()) {
            return call_user_func($column->getFilterQueryCallback(), $query, $value);
        }

        $columnName = $column->getName();

        // For relation columns — Column::applyFilter handles this
        if ($column->getRelation()) {
            return $column->applyFilter($query, $value);
        }

        // Check explicit searchColumns on Column first (Filament-style)
        // These override the column name for filtering purposes
        $explicitColumns = $column->getSearchColumns();
        if (! empty($explicitColumns)) {
            return $query->where(function ($q) use ($column, $explicitColumns, $value) {
                foreach ($explicitColumns as $col) {
                    if (str_contains($col, '.')) {
                        $parts = explode('.', $col);
                        $attr = array_pop($parts);
                        $rel = implode('.', $parts);
                        $q->orWhereHas($rel, function ($subQ) use ($column, $attr, $value) {
                            $column->applyFilterCondition($subQ, $attr, $value);
                        });
                    } else {
                        $q->orWhere(function ($subQ) use ($column, $col, $value) {
                            $column->applyFilterCondition($subQ, $col, $value);
                        });
                    }
                }
            });
        }

        // Check metadata for non-DB columns (accessor columns with searchColumns)
        $metadata = $this->getColumnMetadata($columnName);
        $isRealDbColumn =
            $metadata['isDbColumn'] ||
            ($this->databaseColumns !== null && in_array($columnName, $this->databaseColumns));

        if (! $isRealDbColumn) {
            if (! empty($metadata['searchColumns'])) {
                return $query->where(function ($q) use ($column, $metadata, $value) {
                    foreach ($metadata['searchColumns'] as $col) {
                        $q->orWhere(function ($subQ) use ($column, $col, $value) {
                            $column->applyFilterCondition($subQ, $col, $value);
                        });
                    }
                });
            }

            // Non-DB column without searchColumns — skip
            return $query;
        }

        // Standard DB column — delegate to Column::applyFilter
        return $column->applyFilter($query, $value);
    }

    /**
     * Apply sorting to query
     */
    protected function applySorting(Builder $query): Builder
    {
        if (empty($this->tableSortColumn)) {
            return $query;
        }

        $table = $this->getTable();
        $column = $this->findColumn($this->tableSortColumn);

        if (! $column || ! $column->isSortable()) {
            return $query;
        }

        $columnName = $column->getName();
        $direction = $this->tableSortDirection;

        // 1. Custom sort callback takes highest priority
        $sortCallback = $column->getSortCallback();
        if ($sortCallback !== null) {
            return call_user_func($sortCallback, $query, $direction);
        }

        // 2. Get metadata for this column
        $metadata = $this->getColumnMetadata($columnName);

        // Determine sort column from metadata
        $sortColumn = $metadata['sortColumn'];

        // Cannot sort if no sort column available (accessor without mapped columns)
        if ($sortColumn === null) {
            return $query;
        }

        // Check if sort column contains relation (dot notation)
        if (str_contains($sortColumn, '.')) {
            // Create a temporary column object for relation sorting
            $tempColumn = new Column($sortColumn);

            return $this->applySortingWithRelation($query, $tempColumn, $direction);
        }

        return $query->orderBy($sortColumn, $direction);
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

    /**
     * Apply sorting for relation columns
     */
    protected function applySortingWithRelation(Builder $query, Column $column, string $direction): Builder
    {
        $relation = $column->getRelation();
        $attribute = $column->getRelationshipAttribute();

        // Get the related table name
        $model = $query->getModel();
        $relationInstance = $model->{Str::camel($relation)}();
        $relatedTable = $relationInstance->getRelated()->getTable();
        $foreignKey = $relationInstance->getForeignKeyName();
        $ownerKey = $relationInstance->getOwnerKeyName();
        $table = $model->getTable();

        return $query
            ->leftJoin($relatedTable, "{$table}.{$foreignKey}", '=', "{$relatedTable}.{$ownerKey}")
            ->orderBy("{$relatedTable}.{$attribute}", $direction)
            ->select("{$table}.*");
    }

    /**
     * Check if table is ready to display data
     */
    public function isTableReady(): bool
    {
        return $this->tableReady;
    }

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

        $records = $this->getTableRecords();
        $this->expandedRows = $records->pluck($table->getPrimaryKey())
            ->map(fn ($k) => (string) $k)
            ->all();
    }

    /**
     * Collapse all expanded rows.
     */
    public function collapseAllRows(): void
    {
        $this->expandedRows = [];
    }

    /**
     * Check if a row is expanded.
     */
    public function isRowExpanded(mixed $recordKey): bool
    {
        return in_array((string) $recordKey, $this->expandedRows, true);
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
     */
    public function getSubRows(mixed $record): Collection
    {
        $table = $this->getTable();
        if (! $table->hasSubRows()) {
            return collect();
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
     *
     * @throws ReflectionException
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

    /**
     * @throws ReflectionException
     */
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
     * Handles the before → action → after lifecycle for all action types
     * (row, bulk, header), eliminating ~400 lines of duplicated code.
     *
     * @param  BaseAction  $action  The action to execute
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

        // ── Before hooks ──────────────────────────────────────
        if (! $confirmed && $action->hasBeforeCallbacks()) {
            foreach ($action->getBeforeCallbacks() as $i => $beforeCallback) {
                $this->invokeActionCallback($beforeCallback, array_merge($payload, [
                    'action' => $action,
                    'confirmed' => $confirmed,
                    'component' => $this,
                ]));

                $pendingHalt = $action->consumePendingHalt();
                if ($pendingHalt) {
                    $pendingHalt->source('before', $i);
                    $this->showHaltModal($haltKey, $action->getName(), $pendingHalt, $data, $actionType);

                    return;
                }
            }
        }

        // ── Main action ───────────────────────────────────────
        $callback = $action->getActionCallback();
        $result = null;

        if ($callback) {
            $halt = fn () => ActionHalt::make();

            $result = $this->invokeActionCallback($callback, array_merge($payload, [
                'halt' => $halt,
                'confirmed' => $confirmed,
                'component' => $this,
            ]));

            if ($result instanceof ActionHalt) {
                $result->source('action');
                $this->showHaltModal($haltKey, $action->getName(), $result, $data, $actionType);

                return;
            }
        }

        // ── After hooks ───────────────────────────────────────
        if ($action->hasAfterCallbacks()) {
            foreach ($action->getAfterCallbacks() as $i => $afterCallback) {
                $this->invokeActionCallback($afterCallback, array_merge($payload, [
                    'action' => $action,
                    'result' => $result,
                    'confirmed' => $confirmed,
                    'component' => $this,
                ]));

                $pendingHalt = $action->consumePendingHalt();
                if ($pendingHalt) {
                    $pendingHalt->source('after', $i);
                    $this->showHaltModal($haltKey, $action->getName(), $pendingHalt, $data, $actionType);

                    return;
                }
            }
        }

        // ── Post-action ───────────────────────────────────────
        if ($actionType === 'bulk' && method_exists($action, 'shouldDeselectRecordsAfterCompletion') && $action->shouldDeselectRecordsAfterCompletion()) {
            $this->deselectAllRecords();
        }

        $this->handleActionSuccess($action, $payload['record'] ?? $payload['records'] ?? null);
    }

    /**
     * Show halt modal with dynamic configuration.
     *
     * @param  string  $recordKey  Record key (or '__bulk__' / '__header__' for non-row actions)
     * @param  string  $actionName  Action name
     * @param  ActionHalt  $halt  The halt object with modal configuration
     * @param  array  $formData  Current form data to preserve
     * @param  string  $actionType  'row', 'bulk', or 'header'
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
        $this->showHaltModal = true;
    }

    /**
     * Handle post-action success: table invalidation and redirects.
     *
     * Called after action + after hooks complete without halting.
     *
     * NOTE: Notifications are NOT sent here automatically — action callbacks
     * are responsible for their own notifications (via $this->flash(),
     * $component->sendNotification(), etc.). This avoids double
     * notifications and gives full control to the action author.
     *
     * If you need an automatic notification, use ->successNotification('...')
     * on the action and handle it in your component's action callback,
     * or use the HasLifecycle::sendSuccessNotification() method.
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
     *
     * Livewire's `protected` properties (like $tableInstance) are NOT
     * automatically serialised between requests — they're rebuilt from
     * scratch on each subsequent request via `getTable()`.  However,
     * within the *same* request (e.g. after an action executes and
     * the component re-renders in the same lifecycle) the cached
     * instance still holds the old Builder with stale results.
     *
     * Calling this method forces `getTable()` to rebuild everything,
     * ensuring filters, counts, and records reflect the latest DB state.
     */
    public function invalidateTable(): void
    {
        $this->tableInstance = null;
        $this->columnMetadata = [];
        $this->filterMetadata = [];
        $this->databaseColumns = null;
        $this->cachedRecords = null;
    }

    /**
     * Send a notification through the resolved notification driver.
     *
     * Resolution order:
     *   1. Per-table driver (Table::notificationDriver())
     *   2. Global default (NotificationManager::setDefaultDriver())
     *   3. Built-in SessionDriver (backward compatible)
     *
     * Can be called from action closures or component methods:
     *
     *   // Inside action callback via $component:
     *   ->action(function ($record, $component) {
     *       $record->approve();
     *       $component->sendNotification(Notification::success('Schváleno'));
     *   })
     *
     *   // With rich notification:
     *   $this->sendNotification(
     *       Notification::warning('Pozor!')
     *           ->title('Omezená kapacita')
     *           ->duration(8000)
     *           ->icon('exclamation-triangle')
     *   );
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

    /**
     * Submit action modal (execute action with form data)
     */
    public function submitActionModal(): void
    {
        $isHeaderAction = $this->actionModalIsHeaderAction;
        $isBulkAction = $this->actionModalIsBulk;

        $validation = [];
        $messages = [];
        $attributes = [];
        $fields = [];

        if (! $this->actionModalName) {
            $this->closeActionModal();

            return;
        }

        if ($isHeaderAction) {
            $action = $this->findHeaderAction($this->actionModalName);
            if ($action) {
                $validation = $action->getFormValidation();
                $messages = $action->getValidationMessages();
                $attributes = $action->getValidationAttributes();
                $fields = $action->getFormFields();
            }
        } elseif ($isBulkAction) {
            $action = $this->findBulkAction($this->actionModalName);
            if ($action) {
                // Get selected records for dynamic validation rules
                $selectedRecords = $this->getSelectedRecords();
                $validation = $action->getFormValidation($selectedRecords);
                $messages = $action->getValidationMessages($selectedRecords);
                $attributes = $action->getValidationAttributes($selectedRecords);
                $fields = $action->getFormFields($selectedRecords);
            }
        } else {
            $action = $this->findAction($this->actionModalName);
            if ($action) {
                // Get record for row actions to support dynamic validation rules
                $record = $this->actionModalRecordKey ? $this->getRecord($this->actionModalRecordKey) : null;
                $validation = $action->getFormValidation($record);
                $messages = $action->getValidationMessages($record);
                $attributes = $action->getValidationAttributes($record);
                $fields = $action->getFormFields($record);
            }
        }

        if (! $action) {
            $this->closeActionModal();

            return;
        }

        // Auto-generate attributes from field labels if not explicitly set
        if (empty($attributes) && ! empty($fields)) {
            $attributes = $this->extractAttributesFromFields($fields);
        }

        // Validate form data if validation rules exist
        // Note: getFormValidation() automatically adds 'actionModalFormData.' prefix
        if (! empty($validation)) {
            $this->validateActionModalForm($validation, $messages, $attributes);
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

    /**
     * Extract validation attributes from field definitions
     *
     * This auto-generates attribute names from field labels so that
     * validation messages show "Název" instead of "actionModalFormData.name"
     */
    protected function extractAttributesFromFields(array $fields, string $prefix = 'actionModalFormData.'): array
    {
        $attributes = [];

        foreach ($fields as $field) {
            // Get field name and label
            $name = $field['name'] ?? null;
            $label = $field['label'] ?? null;

            if ($name && $label) {
                $attributes[$prefix.$name] = $label;
            }

            // Process nested schema (sections, grids, fieldsets)
            if (isset($field['schema']) && is_array($field['schema'])) {
                $nestedAttributes = $this->extractAttributesFromFields($field['schema'], $prefix);
                $attributes = array_merge($attributes, $nestedAttributes);
            }
        }

        return $attributes;
    }

    /**
     * Validate action modal form data with custom messages and attributes
     *
     * Uses Laravel Validator directly for better control over validation messages
     */
    protected function validateActionModalForm(array $rules, array $messages = [], array $attributes = []): void
    {
        // Prepare data in the format expected by validation rules
        // Rules use 'actionModalFormData.field' format
        $data = ['actionModalFormData' => $this->actionModalFormData];

        $validator = Validator::make($data, $rules, $messages, $attributes);

        if ($validator->fails()) {
            // Re-throw with errors that Livewire can handle
            throw new ValidationException($validator);
        }
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
     *
     * If cache is empty but modal should be shown (e.g., after validation failure),
     * regenerate the config from the action.
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
     * Regenerate modal config from action
     * Used when validation fails and component re-renders
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
     * Show confirmation modal for action (legacy)
     */
    public function confirmTableAction(string $recordKey, string $actionName): void
    {
        $this->confirmActionName = $actionName;
        $this->confirmRecordKey = $recordKey;
        $this->isBulkAction = false;
        $this->showConfirmationModal = true;
    }

    /**
     * Execute confirmed action (legacy)
     */
    public function executeConfirmedAction(): void
    {
        if (! $this->confirmActionName) {
            $this->closeConfirmationModal();

            return;
        }

        if ($this->isBulkAction) {
            $this->executeConfirmedBulkAction();
        } elseif ($this->confirmRecordKey !== null) {
            $this->executeTableAction($this->confirmRecordKey, $this->confirmActionName, true);
        }

        $this->closeConfirmationModal();
    }

    /**
     * Close confirmation modal (legacy)
     */
    public function closeConfirmationModal(): void
    {
        $this->showConfirmationModal = false;
        $this->confirmActionName = null;
        $this->confirmRecordKey = null;
        $this->isBulkAction = false;

        // Invalidate table cache so next render fetches fresh data
        $this->invalidateTable();
    }

    // ==========================================
    // Halt Modal System (Dynamic Confirmation)
    // ==========================================

    /**
     * Execute confirmed bulk action (legacy)
     */
    protected function executeConfirmedBulkAction(): void
    {
        $this->executeBulkAction($this->confirmActionName);
    }

    /**
     * Get halt modal configuration for view.
     */
    public function getHaltModalData(): array
    {
        return $this->haltModalConfig;
    }

    /**
     * Submit halt modal (confirm and re-execute action).
     *
     * Routes to the correct execution method based on halt context.
     * Respects skipBeforeOnConfirm setting from the ActionHalt that triggered this.
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
            $validator = Validator::make(
                $formData,
                $validation,
                $this->haltModalConfig['formValidationMessages'] ?? [],
                $this->haltModalConfig['formValidationAttributes'] ?? [],
            );

            if ($validator->fails()) {
                throw ValidationException::withMessages($validator->errors()->toArray());
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
        $this->haltActionType = null;
        $this->haltContext = [];

        // Invalidate table cache so next render fetches fresh data
        $this->invalidateTable();
    }

    /**
     * Confirm bulk action (legacy)
     */
    public function confirmBulkAction(string $actionName): void
    {
        $this->confirmActionName = $actionName;
        $this->isBulkAction = true;
        $this->showConfirmationModal = true;
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
        $this->showActionModal = true;
    }

    /**
     * Execute header action
     */
    public function executeHeaderAction(string $actionName, bool $confirmed = false): void
    {
        $this->executeHeaderActionWithData($actionName, [], $confirmed);
    }

    /**
     * Update table cell (inline editing)
     */

    /**
     * Update a single cell in the table.
     *
     * Supports optimistic locking via $recordVersion parameter.
     * If provided, the record's updated_at timestamp is checked before saving.
     * If another user modified the record in the meantime, a conflict error is returned
     * with the current value so the client can reconcile.
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
            return ['success' => false, 'message' => 'Sloupec nenalezen'];
        }

        if (! $column->isEditable()) {
            return ['success' => false, 'message' => 'Sloupec není editovatelný'];
        }

        // ── Permission checks (before transaction — read-only) ──
        if ($column->getPermission()) {
            $user = auth()->user();
            if (! $user) {
                return ['success' => false, 'message' => 'Nemáte oprávnění'];
            }
            if (method_exists($user, 'hasPermissionTo') && ! $user->hasPermissionTo($column->getPermission())) {
                return ['success' => false, 'message' => 'Nemáte oprávnění pro zobrazení'];
            }
        }

        // ── Format & validate (before transaction — no DB writes) ──
        if (method_exists($column, 'formatForSave')) {
            $value = $column->formatForSave($value, null);
        }

        // Pre-validate without record context (basic rules)
        $rules = $column->getEditableRules(null);
        if (! empty($rules)) {
            try {
                $validator = Validator::make([$columnName => $value], [$columnName => $rules]);
                if ($validator->fails()) {
                    return [
                        'success' => false,
                        'message' => $validator->errors()->first($columnName),
                        'errors' => $validator->errors()->get($columnName),
                    ];
                }
            } catch (ValidationException $e) {
                return [
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->errors()[$columnName] ?? [],
                ];
            }
        }

        // ── Atomic update with optimistic locking ───────────────
        // Uses DB::transaction + lockForUpdate to eliminate TOCTOU race.
        // The SELECT ... FOR UPDATE ensures no other transaction can modify
        // the row between our check and our write.
        try {
            $result = DB::transaction(function () use ($table, $column, $columnName, $recordKey, $value, $recordVersion) {
                // Lock the row — blocks concurrent writes until this transaction commits
                $record = $table->getQuery()
                    ->where($table->getPrimaryKey(), $recordKey)
                    ->lockForUpdate()
                    ->first();

                if (! $record) {
                    return ['success' => false, 'message' => 'Záznam nenalezen'];
                }

                // ── Edit permission (record-aware) ──
                if (method_exists($column, 'canEdit') && ! $column->canEdit($record)) {
                    return ['success' => false, 'message' => 'Nemáte oprávnění pro editaci'];
                }

                // ── Optimistic locking ──
                // Compare client's version (updated_at timestamp from render time)
                // with the locked row's current version.
                if ($recordVersion !== null && $recordVersion !== '0' && $record->updated_at) {
                    $currentVersion = (string) $record->updated_at->getTimestamp();
                    if ($currentVersion !== $recordVersion) {
                        $currentValue = $record->{$columnName};
                        if (method_exists($column, 'formatAfterLoad')) {
                            $currentValue = $column->formatAfterLoad($currentValue, $record);
                        }

                        return [
                            'success' => false,
                            'message' => 'Záznam byl mezitím změněn jiným uživatelem. Aktuální hodnota byla načtena.',
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
                            'message' => $validation['errors'][0] ?? 'Validace selhala',
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

                    return ['success' => true, 'version' => $newVersion, 'record' => $record, 'value' => $value];
                }

                // Custom update callback (legacy)
                $editableCallback = $column->getEditableCallback();
                if ($editableCallback) {
                    call_user_func($editableCallback, $record, $value);
                    $record->refresh();
                    $newVersion = $record->updated_at ? (string) $record->updated_at->getTimestamp() : null;

                    return ['success' => true, 'version' => $newVersion, 'record' => $record, 'value' => $value];
                }

                // Pivot update
                if ($column->isPivot()) {
                    $attribute = $column->getRelationshipAttribute();
                    if ($record->pivot) {
                        $record->pivot->{$attribute} = $value;
                        $record->pivot->save();
                    }

                    return ['success' => true, 'record' => $record, 'value' => $value];
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

                    return ['success' => true, 'record' => $record, 'value' => $value];
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

                if ($record && method_exists($column, 'getAfterStateUpdatedCallback') && $column->getAfterStateUpdatedCallback()) {
                    call_user_func($column->getAfterStateUpdatedCallback(), $record, $savedValue);
                }

                $this->invalidateTable();

                // Clean internal keys before returning to client
                unset($result['record'], $result['value']);
            }

            return $result;

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Chyba při ukládání: '.$e->getMessage()];
        }
    }

    /**
     * Update pivot table cell
     */
    protected function updatePivotCell(Model $record, Column $column, mixed $value): void
    {
        $attribute = $column->getRelationshipAttribute();

        if ($record->pivot) {
            $record->pivot->{$attribute} = $value;
            $record->pivot->save();
        }
    }

    /**
     * Update related model cell
     */
    protected function updateRelationCell(Model $record, Column $column, mixed $value): void
    {
        $relation = $column->getRelation();
        $attribute = $column->getRelationshipAttribute();

        $related = data_get($record, $relation);

        if ($related instanceof Model) {
            $related->{$attribute} = $value;
            $related->save();
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
            return ['valid' => false, 'errors' => ['Sloupec nenalezen']];
        }

        $record = $table->getQuery()->find($recordKey);

        if (! $record) {
            return ['valid' => false, 'errors' => ['Záznam nenalezen']];
        }

        // Apply formatters before validation (for TextInputColumn)
        if (method_exists($column, 'formatForSave')) {
            $value = $column->formatForSave($value, $record);
        }

        // Use column's validate method (for TextInputColumn)
        if (method_exists($column, 'validate')) {
            return $column->validate($value, $record);
        }

        // Validate using editable rules (legacy support)
        $rules = $column->getEditableRules($record);
        if (! empty($rules)) {
            $validator = Validator::make([$columnName => $value], [$columnName => $rules]);

            if ($validator->fails()) {
                return [
                    'valid' => false,
                    'errors' => $validator->errors()->get($columnName),
                ];
            }
        }

        return ['valid' => true, 'errors' => []];
    }

    /**
     * Get confirmation modal data
     */
    public function getConfirmationModalData(): array
    {
        if (! $this->confirmActionName) {
            return [
                'title' => 'Potvrdit akci',
                'description' => 'Opravdu chcete provést tuto akci?',
                'confirmLabel' => 'Potvrdit',
                'cancelLabel' => 'Zrušit',
            ];
        }

        if ($this->isBulkAction) {
            $action = $this->findBulkAction($this->confirmActionName);
            $record = null;
        } else {
            $action = $this->findAction($this->confirmActionName);
            $record = $this->confirmRecordKey ? $this->getRecord($this->confirmRecordKey) : null;
        }

        if (! $action) {
            return [
                'title' => 'Potvrdit akci',
                'description' => 'Opravdu chcete provést tuto akci?',
                'confirmLabel' => 'Potvrdit',
                'cancelLabel' => 'Zrušit',
            ];
        }

        return [
            'title' => $action->getModalHeading($record),
            'description' => $action->getModalDescription($record),
            'confirmLabel' => $action->getModalSubmitActionLabel(),
            'cancelLabel' => $action->getModalCancelActionLabel(),
        ];
    }

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
     * Build the complete query with all modifications applied.
     * Useful for debugging.
     */
    protected function buildTableQuery(): Builder
    {
        $table = $this->getTable();
        $query = $table->getQuery();

        // Apply search
        $query = $this->applySearch($query);

        // Apply filters
        $query = $this->applyFilters($query);

        // Apply column filters (respecting accessor/relation columns)
        foreach ($this->columnFilters ?? [] as $columnName => $filterValue) {
            if ($filterValue === null || $filterValue === '') {
                continue;
            }

            $column = $this->findColumn($columnName);
            if ($column && $column->getRelation()) {
                // Filter through relation
                $relation = $column->getRelation();
                $attribute = $column->getRelationshipAttribute() ?? $columnName;
                $query->whereHas($relation, function (Builder $q) use ($attribute, $filterValue) {
                    $q->where($attribute, $filterValue);
                });
            } else {
                $query->where($columnName, $filterValue);
            }
        }

        // Apply sorting
        $sortColumn = $this->tableSortColumn ?: $table->getDefaultSort();
        $sortDirection = $this->tableSortDirection ?: $table->getDefaultSortDirection();

        if ($sortColumn) {
            $query->orderBy($sortColumn, $sortDirection);
        }

        return $query;
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
     * Get the final SQL query with all filters, search, and sorting applied.
     *
     * @return string The complete SQL query
     */
    public function getTableSql(): string
    {
        return static::builderToSql($this->buildTableQuery());
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
     * Invalidates cached records so re-render fetches fresh data from DB.
     *
     * @param  mixed  $recordKey  The primary key of the record to refresh
     */
    public function refreshRow(mixed $recordKey): void
    {
        // Invalidate cached records so next render fetches fresh data
        $this->cachedRecords = null;
    }
}
