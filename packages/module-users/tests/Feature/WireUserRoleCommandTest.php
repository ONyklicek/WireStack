<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use NyonCode\WireModuleUsers\Tests\Fixtures\User;
use NyonCode\WireModuleUsers\Tests\Support\Tables;
use Spatie\Permission\Models\Role;

/*
 * `php artisan wire:user:role` — roles for an account that already exists.
 *
 * `wire:user` gives roles only to the account it has just made, and the
 * installer's own advice for an administrator it could not give a role to
 * pointed at it — a command that makes a new account and refuses the address
 * that already has one.
 */

function wurAccount(string $email = 'jane@example.com'): User
{
    return User::create(['name' => 'Jane', 'email' => $email, 'password' => Hash::make('pw')]);
}

it('is registered', function () {
    expect(array_keys(app(Kernel::class)->all()))->toContain('wire:user:role');
});

it('gives an existing account the super-admin role, touching nothing else about it', function () {
    Tables::users();
    Tables::roles();
    $user = wurAccount();
    $hash = $user->password;

    $this->artisan('wire:user:role', ['email' => 'jane@example.com', '--admin' => true, '--no-interaction' => true])
        ->expectsOutputToContain('super-admin')
        ->assertSuccessful();

    expect($user->fresh()->hasRole('super-admin'))->toBeTrue()
        ->and($user->fresh()->password)->toBe($hash)
        ->and(User::count())->toBe(1);
});

it('gives the roles that were named, creating ones this application has not', function () {
    Tables::users();
    Tables::roles();
    $user = wurAccount();

    $this->artisan('wire:user:role', [
        'email' => 'jane@example.com',
        '--role' => ['editor', 'support'],
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($user->fresh()->hasAllRoles(['editor', 'support']))->toBeTrue()
        ->and(Role::where('name', 'support')->exists())->toBeTrue();
});

it('asks for the address and offers the roles when it is run by hand', function () {
    Tables::users();
    Tables::roles();
    Role::create(['name' => 'editor', 'guard_name' => 'web']);
    $user = wurAccount();

    $this->artisan('wire:user:role')
        ->expectsQuestion('E-mail address', 'jane@example.com')
        ->expectsChoice(
            'Which roles should this account have?',
            ['editor'],
            ['editor' => 'editor', 'super-admin' => 'super-admin'],
        )
        ->assertSuccessful();

    expect($user->fresh()->hasRole('editor'))->toBeTrue();
});

it('says who does not exist, and where accounts come from', function () {
    Tables::users();
    Tables::roles();

    $this->artisan('wire:user:role', ['email' => 'ghost@example.com', '--admin' => true, '--no-interaction' => true])
        ->expectsOutputToContain('No account signs in as ghost@example.com. php artisan wire:user makes one.')
        ->assertFailed();
});

it('asks for an address rather than guessing one, when nobody can be asked', function () {
    Tables::users();
    Tables::roles();

    $this->artisan('wire:user:role', ['--admin' => true, '--no-interaction' => true])
        ->expectsOutputToContain('Name the account')
        ->assertFailed();
});

it('refuses to do nothing quietly, when no role was named', function () {
    Tables::users();
    Tables::roles();
    wurAccount();

    $this->artisan('wire:user:role', ['email' => 'jane@example.com', '--no-interaction' => true])
        ->expectsOutputToContain('No role named')
        ->assertFailed();
});

it('says the application has no roles, rather than pretending to give one', function () {
    Tables::users();
    config()->set('wire-module-users.roles', false);
    wurAccount();

    $this->artisan('wire:user:role', ['email' => 'jane@example.com', '--admin' => true, '--no-interaction' => true])
        ->expectsOutputToContain('has no roles')
        ->assertFailed();
});

it('says there is nobody to give a role to before the users table exists', function () {
    $this->artisan('wire:user:role', ['email' => 'jane@example.com', '--admin' => true, '--no-interaction' => true])
        ->expectsOutputToContain('No users to give a role to')
        ->assertFailed();
});

it('fails when a role could not be given', function () {
    // Unlike `wire:user`, there is no account being made here to call the run a
    // success on: giving the role is the whole of the job.
    Tables::users();
    wurAccount();

    $this->artisan('wire:user:role', ['email' => 'jane@example.com', '--admin' => true, '--no-interaction' => true])
        ->expectsOutputToContain('Could not give it `super-admin`')
        ->assertFailed();
});

it('says --team changes nothing where roles are not scoped to teams', function () {
    Tables::users();
    Tables::roles();
    $user = wurAccount();

    $this->artisan('wire:user:role', [
        'email' => 'jane@example.com',
        '--admin' => true,
        '--team' => 'ops',
        '--no-interaction' => true,
    ])->expectsOutputToContain('--team changes nothing')->assertSuccessful();

    expect($user->fresh()->hasRole('super-admin'))->toBeTrue();
});
