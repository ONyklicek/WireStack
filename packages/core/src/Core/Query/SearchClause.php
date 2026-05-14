<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query;

/**
 * Immutable representation of a search clause in a query plan.
 *
 * Each clause maps a search term to a specific column (possibly through a relation join).
 */
final readonly class SearchClause
{
    /**
     * @param  string  $column  The column or SQL expression to search against
     * @param  string|null  $tableAlias  Table alias if through a join (null = base table)
     * @param  string|null  $sqlExpression  Raw SQL expression (if accessor with SQL mapping)
     * @param  bool  $isRelation  Whether this search goes through a relation
     * @param  string|null  $relationPath  Dot-notation relation path (for eager-loaded relations)
     */
    public function __construct(
        public string $column,
        public ?string $tableAlias = null,
        public ?string $sqlExpression = null,
        public bool $isRelation = false,
        public ?string $relationPath = null,
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
