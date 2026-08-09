<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Actions;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Actions\ActionGroup;
use NyonCode\WireCore\Actions\BaseAction;
use NyonCode\WireCore\Actions\Contracts\ResolvesActionClick;

/**
 * Click resolver for the table's record-less actions.
 *
 * The toolbar's header actions run through the host's record-less methods rather
 * than the row pipeline, which would carry an empty record key. A modal action
 * opens the action modal; everything else executes directly.
 *
 * The header-action *button* view can hardcode those method names (wire-table
 * owns it), but the shared menu-item view is core's, so a header action folded
 * into an {@see ActionGroup} needs the mapping handed
 * to it through this strategy.
 */
class HeaderActionClickResolver implements ResolvesActionClick
{
    public function clickHandler(BaseAction $action, ?Model $record): string
    {
        $name = $action->getName();

        return $action->hasModal()
            ? "openHeaderActionModal('{$name}')"
            : "executeHeaderAction('{$name}')";
    }
}
