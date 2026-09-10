<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Tests\Fixtures;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/**
 * What an application's own `FortifyServiceProvider` binds, standing in.
 *
 * Fortify ships this as a stub rather than a binding: resetting a password
 * writes to the application's user model with the application's password rules,
 * so it is the one part of the reset Fortify deliberately leaves unbound. The
 * code flow hands its request to Fortify's controller, which resolves this — so
 * a test without it fails on a container error rather than on anything about
 * codes, and an application without it has a broken reset screen already.
 */
final class ResetUserPassword implements ResetsUserPasswords
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function reset($user, array $input): void
    {
        Validator::make($input, [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ])->validate();

        $user->forceFill(['password' => Hash::make($input['password'])])->save();
    }
}
