<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * An application user with Fortify's trait on it, which is the one line an
 * application writes to make the two-factor card real.
 */
class FortifyUser extends Authenticatable
{
    use TwoFactorAuthenticatable;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password', 'two_factor_secret', 'two_factor_recovery_codes'];
}
