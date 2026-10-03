<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Support;

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionGroup;
use NyonCode\WireCore\Actions\BaseAction;

/**
 * Which dividers of a menu survive once its hidden items are gone.
 *
 * A divider separates two groups of items, so it means something only with an
 * item on each side of it. Hiding items is per record (a permission, a
 * `hidden()` closure), which is how a menu declared as "A | B | C" becomes
 * "| C" on a row where A and B do not apply — a line above the first item, or
 * two lines with nothing between them. The rule that settles it: no divider
 * first, none last, never two in a row.
 *
 * One owner for every menu built from a list of actions — the action-group
 * dropdown and a table's row context menu both ask here, so the two cannot
 * disagree about the same declaration.
 */
final class MenuDividers
{
    /**
     * Drop the leading, trailing and consecutive dividers from a list that
     * holds only visible items.
     *
     * @template T of BaseAction|ActionGroup
     *
     * @param  array<int, T>  $items
     * @return array<int, T>
     */
    public static function clean(array $items): array
    {
        $cleaned = [];
        $pending = null;

        foreach ($items as $item) {
            if (self::isDivider($item)) {
                // Held until an item follows, so a run of dividers is one and
                // a divider with nothing after it is none.
                $pending = $cleaned === [] ? null : ($pending ?? $item);

                continue;
            }

            if ($pending !== null) {
                $cleaned[] = $pending;
                $pending = null;
            }

            $cleaned[] = $item;
        }

        return $cleaned;
    }

    public static function isDivider(mixed $item): bool
    {
        return $item instanceof Action && $item->isDivider();
    }
}
