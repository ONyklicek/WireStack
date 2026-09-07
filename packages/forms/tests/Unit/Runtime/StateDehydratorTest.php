<?php

declare(strict_types=1);

use NyonCode\WireForms\Components\Layout\Section;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Runtime\StateDehydrator;

/*
 * The write-path transform on its own, away from any host.
 *
 * Two hosts drive it — a form save and an action modal — and both feed it a bag
 * that came from a browser, which is to say a bag that may not have the shape
 * the schema implies. What it does with a key it does not recognise, or a
 * repeater value that is not a list, is the part neither host can assert.
 */

it('applies a field transform and then the owner callback, in that order', function () {
    $schema = [
        Select::make('status'),
        TextInput::make('code')->dehydrateStateUsing(fn (mixed $state): string => strtoupper((string) $state)),
    ];

    expect((new StateDehydrator)->dehydrate(['status' => '', 'code' => 'abc'], $schema))
        ->toBe(['status' => null, 'code' => 'ABC']);
});

it('reaches a field nested in a layout component', function () {
    $schema = [Section::make('Details')->schema([TextInput::make('price')->numeric()])];

    expect((new StateDehydrator)->dehydrate(['price' => ''], $schema))->toBe(['price' => null]);
});

it('leaves a key the schema does not declare exactly as it found it', function () {
    expect((new StateDehydrator)->dehydrate(['ghost' => ''], [TextInput::make('price')->numeric()]))
        ->toBe(['ghost' => '']);
});

it('dehydrates each repeater item, per child field', function () {
    $schema = [Repeater::make('rows')->schema([TextInput::make('price')->numeric()])];

    expect((new StateDehydrator)->dehydrate(['rows' => [['price' => '10.5'], ['price' => '']]], $schema))
        ->toBe(['rows' => [['price' => '10.5'], ['price' => null]]]);
});

it('leaves a repeater key that does not hold a list alone', function () {
    // The bag is client state: a repeater key can arrive as anything at all, and
    // a walk that assumed a list would fatal on it.
    $schema = [Repeater::make('rows')->schema([TextInput::make('price')->numeric()])];

    expect((new StateDehydrator)->dehydrate(['rows' => 'not-a-list'], $schema))
        ->toBe(['rows' => 'not-a-list'])
        ->and((new StateDehydrator)->dehydrate([], $schema))->toBe([]);
});

it('skips a repeater item that is not an array, and still dehydrates its siblings', function () {
    $schema = [Repeater::make('rows')->schema([TextInput::make('price')->numeric()])];

    expect((new StateDehydrator)->dehydrate(['rows' => ['junk', ['price' => '']]], $schema))
        ->toBe(['rows' => ['junk', ['price' => null]]]);
});

it('leaves a child key an item does not carry out of the item', function () {
    $schema = [Repeater::make('rows')->schema([TextInput::make('price')->numeric()])];

    expect((new StateDehydrator)->dehydrate(['rows' => [['other' => '']]], $schema))
        ->toBe(['rows' => [['other' => '']]]);
});
