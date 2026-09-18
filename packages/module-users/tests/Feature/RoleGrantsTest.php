<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use NyonCode\WireModuleUsers\Pages\CreateRole;
use NyonCode\WireModuleUsers\Pages\EditRole;
use NyonCode\WireModuleUsers\Pages\EditUser;
use NyonCode\WireModuleUsers\Support\RoleGrants;
use NyonCode\WireModuleUsers\Support\Roles;
use NyonCode\WireModuleUsers\Tests\Fixtures\Team;
use NyonCode\WireModuleUsers\Tests\Fixtures\TeamAdmin;
use NyonCode\WireModuleUsers\Tests\Support\Access;
use NyonCode\WireModuleUsers\Tests\Support\Tables;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
 * No more than you hold.
 *
 * A team's manager may edit their team's roles and give roles to its members.
 * Without this they could tick `billing.*` into a role of their team, give it to
 * themselves, and hold what nobody gave them. Every attempt below is one a
 * manager could make with the screens or a forged request; every one of them
 * writes only the part the manager was entitled to.
 */

beforeEach(function () {
    Access::grantEveryAbility();

    config()->set('wire-module-users.model', TeamAdmin::class);
    config()->set('auth.providers.users.model', TeamAdmin::class);
    config()->set('wire-module-users.teams.model', Team::class);
    config()->set('permission.teams', true);
    app()->forgetInstance(PermissionRegistrar::class);

    Tables::teamsWithRoles();
    Route::middleware('web')->group(fn () => Route::wireResources());

    foreach (['users.viewAny', 'users.update', 'roles.update', 'invoices.view', 'invoices.*', 'billing.view'] as $name) {
        Permission::findOrCreate($name, 'web');
    }

    $this->ops = Team::query()->create(['name' => 'Ops']);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($this->ops->getKey());

    // The manager's own role in Ops: users, roles, and invoices.view — not
    // invoices.*, not billing.view.
    $this->managerRole = Role::query()->create(['name' => 'team-admin', 'guard_name' => 'web', 'team_id' => $this->ops->getKey()])
        ->givePermissionTo(['users.viewAny', 'users.update', 'roles.update', 'invoices.view']);

    $this->manager = TeamAdmin::query()->create(['name' => 'Mia', 'email' => 'mia@example.com', 'password' => Hash::make('secret')]);
    $this->manager->teams()->attach($this->ops->getKey());
    $this->manager->assignRole($this->managerRole);

    $this->member = TeamAdmin::query()->create(['name' => 'Olga', 'email' => 'olga@example.com', 'password' => Hash::make('secret')]);
    $this->member->teams()->attach($this->ops->getKey());

    $this->opsRole = Role::query()->create(['name' => 'ops-support', 'guard_name' => 'web', 'team_id' => $this->ops->getKey()]);
    $this->clerk = Role::query()->create(['name' => 'clerk', 'guard_name' => 'web', 'team_id' => $this->ops->getKey()])
        ->givePermissionTo('billing.view');

    $this->be($this->manager->fresh());
});

it('offers a manager only the permissions they hold', function () {
    expect(array_keys(Roles::permissionOptions()))->toBe(['invoices.view', 'roles.update', 'users.update', 'users.viewAny']);
});

it('writes into a role only the permissions its editor holds, whatever the request ticks', function () {
    Livewire::test(EditRole::class, ['record' => $this->opsRole->getKey()])
        ->set('data.permissions', ['invoices.view', 'billing.view', 'invoices.*'])
        ->call('save')
        ->assertHasNoErrors();

    expect($this->opsRole->fresh()->permissions->pluck('name')->all())->toBe(['invoices.view']);
});

it('does not take away a permission its editor does not hold', function () {
    Livewire::test(EditRole::class, ['record' => $this->clerk->getKey()])
        ->set('data.permissions', ['invoices.view'])
        ->call('save')
        ->assertHasNoErrors();

    expect($this->clerk->fresh()->permissions->pluck('name')->sort()->values()->all())->toBe(['billing.view', 'invoices.view']);
});

it('creates a role with only the permissions its creator holds', function () {
    Livewire::test(CreateRole::class)
        ->set('data.name', 'self-promotion')
        ->set('data.permissions', ['billing.view', 'users.update'])
        ->call('save')
        ->assertHasNoErrors();

    expect(Role::query()->where('name', 'self-promotion')->firstOrFail()->permissions->pluck('name')->all())->toBe(['users.update']);
});

it('offers a manager only the roles whose every permission they hold', function () {
    expect(array_keys(Roles::options()))->toBe(['ops-support', 'team-admin']);
});

it('gives a member only the roles its giver may give, and keeps the ones they may not', function () {
    $this->member->assignRole($this->clerk);

    Livewire::test(EditUser::class, ['record' => $this->member->getKey()])
        ->set('data.roles', ['ops-support'])
        ->call('save')
        ->assertHasNoErrors();

    expect($this->member->fresh()->roles->pluck('name')->sort()->values()->all())->toBe(['clerk', 'ops-support']);

    // And the manager cannot hand themselves one either.
    Livewire::test(EditUser::class, ['record' => $this->manager->getKey()])
        ->set('data.roles', ['team-admin', 'clerk', 'admin', 'super-admin'])
        ->call('save')
        ->assertHasNoErrors();

    expect($this->manager->fresh()->roles->pluck('name')->all())->toBe(['team-admin']);
});

it('reads a wildcard as a name: holding invoices.* is what lets you give it', function () {
    expect(RoleGrants::mayGrantPermission('invoices.*'))->toBeFalse()
        ->and(RoleGrants::mayGrantPermission('invoices.view'))->toBeTrue();

    $this->managerRole->givePermissionTo('invoices.*');
    $this->be($this->manager->fresh());

    expect(RoleGrants::mayGrantPermission('invoices.*'))->toBeTrue();
});

it('lets a global administrator give what their global role holds, in any team', function () {
    Role::query()->create(['name' => 'admin', 'guard_name' => 'web', 'team_id' => null])->givePermissionTo('billing.view');
    $ada = TeamAdmin::query()->create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => Hash::make('secret')]);
    $ada->assignGlobalRole('admin');
    $ada = $ada->fresh();

    expect(RoleGrants::mayGrantPermission('billing.view', $ada))->toBeTrue()
        ->and(RoleGrants::mayGrantRole($this->clerk, $ada))->toBeTrue()
        // …but never the administrator role itself.
        ->and(RoleGrants::mayGrantRole('admin', $ada))->toBeFalse();
});

it('lets a super-admin give everything except the super-admin', function () {
    Role::query()->create(['name' => 'super-admin', 'guard_name' => 'web', 'team_id' => null]);
    $root = TeamAdmin::query()->create(['name' => 'Root', 'email' => 'root@example.com', 'password' => Hash::make('secret')]);
    $root->assignGlobalRole('super-admin');
    $root = $root->fresh();

    expect(RoleGrants::mayGrantPermission('billing.view', $root))->toBeTrue()
        ->and(RoleGrants::mayGrantRole('admin', $root))->toBeTrue()
        ->and(RoleGrants::mayGrantRole('clerk', $root))->toBeTrue()
        ->and(RoleGrants::mayGrantRole('super-admin', $root))->toBeFalse();
});

it('does not find a role of another team, or one that does not exist, to give', function () {
    $billing = Team::query()->create(['name' => 'Billing']);
    Role::query()->create(['name' => 'billing-free', 'guard_name' => 'web', 'team_id' => $billing->getKey()]);

    expect(RoleGrants::mayGrantRole('billing-free'))->toBeFalse()
        ->and(RoleGrants::mayGrantRole('no-such-role'))->toBeFalse()
        ->and(RoleGrants::mayGrantRole('ops-support'))->toBeTrue();
});

it('lets nobody who is signed out give anything', function () {
    auth()->guard()->forgetUser();

    expect(RoleGrants::mayGrantPermission('invoices.view'))->toBeFalse()
        ->and(RoleGrants::clampPermissions(['billing.view'], ['invoices.view']))->toBe(['billing.view']);
});

it('keeps what was there and drops what cannot be read as a name', function () {
    expect(RoleGrants::clampRoles(['clerk'], ['ops-support', 42, null]))->toBe(['clerk', 'ops-support']);
});
