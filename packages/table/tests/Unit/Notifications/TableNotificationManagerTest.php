<?php

declare(strict_types=1);

use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireCore\Notifications\Drivers\SessionDriver;
use NyonCode\WireCore\Notifications\Notification;
use NyonCode\WireCore\Notifications\NotificationManager;

beforeEach(function () {
    NotificationManager::reset();
});

// ─── Default Driver ─────────────────────────────────────────────────────────

it('uses SessionDriver as default', function () {
    expect(NotificationManager::getDefaultDriver())->toBeInstanceOf(SessionDriver::class);
});

it('can set custom default driver', function () {
    $driver = Mockery::mock(NotificationDriver::class);
    NotificationManager::setDefaultDriver($driver);

    expect(NotificationManager::getDefaultDriver())->toBe($driver);
});

it('reset restores to SessionDriver', function () {
    $driver = Mockery::mock(NotificationDriver::class);
    NotificationManager::setDefaultDriver($driver);
    NotificationManager::reset();

    expect(NotificationManager::getDefaultDriver())->toBeInstanceOf(SessionDriver::class);
});

// ─── Resolution ─────────────────────────────────────────────────────────────

it('resolves explicit driver over default', function () {
    $explicit = Mockery::mock(NotificationDriver::class);
    $resolved = NotificationManager::resolve($explicit);

    expect($resolved)->toBe($explicit);
});

it('resolves to default when null passed', function () {
    expect(NotificationManager::resolve(null))->toBeInstanceOf(SessionDriver::class);
});

// ─── Send ───────────────────────────────────────────────────────────────────

it('sends notification through driver', function () {
    $driver = Mockery::mock(NotificationDriver::class);
    $driver->shouldReceive('send')->once();

    $notification = Notification::success('Test');

    NotificationManager::send($notification, $driver);
});

// ─── Convenience Methods ────────────────────────────────────────────────────

it('success convenience sends success notification', function () {
    $driver = Mockery::mock(NotificationDriver::class);
    $driver->shouldReceive('send')
        ->once()
        ->withArgs(function (Notification $n) {
            return $n->type === 'success' && $n->message === 'Uloženo';
        });

    NotificationManager::success('Uloženo', $driver);
});

it('error convenience sends error notification', function () {
    $driver = Mockery::mock(NotificationDriver::class);
    $driver->shouldReceive('send')
        ->once()
        ->withArgs(function (Notification $n) {
            return $n->type === 'error' && $n->message === 'Chyba';
        });

    NotificationManager::error('Chyba', $driver);
});

it('warning convenience sends warning notification', function () {
    $driver = Mockery::mock(NotificationDriver::class);
    $driver->shouldReceive('send')
        ->once()
        ->withArgs(function (Notification $n) {
            return $n->type === 'warning' && $n->message === 'Pozor';
        });

    NotificationManager::warning('Pozor', $driver);
});

it('info convenience sends info notification', function () {
    $driver = Mockery::mock(NotificationDriver::class);
    $driver->shouldReceive('send')
        ->once()
        ->withArgs(function (Notification $n) {
            return $n->type === 'info' && $n->message === 'Info';
        });

    NotificationManager::info('Info', $driver);
});
