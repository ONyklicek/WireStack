<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use NyonCode\WireTable\Columns\Column;

/**
 * Trait HasSubRows
 *
 * Enables expandable sub-rows in the table. Each parent row can expand
 * to show related child records from an Eloquent relationship.
 *
 * Usage in Table definition:
 *
 *   $table->subRows('invoiceItems')                     // relation name
 *         ->subRowColumns([                              // columns for child rows
 *             Column::make('product_name'),
 *             Column::make('quantity'),
 *             Column::make('price')->summarize('sum'),
 *         ])
 *         ->subRowsDefaultExpanded(false)                // collapsed by default
 *         ->subRowsExpandable(true)                      // user can toggle
 *         ->flattenSubRows(false)                        // show as nested (not flat)
 *         ->subRowsFilterable(true)                      // enable sub-row filtering
 *
 * "Flatten" mode shows all sub-rows as regular table rows (for export/print).
 */
trait HasSubRows
{
    /** Eloquent relationship name for sub-rows */
    protected ?string $subRowRelation = null;

    /**
     * Columns to display in sub-rows
     *
     * @var array<int, Column>
     */
    protected array $subRowColumns = [];

    /** Custom query modifier for sub-rows */
    protected ?Closure $subRowQueryCallback = null;

    /** Whether sub-rows are expanded by default */
    protected bool $subRowsDefaultExpanded = false;

    /** Whether sub-rows can be expanded/collapsed */
    protected bool $subRowsExpandable = true;

    /** Flatten mode — show all sub-rows as regular rows */
    protected bool $flattenSubRows = false;

    /** Whether sub-rows are independently filterable */
    protected bool $subRowsFilterable = false;

    /** Label for the expand/collapse toggle column */
    protected ?string $subRowsToggleLabel = null;

    /** Max sub-rows to show before "show more" */
    protected ?int $subRowsLimit = null;

    /** Custom sub-row Blade view */
    protected ?string $subRowView = null;

    /** Whether sub-row columns can be sorted by clicking their headers */
    protected bool $subRowsSortable = false;

    /** Default sort column for sub-rows (applied until the user clicks a header) */
    protected ?string $subRowsDefaultSort = null;

    /** Default sort direction for sub-rows */
    protected string $subRowsDefaultSortDirection = 'asc';

    /**
     * Per-child-row actions, rendered in a trailing actions cell.
     *
     * @var array<int, object>
     */
    protected array $subRowActions = [];

    // ─── Fluent API ─────────────────────────────────────

    /**
     * Enable sub-rows via an Eloquent relationship.
     *
     * @param  string  $relation  Eloquent relationship method name (e.g. 'items', 'children')
     */
    public function subRows(string $relation): static
    {
        $this->subRowRelation = $relation;

        return $this;
    }

    /**
     * Set columns to display in sub-rows.
     * These can differ from the parent table columns.
     *
     * @param  array<int, Column>  $columns
     */
    public function subRowColumns(array $columns): static
    {
        $this->subRowColumns = $columns;

        return $this;
    }

    /**
     * Modify the sub-row query (add scopes, ordering, etc).
     *
     * Example:
     *   ->subRowQuery(fn($query) => $query->orderBy('sort_order')->where('active', true))
     */
    public function subRowQuery(Closure $callback): static
    {
        $this->subRowQueryCallback = $callback;

        return $this;
    }

    /**
     * Set sub-rows to be expanded by default.
     */
    public function subRowsDefaultExpanded(bool $expanded = true): static
    {
        $this->subRowsDefaultExpanded = $expanded;

        return $this;
    }

    /**
     * Enable/disable expand/collapse toggle for sub-rows.
     */
    public function subRowsExpandable(bool $expandable = true): static
    {
        $this->subRowsExpandable = $expandable;

        return $this;
    }

    /**
     * Flatten sub-rows — display all child rows as regular table rows.
     * Useful for "show all records" mode or export.
     */
    public function flattenSubRows(bool $flatten = true): static
    {
        $this->flattenSubRows = $flatten;

        return $this;
    }

    /**
     * Enable independent filtering of sub-rows.
     */
    public function subRowsFilterable(bool $filterable = true): static
    {
        $this->subRowsFilterable = $filterable;

        return $this;
    }

    /**
     * Set label for the expand/collapse toggle column.
     */
    public function subRowsToggleLabel(?string $label): static
    {
        $this->subRowsToggleLabel = $label;

        return $this;
    }

    /**
     * Limit number of sub-rows shown (null = show all).
     */
    public function subRowsLimit(?int $limit): static
    {
        $this->subRowsLimit = $limit;

        return $this;
    }

    /**
     * Set a custom Blade view for rendering sub-rows.
     */
    public function subRowView(string $view): static
    {
        $this->subRowView = $view;

        return $this;
    }

    /**
     * Enable click-to-sort on sub-row column headers, with an optional default
     * sort applied before the user interacts.
     *
     * Example:
     *   ->subRowsSortable(default: 'created_at', direction: 'desc')
     */
    public function subRowsSortable(bool $sortable = true, ?string $default = null, string $direction = 'asc'): static
    {
        $this->subRowsSortable = $sortable;
        $this->subRowsDefaultSort = $default;
        $this->subRowsDefaultSortDirection = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        return $this;
    }

    /**
     * Set per-child-row actions. Each action renders against the sub-row record,
     * the same way main-table actions render against a parent record.
     *
     * @param  array<int, object>  $actions
     */
    public function subRowActions(array $actions): static
    {
        $this->subRowActions = $actions;

        return $this;
    }

    // ─── Getters ────────────────────────────────────────

    public function hasSubRows(): bool
    {
        return $this->subRowRelation !== null
            || ! empty($this->subRowColumns)
            || $this->subRowView !== null;
    }

    public function getSubRowRelation(): ?string
    {
        return $this->subRowRelation;
    }

    /**
     * @return array<int, Column>
     */
    public function getSubRowColumns(): array
    {
        return $this->subRowColumns;
    }

    public function getSubRowQueryCallback(): ?Closure
    {
        return $this->subRowQueryCallback;
    }

    public function isSubRowsDefaultExpanded(): bool
    {
        return $this->subRowsDefaultExpanded;
    }

    public function isSubRowsExpandable(): bool
    {
        return $this->subRowsExpandable;
    }

    public function isFlattenSubRows(): bool
    {
        return $this->flattenSubRows;
    }

    public function isSubRowsFilterable(): bool
    {
        return $this->subRowsFilterable;
    }

    public function getSubRowsToggleLabel(): ?string
    {
        return $this->subRowsToggleLabel;
    }

    public function getSubRowsLimit(): ?int
    {
        return $this->subRowsLimit;
    }

    public function getSubRowView(): ?string
    {
        return $this->subRowView;
    }

    public function isSubRowsSortable(): bool
    {
        return $this->subRowsSortable;
    }

    public function getSubRowsDefaultSort(): ?string
    {
        return $this->subRowsDefaultSort;
    }

    public function getSubRowsDefaultSortDirection(): string
    {
        return $this->subRowsDefaultSortDirection;
    }

    /**
     * @return array<int, object>
     */
    public function getSubRowActions(): array
    {
        return $this->subRowActions;
    }

    public function hasSubRowActions(): bool
    {
        return ! empty($this->subRowActions);
    }

    /**
     * Build the sub-rows query for a parent record.
     *
     * @param  array{column: string, direction: string}|null  $sort  Active sort override
     * @param  bool  $applyLimit  When false, the configured subRowsLimit is skipped
     *                            (used by "show all" / show-more).
     * @return Builder<Model>
     */
    public function getSubRowsQuery(mixed $record, ?array $sort = null, bool $applyLimit = true): Builder
    {
        $relation = $this->subRowRelation;
        $query = $record->{$relation}();

        if ($this->subRowQueryCallback) {
            $query = ($this->subRowQueryCallback)($query);
        }

        // Apply sorting: an explicit user sort wins over the configured default.
        $sortColumn = $sort['column'] ?? $this->subRowsDefaultSort;
        $sortDirection = $sort['direction'] ?? $this->subRowsDefaultSortDirection;

        if ($sortColumn !== null && $this->isSubRowColumnSortable($sortColumn)) {
            $query->orderBy($sortColumn, $sortDirection === 'desc' ? 'desc' : 'asc');
        }

        if ($applyLimit && $this->subRowsLimit) {
            $query->limit($this->subRowsLimit);
        }

        // A relationship object proxies to its underlying Builder, but is not one;
        // hand back the Builder (constraints already applied) for a stable contract.
        if ($query instanceof Relation) {
            return $query->getQuery();
        }

        return $query;
    }

    /**
     * Whether a given sub-row column name may be sorted.
     * Guards against ordering by arbitrary user-supplied strings.
     */
    public function isSubRowColumnSortable(string $columnName): bool
    {
        if (! $this->subRowsSortable && $columnName !== $this->subRowsDefaultSort) {
            return false;
        }

        foreach ($this->subRowColumns as $column) {
            if ($column->getName() === $columnName) {
                return true;
            }
        }

        return false;
    }
}
