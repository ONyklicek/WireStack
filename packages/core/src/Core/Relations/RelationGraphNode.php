<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Relations;

/**
 * A node in the relation graph.
 */
final readonly class RelationGraphNode
{
    /**
     * @param  array<string, RelationGraphNode>  $children
     * @param  array<int, string>  $columns  Columns accessed on this relation
     */
    public function __construct(
        public string $name,
        public bool $isMorph = false,
        public bool $isToMany = false,
        public bool $requiresEagerLoad = false,
        public array $children = [],
        public array $columns = [],
    ) {}

    public function hasChildren(): bool
    {
        return $this->children !== [];
    }

    public function getChild(string $name): ?self
    {
        return $this->children[$name] ?? null;
    }

    public function withChild(self $child): self
    {
        $children = $this->children;
        $children[$child->name] = $child;

        return new self(
            name: $this->name,
            isMorph: $this->isMorph,
            isToMany: $this->isToMany,
            requiresEagerLoad: $this->requiresEagerLoad,
            children: $children,
            columns: $this->columns,
        );
    }

    public function withColumn(string $column): self
    {
        if (in_array($column, $this->columns, true)) {
            return $this;
        }

        return new self(
            name: $this->name,
            isMorph: $this->isMorph,
            isToMany: $this->isToMany,
            requiresEagerLoad: $this->requiresEagerLoad,
            children: $this->children,
            columns: [...$this->columns, $column],
        );
    }
}
