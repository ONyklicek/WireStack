<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query;

use NyonCode\WireCore\Core\Support\SqlSafety;

/**
 * Immutable representation of a sort clause in a query plan.
 */
final readonly class SortClause
{
    /** Sort direction, normalised to a safe `asc`/`desc` keyword. */
    public string $direction;

    /** NULLS position, normalised to a safe `FIRST`/`LAST` keyword or null. */
    public ?string $nullsPosition;

    /**
     * @param  string  $column  The column or SQL expression to sort by
     * @param  string  $direction  Sort direction (asc/desc)
     * @param  string|null  $tableAlias  Table alias if through a join
     * @param  string|null  $sqlExpression  Raw SQL expression
     * @param  bool  $isRelation  Whether this sort goes through a relation
     * @param  string|null  $nullsPosition  NULLS FIRST or NULLS LAST (null = DB default)
     */
    public function __construct(
        public string $column,
        string $direction = 'asc',
        public ?string $tableAlias = null,
        public ?string $sqlExpression = null,
        public bool $isRelation = false,
        ?string $nullsPosition = null,
    ) {
        // Direction and NULLS position are interpolated into orderByRaw for SQL-expression
        // and NULLS sorts, so they are normalised against SqlSafety's fixed keyword allow-list
        // — the canonical owner of SQL keyword/identifier safety. Any untrusted value collapses
        // to a safe default. ApplySorting prepends "NULLS", so the bare keyword is stored.
        $this->direction = SqlSafety::normalizeDirection($direction);
        $this->nullsPosition = SqlSafety::normalizeNullsPosition($nullsPosition);
    }

    /**
     * Get the fully qualified column reference for SQL.
     */
    public function getQualifiedColumn(): string
    {
        if ($this->sqlExpression !== null) {
            return $this->sqlExpression;
        }

        if ($this->tableAlias !== null) {
            return "{$this->tableAlias}.{$this->column}";
        }

        return $this->column;
    }
}
