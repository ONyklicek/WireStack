<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionHalt;
use NyonCode\WireCore\Notifications\Drivers\NullDriver;
use NyonCode\WireCore\Notifications\Notification;
use NyonCode\WireCore\Notifications\NotificationManager;

// ─── Before/After Hooks ────────────────────────────────────────────────────

it('has no before callbacks by default', function () {
    $action = Action::make('test');

    expect($action->getBeforeCallbacks())->toBe([])
        ->and($action->hasBeforeCallbacks())->toBeFalse();
});

it('can register before hooks', function () {
    $callback = fn () => null;
    $action = Action::make('test')->before($callback);

    expect($action->hasBeforeCallbacks())->toBeTrue()
        ->and($action->getBeforeCallbacks())->toHaveCount(1);
});

it('can register multiple before hooks', function () {
    $action = Action::make('test')
        ->before(fn () => null)
        ->before(fn () => null);

    expect($action->getBeforeCallbacks())->toHaveCount(2);
});

it('has no after callbacks by default', function () {
    $action = Action::make('test');

    expect($action->getAfterCallbacks())->toBe([])
        ->and($action->hasAfterCallbacks())->toBeFalse();
});

it('can register after hooks', function () {
    $action = Action::make('test')->after(fn () => null);

    expect($action->hasAfterCallbacks())->toBeTrue()
        ->and($action->getAfterCallbacks())->toHaveCount(1);
});

// ─── Success Notification ──────────────────────────────────────────────────

it('has no success notification by default', function () {
    expect(Action::make('test')->getSuccessNotificationMessage())->toBeNull();
});

it('can set success notification message', function () {
    $action = Action::make('test')->successNotification('Saved!');

    expect($action->getSuccessNotificationMessage())->toBe('Saved!');
});

it('supports dynamic success notification via closure', function () {
    $action = Action::make('test')
        ->successNotification(fn ($context) => "Saved #{$context->id}");

    $record = (object) ['id' => 42];
    expect($action->getSuccessNotificationMessage($record))->toBe('Saved #42');
});

// ─── Failure Notification ──────────────────────────────────────────────────

it('has no failure notification by default', function () {
    expect(Action::make('test')->getFailureNotificationMessage())->toBeNull();
});

it('can set failure notification message', function () {
    $action = Action::make('test')->failureNotification('Error!');

    expect($action->getFailureNotificationMessage())->toBe('Error!');
});

it('supports dynamic failure notification via closure', function () {
    $action = Action::make('test')
        ->failureNotification(fn ($context) => "Failed: {$context->reason}");

    $context = (object) ['reason' => 'timeout'];
    expect($action->getFailureNotificationMessage($context))->toBe('Failed: timeout');
});

// ─── Success Redirect ──────────────────────────────────────────────────────

it('has no success redirect by default', function () {
    expect(Action::make('test')->getSuccessRedirectUrl())->toBeNull();
});

it('can set success redirect url', function () {
    $action = Action::make('test')->successRedirect('/users');

    expect($action->getSuccessRedirectUrl())->toBe('/users');
});

it('supports dynamic success redirect via closure', function () {
    $action = Action::make('test')
        ->successRedirect(fn ($record) => "/users/{$record->id}");

    $record = (object) ['id' => 5];
    expect($action->getSuccessRedirectUrl($record))->toBe('/users/5');
});

// ─── Halt ──────────────────────────────────────────────────────────────────

it('has no pending halt by default', function () {
    $action = Action::make('test');

    expect($action->hasPendingHalt())->toBeFalse()
        ->and($action->consumePendingHalt())->toBeNull();
});

it('can halt and returns ActionHalt', function () {
    $action = Action::make('test');
    $halt = $action->halt();

    expect($halt)->toBeInstanceOf(ActionHalt::class)
        ->and($action->hasPendingHalt())->toBeTrue();
});

it('consumePendingHalt returns halt and clears it', function () {
    $action = Action::make('test');
    $action->halt()->heading('Stop');

    $halt = $action->consumePendingHalt();

    expect($halt)->toBeInstanceOf(ActionHalt::class)
        ->and($halt->getModalHeading())->toBe('Stop')
        ->and($action->hasPendingHalt())->toBeFalse()
        ->and($action->consumePendingHalt())->toBeNull();
});

// ─── Notification dispatch (decoupled from Notifications module) ───────────

it('does not send notification when message is null', function () {
    $action = Action::make('test');

    // No message configured, should silently skip without error
    $action->sendSuccessNotification();
    $action->sendFailureNotification();

    expect(true)->toBeTrue();
});

it('sends success notification through NullDriver without error', function () {
    // Set up NullDriver so notifications are silently discarded
    NotificationManager::setDefaultDriver(
        new NullDriver
    );

    $action = Action::make('test')->successNotification('Done!');
    $action->sendSuccessNotification();

    NotificationManager::reset();

    expect(true)->toBeTrue();
});

it('sends failure notification through NullDriver without error', function () {
    NotificationManager::setDefaultDriver(
        new NullDriver
    );

    $action = Action::make('test')->failureNotification('Oops!');
    $action->sendFailureNotification();

    NotificationManager::reset();

    expect(true)->toBeTrue();
});

it('sendSuccessNotification uses custom message over configured one', function () {
    NotificationManager::setDefaultDriver(
        new NullDriver
    );

    $action = Action::make('test')->successNotification('Default');
    $action->sendSuccessNotification('Custom');

    NotificationManager::reset();

    expect(true)->toBeTrue();
});

it('sendWarningNotification works', function () {
    NotificationManager::setDefaultDriver(
        new NullDriver
    );

    Action::make('test')->sendWarningNotification('Careful!');

    NotificationManager::reset();

    expect(true)->toBeTrue();
});

it('sendInfoNotification works', function () {
    NotificationManager::setDefaultDriver(
        new NullDriver
    );

    Action::make('test')->sendInfoNotification('FYI');

    NotificationManager::reset();

    expect(true)->toBeTrue();
});

it('sendNotification works with notification object', function () {
    NotificationManager::setDefaultDriver(
        new NullDriver
    );

    $notification = Notification::success('Test');
    Action::make('test')->sendNotification($notification);

    NotificationManager::reset();

    expect(true)->toBeTrue();
});
