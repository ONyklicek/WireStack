<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\LoginRateLimiter;
use NyonCode\WireModuleAuth\Enums\CodePurpose;
use NyonCode\WireModuleAuth\Support\Codes;

/**
 * The second factor, for someone with no authenticator app.
 *
 * **A subclass rather than a pipeline of our own**, and that is the decision
 * ADR 0037 §2 turns on. Fortify assembles its login pipeline from the container:
 * the entry is the `RedirectsIfTwoFactorAuthenticatable` contract, so binding
 * this class to it puts a mailed code into the flow without copying a single
 * line of the list Fortify's controller builds — the throttle, the username
 * canonicalisation and the order they run in stay Fortify's, and keep whatever
 * Fortify changes them to.
 *
 * What is inherited is everything that matters: the credential check, the
 * `Failed` event, the rate-limiter increment on a wrong password, the
 * `login.id` session key, and the branch that hands a user with a confirmed TOTP
 * secret to Fortify's own challenge. **An authenticator app always wins** — it
 * is a stronger factor than an inbox, and the person set it up on purpose.
 *
 * This class adds exactly one thing: where the parent would have carried on into
 * `AttemptToAuthenticate`, a user this installation mails codes to is sent to a
 * challenge instead.
 */
class RedirectIfCodeRequired extends RedirectIfTwoFactorAuthenticatable
{
    /**
     * The user whose credentials were just checked.
     *
     * Memoised in {@see validateCredentials()} because the parent does not hand
     * the user to the pipe's continuation, and asking for it a second time would
     * fire a second `Failed` event and re-hash the password.
     */
    protected mixed $checkedUser = null;

    public function __construct(
        StatefulGuard $guard,
        LoginRateLimiter $limiter,
        private readonly SendOneTimeCode $send,
    ) {
        parent::__construct($guard, $limiter);
    }

    /**
     * @param  Request  $request
     * @param  callable  $next
     */
    public function handle($request, $next): mixed
    {
        // The parent runs first and unchanged: it validates, and it returns its
        // own two-factor response for a user with an authenticator app. Only
        // where it decides there is no second factor does this get a say — which
        // is the branch it expresses by calling `$next`.
        return parent::handle($request, function ($request) use ($next) {
            if (Codes::wantedBy($this->checkedUser)) {
                return $this->codeChallengeResponse($request, $this->checkedUser);
            }

            return $next($request);
        });
    }

    /**
     * @param  Request  $request
     */
    protected function validateCredentials($request): mixed
    {
        return $this->checkedUser = parent::validateCredentials($request);
    }

    /**
     * Put the sign-in on hold, mail the code, and go to the screen for it.
     *
     * The session keys are Fortify's own — `login.id` and `login.remember` — so
     * the screen that follows reads the pending sign-in through Fortify's
     * `TwoFactorLoginRequest`, exactly as Fortify's challenge does. The password
     * was checked here; a flow that had *not* checked one would have no business
     * writing that key, which is why the passwordless screens use their own.
     *
     * @param  Request  $request
     */
    protected function codeChallengeResponse($request, Authenticatable $user): mixed
    {
        $request->session()->put([
            'login.id' => $user->getAuthIdentifier(),
            'login.remember' => $request->boolean('remember'),
        ]);

        ($this->send)(CodePurpose::SecondFactor, $user, Codes::identifierFor($user));

        return $request->wantsJson()
            ? response()->json(['two_factor' => true])
            : redirect()->route('wire-auth.second-factor');
    }
}
