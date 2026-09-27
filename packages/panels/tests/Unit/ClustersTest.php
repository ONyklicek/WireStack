<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Livewire\Component;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\ActiveNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\Core\Resources\Workspace;
use NyonCode\WireCore\Foundation\Routing\Contracts\BelongsToCluster;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireCore\Foundation\Routing\RoutePage;
use NyonCode\WirePanels\Clusters\Cluster;
use NyonCode\WirePanels\Clusters\ClusterNavigation;
use NyonCode\WirePanels\Enums\SubNavigationPosition;
use NyonCode\WirePanels\Exceptions\ClusterException;
use NyonCode\WirePanels\Pages\Page;
use NyonCode\WirePanels\Pages\PageRegistry;

/*
 * A cluster: one section, one menu entry, one prefix, and the way across
 * between its screens on each of them (ADR 0039).
 *
 * The property worth pinning is that the three readers of membership agree — a
 * member the router put under the cluster's prefix is in the cluster's tabs,
 * out of the grouped menu, and guarded by the cluster's permission.
 */
class ClSettings extends Cluster
{
    protected static ?string $slug = 'settings';

    protected static ?string $navigationIcon = 'outline:cog-6-tooth';

    protected static ?string $navigationGroup = 'admin';

    public static SubNavigationPosition $position = SubNavigationPosition::Start;

    public static function subNavigationPosition(): SubNavigationPosition
    {
        return self::$position;
    }
}

class ClGuardedSettings extends Cluster
{
    protected static ?string $slug = 'guarded';

    protected static ?string $permission = 'settings.enter';
}

class ClEmptyCluster extends Cluster
{
    protected static ?string $slug = 'empty';
}

class ClListPage extends Component
{
    public function render(): string
    {
        return '<div>list</div>';
    }
}

class ClCurrencyResource implements BelongsToCluster, DescribesResource, ProvidesNavigation, ProvidesPages
{
    use DescribesRecords;

    /** @var class-string|null */
    public static ?string $in = ClSettings::class;

    public static bool $shown = true;

    public static function modelClass(): ?string
    {
        return null;
    }

    public static function key(): string
    {
        return 'currencies';
    }

    public static function cluster(): ?string
    {
        return self::$in;
    }

    public static function pages(): array
    {
        return [
            'index' => RoutePage::make(ClListPage::class)->permission('currencies.view'),
            'edit' => RoutePage::make(ClListPage::class)->permission('currencies.update'),
        ];
    }

    public static function navigation(): NavigationItem
    {
        return NavigationItem::make()->icon('outline:banknotes')->sort(5)->badge(3, 'danger')->visible(self::$shown);
    }
}

class ClTaxesPage extends Page
{
    protected static string $view = 'cl::content';

    protected ?string $title = 'Taxes';

    protected static ?string $slug = 'taxes';

    protected static int $navigationSort = 10;

    protected static ?string $cluster = ClSettings::class;
}

class ClAuditPage extends Page
{
    protected static string $view = 'cl::content';

    protected static ?string $slug = 'audit';

    protected static ?string $permission = 'audit.view';

    protected static ?string $cluster = ClGuardedSettings::class;
}

class ClNestedCluster extends Cluster
{
    protected static ?string $cluster = ClSettings::class;
}

function clSignIn(array $abilities = []): void
{
    Gate::before(fn ($user, string $ability) => in_array($ability, $abilities, true) ? true : null);

    $user = new Authenticatable;
    $user->setAttribute('id', 1);

    test()->be($user);
}

function clRoutes(): void
{
    Route::middleware('web')->prefix('admin')->group(fn () => Route::wireResources());
    Route::getRoutes()->refreshNameLookups();
}

beforeEach(function () {
    View::addNamespace('cl', __DIR__.'/../fixtures/views/list-tabs');
    View::addLocation(__DIR__.'/../fixtures/views');
    config()->set('livewire.component_layout', 'plain-layout');

    ClCurrencyResource::$in = ClSettings::class;
    ClCurrencyResource::$shown = true;
    ClSettings::$position = SubNavigationPosition::Start;

    app(PageRegistry::class)->registerMany([ClSettings::class, ClTaxesPage::class, ClGuardedSettings::class, ClAuditPage::class, ClEmptyCluster::class]);
    app(ResourceRegistry::class)->register(ClCurrencyResource::class);
});

it('routes a member under the cluster prefix and keeps its route name', function () {
    clRoutes();

    expect(ClCurrencyResource::url())->toBe(url('admin/settings/currencies'))
        ->and(ClCurrencyResource::url('edit', 7))->toBe(url('admin/settings/currencies/7/edit'))
        ->and(ClTaxesPage::url())->toBe(url('admin/settings/taxes'))
        ->and(Route::has('wire.currencies.index'))->toBeTrue()
        ->and(ClSettings::url())->toBe(url('admin/settings'));
});

it('sends the cluster address to the first member this person may open', function () {
    clRoutes();
    clSignIn(['currencies.view']);

    $this->get('/admin/settings')->assertRedirect(url('admin/settings/currencies'));
});

it('passes over a member whose route would refuse, though its entry is shown', function () {
    // Currencies sorts first and is in the tabs; its list asks for a permission
    // this person lacks, so the address sends them to Taxes rather than a 403.
    clRoutes();
    clSignIn();

    $this->get('/admin/settings')->assertRedirect(url('admin/settings/taxes'));
});

it('refuses the cluster address when every member refuses', function () {
    clRoutes();
    clSignIn(['settings.enter']);

    $this->get('/admin/guarded')->assertForbidden();
});

it('answers 404 for a cluster nothing is in', function () {
    clRoutes();
    clSignIn();

    $this->get('/admin/empty')->assertNotFound();
});

it('guards every member with the cluster permission', function () {
    clRoutes();

    $middleware = Route::getRoutes()->getByName('wire.audit.index')->gatherMiddleware();

    expect($middleware)->toContain('can:settings.enter');

    clSignIn(['audit.view']);
    $this->get('/admin/guarded/audit')->assertForbidden();

    clSignIn(['audit.view', 'settings.enter']);
    $this->get('/admin/guarded/audit')->assertOk();
});

it('takes members out of the grouped menu and keeps them in the flat one', function () {
    clRoutes();
    clSignIn();

    $grouped = collect(app(Workspace::class)->navigation())->flatMap(fn ($group) => array_keys($group->getItems()))->all();

    expect($grouped)->toContain('settings')->not->toContain('currencies')->not->toContain('taxes')
        ->and(app(Workspace::class)->items())->toHaveKeys(['currencies', 'taxes', 'settings']);
});

it('lights the cluster entry on every member page, and on its own', function () {
    $entry = ClSettings::navigation();

    expect((new ActiveNavigation(key: 'currencies'))->isActive($entry))->toBeTrue()
        ->and((new ActiveNavigation(key: 'settings'))->isActive($entry))->toBeTrue()
        ->and((new ActiveNavigation(key: 'orders'))->isActive($entry))->toBeFalse()
        ->and((new ActiveNavigation)->isActive($entry))->toBeFalse();
});

it('hides the cluster entry while no member would be shown', function () {
    clSignIn(['audit.view']);

    expect(ClSettings::navigation()->isVisible())->toBeTrue()
        ->and(ClEmptyCluster::navigation()->isVisible())->toBeFalse();

    clSignIn();

    // The guarded cluster's one member asks for a permission this person lacks.
    expect(ClGuardedSettings::navigation()->isVisible())->toBeFalse();
});

it('draws the members beside a member page, the current one marked', function () {
    clRoutes();
    clSignIn();

    $html = $this->get('/admin/settings/taxes')->assertOk()->getContent();

    expect($html)->toContain('data-cluster-frame="start"')
        ->toContain('data-testid="panels-cluster-nav"')
        ->toContain('data-member="currencies"')
        ->toMatch('/aria-current="page"[^>]*data-member="taxes"/')
        ->not->toMatch('/aria-current="page"[^>]*data-member="currencies"/')
        ->toContain('Currencies')
        ->toContain('href="'.url('admin/settings').'"');
});

it('draws tabs and no column when the cluster asks for the top', function () {
    ClSettings::$position = SubNavigationPosition::Top;
    clRoutes();
    clSignIn();

    $html = $this->get('/admin/settings/taxes')->getContent();

    expect($html)->toContain('data-testid="panels-cluster-nav"')->not->toMatch('/<div[^>]*data-cluster-frame=/');
});

it('draws nothing for a cluster of one member', function () {
    ClCurrencyResource::$shown = false;
    clRoutes();
    clSignIn();

    expect($this->get('/admin/settings/taxes')->getContent())->not->toContain('data-testid="panels-cluster-nav"');
});

it('refuses a cluster that is not one', function () {
    ClCurrencyResource::$in = ClTaxesPage::class;

    clRoutes();
})->throws(ClusterException::class, 'does not extend');

it('refuses a cluster that is not registered', function () {
    ClCurrencyResource::$in = ClNestedCluster::class;

    app(ClusterNavigation::class)->clusterOf(ClCurrencyResource::class);
})->throws(ClusterException::class, 'names [ClSettings] as its own cluster');

it('refuses a member of a cluster nobody registered', function () {
    $cluster = new class extends Cluster {};
    ClCurrencyResource::$in = $cluster::class;

    app(ClusterNavigation::class)->clusterOf(ClCurrencyResource::class);
})->throws(ClusterException::class, 'is not registered');

it('answers nothing for a page outside any cluster', function () {
    expect(app(ClusterNavigation::class)->for(ClListPage::class, null))->toBeNull();
});
