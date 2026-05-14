<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query;

/**
 * Immutable representation of a sort clause in a query plan.
 */
final readonly class SortClause
{
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
        public string $direction = 'asc',
        public ?string $tableAlias = null,
        public ?string $sqlExpression = null,
        public bool $isRelation = false,
        public ?string $nullsPosition = null,
    ) {}

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
