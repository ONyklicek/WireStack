<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
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
        '--password' => 'hunter2hunter2',
        '--no-interaction' => true,
    ])->expectsOutputToContain('Created jane@example.com.')->assertSuccessful();

    $user = User::first();

    expect($user->name)->toBe('Jane')
        ->and(Hash::check('hunter2hunter2', $user->password))->toBeTrue();
});

it('asks for what it was not given', function () {
    Tables::users();

    $this->artisan('wire:user', ['--password' => 'hunter2hunter2'])
        ->expectsQuestion('Name', 'Asked')
        ->expectsQuestion('E-mail address', 'asked@example.com')
        ->expectsConfirmation('Make asked@example.com a super-admin? It can do everything.', 'no')
        ->assertSuccessful();

    expect(User::where('email', 'asked@example.com')->exists())->toBeTrue();
});

it('makes a second account, which the installer deliberately will not', function () {
    Tables::users();
    User::create(['name' => 'First', 'email' => 'first@example.com', 'password' => Hash::make('x')]);

    $this->artisan('wire:user', [
        '--name' => 'Second',
        '--email' => 'second@example.com',
        '--password' => 'long-enough-pw',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect(User::count())->toBe(2);
});

it('makes the account a super-admin, globally, when asked', function () {
    Tables::users();
    Tables::roles();

    $this->artisan('wire:user', [
        '--email' => 'boss@example.com',
        '--password' => 'long-enough-pw',
        '--super-admin' => true,
        '--no-interaction' => true,
    ])->expectsOutputToContain('Super-admin')->assertSuccessful();

    $name = (string) config('permission-extended.super_admin_role', 'super-admin');

    expect(User::first()->hasGlobalRole($name))->toBeTrue();
});

it('never makes a later account a super-admin unless told to', function () {
    Tables::users();
    Tables::roles();
    User::create(['name' => 'First', 'email' => 'first@example.com', 'password' => Hash::make('x')]);

    $this->artisan('wire:user', ['--name' => 'Second', '--email' => 'second@example.com', '--password' => 'long-enough-pw'])
        ->assertSuccessful();

    expect(User::where('email', 'second@example.com')->first()->hasGlobalRole('super-admin'))->toBeFalse();
});

it('refuses the super-admin as a role', function () {
    // It can do everything, in every team: never one role among others.
    Tables::users();
    Tables::roles();

    $this->artisan('wire:user', [
        '--email' => 'sneaky@example.com',
        '--password' => 'long-enough-pw',
        '--role' => ['super-admin'],
        '--no-interaction' => true,
    ])->expectsOutputToContain('wire:assign-role sneaky@example.com --super-admin')->assertSuccessful();

    expect(User::first()->hasGlobalRole('super-admin'))->toBeFalse()
        ->and(User::first()->hasRole('super-admin'))->toBeFalse();
});

it('gives it the roles that were named, creating ones this application has not', function () {
    Tables::users();
    Tables::roles();

    $this->artisan('wire:user', [
        '--email' => 'editor@example.com',
        '--password' => 'long-enough-pw',
        '--role' => ['editor', 'support'],
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect(User::first()->hasRole('editor'))->toBeTrue()
        ->and(User::first()->hasRole('support'))->toBeTrue()
        ->and(Role::where('name', 'support')->exists())->toBeTrue();
});

it('asks the first account about the super-admin, then offers the roles without it', function () {
    // The first one is somebody letting themselves in; every later one is an
    // ordinary user until said otherwise. The super-admin is its own question.
    Tables::users();
    Tables::roles();
    Role::create(['name' => 'editor', 'guard_name' => 'web']);
    Role::create(['name' => 'super-admin', 'guard_name' => 'web']);

    $this->artisan('wire:user', ['--name' => 'One', '--email' => 'one@example.com', '--password' => 'long-enough-pw'])
        ->expectsConfirmation('Make one@example.com a super-admin? It can do everything.', 'yes')
        ->expectsChoice('Which roles should this account have?', ['editor'], ['editor' => 'editor'])
        ->assertSuccessful();

    expect(User::first()->hasGlobalRole('super-admin'))->toBeTrue()
        ->and(User::first()->hasRole('editor'))->toBeTrue();
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
    // A column the application requires and this command does not fill.
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->string('tenant');
        $table->timestamps();
    });

    $this->artisan('wire:user', [
        '--email' => 'jane@example.com',
        '--password' => 'long-enough-pw',
        '--no-interaction' => true,
    ])->expectsOutputToContain('Could not create the account')->assertFailed();
});

it('refuses an address that is not one, or that already has an account, from an option', function () {
    Tables::users();
    User::create(['name' => 'Taken', 'email' => 'taken@example.com', 'password' => Hash::make('x')]);

    $this->artisan('wire:user', ['--email' => 'admin', '--password' => 'long-enough-pw', '--no-interaction' => true])
        ->expectsOutputToContain('Not an e-mail address: admin')
        ->assertFailed();

    $this->artisan('wire:user', ['--email' => 'taken@example.com', '--password' => 'long-enough-pw', '--no-interaction' => true])
        ->expectsOutputToContain('An account with taken@example.com already exists')
        ->assertFailed();

    expect(User::query()->count())->toBe(1);
});

it('asks again for an address that is not one, and gives up after three', function () {
    Tables::users();

    $this->artisan('wire:user', ['--password' => 'long-enough-pw', '--name' => 'Jane'])
        ->expectsQuestion('E-mail address', 'admin')
        ->expectsQuestion('E-mail address', 'jane@example.com')
        ->expectsOutputToContain('Not an e-mail address: admin')
        ->expectsConfirmation('Make jane@example.com a super-admin? It can do everything.', 'no')
        ->assertSuccessful();

    expect(User::query()->where('email', 'jane@example.com')->exists())->toBeTrue();

    $this->artisan('wire:user', ['--password' => 'long-enough-pw', '--name' => 'Jane'])
        ->expectsQuestion('E-mail address', 'a')
        ->expectsQuestion('E-mail address', 'b')
        ->expectsQuestion('E-mail address', 'c')
        ->assertFailed();

    expect(User::query()->count())->toBe(1);
});

it('keeps the account when only the role could not be given', function () {
    // The account is made and usable; the role is a screen away rather than a
    // reason to call the whole command failed.
    Tables::users();

    $this->artisan('wire:user', [
        '--email' => 'noroles@example.com',
        '--password' => 'long-enough-pw',
        '--role' => ['editor'],
        '--no-interaction' => true,
    ])->expectsOutputToContain('Could not give it')->assertSuccessful();

    expect(User::where('email', 'noroles@example.com')->exists())->toBeTrue();
});

it('says nothing about roles in an application that has none', function () {
    Tables::users();
    config()->set('wire-module-users.roles', false);

    $this->artisan('wire:user', [
        '--email' => 'plain@example.com',
        '--password' => 'long-enough-pw',
        '--super-admin' => true,
        '--no-interaction' => true,
    ])->doesntExpectOutputToContain('Role')->assertSuccessful();
});

it('refuses a password the application\'s policy refuses, when it came from --password', function () {
    // `Password::defaults()` — the rule the profile screen holds a new password
    // to. `pw` made an account nobody could have given themselves.
    Tables::users();

    $this->artisan('wire:user', ['--name' => 'Jane', '--email' => 'jane@example.com', '--password' => 'pw', '--no-interaction' => true])
        ->expectsOutputToContain('at least 8 characters')
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('asks for a typed password twice, and again while it is refused or mistyped', function () {
    Tables::users();

    $this->artisan('wire:user', ['--name' => 'Jane', '--email' => 'jane@example.com'])
        ->expectsQuestion('Password', 'pw')
        ->expectsOutputToContain('at least 8 characters')
        ->expectsQuestion('Password', 'long-enough-pw')
        ->expectsQuestion('Password again', 'long-enough-typo')
        ->expectsOutputToContain('The two passwords are not the same')
        ->expectsQuestion('Password', 'long-enough-pw')
        ->expectsQuestion('Password again', 'long-enough-pw')
        ->expectsConfirmation('Make jane@example.com a super-admin? It can do everything.', 'no')
        ->assertSuccessful();

    expect(Hash::check('long-enough-pw', User::query()->firstOrFail()->password))->toBeTrue();
});

it('creates nothing after three passwords it cannot use', function () {
    Tables::users();

    $this->artisan('wire:user', ['--name' => 'Jane', '--email' => 'jane@example.com'])
        ->expectsQuestion('Password', 'a')
        ->expectsQuestion('Password', 'b')
        ->expectsQuestion('Password', 'c')
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('stops at an empty typed password, as it always has', function () {
    Tables::users();

    $this->artisan('wire:user', ['--name' => 'Jane', '--email' => 'jane@example.com'])
        ->expectsQuestion('Password', '')
        ->expectsOutputToContain('e-mail address and a password are both required')
        ->assertFailed();
});
