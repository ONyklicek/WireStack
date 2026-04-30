<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

/**
 * Tracks which Livewire component owns this component instance.
 */
trait BelongsToComponent
{
    protected mixed $livewire = null;

    public function livewire(mixed $livewire): static
    {
        $this->livewire = $livewire;

        return $this;
    }

    public function getLivewire(): mixed
    {
        return $this->livewire;
    }
}
