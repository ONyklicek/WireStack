<?php

declare(strict_types=1);

use Livewire\Component;
use NyonCode\WireCore\Core\State\StateContainer;
use NyonCode\WireForms\Components\TextInput;

/**
 * Host Livewire component exposing a `data` state bag, mirroring how a form
 * binds its fields under a statePath.
 */
class ReactiveStateHost extends Component
{
    /** @var array<string, mixed> */
    public array $data = [
        'type' => 'business',
        'name' => 'Acme',
    ];

    public function render(): string
    {
        return '<div></div>';
    }
}

function boundField(string $name = 'name'): TextInput
{
    $host = new ReactiveStateHost;

    return TextInput::make($name)->statePath('data')->livewire($host);
}

test('get accessor reads sibling state through the bound livewire bag', function () {
    $field = boundField();

    $accessors = $field->getStateAccessors();

    expect($accessors['get']('type'))->toBe('business')
        ->and($accessors['get']('missing', 'fallback'))->toBe('fallback');
});

test('state accessor returns the field own value', function () {
    expect(boundField('name')->getStateAccessors()['state'])->toBe('Acme')
        ->and(boundField('type')->getStateAccessors()['state'])->toBe('business');
});

test('get accessor without a path returns the field own live value', function () {
    expect(boundField('name')->getStateAccessors()['get']())->toBe('Acme')
        ->and(boundField('type')->getStateAccessors()['get']())->toBe('business');
});

test('get() reflects a $set made earlier in the same closure, unlike $state', function () {
    $host = new ReactiveStateHost;
    $accessors = TextInput::make('name')->statePath('data')->livewire($host)->getStateAccessors();

    $accessors['set']('name', 'Updated');

    // $state was snapshotted at accessor-build time; $get() reads live.
    expect($accessors['state'])->toBe('Acme')
        ->and($accessors['get']())->toBe('Updated');
});

test('set accessor writes back into the bound livewire bag', function () {
    $host = new ReactiveStateHost;
    $field = TextInput::make('name')->statePath('data')->livewire($host);

    $field->getStateAccessors()['set']('type', 'individual');

    expect($host->data['type'])->toBe('individual');
});

test('visible closure resolves against live state via $get', function () {
    $field = boundField('vat_id')->visible(fn ($get) => $get('type') === 'business');

    expect($field->isVisible())->toBeTrue();
});

test('visible closure hides the field when sibling state differs', function () {
    $host = new ReactiveStateHost;
    $host->data['type'] = 'individual';

    $field = TextInput::make('vat_id')->statePath('data')->livewire($host)
        ->visible(fn ($get) => $get('type') === 'business');

    expect($field->isVisible())->toBeFalse();
});

test('set accessor writes through a StateContainer bag (table action modal)', function () {
    // Mirrors a table action modal: the host's `tableState` bag is a
    // StateContainer, which data_set() cannot write through by reference.
    $host = new class extends Component
    {
        public StateContainer $tableState;

        public function __construct()
        {
            $this->tableState = new StateContainer([
                'modal' => ['action' => ['formData' => ['type' => 'business']]],
            ]);
        }

        public function render(): string
        {
            return '<div></div>';
        }
    };

    $field = TextInput::make('name')
        ->statePath('tableState.modal.action.formData')
        ->livewire($host);

    $field->getStateAccessors()['set']('type', 'individual');

    expect($host->tableState->get('modal.action.formData.type'))->toBe('individual');
});

test('accessors degrade gracefully when no livewire is bound', function () {
    $field = TextInput::make('name')->statePath('data');

    $accessors = $field->getStateAccessors();

    expect($accessors['get']('type', 'default'))->toBe('default')
        ->and($accessors['state'])->toBeNull()
        ->and($accessors['set']('type', 'x'))->toBe('x');
});
