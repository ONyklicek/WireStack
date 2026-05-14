<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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

    // ─── Getters ────────────────────────────────────────

    public function hasSubRows(): bool
    {
        return $this->subRowRelation !== null || ! empty($this->subRowColumns);
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

    /**
     * Build the sub-rows query for a parent record.
     *
     * @return Builder<Model>
     */
    public function getSubRowsQuery(mixed $record): Builder
    {
        $relation = $this->subRowRelation;
        $query = $record->{$relation}();

        if ($this->subRowQueryCallback) {
            $query = call_user_func($this->subRowQueryCallback, $query);
        }

        if ($this->subRowsLimit) {
            $query->limit($this->subRowsLimit);
        }

        return $query;
    }
}
