<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\Core\Tenancy\Concerns\BelongsToTenantDatabase;
use NyonCode\WireCore\Core\Tenancy\Contracts\IsolatesTenants;
use NyonCode\WireCore\Core\Tenancy\CurrentTenant;
use NyonCode\WireCore\Core\Tenancy\Tenancy;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WirePanels\Concerns\InteractsWithTenants;
use NyonCode\WirePanels\Contracts\HasTenants;

/*
 * The tenant zone of ADR 0040 steps 2–3, over the other isolation: each
 * company in its own SQLite file. The middleware, the links and the Livewire
 * round trip must not be able to tell which isolation is underneath.
 */
class TdCompany extends Model
{
    protected $table = 'td_companies';

    protected $guarded = [];

    public $timestamps = false;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}

class TdUser extends Authenticatable implements HasTenants
{
    use InteractsWithTenants;

    protected $table = 'td_users';

    protected $guarded = [];

    public $timestamps = false;
}

class TdNote extends Model
{
    use BelongsToTenantDatabase;

    protected $table = 'td_notes';

    protected $guarded = [];

    public $timestamps = false;
}

class TdNoteList extends Component
{
    public function render(): string
    {
        return '<div>notes='.TdNote::query()->pluck('body')->implode(',').' url='.TdNoteResource::url('edit', 1).'</div>';
    }
}

class TdNoteResource implements DescribesResource, ProvidesPages
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return TdNote::class;
    }

    public static function key(): string
    {
        return 'td-notes';
    }

    public static function pages(): array
    {
        return ['index' => TdNoteList::class, 'edit' => TdNoteList::class];
    }
}

function tdPath(string $slug): string
{
    return sys_get_temp_dir()."/wire-td-{$slug}.sqlite";
}

beforeEach(function () {
    config()->set('database.connections.tenant', ['driver' => 'sqlite', 'database' => null, 'prefix' => '', 'foreign_key_constraints' => false]);
    config()->set('wire-core.tenancy.enabled', true);
    config()->set('wire-core.tenancy.isolation', 'database');
    config()->set('wire-core.tenancy.model', TdCompany::class);
    config()->set('wire-core.tenancy.database.connection', 'tenant');
    config()->set('wire-core.tenancy.database.name', sys_get_temp_dir().'/wire-td-{slug}.sqlite');
    config()->set('livewire.component_layout', 'plain-layout');
    View::addLocation(__DIR__.'/../fixtures/views');
    app()->forgetInstance(IsolatesTenants::class);
    app()->forgetScopedInstances();

    Schema::create('td_companies', function (Blueprint $t) {
        $t->id();
        $t->string('slug');
    });
    Schema::create('td_users', fn (Blueprint $t) => $t->id());
    Schema::create('tenant_user', function (Blueprint $t) {
        $t->unsignedBigInteger('td_user_id');
        $t->unsignedBigInteger('td_company_id');
    });

    $this->user = TdUser::query()->create();

    foreach (['acme', 'globex'] as $slug) {
        @unlink(tdPath($slug));
        $company = TdCompany::query()->create(['slug' => $slug]);
        touch(tdPath($slug));

        app(Tenancy::class)->runAs($company, function () use ($slug): void {
            Schema::connection('tenant')->create('td_notes', function (Blueprint $t) {
                $t->id();
                $t->string('body');
            });
            TdNote::query()->create(['body' => "{$slug} note"]);
        });
    }

    $this->user->tenants()->attach(TdCompany::query()->where('slug', 'acme')->first());
    app(ResourceRegistry::class)->register(TdNoteResource::class);

    Route::middleware(['web', 'wire.tenant'])->prefix('app/{tenant}')->name('app.')->group(fn () => Route::wireResources());
    Route::getRoutes()->refreshNameLookups();
});

afterEach(function () {
    app(CurrentTenant::class)->leave();

    foreach (['tenant_user', 'td_users', 'td_companies'] as $table) {
        Schema::dropIfExists($table);
    }

    foreach (['acme', 'globex'] as $slug) {
        @unlink(tdPath($slug));
    }

    config()->set('wire-core.tenancy.enabled', false);
    config()->set('wire-core.tenancy.isolation', 'column');
    app()->forgetInstance(IsolatesTenants::class);
});

it('serves a member their own database, with links into the company', function () {
    $this->actingAs($this->user);

    expect($this->get('/app/acme/td-notes')->assertOk()->getContent())
        ->toContain('notes=acme note ')
        ->toContain('url='.url('app/acme/td-notes/1/edit'));

    $this->get('/app/globex/td-notes')->assertNotFound();
});

it('stays in the company database on a Livewire round trip', function () {
    Livewire::component('td-note-list', TdNoteList::class);
    $this->actingAs($this->user);

    $page = $this->get('/app/acme/td-notes')->getContent();
    preg_match('/wire:snapshot="([^"]+)"/', $page, $match);

    app(CurrentTenant::class)->leave();
    app()->forgetScopedInstances();

    $response = $this->withHeaders(['X-Livewire' => 'true'])->postJson(app('livewire')->getUpdateUri(), [
        'components' => [[
            'snapshot' => html_entity_decode($match[1] ?? ''),
            'updates' => [],
            'calls' => [['path' => '', 'method' => '$refresh', 'params' => []]],
        ]],
    ])->assertOk();

    expect($response->json('components.0.effects.html'))->toContain('notes=acme note ');
});
