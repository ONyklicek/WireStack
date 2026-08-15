<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\ComponentHook;
use Livewire\Livewire;

use function Livewire\store;

/**
 * A `ComponentHook` can decide `skipRender` for itself — no `DataStore` override.
 *
 * This guards a decision rather than a feature, which is why it exists before
 * anything uses it. Row-granular rendering (Filament's `wire:partial`, the one
 * win islands cannot reach — see
 * `architecture/plans/livewire-4-migration-and-performance.md` §5.4.3a) was
 * assessed as blocked on Filament's approach needing an **app-wide container
 * binding**: they swap `DataStore::class` to intercept the *read* of
 * `skipRender`. Two packages doing that in one app silently disable each other,
 * and these packages ship into apps that may already run Filament.
 *
 * It turns out not to be needed. A hook's `call()` may return a closure that
 * runs after the method, Livewire reads `skipRender` once at render — which is
 * after every call and update in the request — and writing the flag from there
 * is honoured. Emission needs no override either: Filament's own hook ships its
 * partials through `$context->addEffect()` in `dehydrate()`, a plain hook API.
 *
 * So if this stops holding, the blocking question for that decision comes back,
 * and it should come back as a failing test rather than as a surprise.
 */
class SrhHost extends Component
{
    public int $renders = 0;

    public function bump(): void {}

    public function render(): string
    {
        $this->renders++;

        return '<div>{{ $this->renders }}</div>';
    }
}

class SrhSkipHook extends ComponentHook
{
    public static bool $active = false;

    public function call($method = null, $params = [], $returnEarly = null, $context = null)
    {
        if (! static::$active) {
            return null;
        }

        // After the method, and before the render that reads the flag.
        return function () {
            store($this->component)->set('skipRender', true);
        };
    }
}

beforeEach(function () {
    SrhSkipHook::$active = false;
    app('livewire')->componentHook(SrhSkipHook::class);
});

// A component hook is registered for the PROCESS, not the test. Left armed, it
// skips the render of every Livewire component in every file that runs after
// this one — 79 failures the first time, none of them here, and none of them
// visible when this suite is run on its own.
afterEach(fn () => SrhSkipHook::$active = false);

it('renders on a method call while the hook stays out of it', function () {
    // The control. A skipped render is proved by counting renders, so the count
    // has to be worth something first.
    $test = Livewire::test(SrhHost::class);

    expect($test->instance()->renders)->toBe(1);

    $test->call('bump');

    expect($test->instance()->renders)->toBe(2);
});

it('skips the render when a hook sets the flag after the method', function () {
    $test = Livewire::test(SrhHost::class);

    SrhSkipHook::$active = true;

    try {
        $test->call('bump');
    } finally {
        SrhSkipHook::$active = false;
    }

    // Mounted once, and the call rendered nothing.
    expect($test->instance()->renders)->toBe(1);
});
