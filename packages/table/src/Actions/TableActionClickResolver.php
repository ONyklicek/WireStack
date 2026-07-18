<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Actions;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Actions\BaseAction;
use NyonCode\WireCore\Actions\Contracts\ResolvesActionClick;

/**
 * Click resolver for actions rendered inside a table row.
 *
 * This is the single place that knows the table's Livewire action methods: a
 * modal action opens the action modal, everything else runs the table action
 * directly. Both carry the record key so the host resolves the right row.
 */
final class TableActionClickResolver implements ResolvesActionClick
{
    public function clickHandler(BaseAction $action, ?Model $record): string
    {
        $key = $record?->getKey();
        $name = $action->getName();

        return $action->hasModal()
            ? "openActionModal('{$key}', '{$name}')"
            : "executeTableAction('{$key}', '{$name}')";
    }
}
