<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireModuleUsers\Support\Accounts;
use NyonCode\WireModuleUsers\Support\Permissions;
use NyonCode\WireModuleUsers\Support\Roles;
use NyonCode\WireModuleUsers\Support\Teams;
use NyonCode\WireModuleUsers\Tests\Fixtures\Team;
use NyonCode\WireModuleUsers\Tests\Fixtures\TeamAdmin;
use NyonCode\WireModuleUsers\Tests\Support\Tables;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
 * The two administrator roles, and how they are given.
 *
 * `team-admin` manages one team: a global role given inside a team, carrying the
 * abilities of the user and role screens. `admin` manages every team: the same
 * abilities, given with `--global` so they count everywhere. Both are made with
 * those abilities the first time they are given, as exact names — a granted
 * `users.*` would be a name, and `can('users.viewAny')` asks a name.
 */

beforeEach(function () {
    config()->set('wire-module-users.model', TeamAdmin::class);
    config()->set('auth.providers.users.model', TeamAdmin::class);
    config()->set('wire-module-users.teams.model', Team::class);
    config()->set('permission.teams', true);
    app()->forgetInstance(PermissionRegistrar::class);

    Tables::teamsWithRoles();

    $this->ops = Team::query()->create(['name' => 'Ops']);
    $this->billing = Team::query()->create(['name' => 'Billing']);

    $this->mia = TeamAdmin::query()->create(['name' => 'Mia', 'email' => 'mia@example.com', 'password' => Hash::make('secret')]);
    $this->mia->teams()->attach([$this->ops->getKey(), $this->billing->getKey()]);
});

/** Whether the account may do this, with this team current. */
function arCan(TeamAdmin $user, string $ability, int|string|null $team): bool
{
    app(PermissionRegistrar::class)->setPermissionsTeamId($team);

    return $user->fresh()->can($ability);
}

it('lists every ability the user and role screens require, as exact names', function () {
    expect(Permissions::abilities())->toBe([
        'users.viewAny', 'users.view', 'users.create', 'users.update',
        'roles.viewAny', 'roles.view', 'roles.create', 'roles.update',
    ]);

    config()->set('wire-module-users.permissions.roles', ['viewAny' => null, 'view' => '', 'create' => 'roles.create', 'update' => 'users.update']);

    expect(Permissions::abilities())->toBe(['users.viewAny', 'users.view', 'users.create', 'users.update', 'roles.create']);
});

it('makes a team manager of one team, with the abilities of the screens', function () {
    $this->artisan('wire:assign-role', [
        'email' => 'mia@example.com',
        '--role' => ['team-admin'],
        '--team' => (string) $this->ops->getKey(),
        '--no-interaction' => true,
    ])->assertSuccessful();

    $role = Role::query()->where('name', 'team-admin')->firstOrFail();

    expect($role->team_id)->toBeNull()
        ->and($role->permissions->pluck('name')->sort()->values()->all())->toBe(collect(Permissions::abilities())->sort()->values()->all())
        ->and(arCan($this->mia, 'users.viewAny', $this->ops->getKey()))->toBeTrue()
        ->and(arCan($this->mia, 'users.viewAny', $this->billing->getKey()))->toBeFalse()
        ->and(Teams::seesEveryTeam('users.viewAny', $this->mia->fresh()))->toBeFalse();
});

it('gives the defaults only when it makes the role, never over an application\'s own edits', function () {
    $accounts = app(Accounts::class);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->ops->getKey());
    $accounts->assign($this->mia, 'team-admin', $this->ops->getKey());

    Role::query()->where('name', 'team-admin')->firstOrFail()->revokePermissionTo('roles.update');

    $accounts->assign($this->mia, 'team-admin', $this->billing->getKey());

    expect(Role::query()->where('name', 'team-admin')->count())->toBe(1)
        ->and(Role::query()->where('name', 'team-admin')->firstOrFail()->hasPermissionTo('roles.update'))->toBeFalse();
});

it('makes an administrator of every team with --global', function () {
    $ada = TeamAdmin::query()->create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => Hash::make('secret')]);

    $this->artisan('wire:assign-role', ['email' => 'ada@example.com', '--role' => ['admin'], '--global' => true, '--no-interaction' => true])
        ->expectsOutputToContain('in every team')
        ->assertSuccessful();

    expect(arCan($ada, 'users.viewAny', $this->ops->getKey()))->toBeTrue()
        ->and(arCan($ada, 'roles.update', $this->billing->getKey()))->toBeTrue()
        ->and(arCan($ada, 'roles.update', null))->toBeTrue()
        ->and(Teams::seesEveryTeam('users.viewAny', $ada->fresh()))->toBeTrue()
        // An administrator, not a super-admin: nothing beyond what the role carries.
        ->and(arCan($ada, 'billing.view', $this->ops->getKey()))->toBeFalse();
});

it('refuses --global with --team, and the super-admin as a global role', function () {
    $this->artisan('wire:assign-role', ['email' => 'mia@example.com', '--role' => ['admin'], '--global' => true, '--team' => '1', '--no-interaction' => true])
        ->expectsOutputToContain('takes no --team')
        ->assertFailed();

    $this->artisan('wire:assign-role', ['email' => 'mia@example.com', '--role' => ['super-admin'], '--global' => true, '--no-interaction' => true])
        ->expectsOutputToContain('--super-admin')
        ->assertFailed();
});

it('says --global changes nothing without teams, and still gives the role', function () {
    config()->set('permission.teams', false);
    app()->forgetInstance(PermissionRegistrar::class);

    // The pivot as an application without teams has it: no team column.
    Schema::drop('model_has_roles');
    Schema::create('model_has_roles', function (Blueprint $table): void {
        $table->unsignedBigInteger('role_id');
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->primary(['role_id', 'model_id', 'model_type']);
    });

    $this->artisan('wire:assign-role', ['email' => 'mia@example.com', '--role' => ['admin'], '--global' => true, '--no-interaction' => true])
        ->expectsOutputToContain('--global changes nothing')
        ->assertSuccessful();

    expect($this->mia->fresh()->hasRole('admin'))->toBeTrue();
});

it('never picks up a role of another team by its name, and prefers the team\'s own', function () {
    $accounts = app(Accounts::class);
    $billingSupport = Role::query()->create(['name' => 'support', 'guard_name' => 'web', 'team_id' => $this->billing->getKey()]);

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->ops->getKey());
    $accounts->assign($this->mia, 'support', $this->ops->getKey());

    $given = $this->mia->fresh()->roles->firstWhere('name', 'support');

    expect($given->getKey())->not->toBe($billingSupport->getKey())
        ->and($given->team_id)->toBeNull();

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->billing->getKey());
    $accounts->assign($this->mia, 'support', $this->billing->getKey());

    expect($this->mia->fresh()->roles->firstWhere('name', 'support')->getKey())->toBe($billingSupport->getKey());
});

it('makes an ordinary role empty, and a team manager role empty where there is none configured', function () {
    config()->set('wire-module-users.teams.admin_role', null);

    expect(Roles::teamAdmin())->toBeNull()
        ->and(Roles::defaultPermissions('team-admin'))->toBe([])
        ->and(Roles::defaultPermissions('editor'))->toBe([])
        ->and(Roles::defaultPermissions('admin'))->toBe(Permissions::abilities());
});

it('gives no global role where the application has no roles', function () {
    config()->set('wire-module-users.roles', false);

    expect(app(Accounts::class)->assignGlobal($this->mia, 'admin'))->toBeNull();
});
