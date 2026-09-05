<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\Foundation\Routing\Contracts\RegistersPageRoutes;
use NyonCode\WireCore\WireCoreServiceProvider;
use NyonCode\WirePanels\Exceptions\ResourceRoutingException;
use NyonCode\WirePanels\Routing\ConfiguredRoutes;
use NyonCode\WirePanels\Routing\ResourceRoutes;

/*
 * Routes an application declared in config instead of in a route file
 * (ADR 0026 §5).
 *
 * "Routing is opt-in" is about who decides, not which file the decision is typed
 * in — so what is worth pinning is that nothing happens until an application
 * says so, that the same three group arguments arrive, and that the two
 * registration paths cannot both run.
 */

/**
 * Run the boot path that registers them, with config already set.
 *
 * `bootResources()` rather than a reboot, for the reason the domain-module tests
 * give: config set inside a test does not survive `refreshApplication()`. Which
 * makes this the sharper test anyway — it pins the *call site*. Move the call
 * into an `$app->booted()` callback and this registers nothing, which is exactly
 * the mistake that breaks `route:cache` and cannot be seen from a served page.
 */
function crBoot(array $routes): void
{
    config()->set('wire-panels.routes', $routes + [
        'enabled' => true,
        'prefix' => null,
        'middleware' => ['web'],
        'domain' => null,
        'only' => [],
        'except' => [],
    ]);

    $provider = new WireCoreServiceProvider(app());
    (new ReflectionMethod($provider, 'bootResources'))->invoke($provider);

    Route::getRoutes()->refreshNameLookups();
}

it('registers nothing until an application asks', function () {
    // The default, and the reason it is the default: these routes match before
    // everything in routes/web.php, so opting in has to be a decision.
    app(ResourceRegistry::class)->register(RtOrderResource::class);

    crBoot(['enabled' => false]);

    expect(Route::getRoutes()->getByName('wire.rt-orders.index'))->toBeNull()
        ->and(app()->bound(ConfiguredRoutes::MARKER))->toBeFalse();
});

it('registers the pages inside the group config described', function () {
    app(ResourceRegistry::class)->register(RtOrderResource::class);

    crBoot(['prefix' => 'admin', 'middleware' => ['web', 'auth']]);

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

    crBoot(['domain' => '{tenant}.example.test']);

    expect(Route::getRoutes()->getByName('wire.rt-orders.index')?->getDomain())
        ->toBe('{tenant}.example.test');
});

it('honours only and except from config', function () {
    app(ResourceRegistry::class)->register(RtOrderResource::class);
    app(ResourceRegistry::class)->register(RtTenantResource::class);

    crBoot(['except' => ['rt-orders']]);

    expect(Route::getRoutes()->getByName('wire.rt-orders.index'))->toBeNull()
        ->and(Route::getRoutes()->getByName('wire.rt-tenants.index'))->not->toBeNull();
});

it('refuses the macro once config already registered them', function () {
    // Both paths registers every page twice under one route name, the second
    // quietly winning the name lookup. The fix is deleting one line, which
    // nobody can do while nothing says so.
    app(ResourceRegistry::class)->register(RtOrderResource::class);

    crBoot([]);

    expect(fn () => Route::wireResources())
        ->toThrow(ResourceRoutingException::class, 'already registered');
});

it('leaves the macro alone when config registered nothing', function () {
    app(ResourceRegistry::class)->register(RtOrderResource::class);

    crBoot(['enabled' => false]);

    Route::wireResources();
    Route::getRoutes()->refreshNameLookups();

    expect(Route::getRoutes()->getByName('wire.rt-orders.index'))->not->toBeNull();
});

it('ships the config it reads, merged and publishable', function () {
    // The package's first config file. Merged by default, so an application that
    // never publishes it still gets `enabled => false` rather than a missing key
    // the registrar would read as "no".
    expect(config('wire-panels.routes.enabled'))->toBeFalse()
        ->and(config('wire-panels.routes.middleware'))->toBe(['web'])
        ->and(array_keys(ServiceProvider::$publishGroups))
        ->toContain('wire-panels::config');
});

it('mounts one group per zone, named after the zone key', function () {
    // ADR 0027: several mount points over one catalogue. The array key becomes
    // the route-name prefix, so the same resource in two zones is two routes
    // with two names rather than two routes fighting over one.
    app(ResourceRegistry::class)->register(RtOrderResource::class);

    crBoot(['zones' => [
        'admin' => ['prefix' => 'admin', 'middleware' => ['web', 'can:admin']],
        'business' => ['prefix' => 'business', 'middleware' => ['web', 'can:business']],
    ]]);

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

    crBoot(['zones' => [
        'admin' => ['prefix' => 'admin'],
        'business' => ['prefix' => 'business', 'only' => ['rt-orders']],
    ]]);

    expect(Route::getRoutes()->getByName('admin.wire.rt-tenants.index'))->not->toBeNull()
        ->and(Route::getRoutes()->getByName('business.wire.rt-orders.index'))->not->toBeNull()
        ->and(Route::getRoutes()->getByName('business.wire.rt-tenants.index'))->toBeNull();
});

it('lets a zone inherit the shared values and override only what it names', function () {
    // `middleware => ['web']` written once for every zone is the ordinary case;
    // a zone that needs more says only that.
    app(ResourceRegistry::class)->register(RtOrderResource::class);

    crBoot([
        'middleware' => ['web', 'auth'],
        'zones' => [
            'admin' => ['prefix' => 'admin'],
            'ops' => ['prefix' => 'ops', 'domain' => 'ops.example.test'],
        ],
    ]);

    $admin = Route::getRoutes()->getByName('admin.wire.rt-orders.index');
    $ops = Route::getRoutes()->getByName('ops.wire.rt-orders.index');

    expect($admin?->gatherMiddleware())->toContain('auth')
        ->and($admin?->getDomain())->toBeNull()
        ->and($ops?->gatherMiddleware())->toContain('auth')
        ->and($ops?->getDomain())->toBe('ops.example.test');
});

it('answers a url in the zone that was asked for', function () {
    // The one method ADR 0027 had to change: a link is always "where is this key
    // in THIS zone", and a zone that does not route the key answers null — the
    // same answer, and the same rendering, an unrouted key already gets.
    app(ResourceRegistry::class)->register(RtOrderResource::class);
    app(ResourceRegistry::class)->register(RtTenantResource::class);

    crBoot(['zones' => [
        'admin' => ['prefix' => 'admin'],
        'business' => ['prefix' => 'business', 'only' => ['rt-orders']],
    ]]);

    expect(ResourceRoutes::urlFor('rt-orders', zone: 'admin'))->toEndWith('/admin/rt-orders')
        ->and(ResourceRoutes::urlFor('rt-orders', zone: 'business.'))->toEndWith('/business/rt-orders')
        ->and(ResourceRoutes::urlFor('rt-orders'))->toBeNull()
        ->and(ResourceRoutes::urlFor('rt-tenants', zone: 'business'))->toBeNull()
        ->and(array_keys(ResourceRoutes::urls(zone: 'business')))->toBe(['rt-orders']);
});

it('is bound during register, so core sees it before core boots', function () {
    // The ordering this seam exists to remove: package boot order is
    // PackageManifest order, so a routing package that registered its own routes
    // in its own boot() might run before the registries are filled and register
    // nothing — which looks exactly like a resource that declares no pages.
    expect(app(RegistersPageRoutes::class))->toBeInstanceOf(ConfiguredRoutes::class);
});
