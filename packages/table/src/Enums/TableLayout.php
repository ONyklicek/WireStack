<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Enums;

use NyonCode\WireTable\Concerns\StacksOnMobile;
use NyonCode\WireTable\Support\MobileCard;

/**
 * How a table draws its records — as rows, or as a list of cards.
 *
 * The card rendering already existed and was reachable only through
 * {@see StacksOnMobile}: below a breakpoint you got cards, above it you got rows,
 * and both were in the document because CSS chose between them. That is exactly
 * right for a table that has to survive a phone, and exactly wrong for the
 * surfaces that are **never** a table — an inbox, an activity feed, a media
 * library. Those were being built as tables and then talked out of it column by
 * column: collapse three columns into one, delete the state column, replace the
 * badge with row weight, hide the actions, and you are left fighting the header
 * row that cannot be turned off.
 *
 * So the layout is a choice rather than a width:
 *
 *   `List` renders the cards, at every width, and does not render the `<table>`
 *   at all — one rendering per record, not two chosen by CSS.
 *
 * Everything around the records is unchanged and is the reason this is a layout
 * rather than a different page: the search, the filters, the pagination, the
 * selection that survives paging, the bulk actions over it and the exports are
 * the table's, and a hand-written list would have to grow all of them again.
 *
 * The slot vocabulary a card is arranged by belongs to {@see MobileCard}.
 */
enum TableLayout: string
{
    /** Rows in a `<table>`. The default, and what every table did before this existed. */
    case Table = 'table';

    /** A list of cards at every width; no `<table>` is rendered. */
    case List = 'list';

    /** Tolerate the string form so the fluent API can accept both. */
    public static function resolve(self|string $layout): self
    {
        return $layout instanceof self ? $layout : self::from($layout);
    }

    public function rendersTable(): bool
    {
        return $this === self::Table;
    }
}
