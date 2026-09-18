<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use NyonCode\WirePanels\Contracts\HasPreferredZone;
use NyonCode\WirePanels\Routing\ZoneDirectory;

/*
 * Where signing in ends when there is more than one zone.
 *
 * `/` above the zones used to be the application's to answer, and the only
 * answer anybody wrote was a page of links — so a person who works in one zone
 * chose it after every sign-in. The entry lands them in a zone without asking:
 * their own preference, the primary zone, or the only one they can reach. A
 * real choice still shows the picker, and `?choose` shows it on request.
 *
 * Zones are declared by hand here, the way a route file declares them, because
 * that is the case config cannot see — the directory reads the router.
 */

/** A person with the zones they may enter, and perhaps one they prefer. */
class ZeUser extends AuthUser implements HasPreferredZone
{
    /** @var list<string> */
    public array $zones = [];

    public ?string $prefers = null;

    public function preferredZone(): ?string
    {
        return $this->prefers;
    }
}

function zeUser(array $zones, ?string $prefers = null): ZeUser
{
    $user = (new ZeUser)->forceFill(['id' => 1]);
    $user->zones = $zones;
    $user->prefers = $prefers;

    return $user;
}

beforeEach(function () {
    Gate::define('zone', fn ($user, string $zone) => in_array($zone, $user->zones ?? [], true));

    // Three zones: one entered through the panel's own entry, one whose root is
    // a landing page, one with a page that takes a record (not an address).
    Route::middleware(['web', 'can:zone,sales'])->name('sales.')->prefix('sales')->group(function () {
        Route::get('orders/{record}', fn () => 'order')->name('wire.orders.view');
        Route::get('orders', fn () => 'orders')->name('wire.orders.index');
        Route::get('', fn () => 'sales home')->name('wire.home');
    });
    Route::middleware(['web', 'can:zone,stock'])->name('stock.')->prefix('stock')->group(function () {
        Route::get('', fn () => 'stock dashboard')->name('wire.stock-dashboard.index');
    });
    Route::middleware(['web', 'can:zone,admin'])->name('admin.')->prefix('admin')->group(function () {
        Route::get('users', fn () => 'users')->name('wire.users.index');
    });
    Route::middleware('web')->group(fn () => Route::wireZoneEntry('/'));

    Route::getRoutes()->refreshNameLookups();
});

it('finds every zone however it was declared, each at its shortest address', function () {
    $zones = app(ZoneDirectory::class)->all();

    expect(array_keys($zones))->toBe(['sales', 'stock', 'admin'])
        ->and($zones['sales']->uri())->toBe('sales')
        ->and($zones['stock']->getName())->toBe('stock.wire.stock-dashboard.index')
        ->and($zones['admin']->uri())->toBe('admin/users');
});

it('lands a person in the only zone they may enter', function () {
    $this->actingAs(zeUser(['stock']))->get('/')->assertRedirect(url('stock'));
});

it('lands them in the primary zone when they may enter several', function () {
    config()->set('wire-panels.routes.zone_entry.primary', 'stock');

    $this->actingAs(zeUser(['sales', 'stock']))->get('/')->assertRedirect(url('stock'));
});

it('prefers the zone the person chose over the primary one', function () {
    config()->set('wire-panels.routes.zone_entry.primary', 'stock');

    $this->actingAs(zeUser(['sales', 'stock', 'admin'], prefers: 'admin'))->get('/')->assertRedirect(url('admin/users'));
});

it('passes over a preference or a primary zone the person may not enter', function () {
    // A stale preference, and a primary zone behind a permission they lack,
    // are wishes — not a redirect into a 403.
    config()->set('wire-panels.routes.zone_entry.primary', 'admin');

    $this->actingAs(zeUser(['stock'], prefers: 'nowhere'))->get('/')->assertRedirect(url('stock'));

    expect(app(ZoneDirectory::class)->landingFor(zeUser(['sales', 'stock'], prefers: 'admin')))->toBeNull();
});

it('shows the picker when the choice is real', function () {
    View::addLocation(__DIR__.'/../fixtures/views');
    config()->set('wire-panels.routes.zone_entry.view', 'zone-picker');

    $this->actingAs(zeUser(['sales', 'admin']))->get('/')
        ->assertOk()
        ->assertSee('data-zone="sales"', false)
        ->assertSee('data-zone="admin"', false)
        ->assertDontSee('data-zone="stock"', false)
        ->assertSee('data-primary="none"', false);
});

it('shows the picker on request, even to somebody who lands straight in a zone', function () {
    View::addLocation(__DIR__.'/../fixtures/views');
    config()->set('wire-panels.routes.zone_entry.view', 'zone-picker');
    config()->set('wire-panels.routes.zone_entry.primary', 'sales');

    $this->actingAs(zeUser(['sales', 'stock']))->get('/?choose=1')
        ->assertOk()
        ->assertSee('data-zone="stock"', false)
        ->assertSee('data-primary="sales"', false);
});

it('takes the first zone they may enter when there is no picker', function () {
    $this->actingAs(zeUser(['stock', 'admin']))->get('/')->assertRedirect(url('stock'));
});

it('refuses somebody who may enter no zone, and says so', function () {
    $this->actingAs(zeUser([]))->get('/')->assertForbidden();
});

it('is a missing page when nothing is zoned', function () {
    Route::setRoutes(new RouteCollection);
    Route::middleware('web')->group(fn () => Route::wireZoneEntry('/'));

    $this->actingAs(zeUser(['sales']))->get('/')->assertNotFound();
});
