<?php

declare(strict_types=1);

use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use NyonCode\WireCore\Exceptions\RouteRegistrationException;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesRoutes;
use NyonCode\WireCore\Foundation\Routing\RouteGroups;
use NyonCode\WireCore\Foundation\Routing\WireRoutes;

/*
 * Every package's routes are a group, placed from either side (ADR 0041): by
 * `Route::wire('key')` inside any group the application's route file builds,
 * or by an entry of `wire-core.routes.groups` the framework's route file reads.
 * Both go through WireRoutes, so a group means the same whichever placed it.
 */

/** A group of two routes, which records the options it was handed. */
final class WrShopRoutes implements ProvidesRoutes
{
    /** @var array<int, array<string, mixed>> */
    public static array $calls = [];

    public static function key(): string
    {
        return 'shop';
    }

    public function defaults(): array
    {
        return ['middleware' => ['web', 'auth']];
    }

    public function fixesNames(): bool
    {
        return false;
    }

    public function register(array $options): array
    {
        self::$calls[] = $options;
        $zone = isset($options['zone']) ? $options['zone'].'.' : '';

        return [
            'index' => Route::get('products', fn () => 'list')->name($zone.'shop.index'),
            'show' => Route::get('products/{product}', fn () => 'one')->name($zone.'shop.show'),
        ];
    }
}

/** A group whose names are linked to from a mail. */
final class WrMailedRoutes implements ProvidesRoutes
{
    public static function key(): string
    {
        return 'mailed';
    }

    public function defaults(): array
    {
        return [];
    }

    public function fixesNames(): bool
    {
        return true;
    }

    public function register(array $options): array
    {
        return ['confirm' => Route::get('confirm', fn () => 'ok')->name('mailed.confirm')];
    }
}

beforeEach(function () {
    Route::setRoutes(new RouteCollection);
    WrShopRoutes::$calls = [];
    RouteGroups::instance()->register(WrShopRoutes::class, WrMailedRoutes::class);
});

function wrConfigured(array $groups, array $defaults = []): void
{
    config()->set('wire-core.routes', ['defaults' => $defaults, 'groups' => $groups]);

    app(WireRoutes::class)->registerConfigured();
    Route::getRoutes()->refreshNameLookups();
}

it('places a group inside whatever group the route file builds, and hands its routes back keyed', function () {
    $routes = [];

    Route::middleware(['web', 'can:shop'])->prefix('eshop')->group(function () use (&$routes): void {
        $routes = Route::wire('shop', zone: 'b2b');
        $routes['show']->middleware('throttle:10,1');
    });

    expect(array_keys($routes))->toBe(['index', 'show'])
        ->and($routes['index'])->toBeInstanceOf(RouteDefinition::class)
        ->and($routes['index']->uri())->toBe('eshop/products')
        ->and($routes['index']->getName())->toBe('b2b.shop.index')
        ->and($routes['show']->middleware())->toBe(['web', 'can:shop', 'throttle:10,1'])
        // Named arguments are the group's options.
        ->and(WrShopRoutes::$calls[0])->toBe(['zone' => 'b2b']);
});

it('registers nothing from config until an entry asks', function () {
    wrConfigured([]);

    expect(Route::getRoutes()->count())->toBe(0);
});

it('takes every Laravel group attribute from a config entry', function () {
    wrConfigured(['shop' => [
        'prefix' => 'eshop',
        'domain' => 'shop.example.test',
        'middleware' => ['web', 'auth', 'verified'],
        'without_middleware' => ['verified'],
        'as' => 'front.',
        'where' => ['product' => '[0-9]+'],
        'scope_bindings' => true,
        'can' => 'shop.browse',
    ]]);

    $show = Route::getRoutes()->getByName('front.shop.show');

    expect($show->uri())->toBe('eshop/products/{product}')
        ->and($show->getDomain())->toBe('shop.example.test')
        ->and($show->middleware())->toBe(['web', 'auth', 'verified', 'can:shop.browse'])
        ->and($show->excludedMiddleware())->toBe(['verified'])
        ->and($show->wheres)->toBe(['product' => '[0-9]+'])
        ->and($show->enforcesScopedBindings())->toBeTrue();
});

it('starts an entry from the group s own defaults, under the shared ones, under the entry', function () {
    wrConfigured(['shop' => []]);
    expect(Route::getRoutes()->getByName('shop.index')->middleware())->toBe(['web', 'auth']);

    Route::setRoutes(new RouteCollection);
    wrConfigured(['shop' => []], defaults: ['middleware' => ['web', 'auth', 'verified']]);
    expect(Route::getRoutes()->getByName('shop.index')->middleware())->toBe(['web', 'auth', 'verified']);

    Route::setRoutes(new RouteCollection);
    wrConfigured(['shop' => ['middleware' => ['web']]], defaults: ['middleware' => ['web', 'auth', 'verified']]);
    expect(Route::getRoutes()->getByName('shop.index')->middleware())->toBe(['web']);
});

it('changes single routes by the key the group hands them back under', function () {
    wrConfigured(['shop' => ['routes' => [
        'show' => ['middleware' => ['throttle:10,1'], 'can' => 'shop.view', 'without_middleware' => ['auth'], 'where' => ['product' => '[a-z]+']],
        'nothing-by-this-key' => ['middleware' => ['x']],
    ]]]);

    $show = Route::getRoutes()->getByName('shop.show');

    expect($show->middleware())->toBe(['web', 'auth', 'throttle:10,1', 'can:shop.view'])
        ->and($show->excludedMiddleware())->toBe(['auth'])
        ->and($show->wheres)->toBe(['product' => '[a-z]+'])
        ->and(Route::getRoutes()->getByName('shop.index')->middleware())->toBe(['web', 'auth']);
});

it('reads an entry named otherwise as that group s zone', function () {
    wrConfigured([
        'b2b' => ['uses' => 'shop', 'prefix' => 'b2b'],
        'b2c' => ['uses' => 'shop', 'prefix' => 'b2c', 'zone' => 'retail'],
    ]);

    expect(Route::getRoutes()->getByName('b2b.shop.index')->uri())->toBe('b2b/products')
        ->and(Route::getRoutes()->getByName('retail.shop.index')->uri())->toBe('b2c/products')
        // Only the group's own options reach it — the attributes stay Laravel's.
        ->and(WrShopRoutes::$calls[0])->toBe(['zone' => 'b2b']);
});

it('skips an entry that is false or switched off', function () {
    wrConfigured(['shop' => false, 'b2b' => ['uses' => 'shop', 'enabled' => false]]);

    expect(Route::getRoutes()->count())->toBe(0);
});

it('answers which entries place a group, with their defaults', function () {
    config()->set('wire-core.routes.groups', [
        'b2b' => ['uses' => 'shop', 'prefix' => 'b2b'],
        'shop' => ['prefix' => 'eshop'],
        'off' => ['uses' => 'shop', 'enabled' => false],
        'mailed' => [],
        'unknown' => ['uses' => 'nobody-registered-this'],
        'broken' => 'not an array',
    ]);

    expect(app(WireRoutes::class)->configured('shop'))->toBe([
        'b2b' => ['middleware' => ['web', 'auth'], 'uses' => 'shop', 'prefix' => 'b2b'],
        'shop' => ['middleware' => ['web', 'auth'], 'prefix' => 'eshop'],
    ])->and(app(WireRoutes::class)->configured('nobody-registered-this'))->toBe([]);
});

it('refuses the same route placed twice at two addresses', function () {
    wrConfigured(['shop' => ['prefix' => 'eshop']]);

    expect(fn () => Route::wire('shop'))->toThrow(RouteRegistrationException::class, 'registered twice');
});

it('lets the same route be placed over itself, which changes nothing', function () {
    Route::prefix('eshop')->group(fn () => Route::wire('shop'));
    Route::prefix('eshop')->group(fn () => Route::wire('shop'));

    expect(Route::getRoutes()->count())->toBe(2);
});

it('refuses a named group around a group whose names are linked to', function () {
    Route::name('app.')->group(fn () => Route::wire('mailed'));
})->throws(RouteRegistrationException::class, "Route::wire('mailed')");

it('names the groups it knows when asked for one it does not', function () {
    Route::wire('nope');
})->throws(RouteRegistrationException::class, 'No route group is registered as [nope]');

it('keeps one group per key, the last registration winning', function () {
    $groups = new RouteGroups;
    $groups->register(WrShopRoutes::class)->register(WrShopRoutes::class);

    expect($groups->all())->toBe(['shop' => WrShopRoutes::class])
        ->and($groups->has('shop'))->toBeTrue()
        ->and($groups->get('shop'))->toBeInstanceOf(WrShopRoutes::class);
});
