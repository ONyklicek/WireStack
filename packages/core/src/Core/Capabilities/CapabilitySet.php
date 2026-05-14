<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Capabilities;

/**
 * Immutable set of capabilities for a component.
 */
final readonly class CapabilitySet
{
    /** @var array<string, Capability> */
    private array $capabilities;

    public function __construct(Capability ...$capabilities)
    {
        $indexed = [];
        foreach ($capabilities as $capability) {
            $indexed[$capability->value] = $capability;
        }
        $this->capabilities = $indexed;
    }

    public function has(Capability $capability): bool
    {
        return isset($this->capabilities[$capability->value]);
    }

    public function add(Capability ...$capabilities): self
    {
        $merged = $this->capabilities;
        foreach ($capabilities as $capability) {
            $merged[$capability->value] = $capability;
        }

        return new self(...array_values($merged));
    }

    public function remove(Capability ...$capabilities): self
    {
        $filtered = $this->capabilities;
        foreach ($capabilities as $capability) {
            unset($filtered[$capability->value]);
        }

        return new self(...array_values($filtered));
    }

    /**
     * @return array<int, Capability>
     */
    public function all(): array
    {
        return array_values($this->capabilities);
    }

    public function isEmpty(): bool
    {
        return $this->capabilities === [];
    }

    public function count(): int
    {
        return count($this->capabilities);
    }

    public function isSearchable(): bool
    {
        return $this->has(Capability::Searchable);
    }

    public function isSortable(): bool
    {
        return $this->has(Capability::Sortable);
    }

    public function isFilterable(): bool
    {
        return $this->has(Capability::Filterable);
    }

    public function isEditable(): bool
    {
        return $this->has(Capability::Editable);
    }

    public function isRuntimeOnly(): bool
    {
        return $this->has(Capability::RuntimeOnly);
    }

    public function hasSqlExpression(): bool
    {
        return $this->has(Capability::SqlExpression);
    }
}
