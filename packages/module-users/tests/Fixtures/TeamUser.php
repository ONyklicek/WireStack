<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * An application user who belongs to teams, with the relation named the way
 * `wire-module-users.teams.relation` expects to find it.
 */
class TeamUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password'];

    /** @return BelongsToMany<Team, $this> */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_user', 'user_id', 'team_id');
    }
}
