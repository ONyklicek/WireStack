<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use NyonCode\WireCore\Foundation\Support\MobileSheet;
use NyonCode\WireTable\Table;

/**
 * How one render is spaced, bordered and adapted to a narrow screen.
 *
 * Part of {@see TableRenderPlan}, and the group with no edges: nothing else in
 * the plan reads it and it reads nothing back. Four concerns that only ever
 * talk to CSS:
 *
 *  - **density** — the padding maps and the border, owned by the {@see Table} so
 *    a cell rendered outside the main view (the selection cell's own partial)
 *    cannot drift from the ones rendered inside it;
 *  - **the sticky header** — the utilities the `<thead>` pins with, and the cap on
 *    the region it pins against. Those are one decision, not two: the header pins
 *    to the table's own scrollport rather than the page, because the wrapper's
 *    `overflow-x: auto` computes the other axis to `auto` as well — and a
 *    scrollport the size of its content never scrolls, so an uncapped one leaves
 *    the header nothing to stay behind;
 *  - **stacking** — below the breakpoint each row becomes a card, and the two
 *    class strings are what swap the `<table>` for the card list. Both halves are
 *    always in the document; only CSS decides which is shown;
 *  - **the mobile sheet** — floating filter and column-toggle panels present as a
 *    bottom sheet on a phone unless `Table::sheetOnMobile(false)` turns it off.
 *
 * The sheet's five class strings all derive from the same breakpoint through
 * {@see MobileSheet}, which is the canonical owner in `wire-core`; resolving them
 * once here is what stops a partial recomputing them from a breakpoint it was
 * passed separately.
 *
 * Every class value is a literal Tailwind utility string. They are deliberately
 * not built by interpolation anywhere, so the classes survive Tailwind's static
 * extraction — which is also why the sticky cap arrives as an inline style
 * instead: it is an author-supplied CSS length, and no extractor can see it.
 */
final class LayoutRenderPlan
{
    /**
     * @param  string  $cellPadding  Density-mapped padding for a body cell.
     * @param  string  $headerPadding  The same for a header cell.
     * @param  string  $stickyHeaderClass  Pins the `<thead>`; empty when it does not pin.
     * @param  string  $scrollRegionStyle  Inline `max-height` for the scroll region; empty when uncapped.
     * @param  string  $tableHiddenClass  Hides the `<table>` below the breakpoint.
     * @param  string  $cardsVisibleClass  Shows the stacked cards there.
     * @param  string  $sheetBreakpoint  The breakpoint the sheet switches at.
     * @param  float  $sheetBreakpointPx  The same as a pixel value, for JS.
     */
    private function __construct(
        public readonly bool $isBordered,
        public readonly string $cellPadding,
        public readonly string $headerPadding,
        public readonly string $stickyHeaderClass,
        public readonly string $scrollRegionStyle,
        public readonly bool $isStackedOnMobile,
        public readonly string $tableHiddenClass,
        public readonly string $cardsVisibleClass,
        public readonly bool $sheetOnMobile,
        public readonly string $sheetBreakpoint,
        public readonly float $sheetBreakpointPx,
        public readonly string $sheetPanel,
        public readonly string $sheetMotion,
        public readonly string $sheetBackdrop,
    ) {}

    public static function resolve(Table $table): self
    {
        $breakpoint = $table->getMobileBreakpoint();
        $stickyMaxHeight = $table->getStickyHeaderMaxHeight();

        return new self(
            isBordered: $table->isBordered(),
            cellPadding: $table->getCellPadding(),
            headerPadding: $table->getHeaderPadding(),
            // Opaque on both themes, unlike the resting `dark:bg-gray-800/50`:
            // a translucent header shows the rows travelling underneath it.
            stickyHeaderClass: $stickyMaxHeight === null
                ? ''
                : 'sticky top-0 z-10 bg-gray-50 dark:bg-gray-800',
            scrollRegionStyle: $stickyMaxHeight === null
                ? ''
                : 'max-height: '.$stickyMaxHeight,
            isStackedOnMobile: $table->isStackedOnMobile(),
            tableHiddenClass: $table->getStackedTableHiddenClass(),
            cardsVisibleClass: $table->getStackedCardsVisibleClass(),
            sheetOnMobile: $table->usesSheetOnMobile(),
            sheetBreakpoint: $breakpoint,
            sheetBreakpointPx: MobileSheet::px($breakpoint),
            sheetPanel: MobileSheet::panel($breakpoint),
            sheetMotion: MobileSheet::motion($breakpoint),
            sheetBackdrop: MobileSheet::backdropHide($breakpoint),
        );
    }
}
