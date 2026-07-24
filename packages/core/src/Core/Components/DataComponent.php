<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Components;

use Closure;
use NyonCode\WireCore\Core\Capabilities\Capability;
use NyonCode\WireCore\Core\Capabilities\CapabilitySet;
use NyonCode\WireCore\Core\Metadata\ColumnMetadata;
use NyonCode\WireCore\Core\Relations\RelationPath;
use NyonCode\WireCore\Foundation\Concerns\HasName;
use NyonCode\WireCore\Foundation\Support\EvaluatesClosures;

/**
 * Shared base class for data-aware, relation-path-backed components.
 *
 * Provides: name, relation path, capabilities, metadata, state resolution.
 * The table Column extends this, as do the core capability descriptors
 * (Text/Date/Boolean/Relation/Select). Form Fields do not — they extend
 * Foundation\Components\Component, which already composes the same concerns.
 *
 * @phpstan-consistent-constructor
 */
abstract class DataComponent
{
    use EvaluatesClosures;

    // name + getName() delegate to the canonical Foundation owner; the label
    // stays local because its auto-generation is relation-path aware, which
    // Foundation\Concerns\HasLabel (headline of the full dotted name) is not.
    use HasName;

    protected ?RelationPath $relationPath = null;

    protected CapabilitySet $capabilities;

    protected ?ColumnMetadata $columnMetadata = null;

    protected string|Closure|null $label = null;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->capabilities = new CapabilitySet;

        if (str_contains($name, '.') || str_contains($name, '->')) {
            $this->relationPath = RelationPath::parse($name);
        }
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function label(string|Closure $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getLabel(): string
    {
        if ($this->label !== null) {
            return $this->evaluate($this->label);
        }

        // Auto-generate from name: "user.company_name" → "Company name"
        $name = $this->relationPath !== null
            ? $this->relationPath->getColumnName()
            : $this->name;

        return str_replace('_', ' ', ucfirst($name));
    }

    // ── Relation Path ────────────────────────────────────────────────

    public function getRelationPath(): ?RelationPath
    {
        return $this->relationPath;
    }

    public function hasRelation(): bool
    {
        return $this->relationPath !== null && $this->relationPath->hasRelation();
    }

    public function getRelationName(): ?string
    {
        return $this->relationPath?->getRelationPath();
    }

    public function getColumnName(): string
    {
        return $this->relationPath !== null
            ? $this->relationPath->getColumnName()
            : $this->name;
    }

    // ── Capabilities ─────────────────────────────────────────────────

    /** Replace the component's capability set wholesale. */
    public function capabilities(CapabilitySet $capabilities): static
    {
        $this->capabilities = $capabilities;

        return $this;
    }

    /** Add one or more capabilities to the component. */
    public function addCapability(Capability ...$capabilities): static
    {
        $this->capabilities = $this->capabilities->add(...$capabilities);

        return $this;
    }

    /** Remove one or more capabilities from the component. */
    public function removeCapability(Capability ...$capabilities): static
    {
        $this->capabilities = $this->capabilities->remove(...$capabilities);

        return $this;
    }

    public function getCapabilities(): CapabilitySet
    {
        return $this->capabilities;
    }

    public function hasCapability(Capability $capability): bool
    {
        return $this->capabilities->has($capability);
    }

    // ── Metadata ─────────────────────────────────────────────────────

    /** Attach resolved SQL column metadata for this component. */
    public function columnMetadata(ColumnMetadata $metadata): static
    {
        $this->columnMetadata = $metadata;

        return $this;
    }

    public function getColumnMetadata(): ?ColumnMetadata
    {
        return $this->columnMetadata;
    }

    // ── SQL ──────────────────────────────────────────────────────────

    public function isSqlCompatible(): bool
    {
        if ($this->columnMetadata !== null) {
            return $this->columnMetadata->isSqlCompatible();
        }

        return ! $this->capabilities->isRuntimeOnly();
    }
}
