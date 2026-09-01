<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Support;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Actions\BaseAction;
use NyonCode\WireCore\Actions\Contracts\ResolvesActionClick;

/**
 * Click resolver for the buttons an infolist entry or a schema section header
 * renders.
 *
 * A click dispatches to the host's `callInfolistAction()`, which re-resolves the
 * infolist and runs the matching action — read-only surfaces are rebuilt per
 * request, so the button carries a name rather than a closure. A repeatable
 * entry adds the row index, because the same action name appears once per row.
 *
 * It exists so the view does not spell that method name itself. The expression
 * is needed three times in one button — `wire:click`, the `wire:target` that
 * gates the disabled state, and the spinner's own target — and when the view
 * built it inline it was written once and the other two were written as the bare
 * method name. A bare name matches *any* call to it, so one click disabled and
 * spun every infolist button on the page; on a repeatable entry, that is every
 * row at once. One owner for the expression is what makes the three agree.
 */
final readonly class InfolistActionClickResolver implements ResolvesActionClick
{
    public function __construct(private ?int $rowKey = null) {}

    public function clickHandler(BaseAction $action, ?Model $record): string
    {
        $arguments = "'".addslashes($action->getName())."'";

        if ($this->rowKey !== null) {
            $arguments .= ', '.$this->rowKey;
        }

        return "callInfolistAction({$arguments})";
    }
}
