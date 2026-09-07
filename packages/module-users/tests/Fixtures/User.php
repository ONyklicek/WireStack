<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use NyonCode\PermissionExtended\Traits\HasRoles;

/**
 * An application's user model, as the module expects to find one: the
 * permission package's trait on it, and nothing this package owns.
 */
class User extends Authenticatable
{
    use HasRoles;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password'];
}
