<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

/**
 * Alias for BelongsToComponent – provides Livewire component reference.
 * Use this when a class needs to hold a reference to its owning Livewire component.
 */
trait HasLivewire
{
    use BelongsToComponent;
}
