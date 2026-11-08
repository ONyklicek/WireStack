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

    // Orders, Invoices and the keyed child — System opens a group, and the
    // keyless child has nothing to be kept by.
    expect(substr_count($html, 'data-testid="admin-nav-pin"'))->toBe(3)
        ->and($html)->toContain('data-resource="system.server"');

    Livewire::test(NavigationPins::class)
        ->call('toggle', 'system.server')
        ->assertDispatched('wire-admin-pinned', keys: ['system.server'])
        ->assertSeeHtml('data-testid="admin-nav-pinned-item"')
        ->assertSeeHtml('data-resource="system.server"');
});

it('never pins the row that opens a group, whether or not its resource has a page', function (string $parent, int $pins, Closure $register) {
    npStoring();
    app(PluginManager::class)->hook(Hook::NavigationBuilding, $register);

    $html = Blade::render('<x-wire-admin::sidebar />');

    // A parent is the heading of its group, never a page, so its pinned copy
    // would be a link standing for a list. Its children are drawn, and pinnable.
    expect($html)->toContain('data-testid="admin-nav-child"')
        ->and(preg_match_all('/data-testid="admin-nav-pin"[^>]*data-resource="'.$parent.'"/', $html))->toBe($pins);
})->with([
    // The hook itself, not a factory: Pest hands a `Closure` parameter the
    // dataset value as it is, without calling it.
    'own children' => ['system', 0, function (NavigationBuildingPayload $payload) {
        $payload->items['system'] = NavigationItem::make('System')->children([
            NavigationItem::make('Server')->url('/system/server')->key('system.server'),
        ]);

        return $payload;
    }],
    'adopted child, parent has a page' => ['orders', 0, function (NavigationBuildingPayload $payload) {
        $payload->items['returns'] = NavigationItem::make('Returns')->url('/returns')->parent('orders');

        return $payload;
    }],
]);

it('refuses to pin a group, and does not draw one pinned before', function () {
    npStoring();
    app(PluginManager::class)->hook(Hook::NavigationBuilding, function (NavigationBuildingPayload $payload) {
        $payload->items['system'] = NavigationItem::make('System')->children([
            NavigationItem::make('Server')->url('/system/server')->key('system.server'),
        ]);

        return $payload;
    });
    app(NavigationMemory::class)->pin('system');

    Livewire::test(NavigationPins::class)
        ->assertDontSeeHtml('data-resource="system"')
        ->call('toggle', 'invoices')
        ->assertDispatched('wire-admin-pinned')
        ->call('toggle', 'system');

    expect(app(NavigationMemory::class)->pinned())->toBe(['system', 'invoices']);
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

it('lets the badge give way to the pin instead of lying under it', function () {
    npStoring();

    app(PluginManager::class)->hook(Hook::NavigationBuilding, function (NavigationBuildingPayload $payload) {
        $payload->items['orders'] = NavigationItem::make('Orders')->url('/orders')->badge(7);

        return $payload;
    });

    $html = Blade::render('<x-wire-admin::sidebar />');

    // No slot kept free: the row is as wide as one without a pin.
    preg_match('/<a[^>]*data-testid="admin-nav-item"[^>]*data-resource="orders"[^>]*>/', $html, $row);
    expect($row[0])->not->toMatch('/class="[^"]*\\bpe-9\\b/');

    // The badge fades while the row is pointed at, and the pin shows then only.
    expect($html)->toMatch('/<span[^>]*class="[^"]*group-hover\/row:opacity-0[^"]*"[^>]*>\s*<[^>]*data-testid="admin-nav-badge"/')
        ->toMatch('/data-testid="admin-nav-pin"[^>]*data-resource="orders"[^>]*class="[^"]*opacity-0[^"]*group-hover\/row:opacity-100/');
});

it('shows a pinned row no differently at rest', function () {
    npStoring();
    app(NavigationMemory::class)->pin('orders');

    $html = Blade::render('<x-wire-admin::sidebar />');

    expect($html)->not->toContain("pinned ? 'opacity-100");
});

it('unpins from the Pinned section and pins from Recent', function () {
    npStoring();
    app(NavigationMemory::class)->pin('orders');
    app(NavigationMemory::class)->remember('invoices');

    Livewire::test(NavigationPins::class)
        ->assertSeeHtml('data-testid="admin-nav-pinned-toggle"')
        ->assertSeeHtml('data-testid="admin-nav-recent-toggle"')
        ->call('toggle', 'orders')
        ->assertDispatched('wire-admin-pinned', keys: [])
        ->call('toggle', 'invoices')
        ->assertDispatched('wire-admin-pinned', keys: ['invoices']);
});

it('switches pins and recent pages on their own', function (bool $pins, bool $recent) {
    npStoring();
    config()->set('wire-admin.navigation.pins', $pins);
    config()->set('wire-admin.navigation.recent', $recent);

    app(NavigationMemory::class)->pin('orders');
    app(NavigationMemory::class)->remember('invoices');

    $html = Blade::render('<x-wire-admin::sidebar />');

    expect(str_contains($html, 'data-testid="admin-nav-pin"'))->toBe($pins)
        ->and(str_contains($html, 'data-testid="admin-nav-pinned-item"'))->toBe($pins)
        ->and(str_contains($html, 'data-testid="admin-nav-recent-item"'))->toBe($recent)
        // A recent entry offers to pin it only where pins are on.
        ->and(str_contains($html, 'data-testid="admin-nav-recent-toggle"'))->toBe($pins && $recent)
        ->and(str_contains($html, 'data-testid="admin-nav-memory"'))->toBe($pins || $recent);
})->with([
    'both' => [true, true],
    'pins only' => [true, false],
    'recent only' => [false, true],
    'neither' => [false, false],
]);

it('writes no pin while pins are off, even from a forged event', function () {
    npStoring();
    config()->set('wire-admin.navigation.pins', false);

    Livewire::test(NavigationPins::class)
        ->call('toggle', 'orders')
        ->assertNotDispatched('wire-admin-pinned');

    expect(app(NavigationMemory::class)->pinned())->toBe([]);
});

it('records no visit while recent pages are off', function (bool $recent, array $expected) {
    npStoring();
    config()->set('wire-admin.navigation.recent', $recent);

    HookDispatch::typed(Hook::PageMounting, fn () => new PageMountingPayload(
        page: new stdClass,
        target: new HookTarget(surface: 'page', key: 'orders'),
    ));

    expect(app(NavigationMemory::class)->recent())->toBe($expected);
})->with([
    'on' => [true, ['orders']],
    'off' => [false, []],
]);
