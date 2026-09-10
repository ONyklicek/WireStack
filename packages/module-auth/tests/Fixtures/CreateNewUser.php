<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Tests\Fixtures;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * What an application's own `FortifyServiceProvider` binds, standing in.
 *
 * The sibling of {@see ResetUserPassword}, and unbound for the same reason:
 * which columns a new account has is the application's question, and Fortify
 * ships a stub rather than an answer. It matters to these tests because it is
 * the far end of the seam they exist to watch — the register screen decides
 * which inputs exist, and *this* is what reads them. A field added to the schema
 * and not to an action like this one renders, posts, and is thrown away.
 */
final class CreateNewUser implements CreatesNewUsers
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): CodeUser
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ])->validate();

        return CodeUser::query()->create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);
    }
}
