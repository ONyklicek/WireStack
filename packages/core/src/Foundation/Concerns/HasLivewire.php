<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Livewire\Component;

/**
 * Alias for BelongsToComponent – provides Livewire component reference.
 * Use this when a class needs to hold a reference to its owning Livewire component.
 *
 * @phpstan-require-extends Component
 */
trait HasLivewire
{
    use BelongsToComponent;
}
