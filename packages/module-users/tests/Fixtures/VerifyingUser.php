<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Tests\Fixtures;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * An application user whose address has to be proven.
 *
 * `MustVerifyEmail` is the whole point: the rule that clears a verified flag
 * when the address changes asks the model for it, and a model without the
 * contract has nothing to clear. `Notifiable` is what makes the "prove the new
 * one" half reachable — Laravel's own `VerifyEmail` notification goes through
 * `notify()`.
 */
class VerifyingUser extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password'];

    protected $casts = ['email_verified_at' => 'datetime'];
}
