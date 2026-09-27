<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\Exceptions\RouteRegistrationException;
use NyonCode\WireCore\Foundation\Routing\WireRoutes;
use NyonCode\WirePanels\Http\Middleware\RememberPage;
use NyonCode\WirePanels\Routing\PanelRoutes;
use NyonCode\WirePanels\Routing\ResourceRoutes;

/*
 * The panel's pages as a route group placed from config — entries of
 * `wire-core.routes.groups` that the framework's route file registers
 * (ADR 0026 §5, ADR 0027, ADR 0041).
 *
 * What is worth pinning: nothing is registered until an entry asks, the group
 * attributes arrive, the guard is on by default, a zone is the entry's key, and
 * the same page at two addresses under one name is refused.
 */

/** Describe the groups, then do what the framework's route file does. */
function crRoute(array $groups, array $defaults = []): void
{
    config()->set('wire-core.routes', ['defaults' => $defaults, 'groups' => $groups]);

    app(WireRoutes::class)->registerConfigured();

    Route::getRoutes()->refreshNameLookups();
}

it('registers nothing until an entry asks for it', function () {
    app(ResourceRegistry::class)->register(RtOrderResource::class);

    crRoute([]);

    expect(Route::getRoutes()->getByName('wire.rt-orders.index'))->toBeNull();
});

it('skips an entry that is false or switched off', function () {
    app(ResourceRegistry::class)->register(RtOrderResource::class);

    crRoute(['panel' => false, 'admin' => ['uses' => 'panel', 'prefix' => 'admin', 'enabled' => false]]);

    expect(Route::getRoutes()->getByName('wire.rt-orders.index'))->toBeNull()
        ->and(Route::getRoutes()->getByName('admin.wire.rt-orders.index'))->toBeNull();
});

it('registers the pages inside the group config described', function () {
    app(ResourceRegistry::class)->register(RtOrderResource::class);

    crRoute(['panel' => ['prefix' => 'admin', 'middleware' => ['web', 'auth']]]);

    $index = Route::getRoutes()->getByName('wire.rt-orders.index');

    expect($index?->uri())->toBe('admin/rt-orders')
        ->and($index?->gatherMiddleware())->toContain('auth')
        // Per-page configuration still reaches the route: this is the same
        // ResourceRoutes::all() the macro calls, not a second path.
        ->and(Route::getRoutes()->getByName('wire.rt-orders.edit')?->gatherMiddleware())
        ->toContain('can:orders.update');
});

it('puts the whole group on a domain when config names one', function () {
    // The tenant-per-domain case, declared once for every resource rather than
    // per resource — `ConfiguresRoutes::routeDomain()` is still there for the one
    // that differs, and the group's domain is what the rest inherit.
    app(ResourceRegistry::class)->register(RtOrderResource::class);

    crRoute(['panel' => ['domain' => '{tenant}.example.test']]);

    expect(Route::getRoutes()->getByName('wire.rt-orders.index')?->getDomain())
        ->toBe('{tenant}.example.test');
});

it('honours only and except from config', function () {
    app(ResourceRegistry::class)->register(RtOrderResource::class);
    app(ResourceRegistry::class)->register(RtTenantResource::class);

    crRoute(['panel' => ['except' => ['rt-orders']]]);

    expect(Route::getRoutes()->getByName('wire.rt-orders.index'))->toBeNull()
        ->and(Route::getRoutes()->getByName('wire.rt-tenants.index'))->not->toBeNull();
});

it('refuses the same page at another address under the same name', function () {
    // Config places the panel under /admin, and a route file places it again at
    // the root: every page twice under one route name, the second quietly
    // taking the first one's links. The fix is deleting one of the two, which
    // nobody can do while nothing says so.
    app(ResourceRegistry::class)->register(RtOrderResource::class);

    crRoute(['panel' => ['prefix' => 'admin']]);

    expect(fn () => Route::wire('panel'))
        ->toThrow(RouteRegistrationException::class, 'registered twice');
});

it('lets the same page be placed over itself, which changes nothing', function () {
    app(ResourceRegistry::class)->register(RtOrderResource::class);

    crRoute(['panel' => ['prefix' => 'admin']]);
    Route::prefix('admin')->group(fn () => Route::wireResources());
    Route::getRoutes()->refreshNameLookups();

    expect(Route::getRoutes()->getByName('wire.rt-orders.index')?->uri())->toBe('admin/rt-orders');
});

it('ships the config it reads, merged and publishable', function () {
    // The package's first config file. Merged by default, so an application that
    // never publishes it still reads the shipped defaults — and no `enabled`
    // switch any more: the call in the route file is the opt-in.
    expect(config('wire-panels.routes'))->toHaveKeys(['zone_entry', 'tenant_entry'])
        ->and(config('wire-panels.routes'))->not->toHaveKeys(['enabled', 'prefix', 'middleware', 'zones'])
        ->and(array_keys(ServiceProvider::$publishGroups))
        ->toContain('wire-panels::config');
});

it('guards the group it registers, by default', function () {
    // The one default here that is a safety decision rather than a convenience.
    // What this registers is a resource's create, edit and delete screens; a
    // group without `auth` serves every one of them to anybody who knows the
    // URL, and nothing about the panel looks wrong while it does.
    expect((new PanelRoutes)->defaults())->toBe(['middleware' => ['web', 'auth']]);
});

it('lets an application that guards its panel some other way take it out', function () {
    // A public panel, or one behind a gateway, is a real application — the
    // default is what an application gets for saying nothing, not a rule.
    app(ResourceRegistry::class)->register(RtOrderResource::class);

    crRoute(['panel' => ['middleware' => ['web']]]);

    // Only what the application named, plus the page's own bookkeeping —
    // no guard it did not ask for.
    expect(Route::getRoutes()->getByName('wire.rt-orders.index')->gatherMiddleware())
        ->toBe(['web', RememberPage::class]);
});

it('puts the guard on the routes it registers, not only in the config', function () {
    // The config value is only worth anything if it reaches the group. Asserted
    // on the route rather than on the array it came from, which would pass for a
    // registrar that read the key and dropped it.
    app(ResourceRegistry::class)->register(RtOrderResource::class);

    // An entry that names no middleware at all.
    crRoute(['panel' => []]);

    expect(Route::getRoutes()->getByName('wire.rt-orders.index')->gatherMiddleware())
        ->toContain('auth');
});

it('mounts one group per zone, named after the zone key', function () {
    // ADR 0027: several mount points over one catalogue. The array key becomes
    // the route-name prefix, so the same resource in two zones is two routes
    // with two names rather than two routes fighting over one.
    app(ResourceRegistry::class)->register(RtOrderResource::class);

    crRoute([
        'admin' => ['uses' => 'panel', 'prefix' => 'admin', 'middleware' => ['web', 'can:admin']],
        'business' => ['uses' => 'panel', 'prefix' => 'business', 'middleware' => ['web', 'can:business']],
    ]);

    $admin = Route::getRoutes()->getByName('admin.wire.rt-orders.index');
    $business = Route::getRoutes()->getByName('business.wire.rt-orders.index');

    expect($admin?->uri())->toBe('admin/rt-orders')
        ->and($admin?->gatherMiddleware())->toContain('can:admin')
        ->and($business?->uri())->toBe('business/rt-orders')
        ->and($business?->gatherMiddleware())->toContain('can:business')
        // And nothing is left at the unzoned name, which is what a link built
        // without a zone would ask for.
        ->and(Route::getRoutes()->getByName('wire.rt-orders.index'))->toBeNull();
});

it('lets a zone carve out its own membership', function () {
    // Membership is what you routed (ADR 0027 §4) — `only`/`except` per zone,
    // never a second list to keep in step.
    app(ResourceRegistry::class)->register(RtOrderResource::class);
    app(ResourceRegistry::class)->register(RtTenantResource::class);

    crRoute([
        'admin' => ['uses' => 'panel', 'prefix' => 'admin'],
        'business' => ['uses' => 'panel', 'prefix' => 'business', 'only' => ['rt-orders']],
    ]);

    expect(Route::getRoutes()->getByName('admin.wire.rt-tenants.index'))->not->toBeNull()
        ->and(Route::getRoutes()->getByName('business.wire.rt-orders.index'))->not->toBeNull()
        ->and(Route::getRoutes()->getByName('business.wire.rt-tenants.index'))->toBeNull();
});

it('lets every entry inherit the shared defaults and override only what it names', function () {
    // `wire-core.routes.defaults` written once for every group is the ordinary
    // case; an entry that needs more says only that.
    app(ResourceRegistry::class)->register(RtOrderResource::class);

    crRoute([
        'admin' => ['uses' => 'panel', 'prefix' => 'admin'],
        'ops' => ['uses' => 'panel', 'prefix' => 'ops', 'domain' => 'ops.example.test'],
    ], defaults: ['middleware' => ['web', 'auth', 'verified']]);

    $admin = Route::getRoutes()->getByName('admin.wire.rt-orders.index');
    $ops = Route::getRoutes()->getByName('ops.wire.rt-orders.index');

    expect($admin?->gatherMiddleware())->toContain('verified')
        ->and($admin?->getDomain())->toBeNull()
        ->and($ops?->gatherMiddleware())->toContain('verified')
        ->and($ops?->getDomain())->toBe('ops.example.test');
});

it('routes the address above the zones when config names one', function () {
    // In no zone, so behind the shared middleware and no zone's `can:`; and on
    // the shared domain, the one the zones inherit.
    app(ResourceRegistry::class)->register(RtOrderResource::class);

    crRoute([
        'zones' => ['uri' => '/', 'domain' => 'app.example.test'],
        'admin' => ['uses' => 'panel', 'prefix' => 'admin', 'domain' => 'app.example.test', 'middleware' => ['web', 'auth', 'can:admin']],
    ]);

    $entry = Route::getRoutes()->getByName('wire.zones');

    expect($entry?->uri())->toBe('/')
        ->and($entry?->gatherMiddleware())->toBe(['web', 'auth'])
        ->and($entry?->getDomain())->toBe('app.example.test');
});

it('routes no address above the zones unless asked', function () {
    app(ResourceRegistry::class)->register(RtOrderResource::class);

    crRoute(['admin' => ['uses' => 'panel', 'prefix' => 'admin']]);

    expect(Route::getRoutes()->getByName('wire.zones'))->toBeNull();
});

it('answers a url in the zone that was asked for', function () {
    // The one method ADR 0027 had to change: a link is always "where is this key
    // in THIS zone", and a zone that does not route the key answers null — the
    // same answer, and the same rendering, an unrouted key already gets.
    app(ResourceRegistry::class)->register(RtOrderResource::class);
    app(ResourceRegistry::class)->register(RtTenantResource::class);

    crRoute([
        'admin' => ['uses' => 'panel', 'prefix' => 'admin'],
        'business' => ['uses' => 'panel', 'prefix' => 'business', 'only' => ['rt-orders']],
    ]);

    expect(ResourceRoutes::urlFor('rt-orders', zone: 'admin'))->toEndWith('/admin/rt-orders')
        ->and(ResourceRoutes::urlFor('rt-orders', zone: 'business.'))->toEndWith('/business/rt-orders')
        ->and(ResourceRoutes::urlFor('rt-orders'))->toBeNull()
        ->and(ResourceRoutes::urlFor('rt-tenants', zone: 'business'))->toBeNull()
        ->and(array_keys(ResourceRoutes::urls(zone: 'business')))->toBe(['rt-orders']);
});

it('names the zone from a route file too, and a hand-written zone still works', function () {
    app(ResourceRegistry::class)->register(RtOrderResource::class);

    Route::prefix('shop')->group(fn () => Route::wire('panel', zone: 'shop'));
    Route::name('ops.')->prefix('ops')->group(fn () => Route::wireResources());
    Route::getRoutes()->refreshNameLookups();

    expect(Route::getRoutes()->getByName('shop.wire.rt-orders.index')?->uri())->toBe('shop/rt-orders')
        ->and(Route::getRoutes()->getByName('ops.wire.rt-orders.index')?->uri())->toBe('ops/rt-orders');
});

it('routes one resource, or some of its pages', function () {
    Route::wireResource(RtOrderResource::class, ['index']);
    Route::getRoutes()->refreshNameLookups();

    expect(Route::has('wire.rt-orders.index'))->toBeTrue()
        ->and(Route::has('wire.rt-orders.edit'))->toBeFalse();
});
