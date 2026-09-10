<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Http\Controllers;

use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;
use NyonCode\WireModuleAuth\Actions\RedirectIfCodeRequired;
use NyonCode\WireModuleAuth\Actions\SendOneTimeCode;
use NyonCode\WireModuleAuth\Contracts\OneTimeCodes;
use NyonCode\WireModuleAuth\Enums\CodePurpose;
use NyonCode\WireModuleAuth\Support\Codes;

/**
 * The mailed second factor, between a right password and the panel.
 *
 * Deliberately shaped like Fortify's own `TwoFactorAuthenticatedSessionController`
 * and built on the same request object: `TwoFactorLoginRequest` is what reads
 * the half-authenticated session — the `login.id` key
 * {@see RedirectIfCodeRequired} wrote, the
 * "remember me" the person ticked on the login form, and the redirect to `login`
 * for anyone arriving without either.
 *
 * Reusing it is not a shortcut: it means a session this package can open is
 * exactly a session Fortify would have opened, and that the two challenges
 * cannot drift apart on what a pending sign-in means.
 *
 * Fortify's events are fired too, both of them, so an application watching for a
 * failed second factor sees this one as well.
 */
class SecondFactorCodeController extends Controller
{
    public function __construct(
        private readonly StatefulGuard $guard,
        private readonly OneTimeCodes $codes,
        private readonly SendOneTimeCode $send,
    ) {}

    /** The boxes, for a sign-in that is already half done. */
    public function create(TwoFactorLoginRequest $request): View
    {
        $this->ensureChallenged($request);

        return view('wire-module-auth::code-challenge', [
            'action' => route('wire-auth.second-factor'),
            'resend' => route('wire-auth.second-factor.send'),
            'back' => route('login'),
            'address' => null,
        ]);
    }

    /** Another code for the same pending sign-in. */
    public function send(TwoFactorLoginRequest $request): RedirectResponse
    {
        $this->ensureChallenged($request);

        $user = $request->challengedUser();

        $sent = ($this->send)(CodePurpose::SecondFactor, $user, Codes::identifierFor($user));

        return back()->with('status', __($sent
            ? 'wire-module-auth::messages.code_sent'
            : 'wire-module-auth::messages.code_sent_recently'));
    }

    /**
     * The digits, and the session they open.
     *
     * Everything after the check is Fortify's: its event, its `login()`, its
     * session regeneration, its response — so "where does a second factor land"
     * has one answer for both kinds of second factor.
     */
    public function store(TwoFactorLoginRequest $request): mixed
    {
        $this->ensureChallenged($request);

        $user = $request->challengedUser();

        $request->validate(['code' => ['required', 'string']]);

        $verified = $this->codes->verify(
            CodePurpose::SecondFactor,
            Codes::identifierFor($user),
            (string) $request->input('code'),
        );

        if ($verified === null) {
            event(new TwoFactorAuthenticationFailed($user));

            throw ValidationException::withMessages([
                'code' => [__('wire-module-auth::messages.code_invalid')],
            ]);
        }

        event(new ValidTwoFactorAuthenticationCodeProvided($user));

        // The key Fortify's own challenge forgets on a valid code. Left behind,
        // it is a pending sign-in that stays answerable after the session it
        // belonged to has already been opened.
        $request->session()->forget('login.id');

        $this->guard->login($user, $request->remember());

        $request->session()->regenerate();

        return app(TwoFactorLoginResponse::class);
    }

    /**
     * Nobody is half signed in — go back to the start.
     *
     * The same `HttpResponseException` Fortify throws for the same state, rather
     * than a redirect return type on three methods: a bookmarked challenge URL
     * is not an error, it is a session that ended.
     *
     * **`login.id` is not permission to be here, and that was the bug.** Two
     * different branches write that key: Fortify's own, for a user with a
     * confirmed authenticator app, and {@see RedirectIfCodeRequired::codeChallengeResponse()},
     * for a user this installation mails codes to. The session cannot tell them
     * apart. So a challenge that asked only "is somebody half signed in" let the
     * first kind answer with the second kind's factor — password right, ignore
     * the redirect to Fortify's challenge, post to `send`, and an inbox stood in
     * for the authenticator app the person deliberately set up.
     *
     * The same door reopened the one {@see CodeLoginController::handToTwoFactorChallenge()}
     * had just shut: that method refuses to open a session for a TOTP user and
     * writes `login.id` instead, and this screen turned it straight back into a
     * mailed code.
     *
     * So the two questions the pipe asked on the way in are asked again here.
     * They have to be: a route is reachable by anybody who knows its shape, and
     * a check that only runs in the pipeline is a check the pipeline can be
     * stepped around.
     */
    protected function ensureChallenged(TwoFactorLoginRequest $request): void
    {
        if (! $request->hasChallengedUser()) {
            throw new HttpResponseException(redirect()->route('login'));
        }

        $user = $request->challengedUser();

        // An authenticator app always wins (ADR 0037 §2). Sent to Fortify's own
        // challenge rather than to the login form: this person *is* half signed
        // in, they are simply owed a different question, and starting them over
        // would punish them for a URL they should not have reached.
        if (Codes::usesAuthenticatorApp($user)) {
            throw new HttpResponseException(redirect()->to(
                Route::has('two-factor.login') ? route('two-factor.login') : route('login'),
            ));
        }

        // Off for this installation, or declined by this user through
        // `ReceivesLoginCodes` (ADR 0037 §4). Neither factor is answerable here,
        // so there is nothing to send them on to.
        if (! Codes::wantedBy($user)) {
            throw new HttpResponseException(redirect()->route('login'));
        }
    }
}
