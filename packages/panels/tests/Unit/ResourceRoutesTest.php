<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Component;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WirePanels\Exceptions\ResourceRoutingException;
use NyonCode\WirePanels\Resources\Contracts\ConfiguresResourceRoutes;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourcePages;
use NyonCode\WirePanels\Routing\ResourceRoutes;
use NyonCode\WirePanels\Routing\RoutePage;

/*
 * Registering a resource's pages as routes.
 *
 * The registry holds no URL shell and this does not change that: it registers
 * what an application asks for, inside the group the application put it in. What
 * is worth pinning is the convention (a URL shape and a route name a menu can
 * rely on), that the surrounding group still applies, and that per-resource and
 * per-page configuration reaches the route.
 */

class RtOrderResource implements DescribesResource, ProvidesResourcePages
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return null;
    }

    public static function pages(): array
    {
        return [
            'index' => RtListPage::class,
            'create' => RtCreatePage::class,
            'view' => RtViewPage::class,
            'edit' => RoutePage::make(RtEditPage::class)->permission('orders.update'),
        ];
    }
}

/** Configures its own prefix, domain and middleware. */
class RtTenantResource implements ConfiguresResourceRoutes, DescribesResource, ProvidesResourcePages
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return null;
    }

    public static function pages(): array
    {
        return ['index' => RtListPage::class];
    }

    public static function routeMiddleware(): array
    {
        return ['auth', 'can:tenants.view'];
    }

    public static function routeDomain(): ?string
    {
        return '{tenant}.example.test';
    }

    public static function routePrefix(): ?string
    {
        return 'billing/tenants';
    }
}

/** Registered and routable by hand, deliberately not routed here. */
class RtInternalResource implements DescribesResource
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return null;
    }
}

/*
 * Page stand-ins. Livewire components rather than plain classes: Laravel refuses
 * a route action that is neither invokable nor a component, which is the same
 * refusal an application would get for pointing pages() at the wrong thing.
 */
class RtListPage extends Component
{
    public function render(): string
    {
        return '<div>list</div>';
    }
}

class RtCreatePage extends RtListPage {}
class RtViewPage extends RtListPage {}
class RtEditPage extends RtListPage {}

/**
 * One route by name, with the name lookup refreshed first.
 *
 * A route registered during a test is in the collection but not yet in the name
 * map, which Laravel builds when the application boots. Without the refresh
 * every lookup here answers null and the failure reads as "the macro registered
 * nothing" rather than "the test asked too early".
 */
function rtRoute(string $name): ?Illuminate\Routing\Route
{
    Route::getRoutes()->refreshNameLookups();

    return Route::getRoutes()->getByName($name);
}

function rtRegistry(string ...$resources): ResourceRegistry
{
    $registry = app(ResourceRegistry::class);

    foreach ($resources as $resource) {
        $registry->register($resource);
    }

    return $registry;
}

it('registers the four known page kinds at the shape a menu can predict', function () {
    rtRegistry(RtOrderResource::class);

    Route::wireResources();

    expect(rtRoute('wire.rt-orders.index')?->uri())->toBe('rt-orders')
        ->and(rtRoute('wire.rt-orders.create')?->uri())->toBe('rt-orders/create')
        ->and(rtRoute('wire.rt-orders.view')?->uri())->toBe('rt-orders/{record}')
        ->and(rtRoute('wire.rt-orders.edit')?->uri())->toBe('rt-orders/{record}/edit');
});

it('turns a page permission into Laravel-s own can middleware', function () {
    // Not a check of its own: the framework already answers authorization
    // through Gate everywhere else, and `can:` is how a route asks the same
    // question — so Gate, spatie/laravel-permission and permission-extended all
    // keep working unchanged.
    rtRegistry(RtOrderResource::class);

    Route::wireResources();

    $edit = rtRoute('wire.rt-orders.edit');
    $index = rtRoute('wire.rt-orders.index');

    expect($edit?->gatherMiddleware())->toContain('can:orders.update')
        ->and($index?->gatherMiddleware())->not->toContain('can:orders.update');
});

it('keeps the group the application registered it in', function () {
    // The whole point of the macro rather than a router of our own: prefix,
    // middleware and domain stay the application's, exactly as for any route.
    rtRegistry(RtOrderResource::class);

    Route::prefix('admin')->middleware('auth')->group(function (): void {
        Route::wireResources();
    });

    $index = rtRoute('wire.rt-orders.index');

    expect($index?->uri())->toBe('admin/rt-orders')
        ->and($index?->gatherMiddleware())->toContain('auth');
});

it('lets a resource shape its own prefix, domain and middleware', function () {
    rtRegistry(RtTenantResource::class);

    Route::wireResources();

    $index = rtRoute('wire.rt-tenants.index');

    expect($index?->uri())->toBe('billing/tenants')
        ->and($index?->getDomain())->toBe('{tenant}.example.test')
        ->and($index?->gatherMiddleware())->toContain('auth', 'can:tenants.view');
});

it('skips a resource that declares no pages', function () {
    // Ordinary, not an error: an internal or nested resource stays registered
    // and routable by hand, the same opt-in rule the menu follows.
    rtRegistry(RtInternalResource::class, RtOrderResource::class);

    Route::wireResources();

    expect(rtRoute('wire.rt-internals.index'))->toBeNull()
        ->and(rtRoute('wire.rt-orders.index'))->not->toBeNull();
});

it('says so when asked to route one that declares no pages', function () {
    // Loud on the explicit path, because naming a resource and getting nothing
    // is a mistake rather than a choice.
    expect(fn () => Route::wireResource(RtInternalResource::class))
        ->toThrow(ResourceRoutingException::class);
});

it('honours only', function () {
    rtRegistry(RtOrderResource::class, RtTenantResource::class);

    Route::wireResources(only: ['rt-orders']);

    expect(rtRoute('wire.rt-orders.index'))->not->toBeNull()
        ->and(rtRoute('wire.rt-tenants.index'))->toBeNull();
});

it('honours except', function () {
    // The other half, and separately: an application that routes everything but
    // one resource should not have to list the rest.
    rtRegistry(RtOrderResource::class, RtTenantResource::class);

    Route::wireResources(except: ['rt-orders']);

    expect(rtRoute('wire.rt-orders.index'))->toBeNull()
        ->and(rtRoute('wire.rt-tenants.index'))->not->toBeNull();
});

it('lets a page add middleware and sit at a uri of its own', function () {
    // The rest of RoutePage: a page that needs an extra middleware, and one
    // whose segment is not its kind — an "archive" that lives at /old, say.
    $resource = new class implements DescribesResource, ProvidesResourcePages
    {
        use DescribesRecords;

        public static function modelClass(): ?string
        {
            return null;
        }

        public static function key(): string
        {
            return 'rt-custom';
        }

        public static function pages(): array
        {
            return [
                'archive' => RoutePage::make(RtListPage::class)
                    ->middleware('signed')
                    ->middleware(['throttle:10,1'])
                    ->uri('old'),
            ];
        }
    };

    rtRegistry($resource::class);

    Route::wireResources();

    $route = rtRoute('wire.rt-custom.archive');

    expect($route?->uri())->toBe('rt-custom/old')
        ->and($route?->gatherMiddleware())->toContain('signed', 'throttle:10,1');
});

it('hands a menu the url for a routed key, and null for an unrouted one', function () {
    // What replaces the hand-written key→URL map every application wrote: the
    // workspace keys its entries by resource key, and this turns that key into a
    // link only when a route actually exists.
    rtRegistry(RtOrderResource::class, RtInternalResource::class);

    Route::wireResources();

    Route::getRoutes()->refreshNameLookups();

    expect(ResourceRoutes::urlFor('rt-orders'))->toEndWith('/rt-orders')
        ->and(ResourceRoutes::urlFor('rt-orders', 'edit', ['record' => 7]))->toEndWith('/rt-orders/7/edit')
        ->and(ResourceRoutes::urlFor('rt-internals'))->toBeNull()
        ->and(array_keys(ResourceRoutes::urls()))->toBe(['rt-orders']);
});

it('registers an unknown page kind as its own segment', function () {
    // An application adding 'archive' => ArchivedOrders::class should not have
    // to ask anyone: the kind becomes the segment and the route name.
    $resource = new class implements DescribesResource, ProvidesResourcePages
    {
        use DescribesRecords;

        public static function modelClass(): ?string
        {
            return null;
        }

        public static function key(): string
        {
            return 'rt-extras';
        }

        public static function pages(): array
        {
            return ['archive' => RtListPage::class];
        }
    };

    rtRegistry($resource::class);

    Route::wireResources();

    expect(rtRoute('wire.rt-extras.archive')?->uri())->toBe('rt-extras/archive');
});
