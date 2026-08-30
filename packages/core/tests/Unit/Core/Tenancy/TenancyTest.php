<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Core\Tenancy\Concerns\BelongsToTenant;
use NyonCode\WireCore\Core\Tenancy\Contracts\TenantResolver;
use NyonCode\WireCore\Core\Tenancy\Tenancy;
use NyonCode\WireCore\Core\Tenancy\TenantScope;
use NyonCode\WireCore\Exceptions\TenancyException;

/*
 * Multi-tenancy.
 *
 * The plan calls the fail-safe the go/no-go for the whole package, and it is the
 * one property worth stating twice: tenancy on with no tenant resolved returns
 * NOTHING, never everything. Every ordinary state produces a null tenant — before
 * login, on a worker, in a console command — so "null means everything" would
 * hand every row to every one of them.
 */
class TnInvoice extends Model
{
    use BelongsToTenant;

    protected $table = 'tn_invoices';

    protected $guarded = [];

    public $timestamps = false;
}

/** Whatever the test says the current tenant is. */
function tnTenant(int|string|null $id): void
{
    app()->bind(TenantResolver::class, fn () => new class($id) implements TenantResolver
    {
        public function __construct(private int|string|null $id) {}

        public function resolve(): int|string|null
        {
            return $this->id;
        }
    });

    app()->forgetInstance(Tenancy::class);
}

beforeEach(function () {
    Schema::create('tn_invoices', function (Blueprint $t) {
        $t->id();
        $t->string('number');
        $t->string('tenant_id')->nullable();
    });

    // Seeded past the scope, the way a migration or another tenant's request
    // would have written them.
    TnInvoice::withoutGlobalScope(TenantScope::class)->insert([
        ['id' => 1, 'number' => 'A-1', 'tenant_id' => 'acme'],
        ['id' => 2, 'number' => 'A-2', 'tenant_id' => 'acme'],
        ['id' => 3, 'number' => 'G-1', 'tenant_id' => 'globex'],
        // A row nobody owns — the one a "null means null" scope would leak.
        ['id' => 4, 'number' => 'ORPHAN', 'tenant_id' => null],
    ]);

    config()->set('wire-core.tenancy.enabled', true);
    tnTenant('acme');
});

afterEach(function () {
    Schema::dropIfExists('tn_invoices');
    config()->set('wire-core.tenancy.enabled', false);
});

// ─── The fail-safe: the go/no-go for the whole package ───────────────────────

it('returns NOTHING when tenancy is on and no tenant resolved', function () {
    // Before login, on a worker, in a console command. If this ever answers
    // "everything", every one of those states is a full data leak.
    tnTenant(null);

    expect(TnInvoice::query()->count())->toBe(0)
        ->and(TnInvoice::query()->get())->toBeEmpty()
        ->and(TnInvoice::query()->find(1))->toBeNull();
});

it('says so directly, so the rule can be asserted without counting rows', function () {
    tnTenant(null);
    expect(app(Tenancy::class)->shouldBlockEverything())->toBeTrue();

    tnTenant('acme');
    expect(app(Tenancy::class)->shouldBlockEverything())->toBeFalse();
});

it('does not treat an unowned row as everyone-s', function () {
    // `whereNull($column)` instead of "nothing" would hand ORPHAN to every
    // tenant and to every unauthenticated request.
    tnTenant(null);
    expect(TnInvoice::query()->pluck('number')->all())->toBe([]);

    tnTenant('acme');
    expect(TnInvoice::query()->pluck('number')->all())->not->toContain('ORPHAN');
});

// ─── Reads ───────────────────────────────────────────────────────────────────

it('shows one tenant only its own rows', function () {
    expect(TnInvoice::query()->pluck('number')->all())->toBe(['A-1', 'A-2']);

    tnTenant('globex');

    expect(TnInvoice::query()->pluck('number')->all())->toBe(['G-1']);
});

it('hides another tenant-s row from a direct lookup by key', function () {
    // The path a hook on the table query would never have covered.
    expect(TnInvoice::query()->find(3))->toBeNull();
});

it('qualifies the column, so a join carrying its own tenant_id cannot confuse it', function () {
    // The shape that bites in production: a scoped model joined to a table that
    // also has tenant_id. Unqualified, the column is ambiguous at best and the
    // wrong table's at worst — and either way the row set is not what was asked
    // for.
    Schema::create('tn_lines', function (Blueprint $t) {
        $t->id();
        $t->foreignId('invoice_id');
        $t->string('tenant_id')->nullable();
    });

    DB::table('tn_lines')->insert([
        ['id' => 1, 'invoice_id' => 1, 'tenant_id' => 'globex'],
        ['id' => 2, 'invoice_id' => 3, 'tenant_id' => 'acme'],
    ]);

    // Deliberately crossed: the line rows carry the *other* tenant, so an
    // unqualified column would select on the join's value and hand back G-1.
    $numbers = TnInvoice::query()
        ->join('tn_lines', 'tn_lines.invoice_id', '=', 'tn_invoices.id')
        ->pluck('tn_invoices.number')
        ->all();

    expect($numbers)->toBe(['A-1']);

    Schema::dropIfExists('tn_lines');
});

// ─── Writes ──────────────────────────────────────────────────────────────────

it('scopes an update, so one tenant cannot touch another-s row', function () {
    TnInvoice::query()->where('number', 'G-1')->update(['number' => 'stolen']);

    expect(TnInvoice::withoutGlobalScope(TenantScope::class)->find(3)->number)->toBe('G-1');
});

it('scopes a delete for the same reason', function () {
    TnInvoice::query()->whereKey(3)->delete();

    expect(TnInvoice::withoutGlobalScope(TenantScope::class)->find(3))->not->toBeNull();
});

it('attributes a new row to the current tenant', function () {
    $invoice = TnInvoice::create(['number' => 'NEW']);

    expect($invoice->tenant_id)->toBe('acme');
});

it('refuses to create a row it cannot attribute', function () {
    // The alternative is a row with a null tenant, which every scoped query then
    // hides from everyone: the user's work is gone and nothing said so.
    tnTenant(null);

    TnInvoice::create(['number' => 'NEW']);
})->throws(TenancyException::class, 'no tenant resolved');

it('leaves an explicitly set tenant alone', function () {
    // A seeder, or a deliberate cross-tenant move. Overriding it would be the
    // framework second-guessing an explicit instruction.
    $invoice = TnInvoice::withoutGlobalScope(TenantScope::class);
    $created = TnInvoice::create(['number' => 'SEEDED', 'tenant_id' => 'globex']);

    expect($created->tenant_id)->toBe('globex');
});

// ─── Off, and the deliberate way out ─────────────────────────────────────────

it('does nothing at all when tenancy is off', function () {
    // Opt-in: most applications have one tenant, and scoping them would be a
    // WHERE clause bought for nothing.
    config()->set('wire-core.tenancy.enabled', false);
    tnTenant(null);

    expect(TnInvoice::query()->count())->toBe(4)
        ->and(TnInvoice::create(['number' => 'NEW'])->tenant_id)->toBeNull();
});

it('can be stepped past deliberately, and that reads as deliberate', function () {
    // An admin report or a console command has a real need; the point is that
    // every place claiming it is greppable.
    expect(TnInvoice::acrossAllTenants()->count())->toBe(4);
});
