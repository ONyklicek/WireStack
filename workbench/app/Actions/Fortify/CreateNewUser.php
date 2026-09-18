<?php

declare(strict_types=1);

namespace Workbench\App\Actions\Fortify;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Workbench\App\Models\User;

/**
 * What Fortify's registration hands the form to — the application's to write.
 *
 * Same story as {@see ResetUserPassword}: `fortify:install` gives an application
 * this, the workbench never had it, and the register screen it previews answered
 * every submit with a container error. Found by posting an empty form, which the
 * container resolves this for before any validation runs.
 *
 * The fields are exactly the ones `AuthForms::register()` draws. The rest of the
 * row takes its column defaults — a new account is a `viewer` until somebody
 * says otherwise.
 */
final class CreateNewUser implements CreatesNewUsers
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ])->validate();

        return User::query()->create([
            'name' => (string) $input['name'],
            'email' => (string) $input['email'],
            // Hashed by the model's `hashed` cast.
            'password' => (string) $input['password'],
        ]);
    }
}
