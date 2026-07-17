<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query;

/**
 * Immutable representation of a join clause.
 */
final readonly class JoinClause
{
    public function __construct(
        public string $table,
        public string $alias,
        public string $firstColumn,
        public string $operator,
        public string $secondColumn,
        public string $type = 'left',
        // When set, the join is built against a scoped subquery instead of the
        // bare table, so the joined model's global scopes / relation constraints
        // apply — while the LEFT JOIN stays a LEFT JOIN (a parent with no
        // surviving related row keeps NULLs). Null means a plain direct-table join.
        public ?JoinScope $scope = null,
    ) {}
}
