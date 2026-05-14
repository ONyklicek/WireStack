<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Components;

/**
 * Shared behavior for components that work with relation data.
 */
class RelationComponent extends DataComponent
{
    protected ?string $displayColumn = null;

    protected ?string $valueColumn = null;

    public function displayColumn(string $column): static
    {
        $this->displayColumn = $column;

        return $this;
    }

    public function getDisplayColumn(): ?string
    {
        return $this->displayColumn;
    }

    public function valueColumn(string $column): static
    {
        $this->valueColumn = $column;

        return $this;
    }

    public function getValueColumn(): ?string
    {
        return $this->valueColumn;
    }

    public function getRelationDepth(): int
    {
        return $this->relationPath?->depth() ?? 0;
    }
}
