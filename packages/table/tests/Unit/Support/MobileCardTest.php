<?php

declare(strict_types=1);

use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Support\MobileCard;
use NyonCode\WireTable\Support\MobileCardConfig;
use NyonCode\WireTable\Support\MobileSlot;

/**
 * @return array<int, Column>
 */
function invoiceColumns(): array
{
    return [
        TextColumn::make('number'),
        TextColumn::make('customer'),
        BadgeColumn::make('status'),
        TextColumn::make('total')->alignRight(),
    ];
}

// ─── Derivation: a table that says nothing still gets a hierarchy ─────────────

it('derives the title from the first column', function () {
    expect(MobileCard::resolve(invoiceColumns())->title()?->getName())->toBe('number');
});

it('derives the metric from the last right-aligned column', function () {
    // money() / numeric() align right — that is the figure the list is read for.
    expect(MobileCard::resolve(invoiceColumns())->metric()?->getName())->toBe('total');
});

it('derives meta from badge columns', function () {
    $meta = array_map(fn ($c) => $c->getName(), MobileCard::resolve(invoiceColumns())->meta());

    expect($meta)->toBe(['status']);
});

it('derives the subtitle from the first column no other slot claimed', function () {
    expect(MobileCard::resolve(invoiceColumns())->subtitle()?->getName())->toBe('customer');
});

it('leaves everything unclaimed in the detail grid', function () {
    $columns = [...invoiceColumns(), TextColumn::make('note'), TextColumn::make('reference')];
    $details = array_map(fn ($c) => $c->getName(), MobileCard::resolve($columns)->details());

    expect($details)->toBe(['note', 'reference']);
});

it('picks no metric when no column is right-aligned', function () {
    $card = MobileCard::resolve([TextColumn::make('number'), TextColumn::make('customer')]);

    expect($card->metric())->toBeNull()
        ->and($card->title()?->getName())->toBe('number')
        ->and($card->subtitle()?->getName())->toBe('customer');
});

it('survives a table with a single column', function () {
    $card = MobileCard::resolve([TextColumn::make('number')]);

    expect($card->title()?->getName())->toBe('number')
        ->and($card->subtitle())->toBeNull()
        ->and($card->details())->toBe([]);
});

it('resolves nothing from no columns', function () {
    $card = MobileCard::resolve([]);

    expect($card->title())->toBeNull()
        ->and($card->metric())->toBeNull()
        ->and($card->meta())->toBe([])
        ->and($card->details())->toBe([]);
});

// ─── Per-column declaration wins over derivation ─────────────────────────────

it('honours a column that names its own slot', function () {
    $columns = [
        TextColumn::make('number'),
        TextColumn::make('customer')->mobileTitle(),
        TextColumn::make('total')->alignRight(),
    ];

    $card = MobileCard::resolve($columns);

    expect($card->title()?->getName())->toBe('customer')
        // 'number' is now free, so it becomes the supporting line.
        ->and($card->subtitle()?->getName())->toBe('number');
});

it('keeps a column in the detail grid when it asks to stay there', function () {
    $columns = [
        TextColumn::make('number'),
        TextColumn::make('total')->alignRight()->mobileDetail(),
    ];

    $card = MobileCard::resolve($columns);

    expect($card->metric())->toBeNull()
        ->and(array_map(fn ($c) => $c->getName(), $card->details()))->toBe(['total']);
});

it('exposes a sugar method for every slot', function () {
    expect(TextColumn::make('a')->mobileTitle()->getMobileSlot())->toBe(MobileSlot::Title)
        ->and(TextColumn::make('b')->mobileSubtitle()->getMobileSlot())->toBe(MobileSlot::Subtitle)
        ->and(TextColumn::make('c')->mobileMetric()->getMobileSlot())->toBe(MobileSlot::Metric)
        ->and(TextColumn::make('d')->mobileMeta()->getMobileSlot())->toBe(MobileSlot::Meta)
        ->and(TextColumn::make('e')->mobileDetail()->getMobileSlot())->toBe(MobileSlot::Detail)
        ->and(TextColumn::make('f')->getMobileSlot())->toBeNull();
});

it('uses a declared subtitle over the derived one', function () {
    $card = MobileCard::resolve([
        TextColumn::make('number'),
        TextColumn::make('customer'),
        TextColumn::make('reference')->mobileSubtitle(),
    ]);

    expect($card->subtitle()?->getName())->toBe('reference');
});

it('accepts a slot as a string', function () {
    $column = TextColumn::make('total')->mobileSlot('metric');

    expect($column->getMobileSlot())->toBe(MobileSlot::Metric);
});

// ─── The fluent config wins over both ────────────────────────────────────────

it('honours names given to mobileCard()', function () {
    $card = MobileCard::resolve(
        invoiceColumns(),
        fn (MobileCardConfig $c) => $c->title('customer')->subtitle('number')->metric('total')->meta('status'),
    );

    expect($card->title()?->getName())->toBe('customer')
        ->and($card->subtitle()?->getName())->toBe('number')
        ->and($card->metric()?->getName())->toBe('total')
        ->and(array_map(fn ($x) => $x->getName(), $card->meta()))->toBe(['status'])
        ->and($card->details())->toBe([]);
});

it('overrides a per-column declaration from the config', function () {
    $columns = [
        TextColumn::make('number')->mobileTitle(),
        TextColumn::make('customer'),
    ];

    $card = MobileCard::resolve($columns, fn (MobileCardConfig $c) => $c->title('customer'));

    expect($card->title()?->getName())->toBe('customer');
});

it('replaces rather than stacks when a singular slot is named twice', function () {
    $card = MobileCard::resolve(
        invoiceColumns(),
        fn (MobileCardConfig $c) => $c->title('number')->title('customer'),
    );

    expect($card->title()?->getName())->toBe('customer');
});

it('accepts several meta columns', function () {
    $card = MobileCard::resolve(
        invoiceColumns(),
        fn (MobileCardConfig $c) => $c->meta(['status', 'customer']),
    );

    expect(array_map(fn ($x) => $x->getName(), $card->meta()))->toBe(['customer', 'status']);
});
