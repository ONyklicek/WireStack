<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as Authenticatable;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationMemory;
use NyonCode\WireCore\Foundation\Preferences\Drivers\NullPreferenceDriver;
use NyonCode\WireCore\Foundation\Preferences\Drivers\SessionPreferenceDriver;

/*
 * Pins and recent pages, per person and per zone.
 *
 * The property that matters most is the one that fails silently: nothing is
 * ever drawn from storage — a stored key that is no longer in the menu the
 * person is looking at is simply not there.
 */
function nmMemory(): NavigationMemory
{
    return new NavigationMemory(app(SessionPreferenceDriver::class));
}

/** @return array<string, NavigationItem> */
function nmMenu(string ...$keys): array
{
    return array_combine($keys, array_map(fn (string $key): NavigationItem => NavigationItem::make(ucfirst($key)), $keys));
}

beforeEach(function () {
    $user = new Authenticatable;
    $user->setAttribute('id', 7);
    $this->be($user);
});

it('keeps nothing, and says so, over the null driver', function () {
    $memory = new NavigationMemory(new NullPreferenceDriver);
    $memory->pin('orders');

    expect($memory->stores())->toBeFalse()
        ->and($memory->pinned())->toBe([]);
});

it('pins and unpins, once each, in the order pinned', function () {
    $memory = nmMemory();

    $memory->pin('orders');
    $memory->pin('invoices');
    $memory->pin('orders');

    expect($memory->stores())->toBeTrue()
        ->and($memory->pinned())->toBe(['orders', 'invoices'])
        ->and($memory->isPinned('invoices'))->toBeTrue();

    $memory->unpin('orders');

    expect($memory->pinned())->toBe(['invoices']);
});

it('keeps the zones apart', function () {
    $memory = nmMemory();

    $memory->pin('orders', 'admin');

    expect($memory->pinned('business'))->toBe([])
        ->and($memory->pinned('admin'))->toBe(['orders'])
        ->and($memory->pinned())->toBe([]);
});

it('remembers the most recent first, without repeats, and keeps the pins', function () {
    $memory = nmMemory();
    $memory->pin('tasks');

    foreach (['orders', 'invoices', 'orders', 'customers'] as $key) {
        $memory->remember($key);
    }

    expect($memory->recent())->toBe(['customers', 'orders', 'invoices'])
        ->and($memory->pinned())->toBe(['tasks']);
});

it('caps what it stores', function () {
    $memory = nmMemory();

    foreach (range(1, 20) as $i) {
        $memory->remember("page-{$i}");
    }

    expect($memory->recent())->toHaveCount(NavigationMemory::RECENT * 2)
        ->and($memory->recent()[0])->toBe('page-20');
});

it('draws only what the current menu still holds', function () {
    $memory = nmMemory();
    $memory->pin('uninstalled');
    $memory->pin('orders');

    expect(array_keys($memory->pinnedEntries(nmMenu('invoices', 'orders'))))->toBe(['orders']);
});

it('leaves the current page and the pinned ones out of the recent row, and draws five', function () {
    $memory = nmMemory();
    $memory->pin('orders');

    foreach (['a', 'b', 'c', 'd', 'e', 'f', 'orders', 'invoices'] as $key) {
        $memory->remember($key);
    }

    $recent = $memory->recentEntries(nmMenu('a', 'b', 'c', 'd', 'e', 'f', 'orders', 'invoices'), current: 'invoices');

    expect(array_keys($recent))->toBe(['f', 'e', 'd', 'c', 'b']);
});

it('reads a malformed bag as empty', function () {
    $driver = app(SessionPreferenceDriver::class);
    $driver->save('navigation', auth()->user(), ['pinned' => 'orders', 'recent' => ['a', 5]]);

    $memory = new NavigationMemory($driver);

    expect($memory->pinned())->toBe([])
        ->and($memory->recent())->toBe(['a']);
});
