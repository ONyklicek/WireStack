<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Concerns;

/**
 * Binds a field's options to an Eloquent relationship.
 *
 * Shared by choice fields sourced from a relation (Select, Tags). Pure
 * configuration — each field keeps its own query/label resolution; this
 * trait only owns the relationship name and its title attribute.
 */
trait HasRelationship
{
    protected ?string $relationship = null;

    protected ?string $titleAttribute = null;

    /** Source the options from an Eloquent relationship, labelled by `$titleAttribute`. */
    public function relationship(?string $name, ?string $titleAttribute = null): static
    {
        $this->relationship = $name;
        $this->titleAttribute = $titleAttribute;

        return $this;
    }

    public function getRelationship(): ?string
    {
        return $this->relationship;
    }

    public function getTitleAttribute(): ?string
    {
        return $this->titleAttribute;
    }
}
