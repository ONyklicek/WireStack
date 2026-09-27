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
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\Core\Tenancy\Concerns\BelongsToTenant;
use NyonCode\WireCore\Core\Tenancy\CurrentTenant;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WirePanels\Concerns\InteractsWithTenants;
use NyonCode\WirePanels\Contracts\HasTenants;
use NyonCode\WirePanels\Exceptions\TenancyConfigurationException;
use NyonCode\WirePanels\Http\Middleware\IdentifyTenant;
use NyonCode\WirePanels\Routing\ConfiguredRoutes;

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
        $zone = NyonCode\WireCore\Foundation\Routing\Zone::current() ?? '-';
        $url = TrInvoiceResource::url('edit', 7);
        $parameters = implode(',', array_keys(request()->route()?->parameters() ?? []));

        return "<div>numbers={$numbers} url={$url} zone={$zone} params=[{$parameters}]</div>";
    }
}

class TrInvoiceResource implements DescribesResource, ProvidesPages
{
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

it('puts the tenant in a config zone path or domain, with the middleware', function () {
    config()->set('wire-panels.routes', [
        'enabled' => true,
        'middleware' => ['web'],
        'zones' => [
            'app' => ['prefix' => 'app', 'tenant' => 'path', 'only' => ['tr-invoices']],
            'portal' => ['domain' => 'example.test', 'tenant' => 'domain', 'only' => ['tr-invoices']],
        ],
    ]);

    app(ConfiguredRoutes::class)->register();
    Route::getRoutes()->refreshNameLookups();

    $path = Route::getRoutes()->getByName('app.wire.tr-invoices.index');
    $domain = Route::getRoutes()->getByName('portal.wire.tr-invoices.index');

    expect($path->uri())->toBe('app/{tenant}/tr-invoices')
        ->and($path->middleware())->toContain('wire.tenant')
        ->and($domain->getDomain())->toBe('{tenant}.example.test')
        ->and($domain->middleware())->toContain('wire.tenant');
});

it('refuses a tenant mode it does not know, and a domain mode with no domain', function (array $zone, string $message) {
    config()->set('wire-panels.routes', ['enabled' => true, 'zones' => ['app' => $zone]]);

    app(ConfiguredRoutes::class)->register();
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
    config()->set('wire-panels.routes', [
        'enabled' => true,
        'zones' => ['plain' => ['prefix' => 'plain', 'tenant' => false, 'only' => ['tr-invoices']]],
    ]);

    app(ConfiguredRoutes::class)->register();
    Route::getRoutes()->refreshNameLookups();

    expect(Route::getRoutes()->getByName('plain.wire.tr-invoices.index')->uri())->toBe('plain/tr-invoices');
});

it('refuses membership when no tenant model is configured', function () {
    config()->set('wire-core.tenancy.model', null);

    $this->user->tenants();
})->throws(TenancyConfigurationException::class, 'names no Eloquent model');
