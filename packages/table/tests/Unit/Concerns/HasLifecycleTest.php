<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionHalt;

// Using Action as a concrete class that uses HasLifecycle trait

// ─── Before Callbacks ────────────────────────────────────────���──────────────

it('has no before callbacks by default', function () {
    $action = Action::make('test');

    expect($action->hasBeforeCallbacks())->toBeFalse()
        ->and($action->getBeforeCallbacks())->toBe([]);
});

it('can register before callbacks', function () {
    $action = Action::make('test')
        ->before(fn () => null)
        ->before(fn () => null);

    expect($action->hasBeforeCallbacks())->toBeTrue()
        ->and($action->getBeforeCallbacks())->toHaveCount(2);
});

// ─── After Callbacks ────────────────────────────────────────────────────────

it('has no after callbacks by default', function () {
    $action = Action::make('test');

    expect($action->hasAfterCallbacks())->toBeFalse()
        ->and($action->getAfterCallbacks())->toBe([]);
});

it('can register after callbacks', function () {
    $action = Action::make('test')
        ->after(fn () => null)
        ->after(fn () => null);

    expect($action->hasAfterCallbacks())->toBeTrue()
        ->and($action->getAfterCallbacks())->toHaveCount(2);
});

// ─── Success Notification ───────────────────────────────────────────────────

it('has no success notification by default', function () {
    expect(Action::make('test')->getSuccessNotificationMessage())->toBeNull();
});

it('can set success notification message', function () {
    $action = Action::make('test')->successNotification('Uloženo');

    expect($action->getSuccessNotificationMessage())->toBe('Uloženo');
});

it('can set dynamic success notification', function () {
    $action = Action::make('test')
        ->successNotification(fn ($record) => "Uloženo: {$record->name}");

    $record = (object) ['name' => 'Test'];

    expect($action->getSuccessNotificationMessage($record))->toBe('Uloženo: Test');
});

// ─── Failure Notification ───────────────────────────────────────────────────

it('has no failure notification by default', function () {
    expect(Action::make('test')->getFailureNotificationMessage())->toBeNull();
});

it('can set failure notification message', function () {
    $action = Action::make('test')->failureNotification('Chyba');

    expect($action->getFailureNotificationMessage())->toBe('Chyba');
});

// ─── Success Redirect ───────────────────────────────────────────────────────

it('has no success redirect by default', function () {
    expect(Action::make('test')->getSuccessRedirectUrl())->toBeNull();
});

it('can set success redirect as string', function () {
    $action = Action::make('test')->successRedirect('/dashboard');

    expect($action->getSuccessRedirectUrl())->toBe('/dashboard');
});

it('can set dynamic success redirect', function () {
    $action = Action::make('test')
        ->successRedirect(fn ($record) => "/users/{$record->id}");

    $record = (object) ['id' => 42];

    expect($action->getSuccessRedirectUrl($record))->toBe('/users/42');
});

// ─── Halt ───────────────────────────────────────────────────────────────────

it('has no pending halt by default', function () {
    expect(Action::make('test')->hasPendingHalt())->toBeFalse();
});

it('can create a halt', function () {
    $action = Action::make('test');
    $halt = $action->halt();

    expect($halt)->toBeInstanceOf(ActionHalt::class)
        ->and($action->hasPendingHalt())->toBeTrue();
});

it('can consume pending halt', function () {
    $action = Action::make('test');
    $action->halt()->heading('Stop');

    $halt = $action->consumePendingHalt();

    expect($halt)->toBeInstanceOf(ActionHalt::class)
        ->and($halt->getModalHeading())->toBe('Stop')
        ->and($action->hasPendingHalt())->toBeFalse();
});

it('consumePendingHalt returns null when no halt', function () {
    expect(Action::make('test')->consumePendingHalt())->toBeNull();
});
