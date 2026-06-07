<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use NyonCode\WireCore\Notifications\Concerns\InteractsWithNotifications;
use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireCore\Notifications\Drivers\NullDriver;
use NyonCode\WireCore\Notifications\Notification;
use NyonCode\WireCore\Notifications\NotificationManager;

beforeEach(function () {
    NotificationManager::reset();
});

// ─── Notification Value Object ─────────────────────────────────────────

it('can create notifications via shortcuts', function () {
    expect(Notification::success('OK')->type)->toBe('success')
        ->and(Notification::error('Fail')->type)->toBe('error')
        ->and(Notification::warning('Warn')->type)->toBe('warning')
        ->and(Notification::info('Info')->type)->toBe('info');
});

it('is immutable', function () {
    $a = Notification::success('A');
    $b = $a->title('Title B');

    expect($a->title)->toBeNull()
        ->and($b->title)->toBe('Title B')
        ->and($b->message)->toBe('A');
});

it('supports fluent modifiers', function () {
    $n = Notification::make('success', 'Done')
        ->title('Hotovo')
        ->duration(5000)
        ->icon('check')
        ->position('top-right')
        ->extra(['key' => 'value']);

    expect($n->title)->toBe('Hotovo')
        ->and($n->duration)->toBe(5000)
        ->and($n->icon)->toBe('check')
        ->and($n->position)->toBe('top-right')
        ->and($n->extra)->toBe(['key' => 'value']);
});

it('serializes to array filtering nulls', function () {
    $array = Notification::success('Done')->toArray();

    expect($array)->toBe([
        'type' => 'success',
        'message' => 'Done',
    ]);
});

// ─── NotificationManager ───────────────────────────────────────────────

it('can set and reset default driver', function () {
    $driver = new class implements NotificationDriver
    {
        public array $sent = [];

        public function send(Notification $notification, mixed $livewireComponent = null): void
        {
            $this->sent[] = $notification;
        }
    };

    NotificationManager::setDefaultDriver($driver);
    NotificationManager::success('Test');

    expect($driver->sent)->toHaveCount(1)
        ->and($driver->sent[0]->message)->toBe('Test');

    NotificationManager::reset();
});

it('supports explicit driver override', function () {
    $explicit = new class implements NotificationDriver
    {
        public array $sent = [];

        public function send(Notification $notification, mixed $livewireComponent = null): void
        {
            $this->sent[] = $notification;
        }
    };

    NotificationManager::send(Notification::info('Test'), $explicit);

    expect($explicit->sent)->toHaveCount(1);
});

// ─── NullDriver ────────────────────────────────────────────────────────

it('null driver discards notifications silently', function () {
    $driver = new NullDriver;

    // Should not throw
    $driver->send(Notification::success('Test'));

    expect(true)->toBeTrue();
});

// ─── InteractsWithNotifications ────────────────────────────────────────

it('InteractsWithNotifications trait sends via manager', function () {
    $driver = new class implements NotificationDriver
    {
        public array $sent = [];

        public function send(Notification $notification, mixed $livewireComponent = null): void
        {
            $this->sent[] = $notification;
        }
    };

    NotificationManager::setDefaultDriver($driver);

    $component = new class
    {
        use InteractsWithNotifications;
    };

    $component->notifySuccess('Done');
    $component->notifyError('Fail');
    $component->notifyWarning('Warn');
    $component->notifyInfo('Info');

    expect($driver->sent)->toHaveCount(4)
        ->and($driver->sent[0]->type)->toBe('success')
        ->and($driver->sent[1]->type)->toBe('error')
        ->and($driver->sent[2]->type)->toBe('warning')
        ->and($driver->sent[3]->type)->toBe('info');
});

it('InteractsWithNotifications supports per-component driver', function () {
    $componentDriver = new class implements NotificationDriver
    {
        public array $sent = [];

        public function send(Notification $notification, mixed $livewireComponent = null): void
        {
            $this->sent[] = $notification;
        }
    };

    $component = new class
    {
        use InteractsWithNotifications;
    };

    $component->setNotificationDriver($componentDriver);
    $component->notify(Notification::success('Test'));

    expect($componentDriver->sent)->toHaveCount(1)
        ->and($componentDriver->sent[0]->message)->toBe('Test');
});

// ─── Toast container JS helper ─────────────────────────────────────────

it('renders the wireToast JS helper into the toast container', function () {
    $html = Blade::render('<x-wire-notifications::toast-container />');

    expect($html)
        ->toContain('window.wireToast')
        ->toContain("Alpine.magic('toast'")
        ->toContain('table-notification'); // default event name dispatched by helper
});

it('wires the JS helper to a custom event name', function () {
    $html = Blade::render(
        '<x-wire-notifications::toast-container event-name="my-toast" />'
    );

    expect($html)
        ->toContain("eventName = 'my-toast'")
        ->toContain('x-on:my-toast.window');
});
