<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroup;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroups;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\Foundation\Routing\Contracts\ConfiguresRoutes;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireCore\Foundation\Routing\RoutePage;

/*
 * The admin's own address.
 *
 * Every page lives under the prefix, so `/admin` itself used to be a 404 — the
 * address people type, and the one `wire:install` now points Fortify's `home`
 * at. It sends a person to the first page they can open, in the sidebar's own
 * order. Written against the router and the Gate rather than a page render:
 * the redirect is the whole of the behaviour.
 */

class PeListPage extends Component
{
    public function render(): string
    {
        return '<div>page</div>';
    }
}

/** An entry whose place in the menu, visibility and permission a test sets. */
abstract class PeEntry implements DescribesResource, ProvidesNavigation, ProvidesPages
{
    use DescribesRecords;

    /** @var array<string, array{group?: string, sort?: int, visible?: bool, permission?: string}> */
    public static array $shape = [];

    public static function modelClass(): ?string
    {
        return null;
    }

    public static function pages(): array
    {
        $permission = static::$shape[static::key()]['permission'] ?? null;

        return ['index' => RoutePage::make(PeListPage::class)->permission($permission)];
    }

    public static function navigation(): NavigationItem
    {
        $shape = static::$shape[static::key()] ?? [];

        return NavigationItem::make()
            ->label(static::key())
            ->group($shape['group'] ?? null)
            ->sort($shape['sort'] ?? 0)
            ->visible($shape['visible'] ?? true);
    }
}

class PeUsers extends PeEntry
{
    public static function key(): string
    {
        return 'pe-users';
    }
}

class PeRoles extends PeEntry
{
    public static function key(): string
    {
        return 'pe-roles';
    }
}

class PeReports extends PeEntry
{
    public static function key(): string
    {
        return 'pe-reports';
    }
}

class PeLanding extends PeEntry implements ConfiguresRoutes
{
    public static function key(): string
    {
        return 'pe-landing';
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

/** A menu heading with a page only in its child. */
class PeHeading extends PeEntry
{
    public static function key(): string
    {
        return 'pe-heading';
    }

    /** No page of its own: a heading, which is what makes its child the way in. */
    public static function pages(): array
    {
        return [];
    }

    public static function navigation(): NavigationItem
    {
        return NavigationItem::make()->label('Heading')->sort(-10)->children([
            NavigationItem::make()->label('Child')->url('/admin/pe-reports'),
        ]);
    }
}

/** An entry that links outside the application. */
class PeOutside extends PeEntry
{
    public static function key(): string
    {
        return 'pe-outside';
    }

    public static function pages(): array
    {
        return [];
    }

    public static function navigation(): NavigationItem
    {
        return NavigationItem::make()->label('Status')->sort(-20)->url('https://status.example.test');
    }
}

beforeEach(function () {
    PeEntry::$shape = [];

    foreach ([PeUsers::class, PeRoles::class, PeReports::class] as $entry) {
        app(ResourceRegistry::class)->register($entry);
    }

    $this->actingAs((new AuthUser)->forceFill(['id' => 1]));
});

function peRoutes(array $only = ['pe-users', 'pe-roles', 'pe-reports'], string $prefix = 'admin'): void
{
    Route::middleware('web')->prefix($prefix)->group(fn () => Route::wireResources(only: $only));

    // What the router does once routes are loaded at boot; a test adding them
    // afterwards has to ask for it, or a name added with the route is unknown.
    Route::getRoutes()->refreshNameLookups();
}

it('answers at the admin s own address and sends a person to the first page', function () {
    PeEntry::$shape = ['pe-users' => ['sort' => 2], 'pe-roles' => ['sort' => 1], 'pe-reports' => ['sort' => 3]];
    peRoutes();

    expect(Route::has('wire.home'))->toBeTrue()
        ->and(route('wire.home', absolute: false))->toBe('/admin');

    $this->get('/admin')->assertRedirect('/admin/pe-roles');
});

it('orders the way the sidebar does — groups first, then the entries in them', function () {
    app(NavigationGroups::class)->register(NavigationGroup::make('people')->sort(20));
    app(NavigationGroups::class)->register(NavigationGroup::make('insight')->sort(10));

    PeEntry::$shape = [
        'pe-users' => ['group' => 'people', 'sort' => 1],
        'pe-roles' => ['group' => 'people', 'sort' => 2],
        // Sorted last inside its group, and still first: its group comes first.
        'pe-reports' => ['group' => 'insight', 'sort' => 99],
    ];
    peRoutes();

    $this->get('/admin')->assertRedirect('/admin/pe-reports');
});

it('passes over an entry the menu hides', function () {
    PeEntry::$shape = ['pe-users' => ['sort' => 1, 'visible' => false], 'pe-roles' => ['sort' => 2], 'pe-reports' => ['sort' => 3]];
    peRoutes();

    $this->get('/admin')->assertRedirect('/admin/pe-roles');
});

it('passes over a page the Gate would refuse, rather than landing on a 403', function () {
    // The shape of the shipped modules: the Users entry is in the menu for
    // everybody, and its route answers 403 to whoever lacks the ability.
    Gate::define('users.viewAny', fn (): bool => false);
    Gate::define('roles.viewAny', fn (): bool => true);

    PeEntry::$shape = [
        'pe-users' => ['sort' => 1, 'permission' => 'users.viewAny'],
        'pe-roles' => ['sort' => 2, 'permission' => 'roles.viewAny'],
    ];
    peRoutes(['pe-users', 'pe-roles']);

    $this->get('/admin')->assertRedirect('/admin/pe-roles');
    $this->get('/admin/pe-users')->assertForbidden();
});

it('refuses when every page refuses, and says not-found when there are none', function () {
    Gate::define('nobody', fn (): bool => false);

    PeEntry::$shape = ['pe-users' => ['permission' => 'nobody']];
    peRoutes(['pe-users']);

    $this->get('/admin')->assertForbidden();

    PeEntry::$shape = ['pe-users' => ['visible' => false]];

    $this->get('/admin')->assertNotFound();
});

it('follows a heading to its first child', function () {
    app(ResourceRegistry::class)->register(PeHeading::class);
    peRoutes(['pe-heading', 'pe-reports']);

    $this->get('/admin')->assertRedirect('/admin/pe-reports');
});

it('takes an entry that leads out of the application as it stands', function () {
    // A menu may link to a status page or a wiki. Nothing here routes it, so
    // there is no `can:` to ask — and it is still the first thing on the menu.
    app(ResourceRegistry::class)->register(PeOutside::class);
    peRoutes(['pe-outside', 'pe-users']);

    $this->get('/admin')->assertRedirect('https://status.example.test');
});

it('leaves the address to a landing page that claims it', function () {
    app(ResourceRegistry::class)->register(PeLanding::class);
    peRoutes(['pe-landing', 'pe-users']);

    // The landing page answers the address; nothing was put in its way.
    expect(Route::has('wire.home'))->toBeFalse()
        ->and(Route::getRoutes()->match(Request::create('/admin'))->getName())->toBe('wire.pe-landing.index');
});

it('never replaces something the application already routed at the prefix', function () {
    Route::get('admin', fn (): string => 'the application s own');
    peRoutes();

    expect(Route::has('wire.home'))->toBeFalse();

    $this->get('/admin')->assertOk()->assertSee('the application s own');
});

it('leaves the application its own route at the group root, even from a nested group', function () {
    // Laravel merges the prefix into every layer of the group stack, so a
    // nested group used to read `sales/sales` and miss the route at `sales`.
    Route::prefix('sales')->group(function () {
        Route::get('/', fn (): string => 'the application s own')->name('app.entry');

        Route::middleware('web')->group(
            fn () => Route::name('sales.')->group(fn () => Route::wireResources(only: ['pe-users'])),
        );
    });
    Route::getRoutes()->refreshNameLookups();

    expect(Route::getRoutes()->getByName('app.entry'))->not->toBeNull()
        ->and(Route::has('sales.wire.home'))->toBeFalse();

    $this->get('/sales')->assertOk()->assertSee('the application s own');
});

it('registers one entry for a group routed in more than one call', function () {
    Route::middleware('web')->prefix('admin')->group(function () {
        Route::wireResources(only: ['pe-users']);
        Route::wireResources(only: ['pe-roles']);
    });

    expect(collect(Route::getRoutes()->get('GET'))->filter(fn ($r) => $r->uri() === 'admin'))->toHaveCount(1);
});

it('is named inside a zone, and stays inside it', function () {
    PeEntry::$shape = ['pe-users' => ['sort' => 1]];

    Route::middleware('web')->name('staff.')->prefix('staff')
        ->group(fn () => Route::wireResources(only: ['pe-users']));
    Route::getRoutes()->refreshNameLookups();

    expect(Route::has('staff.wire.home'))->toBeTrue();

    $this->get('/staff')->assertRedirect('/staff/pe-users');
});
