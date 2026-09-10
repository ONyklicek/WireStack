<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Http\Controllers;

use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
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
     */
    protected function ensureChallenged(TwoFactorLoginRequest $request): void
    {
        if (! $request->hasChallengedUser()) {
            throw new HttpResponseException(redirect()->route('login'));
        }
    }
}
