<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Components;

use Closure;
use NyonCode\WireCore\Core\Capabilities\Capability;
use NyonCode\WireCore\Core\Capabilities\CapabilitySet;
use NyonCode\WireCore\Core\Metadata\ColumnMetadata;
use NyonCode\WireCore\Core\Relations\RelationPath;
use NyonCode\WireCore\Foundation\Support\EvaluatesClosures;

/**
 * Shared base class for data-aware components (Columns and Fields).
 *
 * Provides: name, relation path, capabilities, metadata, state resolution.
 * Column extends this (display mode), Field extends this (input mode).
 *
 * @phpstan-consistent-constructor
 */
abstract class DataComponent
{
    use EvaluatesClosures;

    protected string $name;

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

    public function getName(): string
    {
        return $this->name;
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

    public function capabilities(CapabilitySet $capabilities): static
    {
        $this->capabilities = $capabilities;

        return $this;
    }

    public function addCapability(Capability ...$capabilities): static
    {
        $this->capabilities = $this->capabilities->add(...$capabilities);

        return $this;
    }

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
