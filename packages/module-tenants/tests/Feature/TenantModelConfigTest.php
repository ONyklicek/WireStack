<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireModuleTenants\Actions\AcceptInvitation;
use NyonCode\WireModuleTenants\Actions\ChangeMemberRole;
use NyonCode\WireModuleTenants\Actions\RegisterTenant;
use NyonCode\WireModuleTenants\Actions\RemoveMember;
use NyonCode\WireModuleTenants\Enums\MemberRole;
use NyonCode\WireModuleTenants\Models\Tenant;
use NyonCode\WireModuleTenants\Models\TenantInvitation;
use NyonCode\WireModuleTenants\Support\Membership;

/*
 * An application's own company model, named where the tenancy docs say to name
 * it: `wire-core.tenancy.model`, with its own pivot table.
 *
 * The module used to read only its own `model` key, so it registered companies
 * into `tenants` while the zone looked them up in the application's table — and
 * it read the pivot by `tenant_id` while `InteractsWithTenants` read it by
 * Laravel's convention, `company_id` for a `Company`.
 */

class TmCompany extends Model
{
    protected $table = 'tm_companies';

    protected $guarded = [];

    public function getForeignKey(): string
    {
        return 'company_id';
    }
}

beforeEach(function () {
    Schema::create('tm_companies', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('slug')->unique();
        $table->timestamps();
    });

    config()->set('wire-core.tenancy.model', TmCompany::class);
    config()->set('wire-core.tenancy.members_table', 'company_user');

    (require __DIR__.'/../../database/migrations/create_wire_tenants_tables.php')->up();
});

it('resolves the company model from core first, then from the module', function () {
    expect(Membership::tenantModel())->toBe(TmCompany::class)
        ->and(Membership::tenantKey())->toBe('company_id')
        ->and(Membership::userKey())->toBe('user_id');

    config()->set('wire-core.tenancy.model', null);

    expect(Membership::tenantModel())->toBe(Tenant::class)
        ->and(Membership::tenantKey())->toBe('tenant_id');
});

it('migrates the configured pivot, with the columns InteractsWithTenants reads', function () {
    expect(Schema::hasTable('company_user'))->toBeTrue()
        ->and(Schema::hasColumns('company_user', ['company_id', 'user_id', 'role']))->toBeTrue();
});

it('registers a company into the application\'s model, and the person can enter it', function () {
    $ada = $this->person('Ada');

    $company = app(RegisterTenant::class)('Acme', 'acme', $ada);

    expect($company)->toBeInstanceOf(TmCompany::class)
        ->and(Tenant::query()->count())->toBe(0)
        ->and(Membership::isOwner($company, $ada))->toBeTrue()
        // The trait's own reading of the same pivot.
        ->and($ada->canAccessTenant($company))->toBeTrue()
        ->and($ada->getTenants())->toHaveCount(1);
});

it('changes, removes and invites against the same pivot', function () {
    $ada = $this->person('Ada');
    $grace = $this->person('Grace');
    $company = app(RegisterTenant::class)('Acme', 'acme', $ada);

    $invitation = TenantInvitation::query()->create([
        'tenant_id' => $company->getKey(),
        'email' => 'grace@example.com',
        'role' => MemberRole::Member,
        'expires_at' => now()->addDay(),
    ]);

    expect($invitation->tenant()->first())->toBeInstanceOf(TmCompany::class)
        ->and(app(AcceptInvitation::class)($invitation, $grace))->not->toBeNull()
        ->and(Membership::roleOf($company, $grace))->toBe(MemberRole::Member);

    app(ChangeMemberRole::class)($company, $grace, MemberRole::Owner, $ada);

    expect(Membership::owners($company))->toBe(2);

    app(RemoveMember::class)($company, $grace, $ada);

    expect(Membership::memberIds($company))->toBe([$ada->getKey()]);
});
