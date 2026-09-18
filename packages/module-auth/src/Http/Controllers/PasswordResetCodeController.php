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
use Laravel\Fortify\Contracts\PasswordResetResponse;
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
 *
 * ## A refused password does not cost the code
 *
 * Verifying consumes the code, and the new password is judged after that — by
 * the application's own `ResetsUserPasswords`, inside the broker. So a password
 * that did not match its confirmation, or fell short of the rules, used to
 * leave a person with a spent code and nothing to show for it: back to
 * `/forgot-password`, where the broker's throttle then refused them.
 *
 * A correct code now leaves a proof in the session — the address, a keyed
 * fingerprint of the digits, and the moment the code would have expired. The
 * form comes back with the code still filled in, and the corrected attempt is
 * accepted on that proof instead of a row that no longer exists. It is narrower
 * than the code ever was: this browser only, this address, these digits, and no
 * later than the code itself would have lived. A successful reset removes it.
 */
class PasswordResetCodeController extends Controller
{
    /** Where the address the reset was asked for is remembered. */
    public const SESSION_KEY = 'wire-auth.reset-code.address';

    /** Where a code this session has already proved is remembered. */
    public const PROOF_KEY = 'wire-auth.reset-code.proven';

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

        // Normalised once and used for all three — the code, the token and the
        // address Fortify resets — as `CodeLoginController::store()` does. The
        // code is filed under the lower-cased address; looking the account up
        // with the address as typed agrees with that only on a case-insensitive
        // collation, and on SQLite or PostgreSQL `Ann@Example.com` spent a
        // correct code and then reported it wrong.
        $address = Codes::identifierFor((string) $request->input($field));
        $code = (string) $request->input('code');

        if (! $this->provenBefore($request, $address, $code)) {
            $verified = $this->codes->verify(CodePurpose::ResetPassword, $address, $code);

            if ($verified === null) {
                throw $this->codeInvalid();
            }

            $request->session()->put(self::PROOF_KEY, [
                'address' => $address,
                'code' => $this->fingerprint($code),
                'until' => $verified->expiresAt->getTimestamp(),
            ]);
        }

        // The broker Fortify will check the token against, and the account it
        // is for. `createToken()` is on Laravel's concrete broker and not on the
        // contract, so an application that bound a broker of its own cannot be
        // minted for — and an address that reaches nobody is an account gone
        // away between the mail and the form. Both are the code being no good,
        // never a word about whether the account exists.
        $broker = Password::broker(config('fortify.passwords'));
        $user = $broker instanceof PasswordBroker ? $broker->getUser([Fortify::email() => $address]) : null;

        if (! $broker instanceof PasswordBroker || $user === null) {
            $request->session()->forget(self::PROOF_KEY);

            throw $this->codeInvalid();
        }

        $request->merge([$field => $address, 'token' => $broker->createToken($user)]);

        try {
            $response = app(NewPasswordController::class)->store($request);
        } catch (ValidationException $refused) {
            // The new password was refused — its confirmation, or the
            // application's rules. The proof stays, so the corrected attempt goes
            // through. The token leaves the input, which Laravel flashes back to
            // the form, so a credential is not written into the session on its
            // way to being thrown away.
            $request->offsetUnset('token');

            throw $refused;
        } finally {
            // The token was minted for this one request. A successful reset has
            // already spent it; anything else did nothing with it, and a live one
            // left behind would stay good for an hour. Either way it goes.
            $broker->deleteToken($user);
        }

        if ($response instanceof PasswordResetResponse) {
            $request->session()->forget(self::PROOF_KEY);
        }

        return $response;
    }

    /**
     * Whether this session already proved this code for this address.
     *
     * All four must hold: the same address, the same digits, the code's own
     * lifetime not yet over, and — by being in this session at all — the same
     * browser that proved it. Anything less and the code has to be verified,
     * which for a spent one means refused.
     */
    private function provenBefore(Request $request, string $address, string $code): bool
    {
        $proof = $request->session()->get(self::PROOF_KEY);

        return is_array($proof)
            && ($proof['address'] ?? null) === $address
            && is_string($proof['code'] ?? null)
            && hash_equals($proof['code'], $this->fingerprint($code))
            && (int) ($proof['until'] ?? 0) > now()->getTimestamp();
    }

    /**
     * The digits, keyed rather than kept.
     *
     * A plain hash of six digits is the digits, a million guesses away. Keyed
     * with the application key, the session holds something that proves a match
     * and gives nothing back to whoever reads the session store.
     */
    private function fingerprint(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    private function codeInvalid(): ValidationException
    {
        return ValidationException::withMessages([
            'code' => [__('wire-module-auth::messages.code_invalid')],
        ]);
    }
}
