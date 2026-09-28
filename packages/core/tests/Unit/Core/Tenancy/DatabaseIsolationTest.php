<?php

declare(strict_types=1);

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Core\Tenancy\Concerns\BelongsToTenantDatabase;
use NyonCode\WireCore\Core\Tenancy\Contracts\IsolatesTenants;
use NyonCode\WireCore\Core\Tenancy\CurrentTenant;
use NyonCode\WireCore\Core\Tenancy\Isolation\DatabaseIsolation;
use NyonCode\WireCore\Core\Tenancy\Tenancy;
use NyonCode\WireCore\Core\Tenancy\TenantDatabases;
use NyonCode\WireCore\Exceptions\TenancyException;

/*
 * A database per tenant (ADR 0040 §2), on SQLite files.
 *
 * The same promises the column scope makes — a tenant sees its own rows and
 * nobody else's, work outside a tenant sees nothing, a queued job runs where it
 * was dispatched — kept by a connection instead of a WHERE clause.
 */
class DiCompany extends Model
{
    protected $table = 'di_companies';

    protected $guarded = [];

    public $timestamps = false;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}

class DiNote extends Model
{
    use BelongsToTenantDatabase;

    protected $table = 'di_notes';

    protected $guarded = [];

    public $timestamps = false;
}

class DiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** @var array<int, array<int, string>> */
    public static array $seen = [];

    public function handle(): void
    {
        self::$seen[] = DiNote::query()->pluck('body')->all();
    }
}

function diPath(string $slug): string
{
    return sys_get_temp_dir()."/wire-di-{$slug}.sqlite";
}

function diCompany(string $slug): DiCompany
{
    return DiCompany::query()->where('slug', $slug)->firstOrFail();
}

beforeEach(function () {
    foreach (['acme', 'globex'] as $slug) {
        @unlink(diPath($slug));
    }

    Schema::create('di_companies', function (Blueprint $t) {
        $t->id();
        $t->string('slug');
    });
    Schema::create('jobs', function (Blueprint $t) {
        $t->id();
        $t->string('queue')->index();
        $t->longText('payload');
        $t->unsignedTinyInteger('attempts');
        $t->unsignedInteger('reserved_at')->nullable();
        $t->unsignedInteger('available_at');
        $t->unsignedInteger('created_at');
    });

    DiCompany::query()->create(['slug' => 'acme']);
    DiCompany::query()->create(['slug' => 'globex']);

    config()->set('database.connections.tenant', ['driver' => 'sqlite', 'database' => null, 'prefix' => '', 'foreign_key_constraints' => false]);
    config()->set('wire-core.tenancy.enabled', true);
    config()->set('wire-core.tenancy.isolation', 'database');
    config()->set('wire-core.tenancy.model', DiCompany::class);
    config()->set('wire-core.tenancy.database', [
        'connection' => 'tenant',
        'name' => sys_get_temp_dir().'/wire-di-{slug}.sqlite',
        'admin_connection' => null,
        'migrations' => realpath(__DIR__.'/../../../Fixtures/tenant-migrations'),
    ]);
    config()->set('queue.connections.database', ['driver' => 'database', 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 90]);
    config()->set('queue.failed.driver', 'null');
    config()->set('cache.default', 'array');

    app()->forgetInstance(IsolatesTenants::class);
    app()->forgetScopedInstances();
    DiJob::$seen = [];
});

afterEach(function () {
    app(CurrentTenant::class)->leave();
    Schema::dropIfExists('jobs');
    Schema::dropIfExists('di_companies');

    foreach (['acme', 'globex'] as $slug) {
        @unlink(diPath($slug));
    }

    config()->set('wire-core.tenancy.enabled', false);
    config()->set('wire-core.tenancy.isolation', 'column');
    app()->forgetInstance(IsolatesTenants::class);
});

it('creates each tenant a database and migrates it', function () {
    Artisan::call('wire:tenants:create', ['--all' => true]);

    expect(app(IsolatesTenants::class))->toBeInstanceOf(DatabaseIsolation::class)
        ->and(file_exists(diPath('acme')))->toBeTrue()
        ->and(file_exists(diPath('globex')))->toBeTrue()
        ->and(app(Tenancy::class)->runAs(diCompany('globex'), fn () => Schema::connection('tenant')->hasTable('di_notes')))->toBeTrue();
});

it('keeps each tenant to its own database', function () {
    Artisan::call('wire:tenants:create', ['--all' => true]);
    $tenancy = app(Tenancy::class);

    $tenancy->runAs(diCompany('acme'), fn () => DiNote::query()->create(['body' => 'acme note']));
    $tenancy->runAs(diCompany('globex'), fn () => DiNote::query()->create(['body' => 'globex note']));

    expect($tenancy->runAs(diCompany('acme'), fn () => DiNote::query()->pluck('body')->all()))->toBe(['acme note'])
        ->and($tenancy->runAs(diCompany('globex'), fn () => DiNote::query()->pluck('body')->all()))->toBe(['globex note']);
});

it('reads no tenant database with no tenant entered — loudly', function () {
    Artisan::call('wire:tenants:create', ['--all' => true]);

    // Whatever the driver throws for a connection that names no database —
    // SQLite a TypeError, MySQL an unknown-database error — it throws.
    $read = null;

    try {
        $read = DiNote::query()->count();
    } catch (Throwable) {
        $read = 'refused';
    }

    expect($read)->toBe('refused');
});

it('runs a queued job in its tenant database', function () {
    Artisan::call('wire:tenants:create', ['--all' => true]);
    app(Tenancy::class)->runAs(diCompany('globex'), function (): void {
        DiNote::query()->create(['body' => 'globex note']);
        DiJob::dispatch()->onConnection('database');
    });

    app()->forgetScopedInstances();
    Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--stop-when-empty' => true, '--tries' => 1]);

    expect(DiJob::$seen)->toBe([['globex note']]);
});

it('migrates only the tenants named, and fresh when asked', function () {
    Artisan::call('wire:tenants:create', ['tenant' => ['acme'], '--no-migrate' => true]);

    expect(file_exists(diPath('acme')))->toBeTrue()
        ->and(file_exists(diPath('globex')))->toBeFalse()
        ->and(app(Tenancy::class)->runAs(diCompany('acme'), fn () => Schema::connection('tenant')->hasTable('di_notes')))->toBeFalse();

    Artisan::call('wire:tenants:migrate', ['--tenant' => ['acme'], '--fresh' => true]);

    expect(app(Tenancy::class)->runAs(diCompany('acme'), fn () => Schema::connection('tenant')->hasTable('di_notes')))->toBeTrue();
});

it('drops a tenant database', function () {
    Artisan::call('wire:tenants:create', ['tenant' => ['acme'], '--no-migrate' => true]);

    expect(file_exists(diPath('acme')))->toBeTrue();

    app(TenantDatabases::class)->drop(diCompany('acme'));

    expect(file_exists(diPath('acme')))->toBeFalse();
});

it('asks for a tenant, and says when there is none to migrate', function () {
    expect(Artisan::call('wire:tenants:create'))->toBe(1)
        ->and(Artisan::output())->toContain('Name a tenant');

    Artisan::call('wire:tenants:migrate', ['--tenant' => ['nobody']]);

    expect(Artisan::output())->toContain('No tenant to migrate');
});

it('refuses a tenant connection that is not defined, and a missing tenant model', function () {
    config()->set('wire-core.tenancy.database.connection', 'nope');

    expect(fn () => app(TenantDatabases::class)->connection())->toThrow(TenancyException::class, '[nope]');

    config()->set('wire-core.tenancy.model', null);

    expect(fn () => Artisan::call('wire:tenants:migrate'))->toThrow(TenancyException::class, 'names no Eloquent model');
});
