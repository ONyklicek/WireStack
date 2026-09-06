<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Runtime\StateDehydrator;

/*
 * The schema walk behind both write paths (ADR 0021): Form::save() and an
 * action modal's submit. TextInput is the field under test only because its
 * transform is the cheapest to read — a cleared number stores null.
 */

it('dehydrates a field nested in a layout component', function () {
    // The walk recurses through layouts, so a field inside a Section is not
    // invisible to it just because it is not top-level.
    $schema = [Section::make('Pricing')->schema([TextInput::make('discount')->numeric()])];

    expect(StateDehydrator::dehydrate($schema, ['discount' => '']))
        ->toBe(['discount' => null]);
});

it('dehydrates the children of a repeater nested in a layout component', function () {
    $schema = [
        Section::make('Rows')->schema([
            Repeater::make('rows')->schema([TextInput::make('quantity')->numeric()]),
        ]),
    ];

    expect(StateDehydrator::dehydrate($schema, ['rows' => [['quantity' => '']]]))
        ->toBe(['rows' => [['quantity' => null]]]);
});

it('leaves keys the schema does not name alone', function () {
    $schema = [TextInput::make('discount')->numeric()];

    expect(StateDehydrator::dehydrate($schema, ['note' => '']))
        ->toBe(['note' => '']);
});

it('skips a repeater whose bag entry is missing or is not a list of rows', function (mixed $bag, mixed $expected) {
    // A repeater key can be absent (never touched) or hold a scalar (a host that
    // wrote the bag itself). Neither is a set of rows to walk.
    $schema = [Repeater::make('rows')->schema([TextInput::make('quantity')->numeric()])];

    expect(StateDehydrator::dehydrate($schema, $bag))->toBe($expected);
})->with([
    'key absent' => [['other' => 1], ['other' => 1]],
    'not an array' => [['rows' => ''], ['rows' => '']],
]);

it('skips a repeater row that is not an array of fields', function () {
    $schema = [Repeater::make('rows')->schema([TextInput::make('quantity')->numeric()])];

    expect(StateDehydrator::dehydrate($schema, ['rows' => ['scrap', ['quantity' => '']]]))
        ->toBe(['rows' => ['scrap', ['quantity' => null]]]);
});
