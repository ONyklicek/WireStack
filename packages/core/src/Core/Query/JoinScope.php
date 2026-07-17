<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Metadata\RelationMetadata;
use NyonCode\WireCore\Core\Query\Pipes\ApplyRelations;

/**
 * How the right-hand side of a relation join should be constrained so it matches
 * what Eloquent's own relation query would return.
 *
 * A join whose {@see JoinClause::$scope} is set is built as a subquery instead of
 * a direct table, so the constraints live inside the derived table (the LEFT JOIN
 * stays a LEFT JOIN). Two shapes, resolved by {@see ApplyRelations}:
 *
 *  - `model` only: the joined model's global scopes, via `{model}::query()`.
 *    Used for the hops of a `hasOneThrough`, where there is no single relation
 *    object to rebuild.
 *  - `model` + `relationParent` + `relationMethod`: the relation's own
 *    constraint-free query, via `Relation::noConstraints()`, which carries global
 *    scopes *and* constraints declared on the relation method (e.g.
 *    `->where('active', true)`) while dropping the parent-key binding. Used for
 *    `belongsTo` / `hasOne`. `model` (the target class) is kept as a fallback.
 *
 * This is pure data (scalar class-strings / method names) so it stays cacheable
 * inside {@see RelationMetadata}. `model` is always the joined target model.
 */
final readonly class JoinScope
{
    /**
     * @param  class-string<Model>  $model
     * @param  class-string<Model>|null  $relationParent
     */
    public function __construct(
        public string $model,
        public ?string $relationParent = null,
        public ?string $relationMethod = null,
    ) {}
}
