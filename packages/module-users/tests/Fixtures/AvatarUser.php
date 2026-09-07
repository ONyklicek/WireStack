<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use NyonCode\WireCore\Foundation\Contracts\HasAvatar;
use NyonCode\WireModuleUsers\Concerns\InteractsWithAvatar;

/**
 * The same application user, with the one line that gives it a face.
 *
 * The contract is `wire-core`'s and the trait is this module's, which is the
 * split the shell depends on: the chrome asks the interface and never learns
 * that this package exists.
 */
class AvatarUser extends Authenticatable implements HasAvatar
{
    use InteractsWithAvatar;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password'];
}
