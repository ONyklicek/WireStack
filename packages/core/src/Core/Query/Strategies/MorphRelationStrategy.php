<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Strategies;

use NyonCode\WireCore\Core\Metadata\MetadataRegistry;
use NyonCode\WireCore\Core\Query\AggregateClause;
use NyonCode\WireCore\Core\Relations\RelationPath;

/**
 * Dedicated strategy for planning morph relation queries.
 *
 * Morph relations CANNOT be joined — they must be:
 * - Eager loaded for display
 * - Handled via subqueries/EXISTS for filtering
 * - Handled via subqueries for aggregation
 */
final readonly class MorphRelationStrategy
{
    public function __construct(
        private MetadataRegistry $metadataRegistry,
    ) {}

    /**
     * Determine if a relation path contains a morph relation.
     */
    public function isMorphPath(RelationPath $path, string $modelClass): bool
    {
        foreach ($path->getRelationSegments() as $segment) {
            $relation = $this->metadataRegistry->getRelation($modelClass, $segment->getName());
            if ($relation !== null && $relation->isMorph) {
                return true;
            }

            // Walk to the next model for nested paths
            if ($relation !== null && $relation->relatedModel !== null) {
                $modelClass = $relation->relatedModel;
            }
        }

        return false;
    }

    /**
     * Get eager load paths for a morph relation.
     *
     * @return array<int, string>
     */
    public function getEagerLoadPaths(RelationPath $path): array
    {
        $relationPath = $path->getRelationPath();
        if ($relationPath === null) {
            return [];
        }

        return [$relationPath];
    }

    /**
     * Plan an aggregate on a morph relation.
     *
     * Morph aggregates always use subquery strategy (never join).
     */
    public function planAggregate(
        string $relation,
        string $function,
        ?string $column = null,
    ): AggregateClause {
        return new AggregateClause(
            relation: $relation,
            function: $function,
            column: $column,
            strategy: $function === 'exists' ? 'exists' : 'subquery',
        );
    }

    /**
     * Check if a specific relation requires morph handling.
     */
    public function requiresMorphHandling(string $modelClass, string $relationName): bool
    {
        $relation = $this->metadataRegistry->getRelation($modelClass, $relationName);

        return $relation !== null && $relation->isMorph;
    }

    /**
     * Get the morph type column for a morph relation.
     */
    public function getMorphType(string $modelClass, string $relationName): ?string
    {
        $relation = $this->metadataRegistry->getRelation($modelClass, $relationName);
        if ($relation === null || ! $relation->isMorph) {
            return null;
        }

        return $relation->morphType;
    }
}
