<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Relations;

/**
 * Immutable graph of relations — merged from multiple RelationPaths.
 *
 * Each node represents a relation with its children.
 */
final readonly class RelationGraph
{
    /**
     * @param  array<string, RelationGraphNode>  $roots  Top-level relations keyed by name
     */
    public function __construct(
        public array $roots = [],
    ) {}

    public function hasRelation(string $name): bool
    {
        return isset($this->roots[$name]);
    }

    public function getNode(string $name): ?RelationGraphNode
    {
        return $this->roots[$name] ?? null;
    }

    /**
     * Get all unique relation paths for eager loading.
     *
     * @return array<int, string>
     */
    public function getEagerLoadPaths(): array
    {
        $paths = [];
        foreach ($this->roots as $node) {
            $this->collectPaths($node, '', $paths);
        }

        return $paths;
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function collectPaths(RelationGraphNode $node, string $prefix, array &$paths): void
    {
        $currentPath = $prefix !== '' ? "{$prefix}.{$node->name}" : $node->name;

        if ($node->requiresEagerLoad) {
            $paths[] = $currentPath;
        }

        foreach ($node->children as $child) {
            $this->collectPaths($child, $currentPath, $paths);
        }
    }

    /**
     * Get all leaf relation paths (deepest level).
     *
     * @return array<int, string>
     */
    public function getAllPaths(): array
    {
        $paths = [];
        foreach ($this->roots as $node) {
            $this->collectAllPaths($node, '', $paths);
        }

        return $paths;
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function collectAllPaths(RelationGraphNode $node, string $prefix, array &$paths): void
    {
        $currentPath = $prefix !== '' ? "{$prefix}.{$node->name}" : $node->name;
        $paths[] = $currentPath;

        foreach ($node->children as $child) {
            $this->collectAllPaths($child, $currentPath, $paths);
        }
    }

    public function isEmpty(): bool
    {
        return $this->roots === [];
    }

    public function nodeCount(): int
    {
        $count = 0;
        foreach ($this->roots as $node) {
            $count += $this->countNodes($node);
        }

        return $count;
    }

    private function countNodes(RelationGraphNode $node): int
    {
        $count = 1;
        foreach ($node->children as $child) {
            $count += $this->countNodes($child);
        }

        return $count;
    }
}
