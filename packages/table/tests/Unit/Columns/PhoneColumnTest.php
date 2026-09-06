<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireForms\Components\PhoneInput;
use NyonCode\WireTable\Columns\PhoneColumn;

class PhoneColumnRecord extends Model
{
    protected $table = 'phone_column_records';

    protected $guarded = [];

    public $timestamps = false;
}

function phoneRecord(?string $phone): PhoneColumnRecord
{
    return new PhoneColumnRecord(['phone' => $phone]);
}

it('writes a stored E.164 number the way a person reads it', function () {
    $column = PhoneColumn::make('phone');

    $record = phoneRecord('+420123456789');

    // getState() is where formatStateUsing runs — the value the cell then shows.
    expect($column->getState($record))->toBe('+420 123 456 789')
        ->and($column->formatValue($column->getState($record), $record))->toBe('+420 123 456 789');
});

it('writes it the same way the field does', function () {
    // One table, one grammar: a number must not read one way in a form and
    // another in the row that lists it.
    $column = PhoneColumn::make('phone');
    $field = PhoneInput::make('phone');

    expect($column->getState(phoneRecord('+12125551234')))
        ->toBe($field->hydrateState('+12125551234'));
});

it('links the number for a dialler, digits only', function () {
    $column = PhoneColumn::make('phone');

    expect($column->isCallable())->toBeTrue()
        ->and($column->getUrl(phoneRecord('+420 123 456 789')))->toBe('tel:+420123456789');
});

it('links nothing when there is no number', function () {
    expect(PhoneColumn::make('phone')->getUrl(phoneRecord(null)))->toBeNull()
        ->and(PhoneColumn::make('phone')->getUrl(phoneRecord('')))->toBeNull();
});

it('keeps the formatting and drops the link when asked', function () {
    $column = PhoneColumn::make('phone')->notCallable();

    expect($column->isCallable())->toBeFalse()
        ->and($column->getUrl(phoneRecord('+420123456789')))->toBeNull()
        ->and($column->getState(phoneRecord('+420123456789')))->toBe('+420 123 456 789');
});

it('leaves a number it cannot place alone rather than mis-grouping it', function () {
    $column = PhoneColumn::make('phone');

    expect($column->getState(phoneRecord('+9995551234')))->toBe('+9995551234')
        // …and still offers to dial it: an unknown prefix is not an invalid number.
        ->and($column->getUrl(phoneRecord('+9995551234')))->toBe('tel:+9995551234');
});
