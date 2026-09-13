<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use NyonCode\WireModuleUsers\Support\Accounts;
use NyonCode\WireModuleUsers\Tests\Fixtures\User;
use NyonCode\WireModuleUsers\Tests\Support\Tables;
use Spatie\Permission\Models\Role;

/*
 * Making an account, over whatever this application calls a user.
 *
 * Two callers ask the same three things of it — the installer's step, which
 * offers once, and `wire:user`, which is asked. What they do not share is when
 * to offer, and that is all they each keep.
 *
 * These are the answers for an application that is not ready yet: no model, no
 * tables, no roles. Each one is an ordinary moment during a first install, so
 * none of them is an exception.
 */

function accounts(): Accounts
{
    return app(Accounts::class);
}

function accountsWithoutADatabase(): void
{
    config()->set('database.connections.broken', [
        'driver' => 'sqlite',
        'database' => '/nonexistent/directory/database.sqlite',
        'prefix' => '',
    ]);
    config()->set('database.default', 'broken');
    DB::purge();
}

it('has no model when the application named one that is not there', function () {
    config()->set('wire-module-users.model', 'App\\Models\\NotHere');

    expect(accounts()->model())->toBeNull()
        ->and(accounts()->ready())->toBeFalse()
        ->and(accounts()->any())->toBeFalse();

    config()->set('wire-module-users.model', '');

    expect(accounts()->model())->toBeNull();
});

it('falls back to the columns Laravel ships when the application renamed none', function () {
    config()->set('wire-module-users.fields', []);

    expect(accounts()->fields())->toBe(['name' => 'name', 'email' => 'email', 'password' => 'password']);
});

it('takes the columns this application calls its own', function () {
    config()->set('wire-module-users.fields', ['name' => 'full_name', 'email' => 'login', 'password' => 'secret']);

    expect(accounts()->fields())->toBe(['name' => 'full_name', 'email' => 'login', 'password' => 'secret']);
});

it('answers no, rather than throwing, with no database behind it', function () {
    accountsWithoutADatabase();

    expect(accounts()->ready())->toBeFalse()
        ->and(accounts()->any())->toBeFalse();
});

it('knows whether anybody can sign in', function () {
    Tables::users();

    expect(accounts()->ready())->toBeTrue()
        ->and(accounts()->any())->toBeFalse();

    accounts()->create('Jane', 'jane@example.com', 'pw');

    expect(accounts()->any())->toBeTrue();
});

it('offers no roles in an application that has none', function () {
    config()->set('wire-module-users.roles', false);

    expect(accounts()->roles())->toBe([]);
});

it('offers no roles while the permission tables are not migrated', function () {
    // An ordinary moment during a first install rather than something to stop
    // for: the package is installed and its tables are not there yet.
    expect(accounts()->roles())->toBe([]);
});

it('lists the roles this application has', function () {
    Tables::roles();
    Role::create(['name' => 'editor', 'guard_name' => 'web']);

    expect(accounts()->roles())->toBe(['editor']);
});

it('gives no role where the application has none, and says so by answering null', function () {
    Tables::users();
    config()->set('wire-module-users.roles', false);

    $user = accounts()->create('Solo', 'solo@example.com', 'pw');

    expect(accounts()->assign($user, 'anything'))->toBeNull()
        ->and(accounts()->makeSuperAdmin($user))->toBeNull();
});

it('names the role the permission gate actually checks', function () {
    expect(accounts()->superAdminRole())->toBe(config('permission-extended.super_admin_role', 'super-admin'));

    config()->set('permission-extended.super_admin_role', 'owner');

    expect(accounts()->superAdminRole())->toBe('owner');
});

it('creates the role it is asked to give, where the application has not', function () {
    Tables::users();
    Tables::roles();

    $user = accounts()->create('Boss', 'boss@example.com', 'pw');

    expect(accounts()->assign($user, 'curator'))->toBe('curator')
        ->and($user->fresh()->hasRole('curator'))->toBeTrue();
});

it('writes the password hashed, because the model is the application\'s', function () {
    // This module cannot assume the application's user model casts it.
    Tables::users();

    accounts()->create('Jane', 'jane@example.com', 'hunter2');

    expect(User::first()->password)->not->toBe('hunter2')
        ->and(Hash::check('hunter2', User::first()->password))->toBeTrue();
});
