<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ViewErrorBag;
use NyonCode\WireCore\Foundation\ValueObjects\MoneyFormat;
use NyonCode\WireForms\Components\MoneyInput;
use NyonCode\WireForms\Validation\Rules\MoneyAmount;

test('make creates a field that writes whole crowns by default', function () {
    $field = MoneyInput::make('total');

    expect($field->getName())->toBe('total')
        ->and($field->getCurrency())->toBe('CZK')
        ->and($field->getDecimals())->toBe(2)
        // Not type=number: it would refuse the grouped value this field writes.
        ->and($field->getInputType())->toBe('text')
        ->and($field->getInputMode())->toBe('decimal');
});

test('an unstated currency and separators come from the application config', function () {
    config([
        'wire-forms.money.currency' => 'EUR',
        'wire-forms.money.decimal_separator' => '.',
        'wire-forms.money.thousands_separator' => ',',
    ]);

    $field = MoneyInput::make('total');

    expect($field->getCurrency())->toBe('EUR')
        ->and($field->getSuffix())->toBe('EUR')
        ->and($field->hydrateState(1234.5))->toBe('1,234.50')
        ->and($field->getDynamicMask())->toBe("\$money(\$input, '.', ',', 2)");

    // A field that states its own still wins.
    expect(MoneyInput::make('total')->currency('Kč')->separators(',', ' ')->hydrateState(1234))
        ->toBe('1 234');
});

test('currency(null) is a choice, not an unset field', function () {
    config(['wire-forms.money.currency' => 'EUR']);

    // The default would be EUR; asking for no currency must not fall back to it.
    expect(MoneyInput::make('total')->currency(null)->getCurrency())->toBeNull()
        ->and(MoneyInput::make('total')->currency(null)->hasAffix())->toBeFalse();
});

test('the currency renders as the trailing affix, and leads when asked to', function () {
    $trailing = MoneyInput::make('total')->currency('Kč');
    $leading = MoneyInput::make('total')->currency('$')->currencyBefore();

    expect($trailing->getSuffix())->toBe('Kč')
        ->and($trailing->getPrefix())->toBeNull()
        ->and($leading->getPrefix())->toBe('$')
        ->and($leading->getSuffix())->toBeNull()
        ->and($leading->hasAffix())->toBeTrue();
});

test('an explicit affix wins over the currency', function () {
    $field = MoneyInput::make('total')->currency('Kč')->suffix('per unit');

    expect($field->getSuffix())->toBe('per unit');
});

test('a field with no currency renders a bare figure', function () {
    $field = MoneyInput::make('total')->currency(null);

    expect($field->getSuffix())->toBeNull()
        ->and($field->hasAffix())->toBeFalse();
});

test('the currency carries its own precision, and a stated one overrides it', function () {
    expect(MoneyInput::make('total')->currency('Kč')->getDecimals())->toBe(0)
        ->and(MoneyInput::make('total')->currency('Kč', 2)->getDecimals())->toBe(2)
        ->and(MoneyInput::make('total')->currency('Kč')->decimals(3)->getDecimals())->toBe(3);
});

test('the mask is Alpine money, configured from this field format', function () {
    $field = MoneyInput::make('total')->separators('.', ',');

    expect($field->getDynamicMask())->toBe("\$money(\$input, '.', ',', 2)")
        // An expression the owner set stays the owner's.
        ->and(MoneyInput::make('total')->dynamicMask('$input')->getDynamicMask())->toBe('$input');
});

test('a stored amount is written out in the field format, without the currency', function () {
    $field = MoneyInput::make('total')->currency('EUR');

    expect($field->hydrateState(1234.5))->toBe('1 234,50')
        ->and($field->hydrateState('1234.5'))->toBe('1 234,50')
        ->and($field->hydrateState(null))->toBeNull()
        ->and($field->hydrateState(''))->toBeNull();
});

test('a typed amount is read back as a number', function () {
    $field = MoneyInput::make('total')->currency('EUR');

    expect($field->dehydrateState('1 234,50'))->toBe(1234.5)
        ->and($field->dehydrateState('1234.50'))->toBe(1234.5)
        // Rounded at the field's precision, so a pasted third decimal cannot
        // reach a column that has two.
        ->and($field->dehydrateState('1 234,567'))->toBe(1234.57)
        ->and($field->dehydrateState(''))->toBeNull()
        ->and($field->dehydrateState(null))->toBeNull()
        ->and($field->dehydrateState(['nonsense']))->toBeNull();
});

test('minor units travel as whole numbers in both directions', function () {
    $field = MoneyInput::make('total')->currency('EUR')->storeAsMinorUnits();

    expect($field->storesMinorUnits())->toBeTrue()
        ->and($field->hydrateState(123450))->toBe('1 234,50')
        ->and($field->dehydrateState('1 234,50'))->toBe(123450)
        ->and($field->dehydrateState('1 234,505'))->toBe(123451);
});

test('the field validates the amount behind the separators', function () {
    $field = MoneyInput::make('total')->currency('EUR')->minValue(10)->maxValue(1000);

    $rules = $field->getValidationRules();

    expect($rules[0])->toBe('nullable')
        ->and($rules[1])->toBeInstanceOf(MoneyAmount::class);

    $validate = fn (mixed $value): bool => Validator::make(
        ['total' => $value],
        ['total' => $rules],
    )->passes();

    // `numeric` would have rejected every one of these on the separators alone.
    expect($validate('1 000,00'))->toBeTrue()
        ->and($validate('10,00'))->toBeTrue()
        ->and($validate(null))->toBeTrue()
        ->and($validate('9,99'))->toBeFalse()
        ->and($validate('1 000,01'))->toBeFalse()
        ->and($validate('abc'))->toBeFalse();
});

test('a required field keeps its own required rule instead of nullable', function () {
    $rules = MoneyInput::make('total')->required()->getValidationRules();

    expect($rules[0])->toBe('required')
        ->and($rules)->not->toContain('nullable');
});

test('the bounds are reported in the currency they were set in', function () {
    $rule = new MoneyAmount(new MoneyFormat('EUR'), min: 10.0, max: 1000.0);

    $messages = fn (mixed $value): array => Validator::make(
        ['total' => $value],
        ['total' => [$rule]],
    )->errors()->get('total');

    expect($messages('9,99'))->toBe(['The total must be at least 10,00 EUR.'])
        ->and($messages('1 000,01'))->toBe(['The total must not be greater than 1 000,00 EUR.'])
        ->and($messages('—'))->toBe(['The total must be an amount.']);
});

test('it renders through the text input view', function () {
    view()->share('errors', new ViewErrorBag);
    view()->share('_instance', new class
    {
        public function getId(): string
        {
            return 'test-component';
        }
    });

    $html = MoneyInput::make('total')->currency('Kč')->render()->render();

    expect($html)->toContain('x-mask:dynamic')
        ->toContain('inputmode="decimal"')
        ->toContain('Kč');
});
