<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;
use NyonCode\PermissionExtended\Traits\HasRoles;

/**
 * An application's user model, as the module expects to find one: the
 * permission package's trait on it, and nothing this package owns.
 *
 * The passkey trait is here rather than on a fixture of its own, and the reason
 * is Eloquent rather than taste: `hasMany()` derives the foreign key from the
 * *parent class name*, so a model called `PasskeyUser` looks for
 * `passkey_user_id` while the package's migration writes `user_id`. Only a class
 * called `User` is the shape an application actually has. The card's
 * "trait missing" branch is tested against {@see AvatarUser}, which has none.
 */
class User extends Authenticatable implements PasskeyUser
{
    use HasRoles;
    use PasskeyAuthenticatable;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password'];
}
