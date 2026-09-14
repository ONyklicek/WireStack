<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use NyonCode\PermissionExtended\Traits\HasRoles;

/**
 * The application user that has both halves: teams, and roles scoped to them.
 *
 * {@see TeamUser} has the relation and no roles, which is what the switcher and
 * the middleware are tested against; {@see User} has the roles and no teams.
 * Giving a role in an application that scopes them needs the two at once, and
 * that combination is the one where an unscoped assignment stops being a
 * question of configuration and becomes a constraint violation.
 */
class TeamAdmin extends Authenticatable
{
    use HasRoles;
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password'];

    /** @return BelongsToMany<Team, $this> */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_user', 'user_id', 'team_id');
    }
}
