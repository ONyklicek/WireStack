<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Actions;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Actions\BaseAction;
use NyonCode\WireCore\Actions\Contracts\ResolvesActionClick;

/**
 * Click resolver for actions rendered inside the table's empty state.
 *
 * The empty state is a record-less surface — there are no rows — so its actions
 * run through the same host methods as the toolbar's header actions rather than
 * the row pipeline, which would carry an empty record key. A modal action opens
 * the action modal; everything else executes directly.
 */
final class EmptyStateActionClickResolver implements ResolvesActionClick
{
    public function clickHandler(BaseAction $action, ?Model $record): string
    {
        $name = $action->getName();

        return $action->hasModal()
            ? "openHeaderActionModal('{$name}')"
            : "executeHeaderAction('{$name}')";
    }
}
