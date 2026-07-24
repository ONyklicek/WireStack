<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

/**
 * The boolean() display mode shared by icon-driven surfaces.
 *
 * A surface in boolean mode derives its icon and colour from the truthiness of
 * the state — a truthy state shows the true icon/colour, a falsy state the false
 * one — before any author-supplied map is consulted. This trait owns that mode's
 * state (the flag plus the four true/false defaults) and its resolving logic.
 *
 * The consuming class keeps a one-line {@see InteractsWithStateIcon::resolveStateIconOverride()}
 * / {@see InteractsWithStateColor::resolveStateColorOverride()} override that
 * delegates here: those hooks already carry a default no-op body in the state
 * traits, so a same-named method here would collide. Delegation keeps one owner
 * for the truthiness rule without a trait-method conflict, and leaves each
 * surface free to expose its own fluent setters (which differ in signature).
 */
trait InteractsWithBooleanState
{
    protected bool $boolean = false;

    protected string $trueIcon = 'check-circle';

    protected string $falseIcon = 'x-circle';

    protected string $trueColor = 'success';

    protected string $falseColor = 'danger';

    /** In boolean() mode the icon is the truthiness of the state; otherwise defer. */
    protected function booleanStateIcon(mixed $state): ?string
    {
        if (! $this->boolean) {
            return null;
        }

        return $state ? $this->trueIcon : $this->falseIcon;
    }

    /** In boolean() mode the colour is the truthiness of the state; otherwise defer. */
    protected function booleanStateColor(mixed $state): ?string
    {
        if (! $this->boolean) {
            return null;
        }

        return $state ? $this->trueColor : $this->falseColor;
    }
}
