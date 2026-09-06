<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Services\AggregateSubqueries;

/**
 * Declares a column as a relation rollup — `withCount` / `withSum` / `withAvg` /
 * `withMin` / `withMax` — rather than an attribute of the record itself.
 *
 * Pure configuration. The subqueries are applied by
 * {@see AggregateSubqueries}, which is the one place
 * the function name becomes SQL; a summary over a rollup reads
 * {@see getAggregateAttribute()} through
 * {@see CanBeSummarized::summaryTarget()}.
 *
 * **The alias convention is stated in two other places** and the three agree:
 * `Core\Query\AggregateClause::getAlias()` and
 * `Core\Relations\AggregateSegment::getName()` both build
 * `{relation}_{function}[_{column}]` for the planner's path. Neither is reachable
 * from here without pointing `Core\Relations` at `Core\Query` (the dependency runs
 * the other way today), so this stays a fourth reader of the same rule rather than
 * a fifth writer of it — consolidating means giving the convention one owner in
 * core first, which is its own change.
 *
 * @phpstan-require-extends Column
 */
trait HasAggregate
{
    /** Aggregate function: 'count', 'sum', 'avg', 'min', 'max'. */
    protected ?string $aggregateFunction = null;

    /** Relation name for the aggregate (e.g. 'orders'). */
    protected ?string $aggregateRelation = null;

    /** Column to aggregate on (e.g. 'total' for a sum); null for a count. */
    protected ?string $aggregateColumn = null;

    /**
     * Count related records.
     *
     * Usage: Column::make('orders_count')->counts('orders')
     */
    public function counts(string $relationship): static
    {
        return $this->aggregate('count', $relationship, null);
    }

    /**
     * Sum a column on related records.
     *
     * Usage: Column::make('orders_total')->sums('orders', 'total')
     */
    public function sums(string $relationship, string $column): static
    {
        return $this->aggregate('sum', $relationship, $column);
    }

    /**
     * Average a column on related records.
     */
    public function averages(string $relationship, string $column): static
    {
        return $this->aggregate('avg', $relationship, $column);
    }

    /**
     * Min of a column on related records.
     */
    public function mins(string $relationship, string $column): static
    {
        return $this->aggregate('min', $relationship, $column);
    }

    /**
     * Max of a column on related records.
     */
    public function maxes(string $relationship, string $column): static
    {
        return $this->aggregate('max', $relationship, $column);
    }

    public function isAggregate(): bool
    {
        return $this->aggregateFunction !== null;
    }

    public function getAggregateFunction(): ?string
    {
        return $this->aggregateFunction;
    }

    public function getAggregateRelation(): ?string
    {
        return $this->aggregateRelation;
    }

    public function getAggregateColumn(): ?string
    {
        return $this->aggregateColumn;
    }

    /**
     * The attribute Eloquent writes the rollup to.
     *
     * withCount('orders') → 'orders_count',
     * withSum('orders', 'total') → 'orders_sum_total'.
     */
    public function getAggregateAttribute(): ?string
    {
        if ($this->aggregateFunction === null || $this->aggregateRelation === null) {
            return null;
        }

        $attribute = "{$this->aggregateRelation}_{$this->aggregateFunction}";

        return $this->aggregateColumn === null
            ? $attribute
            : "{$attribute}_{$this->aggregateColumn}";
    }

    /**
     * The one writer of the aggregate triple.
     *
     * All three fields are assigned on every call, including the null column a
     * count carries: the five setters used to assign only what they needed, so
     * `->sums('orders', 'total')->counts('orders')` left the sum's column behind
     * on a counting column. `getAggregateAttribute()` special-cased `count` and
     * hid it, but `getAggregateColumn()` answered 'total' to anyone who asked.
     */
    protected function aggregate(string $function, string $relation, ?string $column): static
    {
        $this->aggregateFunction = $function;
        $this->aggregateRelation = $relation;
        $this->aggregateColumn = $column;

        return $this;
    }
}
