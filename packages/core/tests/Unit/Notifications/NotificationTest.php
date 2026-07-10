<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use NyonCode\WireCore\Notifications\Concerns\InteractsWithNotifications;
use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireCore\Notifications\Drivers\NullDriver;
use NyonCode\WireCore\Notifications\Notification;
use NyonCode\WireCore\Notifications\NotificationAction;
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

// ─── Persistent / sticky ───────────────────────────────────────────────

it('persistent sets a sticky zero duration', function () {
    $sticky = Notification::info('Stay')->persistent();

    expect($sticky->duration)->toBe(0)
        ->and($sticky->toArray())->toHaveKey('duration', 0); // 0 survives the null filter
});

it('persistent(false) restores auto-dismiss', function () {
    $n = Notification::info('Stay')->persistent()->persistent(false);

    expect($n->duration)->toBeNull()
        ->and($n->toArray())->not->toHaveKey('duration');
});

// ─── Actions ───────────────────────────────────────────────────────────

it('appends an action from the label + event shorthand', function () {
    $n = Notification::success('Deleted')->action('Undo', 'restore-record');

    expect($n->actions)->toHaveCount(1)
        ->and($n->actions[0])->toBeInstanceOf(NotificationAction::class)
        ->and($n->actions[0]->label)->toBe('Undo')
        ->and($n->actions[0]->event)->toBe('restore-record');
});

it('appends multiple actions and preserves order', function () {
    $n = Notification::success('Saved')
        ->action('Undo', 'undo')
        ->action(NotificationAction::make('View', 'view')->payload(['id' => 7]));

    expect($n->actions)->toHaveCount(2)
        ->and($n->actions[1]->payload)->toBe(['id' => 7]);

    $array = $n->toArray();
    expect($array['actions'])->toBe([
        ['label' => 'Undo', 'event' => 'undo', 'close' => true],
        ['label' => 'View', 'event' => 'view', 'payload' => ['id' => 7], 'close' => true],
    ]);
});

it('replaces the action set with actions()', function () {
    $n = Notification::info('X')
        ->action('A', 'a')
        ->actions([NotificationAction::make('B', 'b')]);

    expect($n->actions)->toHaveCount(1)
        ->and($n->actions[0]->label)->toBe('B');
});

it('omits the actions key when there are none', function () {
    expect(Notification::info('X')->toArray())->not->toHaveKey('actions');
});

// ─── NotificationAction ─────────────────────────────────────────────────

it('builds a NotificationAction with fluent modifiers', function () {
    $action = NotificationAction::make('Undo', 'restore')
        ->payload(['id' => 1])
        ->color('primary')
        ->keepOpen();

    expect($action->toArray())->toBe([
        'label' => 'Undo',
        'event' => 'restore',
        'payload' => ['id' => 1],
        'close' => false,
        'color' => 'primary',
    ]);
});

it('defaults a NotificationAction to closing the toast', function () {
    expect(NotificationAction::make('Ok', 'ok')->toArray())->toBe([
        'label' => 'Ok',
        'event' => 'ok',
        'close' => true,
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

it('renders the per-toast countdown bar by default', function () {
    $html = Blade::render('<x-wire-notifications::toast-container />');

    expect($html)
        ->toContain('progress: true')
        ->toContain('barWidth(toast)')
        ->toContain('progress && ! toast.sticky'); // bar is hidden for sticky toasts
});

it('can disable the countdown bar', function () {
    $html = Blade::render('<x-wire-notifications::toast-container :progress="false" />');

    expect($html)->toContain('progress: false');
});

it('renders as a collapsible stack when enabled', function () {
    $html = Blade::render('<x-wire-notifications::toast-container stack />');

    expect($html)
        ->toContain('stack: true')
        ->toContain('cardStyle(index)')      // per-card pile transform
        ->toContain('stack && ! expanded');  // grid-collapse toggle
});

it('is a plain list (not stacked) by default', function () {
    $html = Blade::render('<x-wire-notifications::toast-container />');

    expect($html)->toContain('stack: false');
});

it('resolves the fan-out direction from the anchor position', function () {
    expect(Blade::render('<x-wire-notifications::toast-container position="top-right" />'))
        ->toContain('topAnchored: true');

    expect(Blade::render('<x-wire-notifications::toast-container position="bottom-left" />'))
        ->toContain('topAnchored: false');
});

it('renders action buttons wired to a Livewire dispatch', function () {
    $html = Blade::render('<x-wire-notifications::toast-container />');

    expect($html)
        ->toContain('handleAction(toast, action)')
        ->toContain('window.Livewire.dispatch(action.event')
        ->toContain('toast.actions && toast.actions.length');
});

it('exposes the max-visible overflow cap', function () {
    $html = Blade::render('<x-wire-notifications::toast-container :max="5" />');

    expect($html)
        ->toContain('max: 5')
        ->toContain('visibleList()')
        ->toContain('hiddenCount()');
});

it('respects reduced motion and screen readers', function () {
    $html = Blade::render('<x-wire-notifications::toast-container stack />');

    expect($html)
        ->toContain("matchMedia?.('(prefers-reduced-motion: reduce)')")
        ->toContain('this.reduceMotion || ! this.stack')  // reduced motion never collapses the stack
        ->toContain('motion-reduce:transition-none')
        ->toContain('aria-live="polite"')
        ->toContain('role="status"');
});
