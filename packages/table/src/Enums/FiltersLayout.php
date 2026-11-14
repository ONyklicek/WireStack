<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Enums;

/**
 * Where the filter panel (Table::filters()) is drawn.
 *
 * `Dropdown` is the panel behind the toolbar's Filters button — a bottom sheet
 * on a phone. `AboveContent` lays the filters out above the rows, always open:
 * right for a list people narrow constantly, where opening a dropdown each time
 * is the friction. `AboveContentCollapsible` is the same with a toggle, closed
 * until it is wanted and remembered per browser.
 */
enum FiltersLayout: string
{
    case Dropdown = 'dropdown';
    case AboveContent = 'above';
    case AboveContentCollapsible = 'above_collapsible';

    /** Whether the filters live above the rows rather than behind the toolbar button. */
    public function isAboveContent(): bool
    {
        return $this !== self::Dropdown;
    }
}
