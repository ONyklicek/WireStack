<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Http\Controllers;

use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use NyonCode\WireModuleAuth\Contracts\OneTimeCodes;
use NyonCode\WireModuleAuth\Enums\CodePurpose;
use NyonCode\WireModuleAuth\Support\Codes;

/**
 * A new password, from a code instead of a link.
 *
 * **The broker's token is untouched, and it is still what resets the password.**
 * The mail carries a code; a correct code mints a token from the broker's own
 * repository, and this controller hands the request to Fortify's own
 * `NewPasswordController`. So the token's expiry, its single use, the broker's
 * rules and `ResetsUserPasswords` all keep working exactly as they do behind a
 * link (ADR 0037 §2).
 *
 * That indirection is what makes six digits acceptable here at all: the code is
 * a short-lived, attempt-counted key to a token nobody can guess, rather than a
 * replacement for it.
 *
 * ## Why the token is minted here and not carried
 *
 * It used to ride in the code's payload, which meant the readable original of a
 * secret Laravel stores hashed sat in a second table until somebody used or
 * replaced it. The code is the thing that proves possession of the mailbox; once
 * it has, there is no reason not to ask the broker for a token in the same
 * breath. `createToken()` replaces any token the user already had, so the link
 * flow and this one cannot both be live for the same account — which is the
 * property the link flow has always had.
 *
 * The throttle `sendResetLink()` applies before minting is deliberately not
 * repeated here: it exists to stop an unauthenticated stranger mailing somebody
 * repeatedly, and by this point the caller has already answered a code that only
 * reached that mailbox. The attempt counter on the code row is the limit that
 * belongs on this end.
 */
class PasswordResetCodeController extends Controller
{
    /** Where the address the reset was asked for is remembered. */
    public const SESSION_KEY = 'wire-auth.reset-code.address';

    public function __construct(private readonly OneTimeCodes $codes) {}

    /** The screen: the address, the code from the mail, and the new password. */
    public function create(Request $request): View
    {
        return view('wire-module-auth::reset-password-code', [
            'address' => (string) $request->session()->get(self::SESSION_KEY, ''),
        ]);
    }

    /**
     * Trade the code for the token, then let Fortify do the reset.
     *
     * The code is checked before anything else so a wrong one costs an attempt
     * and nothing more — the password rules, the confirmation and the broker's
     * verdict are all Fortify's, and are reported on the fields they belong to.
     */
    public function store(Request $request): Responsable
    {
        // `Fortify::email()`, not `username()`: the broker keys reset tokens on
        // the address, and the controller this hands off to validates that key.
        // They are the same string in every default installation and are two
        // settings for a reason.
        $field = Fortify::email();

        $request->validate([
            $field => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        $address = Codes::identifierFor((string) $request->input($field));

        $verified = $this->codes->verify(CodePurpose::ResetPassword, $address, (string) $request->input('code'));

        $token = $verified === null ? null : $this->mintToken($request->input($field));

        if (! is_string($token) || $token === '') {
            throw ValidationException::withMessages([
                'code' => [__('wire-module-auth::messages.code_invalid')],
            ]);
        }

        $request->merge(['token' => $token]);

        return app(NewPasswordController::class)->store($request);
    }

    /**
     * A token for the account this address belongs to, from the broker Fortify
     * will check it against.
     *
     * `null` when the address reaches nobody. A code was issued against it, so
     * this is the account having gone away between the mail and the form — rare,
     * and reported as the code being no good rather than as an account that does
     * or does not exist.
     */
    private function mintToken(mixed $address): ?string
    {
        $broker = Password::broker(config('fortify.passwords'));

        if (! $broker instanceof PasswordBroker) {
            return null;
        }

        $user = $broker->getUser([Fortify::email() => $address]);

        return $user === null ? null : $broker->createToken($user);
    }
}
