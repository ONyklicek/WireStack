<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A team, which no package ships.
 *
 * `wire-module-users` switches its team surface on when
 * `permission.teams` is true and this model is configured — it never brings a
 * teams table of its own, because an application that has teams already has one.
 * The workbench stands in for that application.
 */
class Team extends Model
{
    protected $table = 'teams';

    protected $guarded = [];

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
