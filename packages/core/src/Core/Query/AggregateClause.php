<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query;

use InvalidArgumentException;

/**
 * Immutable representation of an aggregate clause in a query plan.
 *
 * Supports: withCount, withSum, withAvg, withMin, withMax, withExists.
 * Strategy: subquery (default for aggregates) or join.
 */
final readonly class AggregateClause
{
    private const VALID_FUNCTIONS = ['count', 'sum', 'avg', 'min', 'max', 'exists'];

    private const VALID_STRATEGIES = ['subquery', 'join', 'exists'];

    /**
     * @param  string  $relation  The relation name
     * @param  string  $function  Aggregate function (count, sum, avg, min, max, exists)
     * @param  string|null  $column  Column to aggregate (null for count/exists)
     * @param  string  $alias  Result alias in the query
     * @param  string  $strategy  Execution strategy (subquery, join, exists)
     * @param  array<int, FilterClause>  $constraints  Additional constraints on the aggregate
     */
    public function __construct(
        public string $relation,
        public string $function,
        public ?string $column = null,
        public ?string $alias = null,
        public string $strategy = 'subquery',
        public array $constraints = [],
    ) {
        if (! in_array($this->function, self::VALID_FUNCTIONS, true)) {
            throw new InvalidArgumentException(
                "Invalid aggregate function [{$this->function}]. Valid: ".implode(', ', self::VALID_FUNCTIONS)
            );
        }

        if (! in_array($this->strategy, self::VALID_STRATEGIES, true)) {
            throw new InvalidArgumentException(
                "Invalid aggregate strategy [{$this->strategy}]. Valid: ".implode(', ', self::VALID_STRATEGIES)
            );
        }

        if (in_array($this->function, ['sum', 'avg', 'min', 'max'], true) && $this->column === null) {
            throw new InvalidArgumentException(
                "Aggregate function [{$this->function}] requires a column."
            );
        }
    }

    /**
     * Get the resolved alias for this aggregate.
     */
    public function getAlias(): string
    {
        if ($this->alias !== null) {
            return $this->alias;
        }

        $alias = "{$this->relation}_{$this->function}";
        if ($this->column !== null) {
            $alias .= "_{$this->column}";
        }

        return $alias;
    }

    /**
     * Determine the optimal strategy for this aggregate.
     */
    public static function resolveStrategy(string $function, bool $isToMany): string
    {
        if ($function === 'exists') {
            return 'exists';
        }

        // Subquery is safer and more predictable for to-many aggregates
        return 'subquery';
    }
}
