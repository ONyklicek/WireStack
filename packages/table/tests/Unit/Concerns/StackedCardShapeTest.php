<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Foundation\View\Skeleton;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Support\MobileCard;
use NyonCode\WireTable\Table;

/*
 * The compiled stacked card — the two rules in StacksOnMobile that only ever
 * showed up in a browser.
 *
 * 1. The compiled shapes are keyed by MobileCard::shapeSignature(), because the
 *    card's shape is derived from the visible columns: hide one and the card
 *    branches differently. A flat memo — one skeleton per table rather than one
 *    per shape — passed all 2258 tests in the package. It would serve the
 *    previous shape after a column toggle: a metric slot that no longer has a
 *    column, or a detail grid the card no longer has.
 *
 * 2. A selectable card indents its detail grid and its action row past the
 *    checkbox gutter (`pl-12`). Dropping that indent also passed everything;
 *    the only symptom is a phone showing the details tucked under the checkbox.
 *
 * Both are decided once at compile time — they are properties of the table and
 * of the card's shape, not of a record — so they are readable straight off the
 * compiled template, which is what these assert.
 */

/** @return array<int, Column> */
function shapeColumns(): array
{
    return [
        TextColumn::make('number'),
        TextColumn::make('customer'),
        BadgeColumn::make('status'),
        TextColumn::make('total')->alignRight(),
    ];
}

// ─── The shape key belongs to the card ───────────────────────────────────────

it('keys a card shape on the slots it has, not on what fills them', function () {
    $full = MobileCard::resolve(shapeColumns());

    // Same columns resolve to the same shape…
    expect(MobileCard::resolve(shapeColumns())->shapeSignature())->toBe($full->shapeSignature())
        // …and dropping the right-aligned column takes the metric slot with it.
        ->and(MobileCard::resolve([
            TextColumn::make('number'),
            TextColumn::make('customer'),
            BadgeColumn::make('status'),
        ])->shapeSignature())->not->toBe($full->shapeSignature());
});

it('separates a card with a detail grid from one without', function () {
    $bare = MobileCard::resolve([TextColumn::make('number'), TextColumn::make('customer')]);
    $withDetails = MobileCard::resolve([
        TextColumn::make('number'),
        TextColumn::make('customer'),
        TextColumn::make('note'),
    ]);

    expect($bare->details())->toBe([])
        ->and($withDetails->details())->not->toBe([])
        ->and($bare->shapeSignature())->not->toBe($withDetails->shapeSignature());
});

// ─── …and the table's cache respects it ──────────────────────────────────────

it('compiles one card shape per signature, not one per table', function () {
    $table = Table::make();

    $full = MobileCard::resolve(shapeColumns());
    $noMetric = MobileCard::resolve([TextColumn::make('number'), TextColumn::make('customer')]);

    $first = $table->getMobileCardSkeleton($full);
    $second = $table->getMobileCardSkeleton($noMetric);

    // A different shape must not be served the shape before it.
    expect($second)->not->toBe($first)
        // …while the same shape asked again is the very same compiled artefact.
        ->and($table->getMobileCardSkeleton($full))->toBe($first);
});

it('drops the slot markup a shape no longer has', function () {
    $table = Table::make();

    // The metric is the readable proof: its own <div> with a data-testid, present
    // only while a right-aligned column claims the slot.
    $withMetric = $table->getMobileCardSkeleton(MobileCard::resolve(shapeColumns()))->toHtml();
    $without = $table->getMobileCardSkeleton(
        MobileCard::resolve([TextColumn::make('number'), TextColumn::make('customer')])
    )->toHtml();

    expect($withMetric)->toContain('data-testid="table-card-metric"')
        ->and($without)->not->toContain('data-testid="table-card-metric"');
});

// ─── The checkbox gutter ─────────────────────────────────────────────────────

it('indents the card details and actions past the selection checkbox', function () {
    $card = MobileCard::resolve([...shapeColumns(), TextColumn::make('note')]);

    $plain = Table::make()
        ->actions([Action::make('open')])
        ->getMobileCardSkeleton($card)->toHtml();

    $selectable = Table::make()
        ->selectable()
        ->actions([Action::make('open')])
        ->getMobileCardSkeleton($card)->toHtml();

    // Both halves of the card body clear the checkbox, or they read as hanging
    // under it: the detail grid…
    expect($selectable)->toContain('px-4 pb-3 grid grid-cols-2 gap-x-4 gap-y-2 pl-12')
        // …and the inline action row.
        ->toContain('flex flex-wrap items-center gap-2 px-4 pb-3 pl-12')
        // A table with no checkbox has no gutter to clear.
        ->and($plain)->toContain('px-4 pb-3 grid grid-cols-2 gap-x-4 gap-y-2"')
        ->not->toContain('pl-12');
});

it('leaves one hole per per-record slot in the compiled card', function () {
    $html = Table::make()
        ->selectable()
        ->actions([Action::make('open')])
        ->getMobileCardSkeleton(MobileCard::resolve([...shapeColumns(), TextColumn::make('note')]))
        ->toHtml();

    // Everything that varies per record is a hole; everything else is baked.
    foreach (['cardClasses', 'key', 'keyJs', 'title', 'metric', 'subtitle', 'meta', 'details', 'actions', 'subRows'] as $slot) {
        expect($html)->toContain(Skeleton::slot($slot));
    }
});
