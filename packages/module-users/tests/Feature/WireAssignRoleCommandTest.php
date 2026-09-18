<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use NyonCode\WireModuleUsers\Tests\Fixtures\User;
use NyonCode\WireModuleUsers\Tests\Support\Tables;
use Spatie\Permission\Models\Role;

/*
 * `php artisan wire:assign-role` — roles for an account that already exists.
 *
 * The super-admin is not one of them: it can do everything, in every team, so
 * it is `--super-admin`, confirmed and global, and `--role=super-admin` is
 * refused.
 */

function warAccount(string $email = 'jane@example.com'): User
{
    return User::create(['name' => 'Jane', 'email' => $email, 'password' => Hash::make('pw')]);
}

it('is registered', function () {
    expect(array_keys(app(Kernel::class)->all()))->toContain('wire:assign-role');
});

it('gives the roles that were named, touching nothing else about the account', function () {
    Tables::users();
    Tables::roles();
    $user = warAccount();
    $hash = $user->password;

    $this->artisan('wire:assign-role', [
        'email' => 'jane@example.com',
        '--role' => ['editor', 'support'],
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($user->fresh()->hasAllRoles(['editor', 'support']))->toBeTrue()
        ->and($user->fresh()->password)->toBe($hash)
        ->and(Role::where('name', 'support')->exists())->toBeTrue()
        ->and(User::count())->toBe(1);
});

it('makes an account a super-admin only with the flag, after saying what it means', function () {
    Tables::users();
    Tables::roles();
    $user = warAccount();

    $this->artisan('wire:assign-role', ['email' => 'jane@example.com', '--super-admin' => true])
        ->expectsConfirmation('Make jane@example.com a super-admin? It can do everything.', 'yes')
        ->expectsOutputToContain('Super-admin')
        ->assertSuccessful();

    expect($user->fresh()->hasGlobalRole('super-admin'))->toBeTrue();
});

it('makes nobody a super-admin when the answer is no', function () {
    Tables::users();
    Tables::roles();
    $user = warAccount();

    $this->artisan('wire:assign-role', ['email' => 'jane@example.com', '--super-admin' => true])
        ->expectsConfirmation('Make jane@example.com a super-admin? It can do everything.', 'no')
        ->assertFailed();

    expect($user->fresh()->hasGlobalRole('super-admin'))->toBeFalse();
});

it('takes the flag as the answer when nobody can be asked', function () {
    Tables::users();
    Tables::roles();
    $user = warAccount();

    $this->artisan('wire:assign-role', ['email' => 'jane@example.com', '--super-admin' => true, '--no-interaction' => true])
        ->assertSuccessful();

    expect($user->fresh()->hasGlobalRole('super-admin'))->toBeTrue();
});

it('refuses the super-admin as a role, and says how it is given', function () {
    Tables::users();
    Tables::roles();
    $user = warAccount();

    $this->artisan('wire:assign-role', ['email' => 'jane@example.com', '--role' => ['super-admin'], '--no-interaction' => true])
        ->expectsOutputToContain('run `php artisan wire:assign-role jane@example.com --super-admin`')
        ->assertFailed();

    expect($user->fresh()->hasRole('super-admin'))->toBeFalse()
        ->and($user->fresh()->hasGlobalRole('super-admin'))->toBeFalse();
});

it('refuses a super-admin in a team, which is a contradiction', function () {
    Tables::users();
    Tables::roles();
    warAccount();

    $this->artisan('wire:assign-role', [
        'email' => 'jane@example.com',
        '--super-admin' => true,
        '--team' => '3',
        '--no-interaction' => true,
    ])->expectsOutputToContain('takes no --team')->assertFailed();
});

it('says when there is no super-admin to give', function () {
    Tables::users();
    Tables::roles();
    config()->set('permission-extended.super_admin_role', null);
    warAccount();

    $this->artisan('wire:assign-role', ['email' => 'jane@example.com', '--super-admin' => true, '--no-interaction' => true])
        ->expectsOutputToContain('no super-admin role to give')
        ->assertFailed();
});

it('says the super-admin could not be given, and fails', function () {
    Tables::users();
    warAccount();

    $this->artisan('wire:assign-role', ['email' => 'jane@example.com', '--super-admin' => true, '--no-interaction' => true])
        ->expectsOutputToContain('Could not make it a super-admin')
        ->assertFailed();
});

it('asks for the address and offers the roles — never the super-admin — when run by hand', function () {
    Tables::users();
    Tables::roles();
    Role::create(['name' => 'editor', 'guard_name' => 'web']);
    Role::create(['name' => 'super-admin', 'guard_name' => 'web']);
    $user = warAccount();

    $this->artisan('wire:assign-role')
        ->expectsQuestion('E-mail address', 'jane@example.com')
        ->expectsChoice('Which roles should this account have?', ['editor'], ['editor' => 'editor'])
        ->assertSuccessful();

    expect($user->fresh()->hasRole('editor'))->toBeTrue();
});

it('says who does not exist, and where accounts come from', function () {
    Tables::users();
    Tables::roles();

    $this->artisan('wire:assign-role', ['email' => 'ghost@example.com', '--role' => ['editor'], '--no-interaction' => true])
        ->expectsOutputToContain('No account signs in as ghost@example.com. php artisan wire:user makes one.')
        ->assertFailed();
});

it('asks for an address rather than guessing one, when nobody can be asked', function () {
    Tables::users();
    Tables::roles();

    $this->artisan('wire:assign-role', ['--role' => ['editor'], '--no-interaction' => true])
        ->expectsOutputToContain('Name the account')
        ->assertFailed();
});

it('refuses to do nothing quietly, when no role was named', function () {
    Tables::users();
    Tables::roles();
    warAccount();

    $this->artisan('wire:assign-role', ['email' => 'jane@example.com', '--no-interaction' => true])
        ->expectsOutputToContain('No role named')
        ->assertFailed();
});

it('says the application has no roles, rather than pretending to give one', function () {
    Tables::users();
    config()->set('wire-module-users.roles', false);
    warAccount();

    $this->artisan('wire:assign-role', ['email' => 'jane@example.com', '--role' => ['editor'], '--no-interaction' => true])
        ->expectsOutputToContain('has no roles')
        ->assertFailed();
});

it('says there is nobody to give a role to before the users table exists', function () {
    $this->artisan('wire:assign-role', ['email' => 'jane@example.com', '--role' => ['editor'], '--no-interaction' => true])
        ->expectsOutputToContain('No users to give a role to')
        ->assertFailed();
});

it('fails when a role could not be given', function () {
    Tables::users();
    warAccount();

    $this->artisan('wire:assign-role', ['email' => 'jane@example.com', '--role' => ['editor'], '--no-interaction' => true])
        ->expectsOutputToContain('Could not give it `editor`')
        ->assertFailed();
});

it('says --team changes nothing where roles are not scoped to teams', function () {
    Tables::users();
    Tables::roles();
    $user = warAccount();

    $this->artisan('wire:assign-role', [
        'email' => 'jane@example.com',
        '--role' => ['editor'],
        '--team' => 'ops',
        '--no-interaction' => true,
    ])->expectsOutputToContain('--team changes nothing')->assertSuccessful();

    expect($user->fresh()->hasRole('editor'))->toBeTrue();
});
