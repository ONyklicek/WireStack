<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;

/*
 * What a host that persists a form's state itself may write: the form's own
 * keys, the way its save lifecycle decides them — never a key the state picked
 * up on its way back from the browser.
 */

function writableForm(): Form
{
    return Form::make()->schema([
        TextInput::make('name'),
        Section::make('Address')->schema([TextInput::make('address.city')]),
        Repeater::make('rows')->schema([TextInput::make('label')]),
        Repeater::make('lines')->relationship('lines')->schema([TextInput::make('label')]),
        TextInput::make('confirm')->dehydrated(false),
    ]);
}

it('keeps the keys the form writes', function (string $key, mixed $value) {
    expect(writableForm()->writableData([$key => $value, 'name' => 'Acme']))
        ->toEqualCanonicalizing([$key => $value, 'name' => 'Acme']);
})->with([
    'a field inside a layout' => ['address', ['city' => 'Praha']],
    'a column-backed repeater' => ['rows', [['label' => 'a']]],
]);

it('drops what the form does not write', function (string $key) {
    expect(writableForm()->writableData([$key => 'x', 'name' => 'Acme']))->toBe(['name' => 'Acme']);
})->with([
    'a key no field declared' => ['owner_id'],
    'a field switched off' => ['confirm'],
    'a relationship repeater' => ['lines'],
]);

it('takes only the declared path out of a nested key', function () {
    expect(writableForm()->writableData(['address' => ['city' => 'Praha', 'owner_id' => 9]]))
        ->toBe(['address' => ['city' => 'Praha']]);
});

it('leaves out a field whose key the data does not carry', function () {
    expect(writableForm()->writableData(['name' => 'Acme']))->toBe(['name' => 'Acme']);
});
