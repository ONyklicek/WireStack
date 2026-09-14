<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use NyonCode\WireModuleUsers\Pages\CreateUser;
use NyonCode\WireModuleUsers\Pages\EditUser;
use NyonCode\WireModuleUsers\Pages\ListUsers;
use NyonCode\WireModuleUsers\Pages\ViewUser;
use NyonCode\WireModuleUsers\Resources\UserResource;
use NyonCode\WireModuleUsers\Support\Teams;
use NyonCode\WireModuleUsers\Tests\Fixtures\Team;
use NyonCode\WireModuleUsers\Tests\Fixtures\TeamAdmin;
use NyonCode\WireModuleUsers\Tests\Support\Access;
use NyonCode\WireModuleUsers\Tests\Support\Tables;
use NyonCode\WirePanels\Exceptions\ResourcePageException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
 * The users screen, in an application with teams.
 *
 * Authorization was already per team — Spatie scopes every permission read to
 * the current one — and the data was not: anybody who could list users in one
 * team listed the users of all of them, and opened any account by its URL. The
 * list, the record pages and the table's own actions now find people among the
 * members of the current team, unless the person looking works across every
 * team: a super-admin, or somebody whose ability comes from a global role.
 */

beforeEach(function () {
    // The page guard has its own tests; these are about which people a page
    // that has let you in shows you.
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

    $this->opsMember = tsuPerson('Olga', 'olga@example.com', [$this->ops]);
    $this->billingMember = tsuPerson('Bruno', 'bruno@example.com', [$this->billing]);
});

/**
 * @param  array<int, Team>  $teams
 */
function tsuPerson(string $name, string $email, array $teams = []): TeamAdmin
{
    $person = TeamAdmin::query()->create(['name' => $name, 'email' => $email, 'password' => Hash::make('secret')]);
    $person->teams()->attach(array_map(static fn (Team $team) => $team->getKey(), $teams));

    return $person;
}

/** A global administrator whose users.* come from a role of no team. */
function tsuGlobalAdmin(): TeamAdmin
{
    $admin = tsuPerson('Ada', 'ada@example.com');
    Permission::findOrCreate('users.viewAny', 'web');
    Role::query()->create(['name' => 'admin', 'guard_name' => 'web'])->givePermissionTo('users.viewAny');
    $admin->assignGlobalRole('admin');

    return $admin->fresh();
}

it('lists only the members of the current team for somebody who manages that team', function () {
    $manager = tsuPerson('Mia', 'mia@example.com', [$this->ops]);
    $this->be($manager);

    Livewire::test(ListUsers::class)
        ->assertSee('olga@example.com')
        ->assertSee('mia@example.com')
        ->assertDontSee('bruno@example.com');
});

it('follows the team switcher', function () {
    $manager = tsuPerson('Mia', 'mia@example.com', [$this->ops, $this->billing]);
    $this->be($manager);
    Teams::switchTo($this->billing->getKey(), $manager);

    Livewire::test(ListUsers::class)
        ->assertSee('bruno@example.com')
        ->assertDontSee('olga@example.com');
});

it('lists nobody for somebody who is in no team', function () {
    $this->be(tsuPerson('Nina', 'nina@example.com'));

    Livewire::test(ListUsers::class)
        ->assertDontSee('olga@example.com')
        ->assertDontSee('bruno@example.com');
});

it('lists everybody for an administrator whose ability comes from a global role', function () {
    $this->be(tsuGlobalAdmin());

    Livewire::test(ListUsers::class)
        ->assertSee('olga@example.com')
        ->assertSee('bruno@example.com');
});

it('does not take the same ability from a role of one team as everywhere', function () {
    $manager = tsuPerson('Mia', 'mia@example.com', [$this->ops]);
    Permission::findOrCreate('users.viewAny', 'web');
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->ops->getKey());
    Role::query()->create(['name' => 'team-admin', 'guard_name' => 'web'])->givePermissionTo('users.viewAny');
    $manager->assignRole('team-admin');
    $this->be($manager->fresh());

    expect(Teams::seesEveryTeam('users.viewAny'))->toBeFalse();

    Livewire::test(ListUsers::class)->assertDontSee('bruno@example.com');
});

it('lists everybody for a super-admin, who is in no team', function () {
    $root = tsuPerson('Root', 'root@example.com');
    Role::query()->create(['name' => 'super-admin', 'guard_name' => 'web']);
    $root->assignGlobalRole('super-admin');
    $this->be($root->fresh());

    Livewire::test(ListUsers::class)
        ->assertSee('olga@example.com')
        ->assertSee('bruno@example.com');
});

it('answers 404, not 403, for an account of another team opened by its URL', function () {
    $this->be(tsuPerson('Mia', 'mia@example.com', [$this->ops]));

    Livewire::test(EditUser::class, ['record' => $this->billingMember->getKey()])->assertStatus(404);
    Livewire::test(ViewUser::class, ['record' => $this->billingMember->getKey()])->assertStatus(404);
});

it('opens an account of the current team', function () {
    $this->be(tsuPerson('Mia', 'mia@example.com', [$this->ops]));

    Livewire::test(EditUser::class, ['record' => $this->opsMember->getKey()])
        ->assertOk()
        ->assertSet('data.email', 'olga@example.com');

    Livewire::test(ViewUser::class, ['record' => $this->opsMember->getKey()])->assertOk();
});

it('opens an account mounted as a model the same way', function () {
    $this->be(tsuPerson('Mia', 'mia@example.com', [$this->ops]));

    Livewire::test(EditUser::class, ['record' => $this->billingMember])->assertStatus(404);
    Livewire::test(EditUser::class, ['record' => $this->opsMember])->assertOk();
});

it('acts on nobody outside the team, whatever key the browser sends', function () {
    $this->be(tsuPerson('Mia', 'mia@example.com', [$this->ops]));

    Livewire::test(ListUsers::class)
        ->call('executeTableAction', (string) $this->billingMember->getKey(), 'delete', true);

    expect(TeamAdmin::query()->find($this->billingMember->getKey()))->not->toBeNull();
});

it('puts an account made by a team manager into that team', function () {
    $manager = tsuPerson('Mia', 'mia@example.com', [$this->ops]);
    $this->be($manager);

    Livewire::test(CreateUser::class)
        ->set('data.'.UserResource::field('name'), 'New Person')
        ->set('data.'.UserResource::field('email'), 'new@example.com')
        ->set('data.'.UserResource::field('password'), 'first-secret')
        ->call('save')
        ->assertHasNoErrors();

    $created = TeamAdmin::query()->where('email', 'new@example.com')->firstOrFail();

    expect($created->teams()->pluck('teams.id')->all())->toBe([$this->ops->getKey()]);
});

it('leaves an account made by a global administrator in no team', function () {
    $this->be(tsuGlobalAdmin());

    Livewire::test(CreateUser::class)
        ->set('data.'.UserResource::field('name'), 'New Person')
        ->set('data.'.UserResource::field('email'), 'new@example.com')
        ->set('data.'.UserResource::field('password'), 'first-secret')
        ->call('save')
        ->assertHasNoErrors();

    expect(TeamAdmin::query()->where('email', 'new@example.com')->firstOrFail()->teams()->count())->toBe(0);
});

it('draws the line only where there are teams, and never for nobody', function () {
    expect(Teams::seesEveryTeam('users.viewAny', null))->toBeFalse();

    config()->set('permission.teams', false);

    expect(Teams::seesEveryTeam('users.viewAny', null))->toBeTrue();
});

it('does not count an application-opened screen as a global ability', function () {
    $this->be(tsuPerson('Mia', 'mia@example.com', [$this->ops]));

    expect(Teams::seesEveryTeam(null))->toBeFalse();
});

it('puts nobody in a team when the manager has none, or the model has no such relation', function () {
    $this->be(tsuPerson('Nina', 'nina@example.com'));
    $loner = tsuPerson('Lone', 'lone@example.com');

    Teams::admitToCurrentTeam($loner);

    expect($loner->teams()->count())->toBe(0);

    $this->be(tsuPerson('Mia', 'mia@example.com', [$this->ops]));
    config()->set('wire-module-users.teams.relation', 'squads');

    Teams::admitToCurrentTeam($loner);

    expect($loner->teams()->count())->toBe(0);
});

it('leaves a page with no record, or no model, to the page\'s own refusal', function () {
    // Nothing to scope: the resource page already says what is missing, in its
    // own words, and a 404 here would hide that.
    $page = new ViewUser;
    $resolve = new ReflectionMethod($page, 'resolveRecord');

    expect(fn () => $resolve->invoke($page))->toThrow(ResourcePageException::class);

    config()->set('wire-module-users.model', null);
    $page->record = 1;

    expect(fn () => $resolve->invoke($page))->toThrow(ResourcePageException::class);
});
