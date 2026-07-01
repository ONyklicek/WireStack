<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;
use NyonCode\WireCore\Foundation\Support\StateMatcher;

/**
 * Trait HasVisibility
 *
 * Shared visibility, disabled, and read-only state logic.
 * Used across form components, actions, columns, filters, and widgets.
 *
 * Includes HasAuthorization for permission/gate checks on all components.
 */
trait HasVisibility
{
    use HasAuthorization;

    protected bool|Closure $isHidden = false;

    protected bool|Closure $isVisible = true;

    protected bool|Closure $isDisabled = false;

    public function visible(bool|Closure $condition = true): static
    {
        $this->isVisible = $condition;

        return $this;
    }

    public function hidden(bool|Closure $condition = true): static
    {
        $this->isHidden = $condition;

        return $this;
    }

    public function disabled(bool|Closure $condition = true): static
    {
        $this->isDisabled = $condition;

        return $this;
    }

    /**
     * Show this component only when another field equals the given value (or is
     * one of the given values). Resolved reactively against live sibling state
     * on surfaces that expose the `$get` accessor; a no-op keeping the component
     * visible where no state context is available.
     */
    public function visibleWhen(string $field, mixed $value = true): static
    {
        return $this->visible($this->stateCondition($field, $value, whenMissing: true));
    }

    /**
     * Hide this component when another field equals the given value (or is one
     * of the given values). Resolved reactively against live sibling state.
     */
    public function hiddenWhen(string $field, mixed $value = true): static
    {
        return $this->hidden($this->stateCondition($field, $value, whenMissing: false));
    }

    /**
     * Disable this component when another field equals the given value (or is
     * one of the given values). Resolved reactively against live sibling state.
     */
    public function disabledWhen(string $field, mixed $value = true): static
    {
        return $this->disabled($this->stateCondition($field, $value, whenMissing: false));
    }

    /**
     * Build a Closure that compares a sibling field's live value against the
     * expected value/set. `$get` is injected by {@see EvaluatesClosures} only on
     * state-aware components; elsewhere it defaults to null and the condition
     * falls back to $whenMissing.
     */
    private function stateCondition(string $field, mixed $value, bool $whenMissing): Closure
    {
        return static function (?callable $get = null) use ($field, $value, $whenMissing): bool {
            if ($get === null) {
                return $whenMissing;
            }

            return StateMatcher::matches($get($field), $value);
        };
    }

    public function isHidden(): bool
    {
        return (bool) $this->evaluate($this->isHidden);
    }

    public function isVisible(): bool
    {
        if (! $this->isAuthorized()) {
            return false;
        }

        return (bool) $this->evaluate($this->isVisible) && ! $this->isHidden();
    }

    public function isDisabled(): bool
    {
        return (bool) $this->evaluate($this->isDisabled);
    }
}
