<?php

declare(strict_types=1);

use Livewire\Component;
use NyonCode\WireForms\Components\TextInput;

/**
 * Host backing a field under the `data` statePath so the reactive `$get`
 * accessor resolves sibling values inside conditioning closures.
 */
class ConditioningSugarHost extends Component
{
    /** @var array<string, mixed> */
    public array $data = ['type' => 'business', 'company' => 'ACME'];

    public function render(): string
    {
        return '<div></div>';
    }
}

function conditioningField(): TextInput
{
    return TextInput::make('company')
        ->statePath('data')
        ->livewire(new ConditioningSugarHost);
}

test('visibleWhen shows the field only when the sibling matches', function () {
    expect(conditioningField()->visibleWhen('type', 'business')->isVisible())->toBeTrue()
        ->and(conditioningField()->visibleWhen('type', 'individual')->isVisible())->toBeFalse();
});

test('visibleWhen accepts a set of matching values', function () {
    expect(conditioningField()->visibleWhen('type', ['business', 'nonprofit'])->isVisible())->toBeTrue();
});

test('hiddenWhen hides the field when the sibling matches', function () {
    expect(conditioningField()->hiddenWhen('type', 'business')->isHidden())->toBeTrue()
        ->and(conditioningField()->hiddenWhen('type', 'individual')->isHidden())->toBeFalse();
});

test('disabledWhen disables the field when the sibling matches', function () {
    expect(conditioningField()->disabledWhen('type', 'business')->isDisabled())->toBeTrue()
        ->and(conditioningField()->disabledWhen('type', 'individual')->isDisabled())->toBeFalse();
});
