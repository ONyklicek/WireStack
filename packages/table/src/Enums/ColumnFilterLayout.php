<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Enums;

/**
 * Where a column's header filter lives.
 *
 * `Row` is the second header row the table has always drawn: every filter open
 * at once, in a cell as wide as its column. It is quick on a wide screen with a
 * few filters and cramped everywhere else — a narrow column gets a narrow text
 * box, a phone scrolls the filters sideways with the data, and there is no room
 * for an operator picker beside the value.
 *
 * `Popover` keeps the header one row high: a filterable column carries a funnel
 * beside its label, marked while the column is filtered, which opens that one
 * filter — operator, value, "Clear" — in a panel of its own, sized for the
 * filter rather than the column, and a bottom sheet on a phone.
 */
enum ColumnFilterLayout: string
{
    case Row = 'row';
    case Popover = 'popover';
}
