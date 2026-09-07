<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Enums;

use NyonCode\WireCore\Foundation\Concerns\CanBeCollapsed;

/**
 * Which items of a repeated list start open.
 *
 * {@see CanBeCollapsed} answers the
 * question for *one* thing — a Section, a navigation group. A list of things has
 * a fourth and fifth answer that a boolean cannot carry: a long Repeater is
 * usually best read with only the row you just added open, and a form that
 * reviews existing rows wants only the first.
 *
 * The cases are deliberately positional rather than a predicate. A closure over
 * the index would cover all four and more, but the two shapes anybody actually
 * writes would then be spelled `fn ($state, $i) => $i > 0` — which is a puzzle
 * at the call site and, worse, cannot be handed to the browser as a static
 * string. The default for an untouched item is resolved server-side per item and
 * baked into that item's markup precisely because the alternative is interpolating
 * a value into the root `x-data`, which is the morph hazard
 * `wireCollapsibleItems` was rewritten to avoid.
 */
enum ItemExpansion: string
{
    /** Every item starts open. The default, and what a non-collapsible list is. */
    case All = 'all';

    /** Every item starts folded away. */
    case None = 'none';

    /** Only the first item starts open. */
    case First = 'first';

    /** Only the last item starts open — the row a user has just added. */
    case Last = 'last';

    /**
     * Whether the item at `$index` of a list of `$count` starts folded.
     *
     * The single place the policy is read. An empty list answers false for any
     * index rather than dividing by its own emptiness.
     */
    public function collapses(int $index, int $count): bool
    {
        return match ($this) {
            self::All => false,
            self::None => true,
            self::First => $index !== 0,
            self::Last => $count > 0 && $index !== $count - 1,
        };
    }
}
