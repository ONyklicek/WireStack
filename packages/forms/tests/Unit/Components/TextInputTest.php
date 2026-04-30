<?php

declare(strict_types=1);

use NyonCode\WireForms\Components\TextInput;

test('make creates instance with name', function () {
    $field = TextInput::make('username');

    expect($field->getName())->toBe('username');
});

test('auto-generates label from name', function () {
    $field = TextInput::make('first_name');

    expect($field->getLabel())->toBe('First Name');
});

test('custom label overrides auto-generated', function () {
    $field = TextInput::make('name')->label('Full Name');

    expect($field->getLabel())->toBe('Full Name');
});

test('email type sets input type and mode', function () {
    $field = TextInput::make('email')->email();

    expect($field->getInputType())->toBe('email')
        ->and($field->getInputMode())->toBe('email');
});

test('password type', function () {
    $field = TextInput::make('password')->password();

    expect($field->getInputType())->toBe('password');
});

test('numeric type sets number input and decimal mode', function () {
    $field = TextInput::make('price')->numeric();

    expect($field->getInputType())->toBe('number')
        ->and($field->getInputMode())->toBe('decimal');
});

test('integer type sets step to 1', function () {
    $field = TextInput::make('quantity')->integer();

    expect($field->getInputType())->toBe('number')
        ->and($field->getInputMode())->toBe('numeric')
        ->and($field->getStep())->toBe(1);
});

test('min and max length', function () {
    $field = TextInput::make('code')->minLength(3)->maxLength(10);

    expect($field->getMinLength())->toBe(3)
        ->and($field->getMaxLength())->toBe(10);
});

test('min and max value', function () {
    $field = TextInput::make('age')->minValue(0)->maxValue(150);

    expect($field->getMinValue())->toBe(0)
        ->and($field->getMaxValue())->toBe(150);
});

test('mask sets mask attribute', function () {
    $field = TextInput::make('phone')->mask('+420 999 999 999');

    expect($field->getMask())->toBe('+420 999 999 999');
});

test('datalist sets options', function () {
    $field = TextInput::make('color')->datalist(['Red', 'Green', 'Blue']);

    expect($field->getDatalistOptions())->toBe(['Red', 'Green', 'Blue']);
});

test('prefix and suffix', function () {
    $field = TextInput::make('price')
        ->prefix('$')
        ->suffix('.00');

    expect($field->getPrefix())->toBe('$')
        ->and($field->getSuffix())->toBe('.00')
        ->and($field->hasAffix())->toBeTrue();
});

test('prefix and suffix icons', function () {
    $field = TextInput::make('search')
        ->prefixIcon('magnifying-glass')
        ->suffixIcon('x-mark');

    expect($field->getPrefixIcon())->toBe('magnifying-glass')
        ->and($field->getSuffixIcon())->toBe('x-mark');
});

test('revealable flag', function () {
    $field = TextInput::make('password')->password()->revealable();

    expect($field->isRevealable())->toBeTrue();
});

test('validation rules include required', function () {
    $field = TextInput::make('name')->required();

    expect($field->getValidationRules())->toContain('required');
});

test('validation rules merge custom rules', function () {
    $field = TextInput::make('email')->rules(['email', 'max:255'])->required();

    $rules = $field->getValidationRules();

    expect($rules)->toContain('required')
        ->and($rules)->toContain('email')
        ->and($rules)->toContain('max:255');
});

test('state path computed correctly', function () {
    $field = TextInput::make('name')->statePath('data');

    expect($field->getStatePath())->toBe('data.name');
});

test('state path without prefix', function () {
    $field = TextInput::make('name');

    expect($field->getStatePath())->toBe('name');
});

test('disabled state', function () {
    $field = TextInput::make('name')->disabled();

    expect($field->isDisabled())->toBeTrue();
});

test('disabled via closure', function () {
    $field = TextInput::make('name')->disabled(fn () => true);

    expect($field->isDisabled())->toBeTrue();
});

test('hidden and visible', function () {
    $field = TextInput::make('name')->hidden();
    expect($field->isHidden())->toBeTrue();
    expect($field->isVisible())->toBeFalse();

    $field2 = TextInput::make('name')->visible(false);
    expect($field2->isVisible())->toBeFalse();
});

test('live and debounce', function () {
    $field = TextInput::make('search')->live()->debounce(300);

    expect($field->isLive())->toBeTrue()
        ->and($field->getDebounce())->toBe(300)
        ->and($field->getWireModelModifier())->toBe('live')
        ->and($field->getDebounceModifier())->toBe('.debounce.300ms');
});

test('readonly state', function () {
    $field = TextInput::make('name')->readOnly();

    expect($field->isReadOnly())->toBeTrue();
});

test('helper text and hint', function () {
    $field = TextInput::make('name')
        ->helperText('Enter your full name')
        ->hint('This will be displayed publicly');

    expect($field->getHelperText())->toBe('Enter your full name')
        ->and($field->getHint())->toBe('This will be displayed publicly');
});

test('placeholder', function () {
    $field = TextInput::make('name')->placeholder('John Doe');

    expect($field->getPlaceholder())->toBe('John Doe');
});

test('default value', function () {
    $field = TextInput::make('name')->default('John');

    expect($field->getDefault())->toBe('John');
});

test('default value via closure', function () {
    $field = TextInput::make('name')->default(fn () => 'Computed');

    expect($field->getDefault())->toBe('Computed');
});

test('column span', function () {
    $field = TextInput::make('name')->columnSpan(2);

    expect($field->getColumnSpan())->toBe(2);
});

test('column span full', function () {
    $field = TextInput::make('name')->columnSpanFull();

    expect($field->getColumnSpan())->toBe('full');
});

test('extra attributes', function () {
    $field = TextInput::make('name')->extraAttributes(['data-foo' => 'bar']);

    expect($field->getExtraAttributes())->toBe(['data-foo' => 'bar']);
});

test('autofocus', function () {
    $field = TextInput::make('name')->autofocus();

    expect($field->hasAutofocus())->toBeTrue();
});

test('size variants', function () {
    expect(TextInput::make('a')->sm()->getSize())->toBe('sm')
        ->and(TextInput::make('b')->md()->getSize())->toBe('md')
        ->and(TextInput::make('c')->lg()->getSize())->toBe('lg');
});

test('autocomplete attribute', function () {
    $field = TextInput::make('email')->autocomplete('email');

    expect($field->getAutocomplete())->toBe('email');
});

test('tel type', function () {
    $field = TextInput::make('phone')->tel();

    expect($field->getInputType())->toBe('tel')
        ->and($field->getInputMode())->toBe('tel');
});

test('url type', function () {
    $field = TextInput::make('website')->url();

    expect($field->getInputType())->toBe('url')
        ->and($field->getInputMode())->toBe('url');
});
