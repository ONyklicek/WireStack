<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\Core\Tenancy\Concerns\BelongsToTenant;
use NyonCode\WireCore\Core\Tenancy\CurrentTenant;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireCore\Foundation\Routing\WireRoutes;
use NyonCode\WireCore\Foundation\Routing\Zone;
use NyonCode\WireCore\Foundation\View\PageChrome;
use NyonCode\WirePanels\Concerns\InteractsWithTenants;
use NyonCode\WirePanels\Contracts\HasTenants;
use NyonCode\WirePanels\Exceptions\TenancyConfigurationException;
use NyonCode\WirePanels\Http\Middleware\IdentifyTenant;
use NyonCode\WirePanels\Routing\TenantEntry;
use NyonCode\WirePanels\Routing\ZoneDirectory;
use NyonCode\WirePanels\Tenancy\TenantSwitcher;

/*
 * A tenant in the URL (ADR 0040 §3–5).
 *
 * What matters: a member gets in and sees only their company, anybody else —
 * or an unknown slug — gets the same 404, and every link built inside carries
 * the tenant without being told.
 */
class TrCompany extends Model
{
    protected $table = 'tr_companies';

    protected $guarded = [];

    public $timestamps = false;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}

class TrUser extends Authenticatable implements HasTenants
{
    use InteractsWithTenants;

    protected $table = 'tr_users';

    protected $guarded = [];

    public $timestamps = false;
}

class TrStranger extends Authenticatable
{
    protected $table = 'tr_users';

    protected $guarded = [];

    public $timestamps = false;
}

class TrInvoice extends Model
{
    use BelongsToTenant;

    protected $table = 'tr_invoices';

    protected $guarded = [];

    public $timestamps = false;
}

class TrInvoiceList extends Component
{
    public function render(): string
    {
        $numbers = TrInvoice::query()->pluck('number')->implode(',');
        $zone = Zone::current() ?? '-';
        $url = TrInvoiceResource::url('edit', 7);
        $parameters = implode(',', array_keys(request()->route()?->parameters() ?? []));

        return "<div>numbers={$numbers} url={$url} zone={$zone} params=[{$parameters}]</div>";
    }
}

class TrInvoiceResource implements DescribesResource, ProvidesNavigation, ProvidesPages
{
    public static function navigation(): NavigationItem
    {
        return NavigationItem::make();
    }

    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return TrInvoice::class;
    }

    public static function key(): string
    {
        return 'tr-invoices';
    }

    public static function pages(): array
    {
        return ['index' => TrInvoiceList::class, 'edit' => TrInvoiceList::class];
    }
}

beforeEach(function () {
    config()->set('wire-core.tenancy.enabled', true);
    config()->set('wire-core.tenancy.model', TrCompany::class);
    config()->set('livewire.component_layout', 'plain-layout');
    View::addLocation(__DIR__.'/../fixtures/views');

    Schema::create('tr_companies', function (Blueprint $t) {
        $t->id();
        $t->string('slug');
    });
    Schema::create('tr_users', function (Blueprint $t) {
        $t->id();
        $t->string('name')->nullable();
    });
    Schema::create('tenant_user', function (Blueprint $t) {
        $t->unsignedBigInteger('tr_user_id');
        $t->unsignedBigInteger('tr_company_id');
    });
    Schema::create('tr_invoices', function (Blueprint $t) {
        $t->id();
        $t->string('number');
        $t->unsignedBigInteger('tenant_id')->nullable();
    });

    $acme = TrCompany::query()->create(['slug' => 'acme']);
    $globex = TrCompany::query()->create(['slug' => 'globex']);
    TrInvoice::query()->withoutGlobalScopes()->insert([
        ['number' => 'A-1', 'tenant_id' => $acme->id],
        ['number' => 'G-1', 'tenant_id' => $globex->id],
    ]);

    $user = TrUser::query()->create(['name' => 'Ada']);
    $user->tenants()->attach($acme);
    $this->user = $user;

    app(ResourceRegistry::class)->register(TrInvoiceResource::class);
});

afterEach(function () {
    foreach (['tr_invoices', 'tenant_user', 'tr_users', 'tr_companies'] as $table) {
        Schema::dropIfExists($table);
    }

    config()->set('wire-core.tenancy.enabled', false);
});

function trRoutes(): void
{
    Route::middleware(['web', 'wire.tenant'])->prefix('app/{tenant}')->name('app.')->group(fn () => Route::wireResources());
    Route::getRoutes()->refreshNameLookups();
}

it('lets a member in, scoped to their company, and fills the tenant into every link', function () {
    trRoutes();
    $this->actingAs($this->user);

    $html = $this->get('/app/acme/tr-invoices')->assertOk()->getContent();

    expect($html)->toContain('numbers=A-1 ')
        ->toContain('url='.url('app/acme/tr-invoices/7/edit'))
        // Taken off the route: no page's mount() is handed a parameter it did not ask for.
        ->toContain('params=[]')
        ->and(app(CurrentTenant::class)->get()?->slug)->toBe('acme');
});

it('answers a stranger and an unknown slug with the same 404', function () {
    trRoutes();
    $this->actingAs($this->user);

    $this->get('/app/globex/tr-invoices')->assertNotFound();
    $this->get('/app/nobody/tr-invoices')->assertNotFound();
});

it('answers a guest who reached it without auth with a 404 too', function () {
    trRoutes();

    $this->get('/app/acme/tr-invoices')->assertNotFound();
});

it('refuses a user model that says nothing about tenants', function () {
    trRoutes();
    $this->withoutExceptionHandling();
    $this->actingAs(TrStranger::query()->create(['name' => 'Eve']));

    $this->get('/app/acme/tr-invoices');
})->throws(TenancyConfigurationException::class, 'does not implement');

it('refuses the middleware on a route with no tenant in it', function () {
    Route::middleware(['web', 'wire.tenant'])->get('/no-tenant', fn () => 'x');
    $this->withoutExceptionHandling();
    $this->actingAs($this->user);

    $this->get('/no-tenant');
})->throws(TenancyConfigurationException::class, 'no {tenant} parameter');

it('refuses a tenant zone without a tenant model', function () {
    config()->set('wire-core.tenancy.model', null);
    trRoutes();
    $this->withoutExceptionHandling();
    $this->actingAs($this->user);

    $this->get('/app/acme/tr-invoices');
})->throws(TenancyConfigurationException::class, 'names no Eloquent model');

it('answers membership from the pivot', function () {
    $acme = TrCompany::query()->where('slug', 'acme')->first();
    $globex = TrCompany::query()->where('slug', 'globex')->first();

    expect($this->user->canAccessTenant($acme))->toBeTrue()
        ->and($this->user->canAccessTenant($globex))->toBeFalse()
        ->and($this->user->canAccessTenant(new TrInvoice))->toBeFalse()
        ->and($this->user->getDefaultTenant()?->slug)->toBe('acme')
        ->and(collect($this->user->getTenants())->pluck('slug')->all())->toBe(['acme']);
});

it('keeps the tenant, and every link, on a Livewire round trip', function () {
    // A round trip is a request to livewire/update, which has no {tenant} in
    // it. Livewire rebuilds the page's original request and runs persistent
    // middleware against it — so the second render is scoped and linked as the
    // first was, not unscoped with every link null.
    expect(Livewire::getPersistentMiddleware())->toContain(IdentifyTenant::class);

    Livewire::component('tr-invoice-list', TrInvoiceList::class);

    // A named zone, on purpose: the round trip has to keep the zone as well as
    // the tenant, or `X::url()` answers null on the second render.
    trRoutes();
    $this->actingAs($this->user);

    $page = $this->get('/app/acme/tr-invoices')->assertOk()->getContent();
    preg_match('/wire:snapshot="([^"]+)"/', $page, $match);

    // A fresh request cycle for the round trip, as a browser's would be.
    app()->forgetScopedInstances();
    URL::defaults(['tenant' => null]);

    $response = $this->withHeaders(['X-Livewire' => 'true'])->postJson(app('livewire')->getUpdateUri(), [
        'components' => [[
            'snapshot' => html_entity_decode($match[1] ?? ''),
            'updates' => [],
            'calls' => [['path' => '', 'method' => '$refresh', 'params' => []]],
        ]],
    ])->assertOk();

    expect($response->json('components.0.effects.html'))
        ->toContain('numbers=A-1 ')
        ->toContain('url='.url('app/acme/tr-invoices/7/edit'))
        ->toContain('zone=app. ');
});

/**
 * Panel zones placed from `wire-core.routes.groups`, registered the way the
 * framework's route file does it (ADR 0041).
 *
 * @param  array<string, array<string, mixed>>  $zones
 * @param  array<string, mixed>  $shared
 */
function trZones(array $zones, array $shared = []): void
{
    config()->set('wire-core.routes.groups', array_map(
        fn (array $zone): array => ['uses' => 'panel', ...$shared, ...$zone],
        $zones,
    ));

    app(WireRoutes::class)->registerConfigured();
    Route::getRoutes()->refreshNameLookups();
}

it('puts the tenant in a config zone path or domain, with the middleware', function () {
    trZones([
        'app' => ['prefix' => 'app', 'tenant' => 'path', 'only' => ['tr-invoices']],
        'portal' => ['domain' => 'example.test', 'tenant' => 'domain', 'only' => ['tr-invoices']],
    ], ['middleware' => ['web']]);

    $path = Route::getRoutes()->getByName('app.wire.tr-invoices.index');
    $domain = Route::getRoutes()->getByName('portal.wire.tr-invoices.index');

    expect($path->uri())->toBe('app/{tenant}/tr-invoices')
        ->and($path->middleware())->toContain('wire.tenant')
        ->and($domain->getDomain())->toBe('{tenant}.example.test')
        ->and($domain->middleware())->toContain('wire.tenant');
});

it('refuses a tenant mode it does not know, and a domain mode with no domain', function (array $zone, string $message) {
    trZones(['app' => $zone]);
})->throws(TenancyConfigurationException::class)->with([
    'unknown' => [['prefix' => 'app', 'tenant' => 'subdomain'], "takes 'path' or 'domain'"],
    'no domain' => [['prefix' => 'app', 'tenant' => 'domain'], 'names no `domain`'],
]);

it('takes a tenant the application bound itself, and refuses a binding that is no tenant', function () {
    Route::bind('tenant', fn (string $value) => $value === 'weird'
        ? new stdClass
        : TrCompany::query()->where('slug', $value)->firstOrFail());

    Route::middleware(['web', SubstituteBindings::class, 'wire.tenant'])
        ->prefix('bound/{tenant}')
        ->get('/probe', fn () => app(CurrentTenant::class)->get()?->slug);
    $this->actingAs($this->user);

    $this->get('/bound/acme/probe')->assertOk()->assertSee('acme');
    $this->get('/bound/weird/probe')->assertNotFound();
});

it('leaves a zone that says no tenant as it is', function () {
    trZones(['plain' => ['prefix' => 'plain', 'tenant' => false, 'only' => ['tr-invoices']]]);

    expect(Route::getRoutes()->getByName('plain.wire.tr-invoices.index')->uri())->toBe('plain/tr-invoices');
});

it('refuses membership when no tenant model is configured', function () {
    config()->set('wire-core.tenancy.model', null);

    $this->user->tenants();
})->throws(TenancyConfigurationException::class, 'names no Eloquent model');

// ─── The zone's own address, and the switcher (ADR 0040 §7) ──────────────────

function trJoin(TrUser $user, string $slug): void
{
    $user->tenants()->attach(TrCompany::query()->firstOrCreate(['slug' => $slug]));
}

function trEntry(): void
{
    Route::middleware(['web'])->name('app.')->group(fn () => Route::wire('tenant-entry', uri: 'app', to: 'app/{tenant}'));
    trRoutes();
}

it('sends the bare zone address to the person own company, and on to its first page', function () {
    trEntry();
    $this->actingAs($this->user);

    $this->get('/app')->assertRedirect(url('app/acme'));
    $this->get('/app/acme')->assertRedirect(url('app/acme/tr-invoices'));
});

it('refuses someone with no company, or shows the page the application named', function () {
    trEntry();
    $this->actingAs(TrUser::query()->create(['name' => 'Nobody']));

    $this->get('/app')->assertForbidden()->assertSee('You do not belong to any company yet.');

    config()->set('wire-panels.routes.tenant_entry.view', 'tr-no-tenant');
    View::addNamespace('tr', __DIR__);
    file_put_contents(sys_get_temp_dir().'/tr-no-tenant.blade.php', '<p>Register a company</p>');
    View::addLocation(sys_get_temp_dir());

    $this->get('/app')->assertOk()->assertSee('Register a company');
});

it('asks a guest to sign in, and refuses a user model that knows no tenants', function () {
    trEntry();

    $this->getJson('/app')->assertUnauthorized();

    $this->withoutExceptionHandling();
    $this->actingAs(TrStranger::query()->create(['name' => 'Eve']));

    expect(fn () => $this->get('/app'))->toThrow(TenancyConfigurationException::class);
});

it('registers the bare address for a config zone, in a path or at a domain root', function () {
    trZones([
        'app' => ['prefix' => 'app', 'tenant' => 'path', 'only' => ['tr-invoices']],
        'portal' => ['domain' => 'example.test', 'tenant' => 'domain', 'only' => ['tr-invoices']],
    ], ['middleware' => ['web']]);

    $path = Route::getRoutes()->getByName('app.wire.tenants');
    $domain = Route::getRoutes()->getByName('portal.wire.tenants');

    expect($path->uri())->toBe('app')
        ->and($path->middleware())->not->toContain('wire.tenant')
        ->and($path->defaults[TenantEntry::TARGET])->toBe('app/{tenant}')
        ->and($domain->getDomain())->toBe('example.test')
        ->and($domain->defaults[TenantEntry::TARGET])->toBe('//{tenant}.example.test')
        // And that address is the zone's, so a zone picker offers it.
        ->and(app(ZoneDirectory::class)->all())->toHaveKey('app');
});

it('sends home to the company inside one, and to the zone s address outside any', function () {
    trZones(['app' => ['prefix' => 'app', 'tenant' => 'path', 'only' => ['tr-invoices']]], ['middleware' => ['web']]);

    $directory = app(ZoneDirectory::class);

    // The page for somebody in no company: `app/{tenant}` cannot be built.
    expect($directory->homeOf('app'))->toBe(url('/app'))
        ->and($directory->homeOf('nowhere'))->toBeNull()
        ->and($directory->homeOf(null))->toBeNull();

    URL::defaults(['tenant' => 'acme']);

    expect($directory->homeOf('app'))->toBe(url('/app/acme'));
});

it('offers every company, each on the same page, and marks the current one', function () {
    trJoin($this->user, 'globex');
    trRoutes();
    Route::middleware(['web', 'wire.tenant'])->prefix('app/{tenant}')->name('app.')
        ->get('/switch-probe', fn () => app(TenantSwitcher::class)->forRequest(request()))
        ->name('wire.tr-invoices.index-probe');
    $this->actingAs($this->user);

    Route::getRoutes()->refreshNameLookups();

    $onList = $this->get('/app/acme/switch-probe')->json();

    expect($onList['current'])->toBe('acme')
        ->and(collect($onList['tenants'])->pluck('url')->all())->toBe([url('app/acme/switch-probe'), url('app/globex/switch-probe')])
        ->and(collect($onList['tenants'])->pluck('current')->all())->toBe([true, false]);
});

it('links a record page to the same list in another company, never the same record', function () {
    trJoin($this->user, 'globex');
    Route::middleware(['web', 'wire.tenant'])->prefix('app/{tenant}')->name('app.')->group(function (): void {
        Route::wireResources();
        Route::get('/probe/{record}', fn () => app(TenantSwitcher::class)->forRequest(request()))
            ->name('wire.tr-invoices.probe');
    });
    Route::getRoutes()->refreshNameLookups();
    $this->actingAs($this->user);

    $urls = collect($this->get('/app/acme/probe/7')->json('tenants'))->pluck('url')->all();

    expect($urls)->toBe([url('app/acme/tr-invoices'), url('app/globex/tr-invoices')]);
});

it('offers nothing to someone with one company, or outside a tenant', function () {
    trRoutes();
    Route::middleware(['web', 'wire.tenant'])->prefix('app/{tenant}')
        ->get('/single-probe', fn () => ['switcher' => app(TenantSwitcher::class)->forRequest(request())]);
    Route::middleware(['web'])->get('/outside-probe', fn () => ['switcher' => app(TenantSwitcher::class)->forRequest(request())]);
    $this->actingAs($this->user);

    expect($this->get('/app/acme/single-probe')->json('switcher'))->toBeNull();

    app(CurrentTenant::class)->leave();

    expect($this->get('/outside-probe')->json('switcher'))->toBeNull();
});

it('names a company by its label, or its route key without one', function () {
    $switcher = app(TenantSwitcher::class);
    $acme = TrCompany::query()->where('slug', 'acme')->first();

    expect($switcher->label($acme))->toBe('acme')
        ->and($switcher->label($acme->forceFill(['name' => 'Acme Ltd'])))->toBe('Acme Ltd');
});

it('puts the switcher in the top bar', function () {
    expect(app(PageChrome::class)->views(PageChrome::TOPBAR))
        ->toContain('wire-panels::tenancy.switcher');
});

it('falls back to coarser places on a record page when the list cannot be linked', function () {
    trJoin($this->user, 'globex');
    $probe = fn () => app(TenantSwitcher::class)->forRequest(request());

    Route::middleware(['web'])->name('app.')->group(fn () => Route::wire('tenant-entry', uri: 'app', to: 'app/{tenant}'));
    Route::middleware(['web', 'wire.tenant'])->prefix('app/{tenant}')->name('app.')->group(function () use ($probe): void {
        // No list at all for `ghost`, and a list for `needy` that asks for a
        // parameter a switch cannot supply.
        Route::get('/ghost/{record}', $probe)->name('wire.ghost.view');
        Route::get('/needy-list/{missing}', fn () => 'x')->name('wire.needy.index');
        Route::get('/needy/{record}', $probe)->name('wire.needy.view');
    });
    Route::getRoutes()->refreshNameLookups();
    $this->actingAs($this->user);

    // No `wire.home` in this group either, so both land on the zone's own address.
    expect(collect($this->get('/app/acme/ghost/7')->json('tenants'))->pluck('url')->all())->toBe([url('app'), url('app')])
        ->and(collect($this->get('/app/acme/needy/7')->json('tenants'))->pluck('url')->all())->toBe([url('app'), url('app')]);
});

it('places a hand-written zone s bare address from config too, guarded by default', function () {
    config()->set('wire-core.routes.groups', ['tenant-entry' => ['uri' => 'app', 'to' => 'app/{tenant}']]);

    app(WireRoutes::class)->registerConfigured();
    Route::getRoutes()->refreshNameLookups();

    $entry = Route::getRoutes()->getByName('wire.tenants');

    expect($entry->uri())->toBe('app')
        ->and($entry->middleware())->toBe(['web', 'auth'])
        ->and($entry->defaults[TenantEntry::TARGET])->toBe('app/{tenant}');
});
