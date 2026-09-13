<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use NyonCode\WireModuleUsers\Tests\Fixtures\User;
use NyonCode\WireModuleUsers\Tests\Support\Tables;
use Spatie\Permission\Models\Role;

/*
 * `php artisan wire:user` — an account, whenever one is wanted.
 *
 * The installer's step makes the account that gets you in and stops there, on
 * purpose: an installer that offered to add another administrator on every run
 * is one nobody could run twice safely. That left the second account with no
 * answer but `php artisan tinker`, which is the gap `make:filament-user` fills
 * in the framework this one is measured against.
 */

it('is registered', function () {
    expect(array_keys(app(Kernel::class)->all()))->toContain('wire:user');
});

it('creates an account from options alone, so a script can call it', function () {
    Tables::users();

    $this->artisan('wire:user', [
        '--name' => 'Jane',
        '--email' => 'jane@example.com',
        '--password' => 'hunter2',
        '--no-interaction' => true,
    ])->expectsOutputToContain('Created jane@example.com.')->assertSuccessful();

    $user = User::first();

    expect($user->name)->toBe('Jane')
        ->and(Hash::check('hunter2', $user->password))->toBeTrue();
});

it('asks for what it was not given', function () {
    Tables::users();

    $this->artisan('wire:user', ['--password' => 'hunter2', '--admin' => true])
        ->expectsQuestion('Name', 'Asked')
        ->expectsQuestion('E-mail address', 'asked@example.com')
        ->assertSuccessful();

    expect(User::where('email', 'asked@example.com')->exists())->toBeTrue();
});

it('makes a second account, which the installer deliberately will not', function () {
    Tables::users();
    User::create(['name' => 'First', 'email' => 'first@example.com', 'password' => Hash::make('x')]);

    $this->artisan('wire:user', [
        '--name' => 'Second',
        '--email' => 'second@example.com',
        '--password' => 'pw',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect(User::count())->toBe(2);
});

it('gives the account the super-admin role when asked', function () {
    Tables::users();
    Tables::roles();

    $this->artisan('wire:user', [
        '--email' => 'boss@example.com',
        '--password' => 'pw',
        '--admin' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    $name = (string) config('permission-extended.super_admin_role', 'super-admin');

    expect(User::first()->hasRole($name))->toBeTrue();
});

it('gives it the roles that were named, creating ones this application has not', function () {
    Tables::users();
    Tables::roles();

    $this->artisan('wire:user', [
        '--email' => 'editor@example.com',
        '--password' => 'pw',
        '--role' => ['editor', 'support'],
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect(User::first()->hasRole('editor'))->toBeTrue()
        ->and(User::first()->hasRole('support'))->toBeTrue()
        ->and(Role::where('name', 'support')->exists())->toBeTrue();
});

it('offers the roles this application has, with super-admin picked for the first account only', function () {
    // The first one is somebody letting themselves in; every later one is an
    // ordinary user until said otherwise.
    Tables::users();
    Tables::roles();
    Role::create(['name' => 'editor', 'guard_name' => 'web']);

    $this->artisan('wire:user', ['--name' => 'One', '--email' => 'one@example.com', '--password' => 'pw'])
        ->expectsChoice(
            'Which roles should this account have?',
            ['super-admin'],
            ['editor' => 'editor', 'super-admin' => 'super-admin'],
        )
        ->assertSuccessful();

    expect(User::first()->hasRole('super-admin'))->toBeTrue();
});

it('refuses rather than inventing a password when nobody can be asked', function () {
    // An empty password is an account anybody can use, and a generated one
    // printed into a deploy log is a credential in a log.
    Tables::users();

    $this->artisan('wire:user', ['--email' => 'x@example.com', '--no-interaction' => true])
        ->expectsOutputToContain('e-mail address and a password are both required')
        ->assertFailed();

    expect(User::count())->toBe(0);
});

it('says which of the two things is missing, without a model to write to', function () {
    config()->set('wire-module-users.model', 'App\\Models\\NotHere');

    $this->artisan('wire:user', ['--no-interaction' => true])
        ->expectsOutputToContain('No user model')
        ->assertFailed();
});

it('sends you to migrate when the table is not there', function () {
    $this->artisan('wire:user', ['--no-interaction' => true])
        ->expectsOutputToContain('No users table yet')
        ->assertFailed();
});

it('fails rather than pretending, when the row cannot be written', function () {
    Tables::users();
    User::create(['name' => 'Taken', 'email' => 'taken@example.com', 'password' => Hash::make('x')]);

    $this->artisan('wire:user', [
        '--email' => 'taken@example.com',
        '--password' => 'pw',
        '--no-interaction' => true,
    ])->expectsOutputToContain('Could not create the account')->assertFailed();
});

it('keeps the account when only the role could not be given', function () {
    // The account is made and usable; the role is a screen away rather than a
    // reason to call the whole command failed.
    Tables::users();

    $this->artisan('wire:user', [
        '--email' => 'noroles@example.com',
        '--password' => 'pw',
        '--admin' => true,
        '--no-interaction' => true,
    ])->expectsOutputToContain('Could not give it')->assertSuccessful();

    expect(User::where('email', 'noroles@example.com')->exists())->toBeTrue();
});

it('says nothing about roles in an application that has none', function () {
    Tables::users();
    config()->set('wire-module-users.roles', false);

    $this->artisan('wire:user', [
        '--email' => 'plain@example.com',
        '--password' => 'pw',
        '--admin' => true,
        '--no-interaction' => true,
    ])->doesntExpectOutputToContain('Role')->assertSuccessful();
});
