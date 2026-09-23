<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\Foundation\Routing\Contracts\ConfiguresRoutes;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireCore\Widgets\Dashboard;
use NyonCode\WireCore\Widgets\DashboardRegistry;
use NyonCode\WirePanels\Exceptions\ResourceRoutingException;
use NyonCode\WirePanels\Routing\RouteClaims;

/*
 * No route of this package's replaces anybody's, and nobody's replaces a page.
 *
 * Laravel keys routes by method and URI, so the second registration at a path
 * does not shadow the first — it replaces it, name and all, without a word.
 * Either order is possible in a route file, so both are pinned: a page refused
 * over a route that is there, and refused again once every route is loaded if
 * one was registered over it. The entry at a group's root yields in both.
 */

class RcOrders implements DescribesResource, ProvidesPages
{
    use DescribesRecords;

    public static function key(): string
    {
        return 'rc-orders';
    }

    public static function modelClass(): ?string
    {
        return null;
    }

    public static function pages(): array
    {
        return ['index' => RcPage::class, 'create' => RcPage::class];
    }
}

/** A second resource that puts its pages where the first one's are. */
class RcClash extends RcOrders implements ConfiguresRoutes
{
    public static function key(): string
    {
        return 'rc-clash';
    }

    public static function routePrefix(): ?string
    {
        return 'rc-orders';
    }

    public static function routeMiddleware(): array
    {
        return [];
    }

    public static function routeDomain(): ?string
    {
        return null;
    }
}

/** The same pages on a host of their own. */
class RcTenantOrders extends RcClash
{
    public static function key(): string
    {
        return 'rc-tenant-orders';
    }

    public static function routeDomain(): ?string
    {
        return 'tenant.example.test';
    }
}

class RcLanding extends Dashboard implements ConfiguresRoutes, ProvidesPages
{
    public static function key(): string
    {
        return 'rc-landing';
    }

    public function widgets(): array
    {
        return [];
    }

    public static function pages(): array
    {
        return ['index' => RcPage::class];
    }

    public static function routePrefix(): ?string
    {
        return self::ROOT;
    }

    public static function routeMiddleware(): array
    {
        return [];
    }

    public static function routeDomain(): ?string
    {
        return null;
    }
}

class RcPage extends Component
{
    public function render(): string
    {
        return '<div>page</div>';
    }
}

beforeEach(function () {
    app(ResourceRegistry::class)->register(RcOrders::class);
    app(ResourceRegistry::class)->register(RcClash::class);
    app(ResourceRegistry::class)->register(RcTenantOrders::class);
    app(DashboardRegistry::class)->register(RcLanding::class);
});

/** What the application does at boot, once every route file has run. */
function rcBooted(): void
{
    app(RouteClaims::class)->verify();
    Route::getRoutes()->refreshNameLookups();
}

it('refuses a page over a route the application registered before it', function () {
    Route::prefix('sales')->group(function () {
        Route::get('rc-orders', fn (): string => 'the application s own');

        expect(fn () => Route::wireResources(only: ['rc-orders']))->toThrow(
            ResourceRoutingException::class,
            '[rc-orders] routes its [index] page at `sales/rc-orders`, where an unnamed route already answers.',
        );
    });
});

it('refuses a route the application registered over a page after it', function () {
    Route::prefix('sales')->group(function () {
        Route::wireResources(only: ['rc-orders']);
        Route::get('rc-orders/create', fn (): string => 'the application s own')->name('app.create');
    });

    expect(fn () => rcBooted())->toThrow(
        ResourceRoutingException::class,
        '[rc-orders] routed its [create] page at `sales/rc-orders/create`, and the route [app.create] registered there afterwards replaced it.',
    );
});

it('refuses two resources on one path, across two calls', function () {
    Route::prefix('sales')->group(function () {
        Route::wireResources(only: ['rc-orders']);

        expect(fn () => Route::wireResources(only: ['rc-clash']))->toThrow(
            ResourceRoutingException::class,
            'where the route [wire.rc-orders.index] already answers',
        );
    });
});

it('does not count one path on two hosts as one path', function () {
    Route::wireResources(only: ['rc-orders', 'rc-tenant-orders']);
    rcBooted();

    expect(Route::has('wire.rc-orders.index'))->toBeTrue()
        ->and(Route::has('wire.rc-tenant-orders.index'))->toBeTrue();
});

it('lets a page be routed twice over itself', function () {
    // Nothing a menu can see changes: the same page, at the same path, under
    // the same name.
    Route::prefix('sales')->group(function () {
        Route::wireResources(only: ['rc-orders']);
        Route::wireResource(RcOrders::class, ['index']);
    });
    rcBooted();

    expect(Route::has('wire.rc-orders.index'))->toBeTrue();
});

it('lets a landing page from a later call take the root from the entry', function () {
    Route::prefix('sales')->group(function () {
        Route::wireResources(only: ['rc-orders']);
        Route::wireResources(only: ['rc-landing']);
    });
    rcBooted();

    expect(Route::has('wire.home'))->toBeFalse()
        ->and(Route::getRoutes()->match(Request::create('/sales'))->getName())->toBe('wire.rc-landing.index');
});

it('leaves the root to a route the application registers after the entry', function () {
    // The same outcome as the route coming first: the entry is a convenience,
    // and an application routing its own root has decided what answers there.
    Route::prefix('sales')->group(function () {
        Route::wireResources(only: ['rc-orders']);
        Route::get('/', fn (): string => 'the application s own')->name('app.entry');
    });
    rcBooted();

    expect(Route::has('app.entry'))->toBeTrue()
        ->and(Route::has('wire.home'))->toBeFalse();

    $this->get('/sales')->assertOk()->assertSee('the application s own');
});

it('says nothing about routes swapped out wholesale, as route:cache does', function () {
    Route::prefix('sales')->group(fn () => Route::wireResources(only: ['rc-orders']));

    // A new collection, holding none of what was claimed in the old one.
    Route::setRoutes(new RouteCollection);

    expect(fn () => rcBooted())->not->toThrow(ResourceRoutingException::class);
});
