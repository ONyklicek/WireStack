<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Actions;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Actions\BaseAction;
use NyonCode\WireCore\Actions\Contracts\ResolvesActionClick;
use NyonCode\WireTable\Support\SubRowKey;

/**
 * Click resolver for an action rendered on a sub-row (`Table::subRowActions()`).
 *
 * The same two table methods a row action calls, under a {@see SubRowKey} — the
 * parent row's key and the child's — so the host resolves the child through its
 * parent and looks the action up among the sub-row actions. Before it the
 * buttons fell back to a bare `mountAction('name')`, a method a table host does
 * not have, so a sub-row action rendered and did nothing but fail.
 */
final readonly class SubRowActionClickResolver implements ResolvesActionClick
{
    public function __construct(private int|string $parentKey) {}

    public function clickHandler(BaseAction $action, ?Model $record): string
    {
        $key = SubRowKey::encode($this->parentKey, (string) $record?->getKey());
        $name = $action->getName();

        return $action->hasModal()
            ? "openActionModal('{$key}', '{$name}')"
            : "executeTableAction('{$key}', '{$name}')";
    }
}
