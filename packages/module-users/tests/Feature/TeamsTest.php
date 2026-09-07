<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NyonCode\WireCore\Foundation\View\PageChrome;
use NyonCode\WireModuleUsers\Livewire\TeamSwitcher;
use NyonCode\WireModuleUsers\Support\Teams;
use NyonCode\WireModuleUsers\Tests\Fixtures\Team;
use NyonCode\WireModuleUsers\Tests\Fixtures\TeamUser;

/*
 * Teams, which this package does not implement.
 *
 * The permission layer owns them — the scoped roles, the extra pivot column, the
 * cache key — and that layer is always `nyoncode/laravel-permission-extended`,
 * over the `spatie/laravel-permission` it requires. What is tested here is the
 * half neither has an opinion about and a panel cannot do without: which team
 * this request is in, who is allowed to say, and what happens to the page when
 * it changes.
 */

beforeEach(function () {
    config()->set('wire-module-users.model', TeamUser::class);
    config()->set('auth.providers.users.model', TeamUser::class);
    config()->set('wire-module-users.roles', false);
    config()->set('wire-module-users.teams.model', Team::class);

    // The switch that makes the whole feature real: it is what puts the team
    // column on the pivot tables and into the permission cache key, so following
    // it is what keeps the panel and the authorization it draws agreeing.
    config()->set('permission.teams', true);

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
});

function signedInWithTeams(array $names = ['Ops', 'Billing']): TeamUser
{
    $user = TeamUser::query()->create([
        'name' => 'Amelia',
        'email' => 'a@example.com',
        'password' => Hash::make('secret'),
    ]);

    foreach ($names as $name) {
        $user->teams()->attach(Team::query()->create(['name' => $name])->getKey());
    }

    test()->be($user);

    return $user;
}

it('follows the permission package rather than a switch of its own', function () {
    expect(Teams::enabled())->toBeTrue();

    config()->set('permission.teams', false);

    expect(Teams::available())->toBeFalse()
        ->and(Teams::enabled())->toBeFalse();
});

it('lets an application answer the question for itself', function () {
    config()->set('permission.teams', false);
    config()->set('wire-module-users.teams.enabled', true);

    expect(Teams::enabled())->toBeTrue();

    config()->set('wire-module-users.teams.enabled', false);

    expect(Teams::enabled())->toBeFalse();
});

it('offers a person their own teams, and nobody else’s', function () {
    $me = signedInWithTeams(['Ops', 'Billing']);

    // A team somebody else is in, and I am not.
    Team::query()->create(['name' => 'Legal']);

    expect(array_values(Teams::optionsFor($me)))->toBe(['Ops', 'Billing']);
});

it('lands on a real team before anybody has chosen one', function () {
    $me = signedInWithTeams(['Ops', 'Billing']);

    $first = array_key_first(Teams::optionsFor($me));

    expect(Teams::currentId($me))->toBe($first)
        ->and(session(Teams::sessionKey()))->toBeNull();
});

it('remembers the chosen team, and forgets one they are no longer in', function () {
    $me = signedInWithTeams(['Ops', 'Billing']);

    $billing = array_key_last(Teams::optionsFor($me));

    expect(Teams::switchTo($billing, $me))->toBeTrue()
        ->and(Teams::currentId($me))->toBe($billing);

    // Removed from it after the fact: the session still names it, and the
    // fallback is what keeps the next page somewhere real rather than nowhere.
    $me->teams()->detach($billing);

    expect(Teams::currentId($me))->not->toBe($billing)
        ->and(Teams::currentId($me))->toBe(array_key_first(Teams::optionsFor($me)));
});

it('refuses a switch into a team they do not belong to', function () {
    $me = signedInWithTeams(['Ops']);

    $theirs = Team::query()->create(['name' => 'Legal']);

    // The control is markup, and markup is whatever reached the browser.
    expect(Teams::switchTo($theirs->getKey(), $me))->toBeFalse()
        ->and(session(Teams::sessionKey()))->toBeNull();
});

it('answers with nothing at all when teams are off', function () {
    signedInWithTeams();

    config()->set('permission.teams', false);

    expect(Teams::currentId())->toBeNull()
        ->and(Teams::switchTo(1))->toBeFalse();
});

it('switches from the top bar, and reloads the page it switched away from', function () {
    $me = signedInWithTeams(['Ops', 'Billing']);

    $billing = array_key_last(Teams::optionsFor($me));

    // A redirect rather than a re-render, and not out of laziness: the team
    // scopes every permission read, so a page composed before the switch — its
    // menu, its actions, the rows a policy let through — was built for the team
    // you just left.
    Livewire::test(TeamSwitcher::class)
        ->call('switchTo', $billing)
        ->assertSet('current', $billing)
        ->assertRedirect();

    expect(session(Teams::sessionKey()))->toBe($billing);
});

it('ignores a switch the person is not entitled to make', function () {
    signedInWithTeams(['Ops']);

    $theirs = Team::query()->create(['name' => 'Legal']);

    Livewire::test(TeamSwitcher::class)
        ->call('switchTo', $theirs->getKey())
        ->assertNoRedirect();

    expect(session(Teams::sessionKey()))->toBeNull();
});

it('puts the switcher in the top bar, not at the end of the document', function () {
    // It has to be *seen*, unlike a modal that only has to exist — which is the
    // whole reason PageChrome grew a second region rather than a second
    // registry.
    $chrome = new PageChrome;
    $chrome->add('wire-module-users::team-switcher', PageChrome::TOPBAR);

    expect($chrome->views(PageChrome::TOPBAR))->toContain('wire-module-users::team-switcher')
        ->and($chrome->views())->toBe([]);
});

it('has no current team for somebody who belongs to none', function () {
    // Teams are on and this person is in none of them: there is no team to be
    // in, which is not the same as teams being off.
    $me = TeamUser::query()->create([
        'name' => 'Amelia',
        'email' => 'a@example.com',
        'password' => Hash::make('secret'),
    ]);

    test()->be($me);

    Team::query()->create(['name' => 'Somebody else\'s']);

    expect(Teams::optionsFor($me))->toBe([])
        ->and(Teams::currentId($me))->toBeNull();
});
