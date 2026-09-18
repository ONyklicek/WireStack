<?php

declare(strict_types=1);

namespace Workbench\App\Actions\Fortify;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Workbench\App\Models\User;

/**
 * The one part of a password reset Fortify leaves to the application.
 *
 * An application gets this from `fortify:install` (or from `wire:install`,
 * which runs it). The workbench never did, so it advertised a reset — by link
 * and by code — that ended in a container error the moment somebody submitted a
 * correct one. Nothing noticed, because no driver ever finished a reset;
 * `verify-auth-reset-code.mjs` does.
 */
final class ResetUserPassword implements ResetsUserPasswords
{
    /**
     * @param  User  $user
     * @param  array<string, mixed>  $input
     */
    public function reset($user, array $input): void
    {
        Validator::make($input, [
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ])->validate();

        $user->forceFill(['password' => Hash::make((string) $input['password'])])->save();
    }
}
