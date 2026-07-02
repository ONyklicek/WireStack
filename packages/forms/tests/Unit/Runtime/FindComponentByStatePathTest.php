<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Schema\Grid;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;

function findPathForm(): Form
{
    return Form::make()
        ->statePath('data')
        ->schema([
            TextInput::make('title'),
            Grid::make()->schema([
                TextInput::make('summary'),
            ]),
            Repeater::make('contacts')->schema([
                TextInput::make('email'),
                Grid::make()->schema([
                    TextInput::make('phone'),
                ]),
                Repeater::make('tags')->schema([
                    TextInput::make('label'),
                ]),
            ]),
        ]);
}

test('finds a flat field by its absolute path', function () {
    $field = findPathForm()->findComponentByStatePath('data.title');

    expect($field)->toBeInstanceOf(TextInput::class)
        ->and($field->getName())->toBe('title');
});

test('finds a layout-wrapped field', function () {
    $field = findPathForm()->findComponentByStatePath('data.summary');

    expect($field?->getName())->toBe('summary');
});

test('finds a field inside a repeater item (regression: flattening treats a repeater as a leaf, so item fields were unreachable)', function () {
    $field = findPathForm()->findComponentByStatePath('data.contacts.2.email');

    expect($field)->toBeInstanceOf(TextInput::class)
        ->and($field->getStatePath())->toBe('data.contacts.2.email');
});

test('finds a layout-wrapped field inside a repeater item', function () {
    $field = findPathForm()->findComponentByStatePath('data.contacts.0.phone');

    expect($field?->getStatePath())->toBe('data.contacts.0.phone');
});

test('finds a field inside a nested repeater item', function () {
    $field = findPathForm()->findComponentByStatePath('data.contacts.1.tags.3.label');

    expect($field)->toBeInstanceOf(TextInput::class)
        ->and($field->getStatePath())->toBe('data.contacts.1.tags.3.label');
});

test('returns null for a non-numeric item segment', function () {
    expect(findPathForm()->findComponentByStatePath('data.contacts.email'))->toBeNull();
});

test('returns null for an unknown path', function () {
    expect(findPathForm()->findComponentByStatePath('data.nope'))->toBeNull()
        ->and(findPathForm()->findComponentByStatePath('data.contacts.0.nope'))->toBeNull();
});
