<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Concerns;

use Closure;
use NyonCode\WireCore\Foundation\Concerns\HasAuthorization;

/**
 * Trait HasVisibility
 *
 * Shared visibility, permission, and disabled state logic for all Action types.
 * Authorization is delegated to HasAuthorization trait.
 */
trait HasVisibility
{
    use HasAuthorization;

    /**
     * Provided by {@see HasDynamicProperties}. Returns whether a dynamic callback
     * should run for the given context: always when a context is supplied, and
     * for record-less closures (`fn () => …`) even without one. Record-scoped
     * closures without a context fall back to the static value instead of erroring.
     */
    abstract protected function shouldInvokeDynamicCallback(?Closure $callback, mixed $context): bool;

    protected bool $hidden = false;

    protected ?Closure $hiddenCallback = null;

    /**
     * True when {@see $hiddenCallback} came from {@see visible()} and its result
     * must be inverted. Tracked separately instead of wrapping the closure so the
     * original callback's arity survives — a record-aware `visible(fn ($record))`
     * can then degrade gracefully when resolved without a record (see
     * {@see isHidden()}) instead of erroring on a missing argument.
     */
    protected bool $hiddenCallbackInverts = false;

    protected bool $disabled = false;

    protected ?Closure $disabledCallback = null;

    /** Show the action only when the condition is true (a bool or a `$record`-aware Closure). */
    public function visible(bool|Closure $visible = true): static
    {
        if ($visible instanceof Closure) {
            $this->hiddenCallback = $visible;
            $this->hiddenCallbackInverts = true;

            return $this;
        }

        return $this->hidden(! $visible);
    }

    /** Hide the action when the condition is true — the inverse of `visible()`. */
    public function hidden(bool|Closure $hidden = true): static
    {
        if ($hidden instanceof Closure) {
            $this->hiddenCallback = $hidden;
            $this->hiddenCallbackInverts = false;
        } else {
            // A literal state supersedes any previously registered closure so the
            // last call wins predictably (e.g. `->visible($cb)->hidden(false)`).
            $this->hidden = $hidden;
            $this->hiddenCallback = null;
            $this->hiddenCallbackInverts = false;
        }

        return $this;
    }

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

    public function isHidden(mixed $context = null): bool
    {
        // Record-required closures resolved without a record fall back to the
        // static default instead of erroring — mirrors HasDynamicProperties so a
        // `visible(fn ($record))` action never fatals when a view checks it bare.
        if ($this->shouldInvokeDynamicCallback($this->hiddenCallback, $context)) {
            $result = (bool) ($this->hiddenCallback)($context);

            return $this->hiddenCallbackInverts ? ! $result : $result;
        }

        return $this->hidden;
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
