<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;

/**
 * Being visible or hidden.
 *
 * Used across form components, actions, columns, filters and widgets. Composes
 * {@see HasAuthorization}, because an unauthorized component is not visible —
 * that is one question, not two.
 *
 * Two capabilities were bundled in here and have moved out:
 *  - {@see CanBeDisabled} — "can this be seen" and "can this be interacted with"
 *    are separate; a table column is hideable and never disableable.
 *  - {@see InteractsWithStateConditions} — visibleWhen()/hiddenWhen() read a
 *    sibling field's live state, which only exists inside a form.
 */
trait HasVisibility
{
    use HasAuthorization;

    protected bool|Closure $isHidden = false;

    protected bool|Closure $isVisible = true;

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
}
