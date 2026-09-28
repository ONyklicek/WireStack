<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use NyonCode\WireAdmin\Livewire\NavigationPins;
use NyonCode\WireCore\Core\Plugin\HookDispatch;
use NyonCode\WireCore\Core\Plugin\Hooks\NavigationBuildingPayload;
use NyonCode\WireCore\Core\Plugin\Hooks\PageMountingPayload;
use NyonCode\WireCore\Core\Plugin\HookTarget;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationMemory;
use NyonCode\WireCore\Foundation\Enums\Hook;
use NyonCode\WireCore\Foundation\Preferences\Drivers\SessionPreferenceDriver;

/*
 * Pinned and recent entries (navigation-surfaces.md § 5b).
 *
 * The first test is the one that fails silently: with nothing configured to keep
 * them, pins must not be offered at all — a pin that does nothing is worse than
 * no pin.
 */
beforeEach(function () {
    app(PluginManager::class)->hook(Hook::NavigationBuilding, function (NavigationBuildingPayload $payload) {
        $payload->items['orders'] = NavigationItem::make('Orders')->url('/orders');
        $payload->items['invoices'] = NavigationItem::make('Invoices')->url('/invoices');

        return $payload;
    });

    $user = new Authenticatable;
    $user->setAttribute('id', 3);
    $this->be($user);
});

function npStoring(): void
{
    config()->set('wire-core.preferences.default', 'session');
    config()->set('wire-core.preferences.drivers.session', SessionPreferenceDriver::class);
}

it('offers no pins when nothing would keep them', function () {
    config()->set('wire-core.preferences.default', 'null');

    $html = Blade::render('<x-wire-admin::sidebar />');

    expect($html)->not->toContain('data-testid="admin-nav-pin"')
        ->not->toContain('admin-nav-memory');
});

it('offers a pin on every top-level row once something keeps them', function () {
    npStoring();

    $html = Blade::render('<x-wire-admin::sidebar />');

    expect(substr_count($html, 'data-testid="admin-nav-pin"'))->toBe(2)
        ->and($html)->toContain('wire:id');
});

it('pins, draws the copy, and tells the rows', function () {
    npStoring();

    Livewire::test(NavigationPins::class)
        ->call('toggle', 'orders')
        ->assertDispatched('wire-admin-pinned', keys: ['orders'])
        ->assertSee('data-testid="admin-nav-pinned-item"', false)
        ->call('toggle', 'orders')
        ->assertDispatched('wire-admin-pinned', keys: [])
        ->assertDontSee('data-testid="admin-nav-pinned-item"', false);
});

it('never draws a stored key the menu no longer has', function () {
    npStoring();
    app(NavigationMemory::class)->pin('uninstalled');

    Livewire::test(NavigationPins::class)->assertDontSee('uninstalled');
});

it('remembers a page as it mounts, and leaves the current one out of the row', function () {
    npStoring();

    foreach (['orders', 'invoices'] as $key) {
        HookDispatch::typed(Hook::PageMounting, fn () => new PageMountingPayload(
            page: new stdClass,
            target: new HookTarget(surface: 'page', key: $key),
        ));
    }

    expect(app(NavigationMemory::class)->recent())->toBe(['invoices', 'orders']);

    Livewire::test(NavigationPins::class, ['current' => 'invoices'])
        ->assertSee('data-testid="admin-nav-recent-item"', false)
        ->assertSeeInOrder(['Recent', 'Orders'])
        ->assertDontSee('data-resource="invoices"', false);
});

it('keeps the zones apart', function () {
    npStoring();

    Livewire::test(NavigationPins::class, ['zone' => 'admin'])->call('toggle', 'orders');

    expect(app(NavigationMemory::class)->pinned('business'))->toBe([])
        ->and(app(NavigationMemory::class)->pinned('admin'))->toBe(['orders']);
});

it('offers a pin on a child that names a key, and draws it once pinned', function () {
    npStoring();

    app(PluginManager::class)->hook(Hook::NavigationBuilding, function (NavigationBuildingPayload $payload) {
        $payload->items['system'] = NavigationItem::make('System')->children([
            NavigationItem::make('Server')->url('/system/server')->key('system.server'),
            NavigationItem::make('Handbook')->url('/system/handbook'),
        ]);

        return $payload;
    });

    $html = Blade::render('<x-wire-admin::sidebar />');

    // Orders, Invoices, System and the keyed child — the keyless one has
    // nothing to be kept by.
    expect(substr_count($html, 'data-testid="admin-nav-pin"'))->toBe(4)
        ->and($html)->toContain('data-resource="system.server"');

    Livewire::test(NavigationPins::class)
        ->call('toggle', 'system.server')
        ->assertDispatched('wire-admin-pinned', keys: ['system.server'])
        ->assertSeeHtml('data-testid="admin-nav-pinned-item"')
        ->assertSeeHtml('data-resource="system.server"');
});

it('shows the icon of what pressing the pin does', function (bool $pinned, string $shown, string $hidden) {
    npStoring();

    if ($pinned) {
        app(NavigationMemory::class)->pin('orders');
    }

    $html = Blade::render('<x-wire-admin::sidebar />');
    $pin = substr($html, strpos($html, 'data-resource="orders"', strpos($html, 'data-testid="admin-nav-pin"')));
    $pin = substr($pin, 0, strpos($pin, '</button>'));

    expect($pin)->toMatch('/data-pin-icon="'.$shown.'" x-show="[^"]*"\s*>/')
        ->toMatch('/data-pin-icon="'.$hidden.'" x-show="[^"]*"\s*style="display: none;"/');
})->with([
    'unpinned: bookmark' => [false, 'pin', 'unpin'],
    'pinned: bookmark-slash' => [true, 'unpin', 'pin'],
]);

it('keeps a slot for the pin so it never lies over the badge', function () {
    npStoring();

    app(PluginManager::class)->hook(Hook::NavigationBuilding, function (NavigationBuildingPayload $payload) {
        $payload->items['orders'] = NavigationItem::make('Orders')->url('/orders')->badge(7);

        return $payload;
    });

    $html = Blade::render('<x-wire-admin::sidebar />');

    preg_match('/<a[^>]*data-testid="admin-nav-item"[^>]*data-resource="orders"[^>]*>/', $html, $row);

    expect($row[0])->toMatch('/class="[^"]*\bpe-9\b/')
        ->and($html)->toMatch('/data-testid="admin-nav-pin"[^>]*data-resource="orders"[^>]*class="[^"]*\bend-1\.5\b/');
});
