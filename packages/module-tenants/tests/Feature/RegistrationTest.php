<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Core\Tenancy\Contracts\IsolatesTenants;
use NyonCode\WireCore\Core\Tenancy\TenantDatabases;
use NyonCode\WireModuleTenants\Jobs\ProvisionTenantDatabase;
use NyonCode\WireModuleTenants\Livewire\RegisterTenant;
use NyonCode\WireModuleTenants\Models\Tenant;
use NyonCode\WireModuleTenants\Support\Membership;
use NyonCode\WireModuleTenants\Support\Registration;

/*
 * Registering a company (ADR 0040 §8): who may, what it may be called, and
 * that whoever registers it owns it and lands in it.
 */

it('registers a company, makes its registrant the owner and lands there', function () {
    $ada = $this->person('Ada');
    $this->actingAs($ada);

    $this->get('/tenants/register')->assertOk()->assertSee('Register a company');

    Livewire::test(RegisterTenant::class)
        ->set('data.name', 'Acme Rockets')
        ->assertSet('data.slug', 'acme-rockets')
        ->call('save')
        ->assertRedirect(url('app/acme-rockets'));

    $tenant = Tenant::query()->where('slug', 'acme-rockets')->firstOrFail();

    expect($tenant->name)->toBe('Acme Rockets')
        ->and(Membership::isOwner($tenant, $ada))->toBeTrue();
});

it('keeps a slug someone typed, and refuses a taken or reserved one', function () {
    $this->company('acme');
    $this->actingAs($this->person('Ada'));

    Livewire::test(RegisterTenant::class)
        ->set('data.slug', 'mine')
        ->set('data.name', 'Something else')
        ->assertSet('data.slug', 'mine');

    Livewire::test(RegisterTenant::class)
        ->set('data.name', 'Acme')
        ->set('data.slug', 'acme')
        ->call('save')
        ->assertHasErrors('data.slug');

    Livewire::test(RegisterTenant::class)
        ->set('data.name', 'Admin')
        ->set('data.slug', 'admin')
        ->call('save')
        ->assertHasErrors('data.slug');
});

it('follows the registration setting', function () {
    $ada = $this->person('Ada');
    $this->actingAs($ada);

    config()->set('wire-module-tenants.registration', 'ability');
    $this->get('/tenants/register')->assertForbidden();

    Gate::define('tenants.create', fn () => true);
    $this->get('/tenants/register')->assertOk();

    config()->set('wire-module-tenants.registration', false);
    $this->get('/tenants/register')->assertForbidden();

    expect(Registration::allows(null))->toBeFalse();
});

it('provisions a database for the company under database isolation', function () {
    Bus::fake([ProvisionTenantDatabase::class]);
    config()->set('wire-core.tenancy.isolation', 'database');
    $this->actingAs($this->person('Ada'));

    Livewire::test(RegisterTenant::class)
        ->set('data.name', 'Acme')
        ->call('save');

    Bus::assertDispatched(ProvisionTenantDatabase::class, fn ($job) => $job->tenant->slug === 'acme');
});

it('creates and migrates that database when the job runs', function () {
    $path = sys_get_temp_dir().'/wire-mt-{slug}.sqlite';
    $migrations = sys_get_temp_dir().'/wire-mt-migrations';
    @mkdir($migrations);
    config()->set('database.connections.tenant', ['driver' => 'sqlite', 'database' => null, 'prefix' => '']);
    config()->set('wire-core.tenancy.database.name', $path);
    config()->set('wire-core.tenancy.database.migrations', $migrations);
    // The job is only dispatched under database isolation, so it runs under it.
    config()->set('wire-core.tenancy.isolation', 'database');
    app()->forgetInstance(IsolatesTenants::class);
    app()->forgetScopedInstances();
    $tenant = $this->company('acme');
    $file = str_replace('{slug}', 'acme', $path);
    @unlink($file);

    (new ProvisionTenantDatabase($tenant))->handle(app(TenantDatabases::class));

    // Created, and migrated: the migrator made its own table in it.
    $tables = (new PDO('sqlite:'.$file))->query("select name from sqlite_master where type = 'table'")->fetchAll(PDO::FETCH_COLUMN);

    expect($tables)->toContain('migrations');

    @unlink($file);
});

it('registers itself as a module and fills what a tenant zone reads', function () {
    expect(app(PluginManager::class)->has('tenants'))->toBeTrue()
        ->and(config('wire-core.tenancy.model'))->toBe(Tenant::class)
        ->and(config('wire-panels.routes.tenant_entry.view'))->toBe('wire-module-tenants::no-tenant');

    Artisan::call('about', ['--only' => 'wire_module_tenants']);
});
