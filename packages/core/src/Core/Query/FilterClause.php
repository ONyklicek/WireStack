<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query;

/**
 * Immutable representation of a filter clause in a query plan.
 */
final readonly class FilterClause
{
    /**
     * @param  string  $column  The column or SQL expression to filter
     * @param  string  $operator  SQL operator (=, !=, >, <, >=, <=, LIKE, IN, NOT IN, BETWEEN, IS NULL, IS NOT NULL)
     * @param  mixed  $value  The filter value(s)
     * @param  string|null  $tableAlias  Table alias if through a join
     * @param  string|null  $sqlExpression  Raw SQL expression
     * @param  bool  $isRelation  Whether this filter goes through a relation
     * @param  string|null  $relationPath  Dot-notation relation path
     * @param  string  $boolean  Boolean connector (and/or)
     */
    public function __construct(
        public string $column,
        public string $operator = '=',
        public mixed $value = null,
        public ?string $tableAlias = null,
        public ?string $sqlExpression = null,
        public bool $isRelation = false,
        public ?string $relationPath = null,
        public string $boolean = 'and',
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

    public function isNullCheck(): bool
    {
        return in_array($this->operator, ['IS NULL', 'IS NOT NULL'], true);
    }
}
