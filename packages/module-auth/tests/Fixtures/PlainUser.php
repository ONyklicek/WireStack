<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * An account with no second factor to have, on an installation that offers one.
 *
 * The opposite of {@see CodeUser}, and deliberately not a subclass of it: what
 * makes this fixture worth having is the trait it does *not* use. Fortify's
 * `TwoFactorAuthenticatable` is what gives a model somewhere to keep a secret,
 * and a model without it has no `two_factor_secret` column to read — so
 * challenging one would be asking for something it cannot answer.
 *
 * `MustVerifyEmail` is left off for the same reason: this is the plainest user
 * an application can hand the package, and the screens have to hold for it.
 */
class PlainUser extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password'];

    protected $casts = ['email_verified_at' => 'datetime'];
}
