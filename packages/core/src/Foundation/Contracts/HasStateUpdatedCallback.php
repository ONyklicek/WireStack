<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Contracts;

use Closure;

/**
 * Marks a component that runs a callback when its bound state changes.
 *
 * Hosts (form components, table action modals) detect the changed wire:model
 * path and invoke {@see runAfterStateUpdated()} on the matching component, which
 * evaluates the registered closure with the reactive state accessors
 * (`$get` / `$set` / `$state`) plus `$old` (the previous value) and `$component`.
 */
interface HasStateUpdatedCallback
{
    public function afterStateUpdated(?Closure $callback): static;

    public function hasAfterStateUpdated(): bool;

    public function runAfterStateUpdated(mixed $old = null): void;
}
