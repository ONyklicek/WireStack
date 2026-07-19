<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Contracts;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Actions\BaseAction;

/**
 * Strategy that resolves the Livewire click expression for a rendered action.
 *
 * The action render views are owned by wire-core and therefore must not hardcode
 * any host's Livewire method names. The host (a table, a standalone form) supplies
 * a resolver that maps an action + record to the bare wire:click expression the
 * button invokes. Modifiers (`.debounce`) are appended by the view, and the same
 * bare expression is reused as the `wire:loading` target, so this returns the
 * expression only.
 */
interface ResolvesActionClick
{
    /**
     * The bare Livewire expression a rendered action invokes on click.
     */
    public function clickHandler(BaseAction $action, ?Model $record): string;
}
