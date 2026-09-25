<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use NyonCode\WireCore\Core\Resources\Workspace;
use NyonCode\WireCore\Foundation\Registration\Catalog;
use NyonCode\WireCore\Foundation\Registration\ClassDiscovery;
use NyonCode\WirePanels\Exceptions\PageRegistrationException;
use NyonCode\WirePanels\Pages\Page;
use NyonCode\WirePanels\Pages\PageRegistry;

/*
 * A page of the application's own, registered the way a resource is.
 *
 * The property that matters: a registered page reaches all three readers of the
 * catalogue — the router, the menu and wire:resources — with nothing written for
 * it beyond the registration, and its permission guards both the route and the
 * menu entry, so the menu never offers a page the route would refuse.
 */
class PrTaskBoardPage extends Page
{
    protected static string $view = 'pr::content';

    protected ?string $title = 'Task board';

    protected static ?string $navigationIcon = 'outline:view-columns';

    protected static ?string $navigationGroup = 'work';

    protected static int $navigationSort = 30;
}

class PrReport extends Page
{
    protected static string $view = 'pr::content';

    protected static ?string $slug = 'monthly-report';

    protected static ?string $navigationLabel = 'Monthly report';

    protected static ?string $permission = 'reports.view';
}

class PrHidden extends Page
{
    protected static string $view = 'pr::content';

    protected static bool $shouldRegisterNavigation = false;
}

/** Claims the report's key. */
class PrImpostor extends Page
{
    protected static string $view = 'pr::content';

    protected static ?string $slug = 'monthly-report';
}

function prSignIn(array $abilities = []): void
{
    Gate::before(fn ($user, string $ability) => in_array($ability, $abilities, true) ? true : null);

    $user = new Authenticatable;
    $user->setAttribute('id', 1);

    test()->be($user);
}

beforeEach(function () {
    View::addNamespace('pr', __DIR__.'/../fixtures/views/list-tabs');
    View::addLocation(__DIR__.'/../fixtures/views');
    config()->set('livewire.component_layout', 'plain-layout');

    app(PageRegistry::class)->registerMany([PrTaskBoardPage::class, PrReport::class, PrHidden::class]);
});

it('derives a key and a label from the class, or takes them as declared', function () {
    expect(PrTaskBoardPage::key())->toBe('pr-task-board')
        ->and(PrTaskBoardPage::label())->toBe('Pr Task Board')
        ->and(PrReport::key())->toBe('monthly-report')
        ->and(PrReport::label())->toBe('Monthly report');
});

it('joins the catalogue beside the resources', function () {
    expect(app(Catalog::class)->all())->toHaveKeys(['pr-task-board', 'monthly-report', 'pr-hidden']);
});

it('is routed at its key by Route::wireResources(), permission and all', function () {
    Route::middleware('web')->group(fn () => Route::wireResources());

    prSignIn(['reports.view']);
    $this->get('/pr-task-board')->assertOk()->assertSee('Task board');
    $this->get('/monthly-report')->assertOk();
});

it('refuses the route to someone without the page permission', function () {
    Route::middleware('web')->group(fn () => Route::wireResources());
    prSignIn();

    $this->get('/monthly-report')->assertForbidden();
});

it('puts an entry in the menu, grouped, sorted and linked', function () {
    Route::middleware('web')->group(fn () => Route::wireResources());
    // What loading a route file does; routes named here in the test need it.
    app('router')->getRoutes()->refreshNameLookups();
    prSignIn(['reports.view']);

    $items = app(Workspace::class)->items();

    expect($items['pr-task-board']->getLabel())->toBe('Pr Task Board')
        ->and($items['pr-task-board']->getGroup())->toBe('work')
        ->and($items['pr-task-board']->getSort())->toBe(30)
        ->and($items['pr-task-board']->getUrl())->toBe(url('pr-task-board'))
        ->and($items)->toHaveKey('monthly-report');
});

it('hides the entry from someone the route would refuse, and a page that asks to be left out', function () {
    prSignIn();

    $items = app(Workspace::class)->items();

    expect($items)->toHaveKey('pr-task-board')
        ->not->toHaveKey('monthly-report')
        ->not->toHaveKey('pr-hidden');
});

it('shows up in wire:resources with its URI and permission', function () {
    Route::middleware('web')->group(fn () => Route::wireResources());

    Artisan::call('wire:resources');

    expect(Artisan::output())->toContain('monthly-report')->toContain('/monthly-report')->toContain('reports.view');
});

it('registers pages from config and from a discovered folder, lazily', function () {
    $registry = new PageRegistry(app(ClassDiscovery::class));

    config()->set('wire-panels.pages', [PrReport::class, 'not-a-string' => 42]);
    config()->set('wire-core.discover.pages', ['NyonCode\WirePanels\Tests\Nowhere' => '/no/such/dir']);

    expect(array_keys($registry->all()))->toBe(['monthly-report']);
});

it('refuses something that is not a page, and two pages on one key', function () {
    $registry = app(PageRegistry::class);

    expect(fn () => $registry->register(stdClass::class))->toThrow(PageRegistrationException::class, 'does not extend')
        ->and(fn () => $registry->register(PrImpostor::class))->toThrow(PageRegistrationException::class, 'both claim the key');
});
