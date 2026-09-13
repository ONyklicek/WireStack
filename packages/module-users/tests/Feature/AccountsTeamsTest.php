<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireModuleUsers\Exceptions\AccountException;
use NyonCode\WireModuleUsers\Support\Accounts;
use NyonCode\WireModuleUsers\Tests\Fixtures\Team;
use NyonCode\WireModuleUsers\Tests\Fixtures\TeamAdmin;
use NyonCode\WireModuleUsers\Tests\Support\Tables;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
 * Giving a role in an application that scopes them to teams.
 *
 * The permission package owns the scoping, and what it scopes by is whatever
 * the registrar was last told about — which, in a command, is nothing. Assigning
 * then writes a null into a column the package made NOT NULL, and what reaches
 * the person is `SQLSTATE[23000] … model_has_roles.team_id` where a sentence
 * belongs. Measured against the workbench, which has teams on.
 */

beforeEach(function () {
    config()->set('wire-module-users.model', TeamAdmin::class);
    config()->set('auth.providers.users.model', TeamAdmin::class);
    config()->set('wire-module-users.teams.model', Team::class);
    config()->set('permission.teams', true);

    // The registrar reads `permission.teams` once, in its constructor, and the
    // container has already built one by the time a test sets the config. Left
    // as it is, the pivot insert carries no team column at all and the failure
    // looks like the bug under test rather than the harness.
    app()->forgetInstance(PermissionRegistrar::class);

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamps();
    });

    Schema::create('teams', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('team_user', function (Blueprint $table) {
        $table->unsignedBigInteger('team_id');
        $table->unsignedBigInteger('user_id');
        $table->primary(['team_id', 'user_id']);
    });

    Tables::roles();

    // The two columns the permission package adds when teams are on. The pivot's
    // is the reason an unscoped assignment fails rather than quietly writing a
    // global role; the one on `roles` is what lets a role belong to one team or
    // to all of them, and every read joins on it.
    Schema::table('model_has_roles', function (Blueprint $table) {
        $table->unsignedBigInteger('team_id');
    });

    Schema::table('roles', function (Blueprint $table) {
        $table->unsignedBigInteger('team_id')->nullable();
    });
});

it('scopes the role to the team the account is in', function () {
    $user = TeamAdmin::query()->create([
        'name' => 'Amelia',
        'email' => 'a@example.com',
        'password' => Hash::make('secret'),
    ]);

    $team = Team::query()->create(['name' => 'Ops']);
    $user->teams()->attach($team->getKey());

    expect(app(Accounts::class)->assign($user, 'super-admin'))->toBe('super-admin');

    $row = DB::table('model_has_roles')->first();

    expect($row->team_id)->toEqual($team->getKey())
        ->and(Role::where('name', 'super-admin')->exists())->toBeTrue();
});

it('explains itself rather than failing on a constraint, when the account is in no team', function () {
    // A brand-new administrator belongs to nothing, so there is no scope to give
    // the role — and the honest answer is the sentence, not a SQL error.
    $user = TeamAdmin::query()->create([
        'name' => 'Nobody',
        'email' => 'n@example.com',
        'password' => Hash::make('secret'),
    ]);

    expect(fn () => app(Accounts::class)->assign($user, 'super-admin'))
        ->toThrow(AccountException::class, 'scoped to a team');
});

it('says the same thing through the command, and keeps the account', function () {
    $this->artisan('wire:user', [
        '--name' => 'Nobody',
        '--email' => 'nobody@example.com',
        '--password' => 'secret',
        '--admin' => true,
        '--no-interaction' => true,
    ])->expectsOutputToContain('scoped to a team')->assertSuccessful();

    expect(TeamAdmin::where('email', 'nobody@example.com')->exists())->toBeTrue();
});
