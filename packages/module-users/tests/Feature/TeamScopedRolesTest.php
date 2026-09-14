<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use NyonCode\WireModuleUsers\Pages\CreateRole;
use NyonCode\WireModuleUsers\Pages\EditRole;
use NyonCode\WireModuleUsers\Pages\ListRoles;
use NyonCode\WireModuleUsers\Pages\ViewRole;
use NyonCode\WireModuleUsers\Support\Roles;
use NyonCode\WireModuleUsers\Tests\Fixtures\Team;
use NyonCode\WireModuleUsers\Tests\Fixtures\TeamAdmin;
use NyonCode\WireModuleUsers\Tests\Support\Access;
use NyonCode\WireModuleUsers\Tests\Support\Tables;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
 * The roles screen, in an application with teams — and the two roles nobody
 * edits by accident.
 *
 * A team's manager sees the global roles, as templates to read, and their own
 * team's roles, to change. Another team's roles are not there: not in the list,
 * not by URL (404), not to a forged row action. The super-admin role is never
 * changed on these screens, and the administrator role only by a super-admin.
 */

beforeEach(function () {
    // The page guard has its own tests; these are about which roles a page that
    // has let you in shows you, and which of them it lets you change.
    Access::grantEveryAbility();

    config()->set('wire-module-users.model', TeamAdmin::class);
    config()->set('auth.providers.users.model', TeamAdmin::class);
    config()->set('wire-module-users.teams.model', Team::class);
    config()->set('permission.teams', true);
    app()->forgetInstance(PermissionRegistrar::class);

    Tables::teamsWithRoles();

    Route::middleware('web')->group(fn () => Route::wireResources());

    $this->ops = Team::query()->create(['name' => 'Ops']);
    $this->billing = Team::query()->create(['name' => 'Billing']);

    Permission::findOrCreate('roles.viewAny', 'web');
    Permission::findOrCreate('roles.create', 'web');
    Permission::findOrCreate('roles.update', 'web');

    $this->template = Role::query()->create(['name' => 'editor', 'guard_name' => 'web']);
    $this->opsRole = Role::query()->create(['name' => 'ops-support', 'guard_name' => 'web', 'team_id' => $this->ops->getKey()]);
    $this->billingRole = Role::query()->create(['name' => 'billing-clerk', 'guard_name' => 'web', 'team_id' => $this->billing->getKey()]);
    $this->superAdminRole = Role::query()->create(['name' => 'super-admin', 'guard_name' => 'web']);
    $this->adminRole = Role::query()->create(['name' => 'admin', 'guard_name' => 'web'])
        ->givePermissionTo(['roles.viewAny', 'roles.create', 'roles.update']);
});

function tsrManager(Team $team): TeamAdmin
{
    $manager = TeamAdmin::query()->create(['name' => 'Mia', 'email' => 'mia@example.com', 'password' => Hash::make('secret')]);
    $manager->teams()->attach($team->getKey());

    return $manager;
}

function tsrGlobalAdmin(): TeamAdmin
{
    $admin = TeamAdmin::query()->create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => Hash::make('secret')]);
    $admin->assignGlobalRole('admin');

    return $admin->fresh();
}

function tsrSuperAdmin(): TeamAdmin
{
    $root = TeamAdmin::query()->create(['name' => 'Root', 'email' => 'root@example.com', 'password' => Hash::make('secret')]);
    $root->assignGlobalRole('super-admin');

    return $root->fresh();
}

it('shows a team manager the global roles and their own team\'s, never another team\'s', function () {
    $this->be(tsrManager($this->ops));

    Livewire::test(ListRoles::class)
        ->assertSee('editor')
        ->assertSee('ops-support')
        ->assertDontSee('billing-clerk');
});

it('shows every team\'s roles to an administrator whose ability is global', function () {
    $this->be(tsrGlobalAdmin());

    Livewire::test(ListRoles::class)
        ->assertSee('ops-support')
        ->assertSee('billing-clerk');
});

it('shows only the global roles to somebody in no team', function () {
    $this->be(TeamAdmin::query()->create(['name' => 'Nina', 'email' => 'nina@example.com', 'password' => Hash::make('secret')]));

    Livewire::test(ListRoles::class)
        ->assertSee('editor')
        ->assertDontSee('ops-support')
        ->assertDontSee('billing-clerk');
});

it('answers 404 for another team\'s role opened by its URL', function () {
    $this->be(tsrManager($this->ops));

    Livewire::test(ViewRole::class, ['record' => $this->billingRole->getKey()])->assertStatus(404);
    Livewire::test(EditRole::class, ['record' => $this->billingRole->getKey()])->assertStatus(404);
});

it('lets a team manager read a global role and not change it', function () {
    $this->be(tsrManager($this->ops));

    Livewire::test(ViewRole::class, ['record' => $this->template->getKey()])->assertOk();
    Livewire::test(EditRole::class, ['record' => $this->template->getKey()])->assertStatus(403);
    Livewire::test(EditRole::class, ['record' => $this->opsRole->getKey()])->assertOk();
});

it('lets nobody change the super-admin role on these screens, a super-admin included', function () {
    $this->be(tsrSuperAdmin());

    expect(Roles::mayChange($this->superAdminRole))->toBeFalse();

    Livewire::test(EditRole::class, ['record' => $this->superAdminRole->getKey()])->assertStatus(403);
});

it('lets only a super-admin change the administrator role', function () {
    $this->be(tsrGlobalAdmin());

    expect(Roles::mayChange($this->adminRole))->toBeFalse()
        ->and(Roles::mayChange($this->template))->toBeTrue();
    Livewire::test(EditRole::class, ['record' => $this->adminRole->getKey()])->assertStatus(403);

    $this->be(tsrSuperAdmin());

    expect(Roles::mayChange($this->adminRole))->toBeTrue();
    Livewire::test(EditRole::class, ['record' => $this->adminRole->getKey()])->assertOk();
});

it('offers no Edit or Delete on a role this person may not change', function () {
    $this->be(tsrManager($this->ops));

    $html = Livewire::test(ListRoles::class)->html();

    // Four roles on this manager's list — editor, ops-support, super-admin,
    // admin — and one of them is theirs to change.
    expect(substr_count($html, 'data-testid="action-delete"'))->toBe(1);
});

it('deletes nothing it would not have offered, whatever key the browser sends', function () {
    $this->be(tsrManager($this->ops));

    Livewire::test(ListRoles::class)
        ->call('executeTableAction', (string) $this->template->getKey(), 'delete', true)
        ->call('executeTableAction', (string) $this->billingRole->getKey(), 'delete', true)
        ->call('executeTableAction', (string) $this->superAdminRole->getKey(), 'delete', true);

    expect(Role::query()->whereKey([$this->template->getKey(), $this->billingRole->getKey(), $this->superAdminRole->getKey()])->count())->toBe(3);
});

it('makes a role of the team for a team manager, and a global one for a global administrator', function () {
    $this->be(tsrManager($this->ops));

    Livewire::test(CreateRole::class)
        ->set('data.name', 'ops-night-shift')
        ->call('save')
        ->assertHasNoErrors();

    expect(Role::query()->where('name', 'ops-night-shift')->value('team_id'))->toEqual($this->ops->getKey());

    $this->be(tsrGlobalAdmin());

    Livewire::test(CreateRole::class)
        ->set('data.name', 'auditor')
        ->call('save')
        ->assertHasNoErrors();

    expect(Role::query()->where('name', 'auditor')->value('team_id'))->toBeNull();
});

it('refuses a new role from a manager who is in no team', function () {
    // It could only be a global role, and changing global roles is not theirs.
    $this->be(TeamAdmin::query()->create(['name' => 'Nina', 'email' => 'nina@example.com', 'password' => Hash::make('secret')]));

    Livewire::test(CreateRole::class)
        ->set('data.name', 'rogue')
        ->call('save')
        ->assertStatus(403);

    expect(Role::query()->where('name', 'rogue')->exists())->toBeFalse();
});

it('offers a team manager\'s user form only the roles they can see', function () {
    $this->be(tsrManager($this->ops));

    expect(array_keys(Roles::options()))->toBe(['editor', 'ops-support']);

    $this->be(tsrSuperAdmin());

    expect(array_keys(Roles::options()))->toBe(['admin', 'billing-clerk', 'editor', 'ops-support']);
});

it('protects the administrator role without teams too, and not at all where there is none', function () {
    config()->set('permission.teams', false);
    $this->be(TeamAdmin::query()->create(['name' => 'Nina', 'email' => 'nina@example.com', 'password' => Hash::make('secret')]));

    expect(Roles::mayChange($this->adminRole))->toBeFalse()
        ->and(Roles::mayChange($this->template))->toBeTrue();

    config()->set('wire-module-users.admin_role', null);

    expect(Roles::admin())->toBeNull()
        ->and(Roles::mayChange($this->adminRole))->toBeTrue();
});
