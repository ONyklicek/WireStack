<?php

declare(strict_types=1);

use NyonCode\WireCore\Notifications\Drivers\FlasherDriver;
use NyonCode\WireCore\Notifications\Drivers\LivewireEventDriver;
use NyonCode\WireCore\Notifications\Drivers\NullDriver;
use NyonCode\WireCore\Notifications\Notification;

/**
 * Minimal chainable stand-in for the optional php-flasher service so the
 * FlasherDriver integration path can be exercised without the real package.
 */
class FakeFlasher
{
    /** @var array<int, array{0: string, 1: mixed}> */
    public array $optionCalls = [];

    /** @var array<int, array<string, mixed>> */
    public array $optionsCalls = [];

    /** @var array<int, array{0: string, 1: string}> */
    public array $flashes = [];

    public ?string $adapterUsed = null;

    public function option(string $key, mixed $value): static
    {
        $this->optionCalls[] = [$key, $value];

        return $this;
    }

    /** @param array<string, mixed> $options */
    public function options(array $options): static
    {
        $this->optionsCalls[] = $options;

        return $this;
    }

    public function addFlash(string $type, string $message): static
    {
        $this->flashes[] = [$type, $message];

        return $this;
    }

    public function __call(string $name, array $arguments): static
    {
        // Used for the adapter selector: flash()->toastr()
        $this->adapterUsed = $name;

        return $this;
    }
}

if (! function_exists('flash')) {
    function flash(): FakeFlasher
    {
        return $GLOBALS['__fake_flasher'] ??= new FakeFlasher;
    }
}

beforeEach(function () {
    $GLOBALS['__fake_flasher'] = new FakeFlasher;
});

// ─── NullDriver ────────────────────────────────────────────────

it('NullDriver silently discards notifications', function () {
    (new NullDriver)->send(Notification::success('hi'));

    expect(true)->toBeTrue(); // no exception, nothing emitted
});

// ─── LivewireEventDriver ───────────────────────────────────────

it('LivewireEventDriver dispatches a browser event to the component', function () {
    $component = new class
    {
        public array $dispatched = [];

        public function dispatch(string $event, mixed ...$params): void
        {
            $this->dispatched = [$event, $params];
        }
    };

    (new LivewireEventDriver('toast'))->send(
        Notification::success('Saved')->title('Done'),
        $component,
    );

    expect($component->dispatched[0])->toBe('toast')
        ->and($component->dispatched[1])->toMatchArray(['type' => 'success', 'message' => 'Saved', 'title' => 'Done']);
});

it('LivewireEventDriver defaults the event name', function () {
    $component = new class
    {
        public string $event = '';

        public function dispatch(string $event, mixed ...$params): void
        {
            $this->event = $event;
        }
    };

    (new LivewireEventDriver)->send(Notification::info('Hi'), $component);

    expect($component->event)->toBe('table-notification');
});

it('LivewireEventDriver falls back to the session without a component', function () {
    (new LivewireEventDriver)->send(Notification::error('Boom'));

    expect(session()->get('table-notification'))->toMatchArray(['type' => 'error', 'message' => 'Boom']);
});

it('LivewireEventDriver falls back to the session when dispatch is missing', function () {
    (new LivewireEventDriver)->send(Notification::warning('Careful'), new stdClass);

    expect(session()->get('table-notification'))->toMatchArray(['type' => 'warning', 'message' => 'Careful']);
});

// ─── FlasherDriver ─────────────────────────────────────────────

it('FlasherDriver sends a flash with title, duration and position options', function () {
    (new FlasherDriver)->send(
        Notification::success('Saved')->title('Done')->duration(3000)->position('top-end'),
    );

    $flasher = $GLOBALS['__fake_flasher'];

    expect($flasher->flashes)->toBe([['success', 'Saved']])
        ->and($flasher->optionCalls)->toBe([['title', 'Done']])
        ->and($flasher->optionsCalls)->toBe([['timeout' => 3000, 'position' => 'top-end']]);
});

it('FlasherDriver sends a bare flash with no options', function () {
    (new FlasherDriver)->send(Notification::info('Plain'));

    $flasher = $GLOBALS['__fake_flasher'];

    expect($flasher->flashes)->toBe([['info', 'Plain']])
        ->and($flasher->optionCalls)->toBe([])
        ->and($flasher->optionsCalls)->toBe([]);
});

it('FlasherDriver uses a configured adapter', function () {
    (new FlasherDriver('toastr'))->send(Notification::success('Saved'));

    expect($GLOBALS['__fake_flasher']->adapterUsed)->toBe('toastr');
});

it('FlasherDriver maps notification types to flasher types', function () {
    $driver = new FlasherDriver;

    $map = [
        'success' => 'success',
        'error' => 'error',
        'warning' => 'warning',
        'info' => 'info',
    ];

    foreach ($map as $type => $expected) {
        $GLOBALS['__fake_flasher'] = new FakeFlasher;
        $driver->send(Notification::make($type, 'msg'));
        expect($GLOBALS['__fake_flasher']->flashes[0][0])->toBe($expected);
    }

    // 'danger' maps to 'error'; unknown types fall back to 'info'.
    $GLOBALS['__fake_flasher'] = new FakeFlasher;
    $driver->send(Notification::make('danger', 'msg'));
    expect($GLOBALS['__fake_flasher']->flashes[0][0])->toBe('error');

    $GLOBALS['__fake_flasher'] = new FakeFlasher;
    $driver->send(Notification::make('weird', 'msg'));
    expect($GLOBALS['__fake_flasher']->flashes[0][0])->toBe('info');
});
