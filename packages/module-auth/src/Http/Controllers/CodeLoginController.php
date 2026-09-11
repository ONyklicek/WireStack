<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Fortify;
use NyonCode\WireModuleAuth\Actions\SendOneTimeCode;
use NyonCode\WireModuleAuth\Contracts\OneTimeCodes;
use NyonCode\WireModuleAuth\Enums\CodePurpose;
use NyonCode\WireModuleAuth\Support\Codes;

/**
 * Signing in with a code and no password.
 *
 * Two screens: one that takes an address, one that takes the six digits mailed
 * to it. The flow with no Fortify half to keep (ADR 0037 §2), so it is the one
 * place in this package where a session is opened — and the three rules that
 * makes it answerable for are all here rather than spread across the views:
 *
 *  - **the reply never says whether the address exists.** Sending redirects to
 *    the challenge whatever happened, exactly as Fortify's forgot-password does,
 *    because a screen that says "no such account" is an account enumerator;
 *  - **the pending sign-in is this package's session key, not Fortify's.**
 *    `login.id` means "the password was right"; nothing here has checked one, and
 *    borrowing the key would hand a half-authenticated session to any code that
 *    trusts what it implies;
 *  - **a code cannot walk past a second factor.** A user with an authenticator
 *    app is handed to Fortify's challenge with the code accepted but the session
 *    still closed — an inbox is one factor, and this must not be the way around
 *    the other.
 */
class CodeLoginController extends Controller
{
    /** Where the address a code was sent to is remembered between the two screens. */
    public const SESSION_KEY = 'wire-auth.login-code.identifier';

    public function __construct(
        private readonly StatefulGuard $guard,
        private readonly OneTimeCodes $codes,
        private readonly SendOneTimeCode $send,
    ) {}

    /** The screen that asks for an address. */
    public function create(): View
    {
        return view('wire-module-auth::login-code');
    }

    /** Mail a code — or do nothing at all, and say the same thing either way. */
    public function store(Request $request): RedirectResponse
    {
        // Normalised once, then used for all three things — the session key, the
        // user lookup and the code's identifier. It used to look the user up with
        // the address exactly as typed while filing the code under the lower-cased
        // one, which agrees with itself on a case-insensitive collation and comes
        // apart on PostgreSQL or a binary MySQL collation: `Ann@Example.com`
        // matched no row, so no code was ever sent, while the screen said one was.
        // `send()` and `verify()` were already keyed this way; only this one was not.
        $identifier = Codes::identifierFor($this->address($request));

        $request->session()->put(self::SESSION_KEY, $identifier);

        ($this->send)(CodePurpose::Login, $this->user($identifier), $identifier);

        return redirect()->route('wire-auth.login-code.challenge')
            ->with('status', __('wire-module-auth::messages.code_sent'));
    }

    /**
     * The screen that takes the digits.
     *
     * Reached only with an address in the session: arriving here cold means a
     * bookmarked URL or an expired session, and a form that posts a code for
     * nobody would report "that code is wrong" for the rest of time.
     */
    public function challenge(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has(self::SESSION_KEY)) {
            return redirect()->route('wire-auth.login-code');
        }

        return view('wire-module-auth::code-challenge', [
            'action' => route('wire-auth.login-code.challenge'),
            'resend' => route('wire-auth.login-code.send'),
            'back' => route('login'),
            'address' => (string) $request->session()->get(self::SESSION_KEY),
        ]);
    }

    /** Another code for the same address, when the first one did not arrive. */
    public function send(Request $request): RedirectResponse
    {
        $identifier = (string) $request->session()->get(self::SESSION_KEY, '');

        if ($identifier === '') {
            return redirect()->route('wire-auth.login-code');
        }

        $sent = ($this->send)(CodePurpose::Login, $this->user($identifier), $identifier);

        return back()->with('status', __($sent
            ? 'wire-module-auth::messages.code_sent'
            : 'wire-module-auth::messages.code_sent_recently'));
    }

    /**
     * The digits, and what they open.
     *
     * The user is looked up *after* the code checks out, and a code that checks
     * out for an address with no account is treated as a wrong code: the row
     * cannot exist, but the branch is written anyway because "verified, then no
     * user" is the shape a store swapped for one of an application's own could
     * produce.
     */
    public function verify(Request $request): mixed
    {
        $identifier = (string) $request->session()->get(self::SESSION_KEY, '');

        if ($identifier === '') {
            return redirect()->route('wire-auth.login-code');
        }

        $request->validate(['code' => ['required', 'string']]);

        $verified = $this->codes->verify(CodePurpose::Login, $identifier, (string) $request->input('code'));
        $user = $verified === null ? null : $this->user($identifier);

        if ($user === null) {
            throw ValidationException::withMessages([
                'code' => [__('wire-module-auth::messages.code_invalid')],
            ]);
        }

        $request->session()->forget(self::SESSION_KEY);

        if (Codes::usesAuthenticatorApp($user)) {
            return $this->handToTwoFactorChallenge($request, $user);
        }

        $this->guard->login($user);

        $request->session()->regenerate();

        // Fortify's own response, so where a sign-in lands is answered in one
        // place for both ways in — including for an application that has bound
        // its own.
        return app(LoginResponse::class);
    }

    /**
     * A code is not a second factor.
     *
     * The code was right, so the session moves on to the state a right password
     * would have produced — Fortify's `login.id`, its event, its challenge — and
     * the panel stays shut until the authenticator app has said so too.
     */
    protected function handToTwoFactorChallenge(Request $request, Authenticatable $user): RedirectResponse
    {
        $request->session()->put([
            'login.id' => $user->getAuthIdentifier(),
            'login.remember' => false,
        ]);

        TwoFactorAuthenticationChallenged::dispatch($user);

        return redirect()->route('two-factor.login');
    }

    /** The address as typed, validated the way Fortify validates the same field. */
    protected function address(Request $request): string
    {
        $field = Fortify::username();

        $validated = $request->validate([
            $field => ['required', 'string', 'max:255'],
        ]);

        return (string) $validated[$field];
    }

    /** Whoever that address belongs to, if anybody. */
    protected function user(string $address): ?Authenticatable
    {
        return $this->guard->getProvider()->retrieveByCredentials([
            Fortify::username() => $address,
        ]);
    }
}
