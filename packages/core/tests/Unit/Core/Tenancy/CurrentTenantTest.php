<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Core\Tenancy\Concerns\BelongsToTenant;
use NyonCode\WireCore\Core\Tenancy\Contracts\IsolatesTenants;
use NyonCode\WireCore\Core\Tenancy\Contracts\TenantResolver;
use NyonCode\WireCore\Core\Tenancy\CurrentTenant;
use NyonCode\WireCore\Core\Tenancy\CurrentTenantResolver;
use NyonCode\WireCore\Core\Tenancy\Events\TenantEntered;
use NyonCode\WireCore\Core\Tenancy\Events\TenantLeft;
use NyonCode\WireCore\Core\Tenancy\Isolation\ColumnIsolation;
use NyonCode\WireCore\Core\Tenancy\Tenancy;
use NyonCode\WireCore\Core\Tenancy\TenantScope;
use NyonCode\WireCore\Exceptions\TenancyException;

/*
 * The current tenant, and the one way it changes (ADR 0040 §1–2).
 *
 * The property worth pinning is that nothing needs wiring: enter a tenant and
 * the existing scope follows it, leave and the fail-safe is back — and that a
 * callback, a job or a reused container can never leave a tenant behind.
 */
class CtCompany extends Model
{
    protected $table = 'ct_companies';

    protected $guarded = [];

    public $timestamps = false;
}

class CtInvoice extends Model
{
    use BelongsToTenant;

    protected $table = 'ct_invoices';

    protected $guarded = [];

    public $timestamps = false;
}

/** Records what the holder asked of it. */
class CtRecordingIsolation implements IsolatesTenants
{
    /** @var array<int, string> */
    public static array $calls = [];

    public function enter(Model $tenant): void
    {
        self::$calls[] = 'enter:'.$tenant->getKey();
    }

    public function leave(): void
    {
        self::$calls[] = 'leave';
    }
}

function ctCompany(int $id): CtCompany
{
    return (new CtCompany)->forceFill(['id' => $id])->syncOriginal();
}

beforeEach(function () {
    Schema::create('ct_invoices', function (Blueprint $t) {
        $t->id();
        $t->string('number');
        $t->unsignedBigInteger('tenant_id')->nullable();
    });

    CtInvoice::withoutGlobalScope(TenantScope::class)->insert([
        ['number' => 'A-1', 'tenant_id' => 1],
        ['number' => 'B-1', 'tenant_id' => 2],
    ]);

    config()->set('wire-core.tenancy.enabled', true);
    CtRecordingIsolation::$calls = [];
});

afterEach(function () {
    Schema::dropIfExists('ct_invoices');
    config()->set('wire-core.tenancy.enabled', false);
});

it('resolves the entered tenant by default, and nothing before one is entered', function () {
    expect(app(TenantResolver::class))->toBeInstanceOf(CurrentTenantResolver::class)
        ->and(CtInvoice::query()->count())->toBe(0);

    app(CurrentTenant::class)->enter(ctCompany(1));

    expect(CtInvoice::query()->pluck('number')->all())->toBe(['A-1'])
        ->and(app(CurrentTenant::class)->key())->toBe(1);

    app(CurrentTenant::class)->leave();

    expect(CtInvoice::query()->count())->toBe(0)
        ->and(app(CurrentTenant::class)->has())->toBeFalse();
});

it('attributes a new row to the entered tenant', function () {
    app(CurrentTenant::class)->enter(ctCompany(2));

    expect(CtInvoice::query()->create(['number' => 'B-2'])->tenant_id)->toBe(2);
});

it('runs a callback inside a tenant and restores what was there, even when it throws', function () {
    $tenancy = app(Tenancy::class);
    $current = app(CurrentTenant::class);

    $numbers = $tenancy->runAs(ctCompany(1), fn (CtCompany $tenant) => CtInvoice::query()->pluck('number')->all());

    expect($numbers)->toBe(['A-1'])->and($current->has())->toBeFalse();

    $current->enter(ctCompany(2));

    $inner = $tenancy->runAs(ctCompany(1), fn () => $tenancy->runAs(ctCompany(2), fn () => $current->key()));

    expect($inner)->toBe(2)->and($current->key())->toBe(2);

    expect(fn () => $tenancy->runAs(ctCompany(1), fn () => throw new RuntimeException('boom')))
        ->toThrow(RuntimeException::class);

    expect($current->key())->toBe(2);
});

it('drives the isolation once per change, leaving the old tenant first', function () {
    app()->instance(IsolatesTenants::class, new CtRecordingIsolation);
    app()->forgetScopedInstances();

    $current = app(CurrentTenant::class);
    $current->enter(ctCompany(1));
    $current->enter(ctCompany(1));
    $current->enter(ctCompany(2));
    $current->leave();
    $current->leave();

    expect(CtRecordingIsolation::$calls)->toBe(['enter:1', 'leave', 'enter:2', 'leave']);
});

it('tells tenants apart by class and key, not by object', function () {
    $current = app(CurrentTenant::class);
    $current->enter(ctCompany(1));

    expect($current->is(ctCompany(1)))->toBeTrue()
        ->and($current->is(ctCompany(2)))->toBeFalse()
        ->and($current->get()?->getKey())->toBe(1);
});

it('starts every request and job empty', function () {
    // `scoped()`: Octane and the queue worker forget scoped instances between
    // requests and jobs, and a held tenant would outlive the one that set it.
    app(CurrentTenant::class)->enter(ctCompany(1));

    app()->forgetScopedInstances();

    expect(app(CurrentTenant::class)->has())->toBeFalse()
        ->and(CtInvoice::query()->count())->toBe(0);
});

it('isolates by column unless configured otherwise', function () {
    app()->forgetInstance(IsolatesTenants::class);
    expect(app(IsolatesTenants::class))->toBeInstanceOf(ColumnIsolation::class);

    config()->set('wire-core.tenancy.isolation', CtRecordingIsolation::class);
    app()->forgetInstance(IsolatesTenants::class);
    expect(app(IsolatesTenants::class))->toBeInstanceOf(CtRecordingIsolation::class);

    config()->set('wire-core.tenancy.isolation', 'schema');
    app()->forgetInstance(IsolatesTenants::class);
    expect(fn () => app(IsolatesTenants::class))->toThrow(TenancyException::class, '[schema] is not a tenant isolation');

    config()->set('wire-core.tenancy.isolation', 'column');
});

it('leaves an application its own resolver', function () {
    app()->bind(TenantResolver::class, fn () => new class implements TenantResolver
    {
        public function resolve(): int|string|null
        {
            return 2;
        }
    });
    app()->forgetInstance(Tenancy::class);

    app(CurrentTenant::class)->enter(ctCompany(1));

    expect(CtInvoice::query()->pluck('number')->all())->toBe(['B-1']);
});

it('announces entering and leaving, once per change', function () {
    Event::fake([
        TenantEntered::class,
        TenantLeft::class,
    ]);

    $current = app(CurrentTenant::class);
    $current->enter(ctCompany(1));
    $current->enter(ctCompany(1));
    $current->enter(ctCompany(2));
    $current->leave();

    Event::assertDispatchedTimes(TenantEntered::class, 2);
    Event::assertDispatched(TenantLeft::class, fn ($event) => $event->tenant->getKey() === 2);
    Event::assertDispatchedTimes(TenantLeft::class, 2);
});
