<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Tests\Fixtures;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * The application's user, with everything the code flows can ask of one.
 *
 * All three traits on purpose. `Notifiable` is what makes a code deliverable,
 * `MustVerifyEmail` is what the confirmation flow needs to have anything to
 * confirm, and Fortify's `TwoFactorAuthenticatable` is what makes "this person
 * has an authenticator app" a question with a real answer rather than a stub —
 * the branch where a mailed code must *not* open a session.
 */
class CodeUser extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;
    use TwoFactorAuthenticatable;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
    ];
}
