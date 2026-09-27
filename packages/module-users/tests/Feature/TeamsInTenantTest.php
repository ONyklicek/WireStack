<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Core\Tenancy\Concerns\BelongsToTenant;
use NyonCode\WireCore\Core\Tenancy\CurrentTenant;
use NyonCode\WireModuleUsers\Support\Teams;

/*
 * Teams inside a company (ADR 0040 §6).
 *
 * The owner's own example is the fixture: a person in company A only, and in
 * three of A's five projects. A company is the tenant; a project is a team of
 * one company, so the team model is tenant-owned and the existing scope keeps
 * every team list to the company in the URL.
 */
class TtCompany extends Model
{
    protected $table = 'tt_companies';

    protected $guarded = [];

    public $timestamps = false;
}

class TtProject extends Model
{
    use BelongsToTenant;

    protected $table = 'teams';

    protected $guarded = [];
}

class TtUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(TtProject::class, 'team_user', 'user_id', 'team_id');
    }
}

beforeEach(function () {
    config()->set('wire-module-users.model', TtUser::class);
    config()->set('auth.providers.users.model', TtUser::class);
    config()->set('wire-module-users.roles', false);
    config()->set('wire-module-users.teams.model', TtProject::class);
    config()->set('permission.teams', true);
    config()->set('wire-core.tenancy.enabled', true);

    Schema::create('tt_companies', fn (Blueprint $t) => $t->id());
    Schema::create('users', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->timestamps();
    });
    Schema::create('teams', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->unsignedBigInteger('tenant_id');
        $t->timestamps();
    });
    Schema::create('team_user', function (Blueprint $t) {
        $t->unsignedBigInteger('team_id');
        $t->unsignedBigInteger('user_id');
    });

    $this->a = TtCompany::query()->create();
    $this->b = TtCompany::query()->create();

    // Five projects in A, two in B.
    $projects = [];
    foreach (['A1', 'A2', 'A3', 'A4', 'A5'] as $name) {
        $projects[$name] = TtProject::query()->withoutGlobalScopes()->create(['name' => $name, 'tenant_id' => $this->a->id]);
    }
    foreach (['B1', 'B2'] as $name) {
        $projects[$name] = TtProject::query()->withoutGlobalScopes()->create(['name' => $name, 'tenant_id' => $this->b->id]);
    }
    $this->projects = $projects;

    $this->user = TtUser::query()->create(['name' => 'Ada']);
    $this->user->teams()->attach([$projects['A1']->id, $projects['A2']->id, $projects['A3']->id, $projects['B1']->id, $projects['B2']->id]);
    $this->be($this->user);
});

afterEach(function () {
    app(CurrentTenant::class)->leave();

    foreach (['team_user', 'teams', 'users', 'tt_companies'] as $table) {
        Schema::dropIfExists($table);
    }

    config()->set('wire-core.tenancy.enabled', false);
});

it('offers the projects of the current company the person belongs to — three of five', function () {
    app(CurrentTenant::class)->enter($this->a);

    expect(array_values(Teams::optionsFor()))->toBe(['A1', 'A2', 'A3']);

    app(CurrentTenant::class)->enter($this->b);

    expect(array_values(Teams::optionsFor()))->toBe(['B1', 'B2']);
});

it('remembers the current project per company', function () {
    $current = app(CurrentTenant::class);

    $current->enter($this->a);
    expect(Teams::switchTo($this->projects['A2']->id))->toBeTrue()
        ->and(Teams::sessionKey())->toBe('wire.team.'.$this->a->id);

    $current->enter($this->b);
    expect(Teams::currentId())->toBe($this->projects['B1']->id);
    Teams::switchTo($this->projects['B2']->id);

    $current->enter($this->a);
    expect(Teams::currentId())->toBe($this->projects['A2']->id);
});

it('never switches into a project of another company', function () {
    app(CurrentTenant::class)->enter($this->a);

    expect(Teams::switchTo($this->projects['B1']->id))->toBeFalse()
        ->and(Teams::currentId())->toBe($this->projects['A1']->id);
});

it('points the permission layer at the company project the moment the company is entered', function () {
    // SetCurrentTeam ran in the `web` group, before any company: no team.
    Teams::apply(null);

    app(CurrentTenant::class)->enter($this->b);

    expect(app(Teams::REGISTRAR)->getPermissionsTeamId())->toBe($this->projects['B1']->id);

    app(CurrentTenant::class)->leave();

    // Outside a company no project resolves: the scope keeps every team out.
    expect(app(Teams::REGISTRAR)->getPermissionsTeamId())->toBeNull();
});

it('keeps one key outside tenancy, as before', function () {
    expect(Teams::sessionKey())->toBe('wire.team');
});
