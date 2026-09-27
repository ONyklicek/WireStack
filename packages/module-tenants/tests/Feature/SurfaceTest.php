<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Notification;
use NyonCode\WireCore\Core\Tenancy\CurrentTenant;
use NyonCode\WireModuleTenants\Models\TenantInvitation;
use NyonCode\WireModuleTenants\Notifications\TenantInvitationNotification;
use NyonCode\WireModuleTenants\Pages\ListMembers;
use NyonCode\WireModuleTenants\Resources\CompanyResource;
use NyonCode\WireModuleTenants\Resources\MemberResource;
use NyonCode\WireModuleTenants\Tests\Fixtures\User;

/*
 * The small print of the module's surfaces: what its resources are called,
 * who the members list may show, and what the installer says.
 */

it('names its resources', function () {
    expect(CompanyResource::modelClass())->toBeNull()
        ->and(CompanyResource::label())->toBe('Company')
        ->and(CompanyResource::pluralLabel())->toBe('Company')
        ->and(MemberResource::label())->toBe('Member')
        ->and(MemberResource::modelClass())->toBe(User::class);
});

it('lists nobody and shows no role outside a company', function () {
    $ada = $this->person('Ada');
    $this->company('acme', $ada);

    expect(MemberResource::ofCurrentCompany(User::query())->count())->toBe(0)
        ->and(MemberResource::roleLabel($ada))->toBeNull();
});

it('lists the members of the company being worked in, with their role', function () {
    $ada = $this->person('Ada');
    $tenant = $this->company('acme', $ada);
    $this->person('Sam');
    app(CurrentTenant::class)->enter($tenant);

    expect(MemberResource::ofCurrentCompany(User::query())->pluck('name')->all())->toBe(['Ada'])
        ->and(MemberResource::roleLabel($ada))->toBe('Owner')
        ->and($tenant->invitations()->count())->toBe(0);
});

it('invites from the members page and says so', function () {
    Notification::fake();
    $ada = $this->person('Ada');
    $tenant = $this->company('acme', $ada);
    $this->actingAs($ada);
    app(CurrentTenant::class)->enter($tenant);

    $page = new class extends ListMembers
    {
        public function send(array $data): void
        {
            $this->invite($data);
        }
    };

    $page->send(['email' => 'new@example.com', 'role' => 'member']);

    expect(TenantInvitation::query()->where('email', 'new@example.com')->exists())->toBeTrue();
    Notification::assertSentOnDemand(TenantInvitationNotification::class);
});

it('installs under its short name and says what is left to do', function () {
    expect(array_keys(app(Kernel::class)->all()))->toContain('wire-module-tenants:install');

    $config = config_path('wire-module-tenants.php');
    $existed = is_file($config);
    $migrations = glob(database_path('migrations/*create_wire_tenants_tables.php')) ?: [];

    try {
        $this->artisan('wire-module-tenants:install')
            ->expectsOutputToContain('`tenants` module')
            ->expectsOutputToContain('HasTenants')
            ->assertSuccessful();
    } finally {
        if (! $existed && is_file($config)) {
            unlink($config);
        }

        foreach (glob(database_path('migrations/*create_wire_tenants_tables.php')) ?: [] as $path) {
            if (! in_array($path, $migrations, true)) {
                unlink($path);
            }
        }
    }
});
