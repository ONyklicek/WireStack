<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;
use NyonCode\WireCore\Foundation\Components\Component;

/**
 * Canonical owner of the `afterStateUpdated()` reactive hook.
 *
 * Stores a closure that fires after the component's bound state changes and runs
 * it through {@see Component::evaluate()}, so the callback receives the same
 * reactive accessors as `visible()` / `disabled()` (`$get`, `$set`, `$state`)
 * plus the previous value as `$old`.
 *
 * The hook only fires when the field roundtrips to the server, so live components
 * (fields) typically enable `live()` when registering a callback — the wire-forms
 * Field overrides afterStateUpdated() to do so automatically.
 *
 * @phpstan-require-extends Component
 */
trait HasAfterStateUpdated
{
    protected ?Closure $afterStateUpdated = null;

    public function afterStateUpdated(?Closure $callback): static
    {
        $this->afterStateUpdated = $callback;

        return $this;
    }

    public function hasAfterStateUpdated(): bool
    {
        return $this->afterStateUpdated !== null;
    }

    public function runAfterStateUpdated(mixed $old = null): void
    {
        if ($this->afterStateUpdated === null) {
            return;
        }

        $this->evaluate($this->afterStateUpdated, ['old' => $old]);
    }
}
