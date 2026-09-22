<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Foundation\Routing\Contracts\AuthorizesUrls;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireCore\Foundation\Routing\RoutePage;
use NyonCode\WirePanels\Routing\ResourceRoutes;
use NyonCode\WirePanels\Routing\RouteAccess;

/*
 * Whether a route would let somebody in, asked before sending them there.
 *
 * The admin's entry and the account link in the user menu both pick a
 * destination, and a destination that answers 403 is worse than none. The
 * question is the route's `can:` middleware put to the Gate — here, once.
 */

class RaPage extends Component
{
    public function render(): string
    {
        return '<div></div>';
    }
}

class RaResource implements DescribesResource, ProvidesPages
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return null;
    }

    public static function key(): string
    {
        return 'ra-rows';
    }

    public static function pages(): array
    {
        return [
            'index' => RaPage::class,
            'mine' => RaPage::class,
            'edit' => RoutePage::make(RaPage::class)->permission('rows.update'),
        ];
    }
}

function raUser(int $id = 1): AuthUser
{
    return (new AuthUser)->forceFill(['id' => $id]);
}

it('lets a route without a permission through', function () {
    $route = Route::get('open', fn () => 'ok');

    expect(app(RouteAccess::class)->allows($route, raUser()))->toBeTrue();
});

it('asks every can: middleware of the Gate, for that person', function () {
    Gate::define('reports.view', fn (AuthUser $user): bool => $user->getKey() === 1);

    $route = Route::middleware(['web', 'can:reports.view'])->get('reports', fn () => 'ok');

    expect(app(RouteAccess::class)->allows($route, raUser(1)))->toBeTrue()
        ->and(app(RouteAccess::class)->allows($route, raUser(2)))->toBeFalse()
        // A guest is asked too, and a Gate closure typed on a user refuses one.
        ->and(app(RouteAccess::class)->allows($route, null))->toBeFalse();
});

it('passes the arguments a can: middleware names', function () {
    Gate::define('manage', fn (AuthUser $user, string $what): bool => $what === 'invoices');

    $invoices = Route::middleware('can:manage,invoices')->get('a', fn () => 'ok');
    $payroll = Route::middleware('can:manage,payroll')->get('b', fn () => 'ok');

    expect(app(RouteAccess::class)->allows($invoices, raUser()))->toBeTrue()
        ->and(app(RouteAccess::class)->allows($payroll, raUser()))->toBeFalse();
});

it('answers for a URL by the route behind it, and lets one it does not route through', function () {
    Gate::define('nobody', fn (): bool => false);
    Route::middleware('can:nobody')->get('closed', fn () => 'no');

    expect(app(RouteAccess::class)->allowsUrl(url('/closed'), raUser()))->toBeFalse()
        ->and(app(RouteAccess::class)->allowsUrl('https://status.example.test/', raUser()))->toBeTrue();
});

it('is the answer core gets when it asks whether a URL may be opened', function () {
    // A core surface — a tour carrying on to another page — asks through the
    // contract and never names this package; this is what it is handed.
    Gate::define('nobody', fn (): bool => false);
    Route::middleware('can:nobody')->get('shut', fn () => 'no');

    expect(app(AuthorizesUrls::class))->toBeInstanceOf(RouteAccess::class)
        ->and(app(AuthorizesUrls::class)->allowsUrl(url('/shut'), raUser()))->toBeFalse();
});

it('routes only the pages asked for when a resource is registered with a list of them', function () {
    Route::middleware('web')->group(fn () => Route::wireResource(RaResource::class, pages: ['mine']));
    Route::getRoutes()->refreshNameLookups();

    expect(Route::has('wire.ra-rows.mine'))->toBeTrue()
        ->and(Route::has('wire.ra-rows.index'))->toBeFalse()
        ->and(Route::has('wire.ra-rows.edit'))->toBeFalse()
        ->and(ResourceRoutes::for(RaResource::class))->toHaveCount(3);
});
