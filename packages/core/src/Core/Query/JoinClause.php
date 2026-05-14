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
    ) {}
}
