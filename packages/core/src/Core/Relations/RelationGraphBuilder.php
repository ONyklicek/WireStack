<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Relations;

/**
 * Builds a RelationGraph from a collection of RelationPaths.
 *
 * Merges duplicate paths and deduplicates nodes.
 */
final class RelationGraphBuilder
{
    /** @var array<string, RelationGraphNode> */
    private array $roots = [];

    public function addPath(RelationPath $path): self
    {
        if (! $path->hasRelation()) {
            return $this;
        }

        $relationSegments = $path->getRelationSegments();
        if ($relationSegments === []) {
            return $this;
        }

        $terminal = $path->getTerminal();
        $columnName = $terminal instanceof ColumnSegment ? $terminal->getName() : null;

        $this->mergeSegments($this->roots, $relationSegments, 0, $columnName);

        return $this;
    }

    /**
     * @param  array<string, RelationGraphNode>  $nodes
     * @param  array<int, RelationSegment|MorphSegment>  $segments
     */
    private function mergeSegments(array &$nodes, array $segments, int $index, ?string $columnName): void
    {
        if (! isset($segments[$index])) {
            return;
        }

        $segment = $segments[$index];
        $name = $segment->getName();
        $isLast = $index === count($segments) - 1;
        $isMorph = $segment instanceof MorphSegment;

        if (! isset($nodes[$name])) {
            $nodes[$name] = new RelationGraphNode(
                name: $name,
                isMorph: $isMorph,
                requiresEagerLoad: $isMorph,
            );
        }

        // Add column to the last relation node
        if ($isLast && $columnName !== null) {
            $nodes[$name] = $nodes[$name]->withColumn($columnName);
        }

        // Recurse into children
        if (! $isLast) {
            $children = $nodes[$name]->children;
            $this->mergeSegments($children, $segments, $index + 1, $columnName);

            $nodes[$name] = new RelationGraphNode(
                name: $nodes[$name]->name,
                isMorph: $nodes[$name]->isMorph,
                isToMany: $nodes[$name]->isToMany,
                requiresEagerLoad: $nodes[$name]->requiresEagerLoad,
                children: $children,
                columns: $nodes[$name]->columns,
            );
        }
    }

    public function build(): RelationGraph
    {
        return new RelationGraph($this->roots);
    }

    public function reset(): self
    {
        $this->roots = [];

        return $this;
    }
}
