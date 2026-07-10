<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Notifications\Concerns\InteractsWithNotifications;
use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireCore\Notifications\Drivers\CurrentComponentDriver;
use NyonCode\WireCore\Notifications\Notification;
use NyonCode\WireCore\Notifications\NotificationManager;

beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    NotificationManager::reset();
});

afterEach(function () {
    NotificationManager::reset();
});

/**
 * A real Livewire host that notifies WITHOUT passing `$this` anywhere — the
 * default CurrentComponentDriver has to discover the active component itself.
 */
class CurrentComponentNotifyHost extends Component
{
    use InteractsWithNotifications;

    public function ping(): void
    {
        $this->notifySuccess('Saved');
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

it('is the built-in default driver', function () {
    expect(NotificationManager::getDefaultDriver())
        ->toBeInstanceOf(CurrentComponentDriver::class);
});

it('resolves the active Livewire component and dispatches without threading $this', function () {
    Livewire::test(CurrentComponentNotifyHost::class)
        ->call('ping')
        ->assertDispatched('table-notification', type: 'success', message: 'Saved');
});

it('forwards the resolved component to the wrapped driver', function () {
    $captured = new class implements NotificationDriver
    {
        public mixed $component = 'untouched';

        public function send(Notification $notification, mixed $livewireComponent = null): void
        {
            $this->component = $livewireComponent;
        }
    };

    $explicit = new stdClass;

    (new CurrentComponentDriver($captured))->send(Notification::info('X'), $explicit);

    // An explicitly passed component wins over Livewire::current().
    expect($captured->component)->toBe($explicit);
});

it('delegates with a null component when no Livewire component is active', function () {
    $captured = new class implements NotificationDriver
    {
        public bool $called = false;

        public mixed $component = 'untouched';

        public function send(Notification $notification, mixed $livewireComponent = null): void
        {
            $this->called = true;
            $this->component = $livewireComponent;
        }
    };

    // No Livewire request in flight -> current() is empty, delegate still runs.
    (new CurrentComponentDriver($captured))->send(Notification::info('X'));

    expect($captured->called)->toBeTrue()
        ->and($captured->component)->toBeNull();
});

it('defaults to the backwards-compatible session driver', function () {
    // Outside a Livewire request the wrapped SessionDriver still flashes.
    (new CurrentComponentDriver)->send(Notification::success('Flashed'));

    expect(session('table-notification'))->toMatchArray([
        'type' => 'success',
        'message' => 'Flashed',
    ]);
});
