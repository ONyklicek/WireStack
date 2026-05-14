<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query;

use NyonCode\WireCore\Core\Relations\RelationPath;

/**
 * Input definition for a filter to be planned.
 *
 * This is the input to the QueryPlanner, not the output.
 * The planner converts this to FilterClause (with resolved joins/aliases).
 */
final readonly class FilterDefinition
{
    public function __construct(
        public string $column,
        public string $operator = '=',
        public mixed $value = null,
        public ?RelationPath $relationPath = null,
        public ?string $sqlExpression = null,
    ) {}

    public static function make(
        string $column,
        string $operator = '=',
        mixed $value = null,
        ?string $sqlExpression = null,
    ): self {
        $relationPath = null;
        $colName = $column;

        if (str_contains($column, '.') || str_contains($column, '->')) {
            $relationPath = RelationPath::parse($column);
            $colName = $relationPath->getColumnName();
        }

        return new self(
            column: $colName,
            operator: $operator,
            value: $value,
            relationPath: $relationPath,
            sqlExpression: $sqlExpression,
        );
    }
}
