<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;

/**
 * Whether a widget-backed input accepts typing, or only the widget behind it.
 *
 * The canonical owner of the "keyboard, or picker only?" choice for any surface
 * that shows a *formatted* value over state the widget owns — today the two
 * date/time pickers, whose trigger reads `j. n. Y H:i` over a `Y-m-d\TH:i`
 * state, so a typed string has to be parsed back before it can be stored.
 *
 * Typing is the default. A trigger nobody can type into turns a far-off date
 * into a long walk through the month arrows, and a time that does not sit on the
 * slot interval into something the user cannot express at all. `typeable(false)`
 * opts a single surface back out where the value really must come from the
 * widget.
 *
 * Distinct from {@see CanBeReadOnly}, which forbids *every* edit, the picker
 * included. This one closes only the keyboard route and leaves the widget open.
 */
trait CanBeTyped
{
    protected bool|Closure $isTypeable = true;

    /** Let the value be typed into the input as well as picked (a bool or a `$get`-aware Closure). */
    public function typeable(bool|Closure $condition = true): static
    {
        $this->isTypeable = $condition;

        return $this;
    }

    public function isTypeable(): bool
    {
        return (bool) $this->evaluate($this->isTypeable);
    }
}
