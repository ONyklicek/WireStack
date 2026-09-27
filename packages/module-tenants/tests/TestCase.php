<?php

declare(strict_types=1);

namespace NyonCode\WireModuleTenants\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Livewire\LivewireServiceProvider;
use NyonCode\WireCore\WireCoreServiceProvider;
use NyonCode\WireForms\WireFormsServiceProvider;
use NyonCode\WireModuleTenants\Models\Tenant;
use NyonCode\WireModuleTenants\Tests\Fixtures\User;
use NyonCode\WireModuleTenants\WireModuleTenantsServiceProvider;
use NyonCode\WirePanels\WirePanelsServiceProvider;
use NyonCode\WireTable\WireTableServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            WireCoreServiceProvider::class,
            WireFormsServiceProvider::class,
            WireTableServiceProvider::class,
            WirePanelsServiceProvider::class,
            WireModuleTenantsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('wire-core.tenancy.enabled', true);
        $app['config']->set('livewire.component_layout', 'tenants-layout');
    }

    protected function defineRoutes($router): void
    {
        $router->middleware(['web', 'auth'])->prefix('tenants')->group(fn () => $router->wire('tenants'));
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->default('');
            $table->timestamps();
        });

        (require __DIR__.'/../database/migrations/create_wire_tenants_tables.php')->up();
    }

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/fixtures/views');
    }

    protected function company(string $slug, ?User $owner = null): Tenant
    {
        $tenant = Tenant::query()->create(['name' => ucfirst($slug), 'slug' => $slug]);

        if ($owner !== null) {
            $tenant->members()->attach($owner->getKey(), ['role' => 'owner']);
        }

        return $tenant;
    }

    protected function person(string $name): User
    {
        return User::query()->create(['name' => $name, 'email' => strtolower($name).'@example.com']);
    }
}
