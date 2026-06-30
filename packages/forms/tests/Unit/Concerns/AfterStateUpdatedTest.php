<?php

declare(strict_types=1);

use Livewire\Component;
use NyonCode\WireForms\Components\TextInput;

/**
 * Host whose `data` bag mirrors a form binding under a statePath.
 */
class AfterStateHost extends Component
{
    /** @var array<string, mixed> */
    public array $data = [
        'type' => 'business',
        'vat_id' => 'OLD',
    ];

    public function render(): string
    {
        return '<div></div>';
    }
}

test('afterStateUpdated is not registered by default', function () {
    expect(TextInput::make('type')->hasAfterStateUpdated())->toBeFalse();
});

test('afterStateUpdated enables live so the hook can fire', function () {
    $field = TextInput::make('type')->afterStateUpdated(fn () => null);

    expect($field->hasAfterStateUpdated())->toBeTrue()
        ->and($field->isLive())->toBeTrue();
});

test('afterStateUpdated(null) clears a previously registered callback', function () {
    $field = TextInput::make('type')->afterStateUpdated(fn () => null);

    expect($field->afterStateUpdated(null)->hasAfterStateUpdated())->toBeFalse();
});

test('runAfterStateUpdated receives the new state, old value and reactive accessors', function () {
    $host = new AfterStateHost;
    $seen = [];

    $field = TextInput::make('type')
        ->statePath('data')
        ->livewire($host)
        ->afterStateUpdated(function ($state, $old, $get, $set) use (&$seen) {
            $seen = ['state' => $state, 'old' => $old, 'sibling' => $get('vat_id')];

            // A $set from the callback must persist back into the bound bag.
            $set('vat_id', $state === 'business' ? 'NEW' : null);
        });

    $field->runAfterStateUpdated('individual');

    expect($seen['state'])->toBe('business')
        ->and($seen['old'])->toBe('individual')
        ->and($seen['sibling'])->toBe('OLD')
        ->and($host->data['vat_id'])->toBe('NEW');
});

test('runAfterStateUpdated is a no-op without a registered callback', function () {
    $field = TextInput::make('type')->statePath('data')->livewire(new AfterStateHost);

    $field->runAfterStateUpdated('x');

    expect($field->hasAfterStateUpdated())->toBeFalse();
});
