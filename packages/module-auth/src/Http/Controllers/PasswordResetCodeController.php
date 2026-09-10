<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Http\Controllers;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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
 * The mail carries a code; the code's payload *is* that token
 * (`Actions\MailResetCode`), and this controller's whole job is to swap one for
 * the other and hand the request to Fortify's own `NewPasswordController`. So
 * the token's expiry, its single use, the broker's rules and
 * `ResetsUserPasswords` all keep working exactly as they do behind a link
 * (ADR 0037 §2).
 *
 * That indirection is what makes six digits acceptable here at all: the code is
 * a short-lived, attempt-counted key to a token nobody can guess, rather than a
 * replacement for it.
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
        $token = $verified?->payload('token');

        if (! is_string($token) || $token === '') {
            throw ValidationException::withMessages([
                'code' => [__('wire-module-auth::messages.code_invalid')],
            ]);
        }

        $request->merge(['token' => $token]);

        return app(NewPasswordController::class)->store($request);
    }
}
