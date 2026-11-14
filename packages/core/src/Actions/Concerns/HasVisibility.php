<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Concerns;

use Closure;
use NyonCode\WireCore\Foundation\Concerns\HasAuthorization;
use NyonCode\WireCore\Foundation\Concerns\HasContextualVisibility;

/**
 * Trait HasVisibility
 *
 * Shared visibility, permission, and disabled state logic for all Action types.
 * Visibility itself is {@see HasContextualVisibility}'s, called with the record.
 * Authorization is delegated to HasAuthorization trait.
 */
trait HasVisibility
{
    use HasAuthorization;

    // visible() / hidden() / isHidden($record): shared with table filters.
    use HasContextualVisibility;

    /**
     * Provided by {@see HasDynamicProperties}. Returns whether a dynamic callback
     * should run for the given context: always when a context is supplied, and
     * for record-less closures (`fn () => …`) even without one. Record-scoped
     * closures without a context fall back to the static value instead of erroring.
     */
    abstract protected function shouldInvokeDynamicCallback(?Closure $callback, mixed $context): bool;

    protected bool $disabled = false;

    protected ?Closure $disabledCallback = null;

    /** Disable the action — shown but non-interactive (a bool or a `$record`-aware Closure). */
    public function disabled(bool|Closure $disabled = true): static
    {
        if ($disabled instanceof Closure) {
            $this->disabledCallback = $disabled;
        } else {
            $this->disabled = $disabled;
            $this->disabledCallback = null;
        }

        return $this;
    }

    public function isDisabled(mixed $context = null): bool
    {
        if ($this->shouldInvokeDynamicCallback($this->disabledCallback, $context)) {
            return (bool) ($this->disabledCallback)($context);
        }

        return $this->disabled;
    }

    public function canExecute(mixed $context = null): bool
    {
        if ($this->isHidden($context)) {
            return false;
        }

        return $this->isAuthorized($context);
    }
}
