<?php

declare(strict_types=1);

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Livewire\Component;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;

/**
 * Host whose `data` bag backs a field bound under the `data` statePath, so the
 * reactive `$get` accessor resolves sibling values in conditioning closures.
 */
class HasFormValidationHost extends Component
{
    /** @var array<string, mixed> */
    public array $data = [
        'type' => 'business',
        'company' => 'ACME',
    ];

    public function render(): string
    {
        return '<div></div>';
    }
}

test('rules() accepts a Closure returning an array of rules', function () {
    $field = TextInput::make('name')->rules(fn (): array => ['string', 'max:10']);

    expect($field->getValidationRules())->toBe(['string', 'max:10']);
});

test('rules() accepts a Closure returning a single string rule', function () {
    $field = TextInput::make('name')->rules(fn (): string => 'email');

    expect($field->getValidationRules())->toBe(['email']);
});

test('rules() flattens Closure entries inside a rules array', function () {
    $field = TextInput::make('name')->rules([
        'string',
        fn (): array => ['max:10', 'min:2'],
        fn (): string => 'alpha',
    ]);

    expect($field->getValidationRules())->toBe(['string', 'max:10', 'min:2', 'alpha']);
});

test('rules() Closure can read live sibling state via $get', function () {
    $field = TextInput::make('company')
        ->statePath('data')
        ->livewire(new HasFormValidationHost)
        ->rules(fn (callable $get): array => $get('type') === 'business' ? ['required'] : ['nullable']);

    expect($field->getValidationRules())->toBe(['required']);
});

test('requiredIf makes the field required only when the sibling matches', function () {
    $required = TextInput::make('company')
        ->statePath('data')
        ->livewire(new HasFormValidationHost)
        ->requiredIf('type', 'business');

    $optional = TextInput::make('company')
        ->statePath('data')
        ->livewire(new HasFormValidationHost)
        ->requiredIf('type', 'individual');

    expect($required->isRequired())->toBeTrue()
        ->and($optional->isRequired())->toBeFalse();
});

test('requiredIf accepts a set of accepted values', function () {
    $field = TextInput::make('company')
        ->statePath('data')
        ->livewire(new HasFormValidationHost)
        ->requiredIf('type', ['business', 'nonprofit']);

    expect($field->isRequired())->toBeTrue();
});

test('requiredUnless inverts the match', function () {
    $required = TextInput::make('company')
        ->statePath('data')
        ->livewire(new HasFormValidationHost)
        ->requiredUnless('type', 'individual');

    $optional = TextInput::make('company')
        ->statePath('data')
        ->livewire(new HasFormValidationHost)
        ->requiredUnless('type', 'business');

    expect($required->isRequired())->toBeTrue()
        ->and($optional->isRequired())->toBeFalse();
});

test('requiredWith requires the field when the sibling is filled', function () {
    $host = new HasFormValidationHost;

    $required = TextInput::make('name')
        ->statePath('data')
        ->livewire($host)
        ->requiredWith('company');

    expect($required->isRequired())->toBeTrue();

    $host->data['company'] = '';

    expect($required->isRequired())->toBeFalse();
});

test('resolved rules preserve a Rule object and skip the implicit option constraint', function () {
    $field = Select::make('role')
        ->options(['a' => 'A', 'b' => 'B'])
        ->rules([Rule::in(['a', 'b'])]);

    $rules = $field->getValidationRules();

    expect($rules)->toHaveCount(1)
        ->and($rules[0])->toBeInstanceOf(In::class);
});

test('validatesLive is false by default', function () {
    expect(TextInput::make('name')->validatesLive())->toBeFalse();
});

test('validateLive marks the field and enables live binding', function () {
    $field = TextInput::make('name')->validateLive();

    expect($field->validatesLive())->toBeTrue()
        ->and($field->isLive())->toBeTrue();
});

test('validateOnBlur marks the field and enables blur binding', function () {
    $field = TextInput::make('name')->validateOnBlur();

    expect($field->validatesLive())->toBeTrue()
        ->and($field->isLiveOnBlur())->toBeTrue();
});

test('validateLive(false) leaves the field opted out', function () {
    expect(TextInput::make('name')->validateLive(false)->validatesLive())->toBeFalse();
});

test('validateOnBlur(false) leaves the field opted out', function () {
    expect(TextInput::make('name')->validateOnBlur(false)->validatesLive())->toBeFalse();
});
