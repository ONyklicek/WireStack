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

    expect(app(Accounts::class)->assign($user, 'editor'))->toBe('editor');

    $row = DB::table('model_has_roles')->first();

    expect($row->team_id)->toEqual($team->getKey())
        ->and(Role::where('name', 'editor')->exists())->toBeTrue();
});

it('explains itself rather than failing on a constraint, when the account is in no team', function () {
    // A brand-new account belongs to nothing, so there is no scope to give the
    // role — and the honest answer is the sentence, not a SQL error.
    $user = TeamAdmin::query()->create(['name' => 'Nobody', 'email' => 'n@example.com', 'password' => Hash::make('secret')]);

    expect(fn () => app(Accounts::class)->assign($user, 'editor'))
        ->toThrow(AccountException::class, 'run `php artisan wire:assign-role n@example.com --role=editor`');
});

it('says the same thing through the command, and keeps the account', function () {
    $this->artisan('wire:user', [
        '--name' => 'Nobody',
        '--email' => 'nobody@example.com',
        '--password' => 'secret',
        '--role' => ['editor'],
        '--no-interaction' => true,
    ])->expectsOutputToContain('scoped to a team')->assertSuccessful();

    expect(TeamAdmin::where('email', 'nobody@example.com')->exists())->toBeTrue();
});

it('makes a super-admin that needs no team, and counts in every team', function () {
    // The first administrator of a new installation is in no team. A super-admin
    // is global, so that is no obstacle — and it bypasses the gate whichever
    // team is current.
    $user = TeamAdmin::query()->create(['name' => 'Root', 'email' => 'root@example.com', 'password' => Hash::make('secret')]);

    expect(app(Accounts::class)->makeSuperAdmin($user))->toBe('super-admin');

    expect(app(Accounts::class)->superAdminMeaning())->toBe('It can do everything, in every team.');

    foreach ([1, 2, null] as $team) {
        app(PermissionRegistrar::class)->setPermissionsTeamId($team);

        expect($user->fresh()->can('users.viewAny'))->toBeTrue();
    }

    expect(DB::table('model_has_roles')->value('team_id'))->toEqual(0)
        ->and(Role::where('name', 'super-admin')->value('team_id'))->toBeNull();
});

it('refuses the super-admin as a role of a team', function () {
    $user = TeamAdmin::query()->create(['name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('secret')]);
    $team = Team::query()->create(['name' => 'Ops']);
    $user->teams()->attach($team->getKey());

    expect(fn () => app(Accounts::class)->assign($user, 'super-admin'))
        ->toThrow(AccountException::class, 'it is not given as a role');

    expect(DB::table('model_has_roles')->count())->toBe(0);
});

it('gives the role in the team named, once the account is in it', function () {
    $user = TeamAdmin::query()->create(['name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('secret')]);
    $first = Team::query()->create(['name' => 'Ops']);
    $second = Team::query()->create(['name' => 'Billing']);
    $user->teams()->attach([$first->getKey(), $second->getKey()]);

    $this->artisan('wire:assign-role', [
        'email' => 'a@example.com',
        '--role' => ['editor'],
        '--team' => (string) $second->getKey(),
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect(DB::table('model_has_roles')->value('team_id'))->toEqual($second->getKey());
});

it('refuses a team the account is not a member of', function () {
    // The role would be stored, in a team the switcher never offers this person.
    $user = TeamAdmin::query()->create(['name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('secret')]);
    $theirs = Team::query()->create(['name' => 'Ops']);
    $other = Team::query()->create(['name' => 'Billing']);
    $user->teams()->attach($theirs->getKey());

    $this->artisan('wire:assign-role', [
        'email' => 'a@example.com',
        '--role' => ['editor'],
        '--team' => (string) $other->getKey(),
        '--no-interaction' => true,
    ])->expectsOutputToContain('a@example.com is not a member of team '.$other->getKey())->assertFailed();

    expect(DB::table('model_has_roles')->count())->toBe(0);
});

it('takes a team named by a key that is not a number as it is', function () {
    TeamAdmin::query()->create(['name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('secret')]);

    $this->artisan('wire:assign-role', [
        'email' => 'a@example.com',
        '--role' => ['editor'],
        '--team' => 'ops-team',
        '--no-interaction' => true,
    ])->expectsOutputToContain('is not a member of team ops-team')->assertFailed();
});
