<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Concerns;

use NyonCode\WireCore\Foundation\Components\Component;
use NyonCode\WireCore\Foundation\Concerns\InteractsWithState;

/**
 * Supplies Filament-style reactive state accessors to evaluated Closures.
 *
 * Delegates the accessor wiring to the canonical Core
 * {@see InteractsWithState} owner and layers on the field-specific own-value
 * semantics so dynamic field callbacks — `visible()`, `disabled()`,
 * `hidden()`, `afterStateUpdated()` — can read sibling state via `$get`, write
 * via `$set`, and read their own value, all resolved against the bound Livewire
 * component's live state bag.
 *
 * Two ways to read the field's own value:
 *  - `$get()` (no path) resolves the field's own value live, every call — use it
 *    when later reads must reflect a `$set` made earlier in the same closure.
 *  - `$state` is a snapshot taken when the closure is invoked (Filament-style
 *    convenience); it does not reflect a `$set` performed inside that same call.
 *
 * Requires the host to expose getLivewire() (BelongsToComponent), getStatePath()
 * and a $statePath bag prefix (HasState).
 *
 * @phpstan-require-extends Component
 */
trait InteractsWithFormState
{
    use InteractsWithState;

    /**
     * Fields resolve sibling state against their bound state-path prefix.
     */
    protected function resolveStateBagRoot(): ?string
    {
        return $this->statePath;
    }

    /**
     * The field's own current value, taken as the `$state` snapshot.
     */
    protected function resolveOwnState(mixed $livewire): mixed
    {
        return $livewire !== null
            ? data_get($livewire, $this->getStatePath())
            : null;
    }
}
