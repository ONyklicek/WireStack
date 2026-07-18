<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

/**
 * State path management for form components (wire:model bindings).
 */
trait HasState
{
    protected ?string $statePath = null;

    /** Set the state key this component binds to (under the form's state path). */
    public function statePath(?string $path): static
    {
        $this->statePath = $path;

        return $this;
    }

    public function getStatePath(): string
    {
        if ($this->statePath) {
            return "{$this->statePath}.{$this->getName()}";
        }

        return $this->getName();
    }

    public function getWireModelAttribute(): string
    {
        return $this->getStatePath();
    }
}
