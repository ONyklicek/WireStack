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
 *    class strings are what swap the `<table>` for the card list. Without a
 *    `wire_viewport` cookie both halves are in the document and CSS decides;
 *    with one, only the half that browser shows is emitted
 *    ({@see Table::getClientLayout()}) and the swap classes go empty;
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
     * @param  bool  $stickyHeaderFollowsPage  Whether `wire-table-sticky.js` moves the `<thead>` with the page scroll.
     * @param  string  $stickyLayerClass  Isolates the scroll region and the `<tbody>` under a pinned header; empty when it does not pin.
     * @param  string  $scrollRegionStyle  Inline `max-height` for the scroll region; empty when uncapped.
     * @param  bool  $rendersTable  Whether a `<table>` is emitted in this response.
     * @param  bool  $rendersCards  Whether the card rendering is emitted in this response.
     * @param  ?string  $clientLayout  `'table'`, `'cards'`, or null when both are emitted.
     * @param  bool  $tracksClientLayout  Whether the viewport script watches this table.
     * @param  string  $stackedMediaQuery  The query the stacking breakpoint answers to.
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
        public readonly bool $stickyHeaderFollowsPage,
        public readonly string $stickyLayerClass,
        public readonly string $scrollRegionStyle,
        public readonly bool $isStackedOnMobile,
        public readonly bool $rendersTable,
        public readonly bool $rendersCards,
        public readonly ?string $clientLayout,
        public readonly bool $tracksClientLayout,
        public readonly string $stackedMediaQuery,
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
        $followsPage = $table->hasPageStickyHeader();
        $pins = $followsPage || $stickyMaxHeight !== null;

        return new self(
            isBordered: $table->isBordered(),
            cellPadding: $table->getCellPadding(),
            headerPadding: $table->getHeaderPadding(),
            // Opaque on both themes, unlike the resting `dark:bg-gray-800/50`:
            // a translucent header shows the rows travelling underneath it.
            //
            // Two mechanisms for one promise. Inside a capped region the region
            // is the scrollport, and CSS `sticky` pins to it. Against the page it
            // cannot: the wrapper's `overflow-x: auto` makes the wrapper the
            // scrollport, and it never scrolls vertically. There the script
            // translates the `<thead>` instead, and `relative` is what lets its
            // z-index lift it over the rows it slides across.
            //
            // `z-30` and not `z-10`: it has to beat everything the rows and the
            // region carry — a cell's `relative z-10` radio or checkbox list,
            // the fill overlay (`z-10`) and the fill handle (`z-20`), all later
            // in the document than the header. Two `isolate`s make that hold for
            // whatever a cell renders: the `<tbody>` folds every z-index inside
            // it into one layer at 0, and the scroll region keeps the header's
            // `z-30` from reaching anything outside the table — the admin top
            // bar is `z-30` too, earlier in the document, and stays on top.
            stickyHeaderClass: match (true) {
                $followsPage => 'relative z-30 bg-gray-50 dark:bg-gray-800',
                $stickyMaxHeight !== null => 'sticky top-0 z-30 bg-gray-50 dark:bg-gray-800',
                default => '',
            },
            stickyHeaderFollowsPage: $followsPage,
            stickyLayerClass: $pins ? 'isolate' : '',
            scrollRegionStyle: $stickyMaxHeight === null
                ? ''
                : 'max-height: '.$stickyMaxHeight,
            isStackedOnMobile: $table->isStackedOnMobile(),
            // Which halves exist, as opposed to which is visible: the list
            // layout emits one rendering per record rather than two chosen by
            // CSS, and a stacked table whose browser said how wide it is emits
            // only the half it shows — a payload decision, not a display one.
            rendersTable: $table->emitsTable(),
            rendersCards: $table->emitsCards(),
            clientLayout: $table->getClientLayout(),
            tracksClientLayout: $table->rendersVisibleLayoutOnly(),
            stackedMediaQuery: $table->getStackedMediaQuery(),
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
