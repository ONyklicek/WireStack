<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use NyonCode\WireTable\Table;

/**
 * A column pinned against the horizontal scroll — and the three layers that are
 * what actually make one work.
 *
 * Pinning is one CSS declaration (`position: sticky` + an edge offset) and one
 * hard problem: **a sticky cell is transparent by default**, so the columns it
 * is supposed to stay in front of scroll straight through it. Every state a row
 * can be in has to end up painted on the pinned cell, opaquely, and this repo's
 * row backgrounds are not opaque — `Table::getRowClasses()` gives an untinted,
 * unstriped row no background at all, the stripe is `bg-gray-50/50`, the dark
 * tints are `/20`, and the selected class arrives from Alpine at runtime.
 *
 * ## Why three layers rather than an opaque row
 *
 * The obvious repair — make every row background opaque so the cell can
 * `bg-inherit` from it — needs an opaque twin of two 25-arm colour maps, and
 * those twins would be **invisible to Tailwind**: a utility that is only ever
 * built by string manipulation appears in no source file, so the extractor never
 * emits it and the class silently does nothing. Same objection to rewriting
 * `hover:` into `group-hover:` for an overlay.
 *
 * So the cell composes instead, out of two literals and CSS inheritance:
 *
 *  1. the `<td>` itself takes `bg-inherit`, so its background-color is *the
 *     row's*, whatever the row currently has — tint, stripe, hover, the
 *     Alpine-applied selection, all of it live, with no second vocabulary;
 *  2. an opaque surface layer ({@see $surfaceClass}) is painted over it, which
 *     is what the scrolling columns disappear behind;
 *  3. a second `bg-inherit` layer is painted over *that*, and inherits the same
 *     row colour a third time — now composited against an opaque backdrop
 *     instead of against whatever happened to be scrolling past.
 *
 * The cell's own background in step 1 is doing no visual work; it is there so
 * the layer in step 3 has something to inherit. Content goes in a `relative`
 * sibling above all three.
 *
 * ## The two z tiers
 *
 * `z-[1]` on a body cell rather than nothing: a positioned element paints above
 * ordinary cell content, but an editable cell is `relative` too, and with the
 * actions column at `start` every one of them comes later in the row. A positive
 * z-index lifts the pinned cell over its whole row. `z-10` on a header cell for
 * the mirror reason — it has to beat its own row's other headers — and both stay
 * under the sticky `<thead>`'s own `z-10` stacking context, so a pinned body cell
 * can never ride over the pinned header when both axes are scrolled.
 *
 * Everything here is a literal utility string for the reason given above. The
 * side is the only variable, which is what lets `Column::sticky()` reuse this
 * owner later without a rewrite: {@see on()} knows nothing about actions.
 */
final class StickyColumn
{
    /**
     * The table's own surface — the card the table is drawn on
     * (`tables/index.blade.php`), not the page behind it. Getting this wrong is
     * visible immediately: the pinned column reads as a differently-coloured
     * pane.
     */
    private const SURFACE = 'bg-white dark:bg-gray-800';

    private ?string $layers = null;

    private function __construct(
        public readonly bool $isPinned,
        public readonly string $side,
        public readonly string $cellClass,
        public readonly string $headerCellClass,
        public readonly string $surfaceClass,
    ) {}

    /** A column that is not pinned: every string empty, so a view can echo them unguarded. */
    public static function none(): self
    {
        return new self(false, '', '', '', '');
    }

    /**
     * @param  string  $side  'start' pins to the left edge, anything else to the right.
     */
    public static function on(string $side): self
    {
        // The divider is drawn whatever the table's border mode: it is the edge
        // of the pinned pane, not a cell border, and without it the frozen
        // column and the column beside it read as one until something scrolls.
        $edge = $side === 'start'
            ? 'sticky left-0 bg-inherit border-r border-gray-200 dark:border-gray-700'
            : 'sticky right-0 bg-inherit border-l border-gray-200 dark:border-gray-700';

        return new self(
            isPinned: true,
            side: $side,
            cellClass: $edge.' z-[1]',
            headerCellClass: $edge.' z-10',
            surfaceClass: self::SURFACE,
        );
    }

    /**
     * The actions column's pinning, as the table declared it.
     *
     * A table with no actions has no actions column to pin, so it reports
     * unpinned however `stickyActions()` was called — the alternative is a plan
     * that claims a pinned column no view renders.
     */
    public static function forActions(Table $table): self
    {
        return $table->hasStickyActions() && $table->hasActions()
            ? self::on($table->getActionsPosition())
            : self::none();
    }

    /**
     * The surface and inheriting layers, rendered once and spliced into every
     * pinned cell — empty for an unpinned column.
     *
     * Rendered here rather than written as a string: the markup belongs in a
     * publishable Blade partial like every other piece of a cell, and this is
     * called once per render, not once per row (the row's copy is baked into the
     * compiled action-cell skeleton).
     */
    public function layers(): string
    {
        if (! $this->isPinned) {
            return '';
        }

        return $this->layers ??= view('wire-table::tables.partials.sticky-layers', [
            'surfaceClass' => $this->surfaceClass,
        ])->render();
    }
}
