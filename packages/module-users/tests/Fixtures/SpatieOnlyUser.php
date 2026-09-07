<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * A user model on **bare Spatie**, which this module deliberately does not
 * recognise: the screens are built against
 * `nyoncode/laravel-permission-extended`, and its wildcard matching,
 * super-admin gate and permission-change events are not optional extras the
 * role forms can do without.
 */
class SpatieOnlyUser extends Authenticatable
{
    use HasRoles;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password'];
}
