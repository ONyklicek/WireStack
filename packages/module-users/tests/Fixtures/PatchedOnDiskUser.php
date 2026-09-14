<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use NyonCode\PermissionExtended\Traits\HasRoles;

/**
 * A user model as a running installer sees it after `permission-extended:install`.
 *
 * The file carries the import the permission installer writes, and the class
 * this process loaded does not use the trait — which is what a model patched on
 * disk after boot looks like from inside the process that booted. PHP cannot
 * load the patched class twice, so the role has to be given from a fresh one.
 */
class PatchedOnDiskUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password'];

    /** @return BelongsToMany<Team, $this> */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_user', 'user_id', 'team_id');
    }

    /** Keeps the import a used one, so a linter does not strip the line under test. */
    public static function traitItWillHave(): string
    {
        return HasRoles::class;
    }
}
