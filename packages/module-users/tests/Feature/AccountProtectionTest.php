<?php

declare(strict_types=1);

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NyonCode\WireModuleUsers\Livewire\DeleteAccount;
use NyonCode\WireModuleUsers\Pages\EditUser;
use NyonCode\WireModuleUsers\Pages\ListUsers;
use NyonCode\WireModuleUsers\Resources\UserResource;
use NyonCode\WireModuleUsers\Support\AccountGuard;
use NyonCode\WireModuleUsers\Support\Accounts;
use NyonCode\WireModuleUsers\Tests\Fixtures\Team;
use NyonCode\WireModuleUsers\Tests\Fixtures\TeamAdmin;
use NyonCode\WireModuleUsers\Tests\Support\Access;
use NyonCode\WireModuleUsers\Tests\Support\Tables;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
 * Whose account may be touched, and how.
 *
 * A super-admin's account only by a super-admin — an administrator who could set
 * its password could sign in as it. The last super-admin never deleted. A team's
 * manager removes from the team rather than deleting, and sends a reset link
 * rather than setting a password, because the account may be in other teams.
 */

beforeEach(function () {
    Access::grantEveryAbility();

    config()->set('wire-module-users.model', TeamAdmin::class);
    config()->set('auth.providers.users.model', TeamAdmin::class);
    config()->set('wire-module-users.teams.model', Team::class);
    config()->set('permission.teams', true);
    app()->forgetInstance(PermissionRegistrar::class);

    Tables::teamsWithRoles();

    Schema::create('password_reset_tokens', function (Blueprint $table): void {
        $table->string('email')->primary();
        $table->string('token');
        $table->timestamp('created_at')->nullable();
    });

    Route::middleware('web')->group(fn () => Route::wireResources());
    Route::get('/reset-password/{token}', fn () => '')->name('password.reset');

    $this->ops = Team::query()->create(['name' => 'Ops']);
    $this->billing = Team::query()->create(['name' => 'Billing']);

    Permission::findOrCreate('users.viewAny', 'web');
    Permission::findOrCreate('users.update', 'web');
    Role::query()->create(['name' => 'super-admin', 'guard_name' => 'web']);
    Role::query()->create(['name' => 'admin', 'guard_name' => 'web'])->givePermissionTo(['users.viewAny', 'users.update']);

    $this->root = apPerson('Root', 'root@example.com');
    $this->root->assignGlobalRole('super-admin');

    $this->ada = apPerson('Ada', 'ada@example.com');
    $this->ada->assignGlobalRole('admin');

    $this->mia = apPerson('Mia', 'mia@example.com', [$this->ops]);
    $this->olga = apPerson('Olga', 'olga@example.com', [$this->ops, $this->billing]);
});

/**
 * @param  array<int, Team>  $teams
 */
function apPerson(string $name, string $email, array $teams = []): TeamAdmin
{
    $person = TeamAdmin::query()->create(['name' => $name, 'email' => $email, 'password' => Hash::make('secret')]);
    $person->teams()->attach(array_map(static fn (Team $team) => $team->getKey(), $teams));

    return $person;
}

it('keeps a super-admin\'s account out of an administrator\'s reach', function () {
    $this->be($this->ada->fresh());

    expect(AccountGuard::mayEdit($this->root->fresh()))->toBeFalse()
        ->and(AccountGuard::mayDelete($this->root->fresh()))->toBeFalse();

    Livewire::test(EditUser::class, ['record' => $this->root->getKey()])->assertStatus(403);

    Livewire::test(ListUsers::class)
        ->call('executeTableAction', (string) $this->root->getKey(), 'delete', true);

    expect(TeamAdmin::query()->find($this->root->getKey()))->not->toBeNull();
});

it('lets a super-admin edit and delete another super-admin, never the last', function () {
    $second = apPerson('Second', 'second@example.com');
    $second->assignGlobalRole('super-admin');
    $this->be($this->root->fresh());

    expect(AccountGuard::isLastSuperAdmin($second->fresh()))->toBeFalse()
        ->and(AccountGuard::mayDelete($second->fresh()))->toBeTrue();

    Livewire::test(EditUser::class, ['record' => $second->getKey()])->assertOk();
    Livewire::test(ListUsers::class)->call('executeTableAction', (string) $second->getKey(), 'delete', true);

    expect(TeamAdmin::query()->find($second->getKey()))->toBeNull()
        ->and(AccountGuard::isLastSuperAdmin($this->root->fresh()))->toBeTrue()
        ->and(AccountGuard::mayDelete($this->root->fresh()))->toBeFalse();
});

it('does not let the last super-admin delete itself from its profile', function () {
    $this->be($this->root->fresh());

    Livewire::test(DeleteAccount::class)
        ->call('confirm')
        ->set('data.password', 'secret')
        ->call('delete');

    expect(TeamAdmin::query()->find($this->root->getKey()))->not->toBeNull();
});

it('gives a team manager Remove from team instead of Delete, and a reset link instead of a password', function () {
    $this->be($this->mia->fresh());

    $html = Livewire::test(ListUsers::class)->html();

    expect($html)->toContain('data-testid="action-removeFromTeam"')
        // Olga's row only: Mia's own password is her profile's, not a reset link.
        ->and(substr_count($html, 'data-testid="action-sendPasswordReset"'))->toBe(1)
        ->and($html)->not->toContain('data-testid="action-delete"')
        ->and(AccountGuard::mayDelete($this->olga->fresh()))->toBeFalse()
        ->and(AccountGuard::mayChangeCredentials($this->olga->fresh()))->toBeFalse();

    Livewire::test(ListUsers::class)->call('executeTableAction', (string) $this->olga->getKey(), 'delete', true);

    expect(TeamAdmin::query()->find($this->olga->getKey()))->not->toBeNull();
});

it('removes an account from the team with its roles there, and nothing else', function () {
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($this->ops->getKey());
    Role::query()->create(['name' => 'ops-support', 'guard_name' => 'web', 'team_id' => $this->ops->getKey()]);
    $this->olga->assignRole('ops-support');
    $registrar->setPermissionsTeamId($this->billing->getKey());
    Role::query()->create(['name' => 'billing-clerk', 'guard_name' => 'web', 'team_id' => $this->billing->getKey()]);
    $this->olga->assignRole('billing-clerk');
    $this->olga->assignGlobalRole('admin');

    $this->be($this->mia->fresh());
    $registrar->setPermissionsTeamId($this->ops->getKey());

    Livewire::test(ListUsers::class)
        ->call('executeTableAction', (string) $this->olga->getKey(), 'removeFromTeam', true);

    $olga = $this->olga->fresh();

    expect($olga->teams()->pluck('teams.id')->all())->toBe([$this->billing->getKey()])
        ->and($olga->hasGlobalRole('admin'))->toBeTrue();

    $registrar->setPermissionsTeamId($this->ops->getKey());
    expect($olga->fresh()->hasRole('ops-support'))->toBeFalse();

    $registrar->setPermissionsTeamId($this->billing->getKey());
    expect($olga->fresh()->hasRole('billing-clerk'))->toBeTrue();
});

it('lets a team manager correct a name and nothing a person signs in with', function () {
    $this->be($this->mia->fresh());
    $hash = $this->olga->password;

    Livewire::test(EditUser::class, ['record' => $this->olga->getKey()])
        ->set('data.'.UserResource::field('name'), 'Olga K')
        ->set('data.'.UserResource::field('email'), 'mine-now@example.com')
        ->set('data.'.UserResource::field('password'), 'taken-over')
        ->call('save')
        ->assertHasNoErrors();

    $olga = $this->olga->fresh();

    expect($olga->name)->toBe('Olga K')
        ->and($olga->email)->toBe('olga@example.com')
        ->and($olga->password)->toBe($hash);
});

it('lets an administrator of every team change them', function () {
    $this->be($this->ada->fresh());

    Livewire::test(EditUser::class, ['record' => $this->olga->getKey()])
        ->set('data.'.UserResource::field('email'), 'olga.k@example.com')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->olga->fresh()->email)->toBe('olga.k@example.com')
        ->and(AccountGuard::mayDelete($this->olga->fresh()))->toBeTrue()
        ->and(AccountGuard::mayRemoveFromTeam($this->olga->fresh()))->toBeFalse();
});

it('sends the application\'s own reset link', function () {
    Notification::fake();
    $this->be($this->mia->fresh());

    Livewire::test(ListUsers::class)
        ->call('executeTableAction', (string) $this->olga->getKey(), 'sendPasswordReset', true);

    Notification::assertSentTo($this->olga->fresh(), ResetPassword::class);
});

it('says so when the reset link cannot be sent', function () {
    $this->be($this->mia->fresh());
    $this->olga->forceFill(['email' => 'nobody-by-this-address@example.com']);

    $page = new ListUsers;
    $send = new ReflectionMethod($page, 'sendPasswordReset');
    $send->invoke($page, $this->olga);

    expect(TeamAdmin::query()->where('email', 'nobody-by-this-address@example.com')->exists())->toBeFalse();
});

// ---------------------------------------------------------------------------
// wire:revoke-role
// ---------------------------------------------------------------------------

it('takes a team role, a global role and the super-admin away', function () {
    $second = apPerson('Second', 'second@example.com');
    $second->assignGlobalRole('super-admin');
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->ops->getKey());
    Role::query()->create(['name' => 'ops-support', 'guard_name' => 'web', 'team_id' => $this->ops->getKey()]);
    $this->mia->assignRole('ops-support');

    $this->artisan('wire:revoke-role', ['email' => 'mia@example.com', '--role' => ['ops-support'], '--team' => (string) $this->ops->getKey()])
        ->expectsOutputToContain('taken away')->assertSuccessful();
    $this->artisan('wire:revoke-role', ['email' => 'ada@example.com', '--role' => ['admin'], '--global' => true])
        ->expectsOutputToContain('taken away')->assertSuccessful();
    $this->artisan('wire:revoke-role', ['email' => 'second@example.com', '--super-admin' => true])
        ->expectsOutputToContain('taken away')->assertSuccessful();

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->ops->getKey());

    expect($this->mia->fresh()->hasRole('ops-support'))->toBeFalse()
        ->and($this->ada->fresh()->hasGlobalRole('admin'))->toBeFalse()
        ->and($second->fresh()->hasGlobalRole('super-admin'))->toBeFalse();
});

it('refuses to take the super-admin from the last account without --force', function () {
    $this->artisan('wire:revoke-role', ['email' => 'root@example.com', '--super-admin' => true])
        ->expectsOutputToContain('the only super-admin')->assertFailed();

    expect($this->root->fresh()->hasGlobalRole('super-admin'))->toBeTrue();

    $this->artisan('wire:revoke-role', ['email' => 'root@example.com', '--super-admin' => true, '--force' => true])
        ->assertSuccessful();

    expect($this->root->fresh()->hasGlobalRole('super-admin'))->toBeFalse();
});

it('says what an account did not have, and what it cannot be asked', function () {
    $this->artisan('wire:revoke-role', ['email' => 'mia@example.com', '--role' => ['nothing'], '--global' => true])
        ->expectsOutputToContain('did not have it')->assertSuccessful();
    $this->artisan('wire:revoke-role', ['email' => 'mia@example.com', '--super-admin' => true])
        ->expectsOutputToContain('did not have it')->assertSuccessful();
    $this->artisan('wire:revoke-role', ['email' => 'mia@example.com', '--role' => ['super-admin'], '--global' => true])
        ->expectsOutputToContain('--super-admin')->assertFailed();
    $this->artisan('wire:revoke-role', ['email' => 'mia@example.com', '--role' => ['x'], '--global' => true, '--team' => '1'])
        ->expectsOutputToContain('takes no --team')->assertFailed();
    $this->artisan('wire:revoke-role', ['email' => 'mia@example.com'])
        ->expectsOutputToContain('No role named')->assertFailed();
    $this->artisan('wire:revoke-role', ['email' => 'ghost@example.com', '--role' => ['x']])
        ->expectsOutputToContain('No account signs in as ghost@example.com')->assertFailed();
});

it('takes a role from the team the account is in when no team is named, and refuses without roles', function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->ops->getKey());
    Role::query()->create(['name' => 'ops-support', 'guard_name' => 'web', 'team_id' => $this->ops->getKey()]);
    $this->mia->assignRole('ops-support');

    $this->artisan('wire:revoke-role', ['email' => 'mia@example.com', '--role' => ['ops-support']])->assertSuccessful();

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->ops->getKey());
    expect($this->mia->fresh()->hasRole('ops-support'))->toBeFalse();

    config()->set('wire-module-users.roles', false);

    $this->artisan('wire:revoke-role', ['email' => 'mia@example.com', '--role' => ['x']])
        ->expectsOutputToContain('no accounts with roles')->assertFailed();
});

it('takes nothing from an account where the application has no roles', function () {
    config()->set('wire-module-users.roles', false);

    expect(app(Accounts::class)->revoke($this->mia, 'ops-support'))->toBeFalse()
        ->and(app(Accounts::class)->revokeGlobal($this->ada, 'admin'))->toBeFalse();
});
