<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\Action;

// Using Action as a concrete class that uses HasVisibility trait

// ─── Hidden ─────────────────────────────────────────────────────────────────

it('is not hidden by default', function () {
    expect(Action::make('test')->isHidden())->toBeFalse();
});

it('can be hidden statically', function () {
    expect(Action::make('test')->hidden()->isHidden())->toBeTrue();
});

it('can be hidden via closure without context', function () {
    $action = Action::make('test')->hidden(fn () => true);

    expect($action->isHidden())->toBeTrue();
});

it('can be hidden via closure with context', function () {
    $action = Action::make('test')->hidden(fn ($record) => $record->locked);

    $locked = (object) ['locked' => true];
    $unlocked = (object) ['locked' => false];

    expect($action->isHidden($locked))->toBeTrue()
        ->and($action->isHidden($unlocked))->toBeFalse();
});

it('visible is inverse of hidden', function () {
    expect(Action::make('test')->visible(false)->isHidden())->toBeTrue()
        ->and(Action::make('test')->visible(true)->isHidden())->toBeFalse();
});

// ─── Disabled ───────────────────────────────────────────────────────────────

it('is not disabled by default', function () {
    expect(Action::make('test')->isDisabled())->toBeFalse();
});

it('can be disabled statically', function () {
    expect(Action::make('test')->disabled()->isDisabled())->toBeTrue();
});

it('can be disabled via closure with context', function () {
    $action = Action::make('test')->disabled(fn ($record) => $record->readonly);

    $readonly = (object) ['readonly' => true];
    $editable = (object) ['readonly' => false];

    expect($action->isDisabled($readonly))->toBeTrue()
        ->and($action->isDisabled($editable))->toBeFalse();
});

// ─── Permission ─────────────────────────────────────────────────────────────

it('has no permission by default', function () {
    expect(Action::make('test')->getPermission())->toBeNull();
});

it('can set permission', function () {
    expect(Action::make('delete')->permission('delete_users')->getPermission())->toBe('delete_users');
});

// ─── canExecute ─────────────────────────────────────────────────────────────

it('can execute by default', function () {
    expect(Action::make('test')->canExecute())->toBeTrue();
});

it('cannot execute when hidden', function () {
    expect(Action::make('test')->hidden()->canExecute())->toBeFalse();
});
