<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use NyonCode\WireCore\Foundation\ValueObjects\DialingCodes;
use NyonCode\WireForms\Components\PhoneInput;
use NyonCode\WireForms\Validation\Rules\PhoneNumber;

test('it offers the whole table until it is told otherwise', function () {
    $field = PhoneInput::make('phone');

    expect($field->getCountries())->toEqual(DialingCodes::all())
        ->and($field->getDefaultCountry()?->country)->toBe('CZ');
});

test('countries are offered in the order they were listed', function () {
    $field = PhoneInput::make('phone')->countries(['sk', 'CZ', 'XX']);

    expect(array_column($field->getCountryOptions(), 'country'))->toBe(['SK', 'CZ'])
        // An unknown code is dropped rather than offered as a dead option.
        ->and($field->getDefaultCountry()?->dialingCode)->toBe('+421');
});

test('the offer and its default can be set once, in the application config', function () {
    config([
        'wire-forms.phone.countries' => ['DE', 'AT', 'CZ'],
        'wire-forms.phone.default_country' => 'at',
    ]);

    $field = PhoneInput::make('phone');

    expect(array_column($field->getCountryOptions(), 'country'))->toBe(['DE', 'AT', 'CZ'])
        ->and($field->getDefaultCountry()?->country)->toBe('AT');

    // A field that names its own offer still wins over the config.
    $stated = PhoneInput::make('phone')->countries(['SK']);

    expect(array_column($stated->getCountryOptions(), 'country'))->toBe(['SK'])
        // …and a config default the field does not offer is ignored.
        ->and($stated->getDefaultCountry()?->country)->toBe('SK');
});

test('a stated default country wins over the first offered one', function () {
    $field = PhoneInput::make('phone')->countries(['SK', 'CZ'])->defaultCountry('cz');

    expect($field->getDefaultCountry()?->country)->toBe('CZ');
});

test('a default country the field does not offer falls back to the first', function () {
    $field = PhoneInput::make('phone')->countries(['SK'])->defaultCountry('DE');

    expect($field->getDefaultCountry()?->country)->toBe('SK');
});

test('an option carries the flag its ISO code computes', function () {
    $options = PhoneInput::make('phone')->countries(['CZ'])->getCountryOptions();

    expect($options[0])->toBe([
        'country' => 'CZ',
        'dialingCode' => '+420',
        'label' => '🇨🇿 +420',
    ]);
});

test('a stored number is written out spaced, and read back as E.164', function () {
    $field = PhoneInput::make('phone');

    expect($field->hydrateState('+420123456789'))->toBe('+420 123 456 789')
        // Ten digits: the trailing one joins the group before it rather than
        // standing alone.
        ->and($field->hydrateState('+12125551234'))->toBe('+1 212 555 1234')
        ->and($field->dehydrateState('+420 123 456 789'))->toBe('+420123456789')
        ->and($field->dehydrateState('+1 212 555 1234'))->toBe('+12125551234');
});

test('an empty field stores nothing', function () {
    $field = PhoneInput::make('phone');

    expect($field->hydrateState(null))->toBeNull()
        ->and($field->hydrateState(''))->toBeNull()
        ->and($field->dehydrateState(''))->toBeNull()
        ->and($field->dehydrateState(null))->toBeNull()
        ->and($field->dehydrateState(['nonsense']))->toBeNull();
});

test('a number from outside the table survives both directions unchanged', function () {
    $field = PhoneInput::make('phone');

    expect($field->hydrateState('+9995551234'))->toBe('+9995551234')
        ->and($field->dehydrateState('+999 555 1234'))->toBe('+9995551234');
});

test('the longest matching prefix wins', function () {
    expect(DialingCodes::match('+420123456789')?->country)->toBe('CZ')
        // +1 must not swallow a number that starts with it.
        ->and(DialingCodes::match('+12125551234')?->country)->toBe('US')
        ->and(DialingCodes::match('+9995551234'))->toBeNull()
        ->and(DialingCodes::find('cz')?->dialingCode)->toBe('+420')
        ->and(DialingCodes::find('XX'))->toBeNull();
});

test('the field validates the prefix and the digits behind it', function () {
    $rules = PhoneInput::make('phone')->countries(['CZ', 'SK'])->getValidationRules();

    expect($rules[0])->toBe('nullable')
        ->and($rules[1])->toBeInstanceOf(PhoneNumber::class);

    $validate = fn (mixed $value): bool => Validator::make(
        ['phone' => $value],
        ['phone' => $rules],
    )->passes();

    expect($validate('+420 123 456 789'))->toBeTrue()
        ->and($validate('+421123456789'))->toBeTrue()
        ->and($validate(null))->toBeTrue()
        // Nine digits is what CZ issues; eight is not a Czech number.
        ->and($validate('+420 123 456 78'))->toBeFalse()
        // A country this field does not offer.
        ->and($validate('+49 151 12345678'))->toBeFalse()
        // Not international at all.
        ->and($validate('123456789'))->toBeFalse();
});

test('each failure reads back as its own message', function () {
    $messages = fn (mixed $value, array $countries = ['CZ']): array => Validator::make(
        ['phone' => $value],
        ['phone' => [new PhoneNumber(DialingCodes::only($countries))]],
    )->errors()->get('phone');

    expect($messages('123456789'))
        ->toBe(['The phone must be a valid international phone number.'])
        ->and($messages('+49 151 12345678'))
        ->toBe(['The phone must be a number from one of the offered countries.'])
        ->and($messages('+420 123'))
        ->toBe(['The phone must have between 9 and 9 digits after the dialling code.']);
});

test('a number outside the table is held to E.164 bounds alone', function () {
    $validate = fn (mixed $value): bool => Validator::make(
        ['phone' => $value],
        ['phone' => [new PhoneNumber]],
    )->passes();

    expect($validate('+999 555 1234'))->toBeTrue()
        ->and($validate('+999'))->toBeFalse()
        ->and($validate('+9991234567890123456'))->toBeFalse();
});

test('a required field keeps required instead of nullable', function () {
    $rules = PhoneInput::make('phone')->required()->getValidationRules();

    expect($rules[0])->toBe('required')
        ->and($rules)->not->toContain('nullable');
});
