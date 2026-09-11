<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Http\Controllers;

use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use NyonCode\WireModuleAuth\Actions\SendOneTimeCode;
use NyonCode\WireModuleAuth\Contracts\OneTimeCodes;
use NyonCode\WireModuleAuth\Enums\CodePurpose;
use NyonCode\WireModuleAuth\Support\Codes;

/**
 * Confirming an address by typing a code instead of following a link.
 *
 * Beside Fortify's signed link rather than in place of it: the link in the mail
 * keeps working, and this exists for the case it does not — a mail client that
 * rewrites URLs, a code read off a phone into a browser on a desk, a link
 * that expired while somebody was away from the machine they opened it on.
 *
 * What "verified" means stays Laravel's: `markEmailAsVerified()` and the
 * `Verified` event, which is exactly what Fortify's own controller does with a
 * valid signature. Anything listening — a welcome mail, an audit entry, a role
 * that is only granted once the address is real — hears both ways in.
 */
class EmailVerificationCodeController extends Controller
{
    public function __construct(
        private readonly OneTimeCodes $codes,
        private readonly SendOneTimeCode $send,
    ) {}

    /** The boxes, for the person already signed in but not yet confirmed. */
    public function create(Request $request): View|RedirectResponse
    {
        if ($this->alreadyVerified($request)) {
            return $this->home();
        }

        $user = $request->user();

        return view('wire-module-auth::code-challenge', [
            'action' => route('wire-auth.verify-email-code'),
            'resend' => route('wire-auth.verify-email-code.send'),
            'back' => route('verification.notice'),
            'address' => $user instanceof MustVerifyEmail ? $user->getEmailForVerification() : null,
        ]);
    }

    /** Mail a code to the address being confirmed. */
    public function send(Request $request): RedirectResponse
    {
        if ($this->alreadyVerified($request)) {
            return $this->home();
        }

        $user = $request->user();

        // The address rides on the code. `identifierFor()` files it under the
        // user *key* on purpose — a code should survive the person editing their
        // address mid-flow — but "survive" must not mean "follow". Without the
        // payload, a code requested for one address and typed after the address
        // was changed marked the *new* one verified, which is a way to have an
        // address you do not control confirmed on your account.
        $sent = $user === null ? false : ($this->send)(
            CodePurpose::VerifyEmail,
            $user,
            Codes::identifierFor($user),
            ['email' => $this->addressOf($user)],
        );

        return redirect()->route('wire-auth.verify-email-code')->with('status', __($sent
            ? 'wire-module-auth::messages.code_sent'
            : 'wire-module-auth::messages.code_sent_recently'));
    }

    /** The digits, and the address they confirm. */
    public function store(Request $request): RedirectResponse
    {
        if ($this->alreadyVerified($request)) {
            return $this->home();
        }

        $request->validate(['code' => ['required', 'string']]);

        $user = $request->user();

        $verified = $user === null ? null : $this->codes->verify(
            CodePurpose::VerifyEmail,
            Codes::identifierFor($user),
            (string) $request->input('code'),
        );

        if ($verified === null || ! $user instanceof MustVerifyEmail) {
            throw ValidationException::withMessages([
                'code' => [__('wire-module-auth::messages.code_invalid')],
            ]);
        }

        // The code proves one address, and only the one it was mailed to. A
        // mismatch is a wrong code rather than an error of its own: the two are
        // worth telling apart only to somebody trying to get an address
        // confirmed that was never sent anything. The row is consumed either
        // way, so this is not a guess that can be repeated.
        if (! $this->confirms($verified->payload('email'), $user)) {
            throw ValidationException::withMessages([
                'code' => [__('wire-module-auth::messages.code_invalid')],
            ]);
        }

        // `markEmailAsVerified()` returns false when the column was already set
        // — a second tab, a link followed on a phone — and firing `Verified` for
        // that would send the welcome mail twice.
        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->intended($this->homePath().'?verified=1');
    }

    /**
     * Whether the code was issued for the address this account carries now.
     *
     * Compared the way an address is compared everywhere else in this package —
     * trimmed and lower-cased — so a code mailed to `Ann@Example.com` still
     * confirms `ann@example.com`. Case is not a change of address.
     *
     * A code with no address on it fails this. Codes are minted and read by the
     * same release and live minutes, so the only rows without one are from
     * before this was carried at all; refusing them costs somebody one resend
     * and is the safe direction to be wrong in.
     */
    protected function confirms(mixed $issuedFor, MustVerifyEmail $user): bool
    {
        return is_string($issuedFor)
            && $issuedFor !== ''
            && Codes::identifierFor($issuedFor) === Codes::identifierFor($this->addressOf($user));
    }

    /** The address being confirmed, as the model reports it. */
    protected function addressOf(mixed $user): string
    {
        return $user instanceof MustVerifyEmail ? (string) $user->getEmailForVerification() : '';
    }

    /** Where Fortify sends somebody who has nothing left to do here. */
    protected function home(): RedirectResponse
    {
        return redirect()->intended($this->homePath());
    }

    /** Fortify's configured landing path, which is also where a signed link ends. */
    private function homePath(): string
    {
        return (string) config('fortify.home', '/');
    }

    /**
     * Whether there is anything left to confirm.
     *
     * A model that does not implement `MustVerifyEmail` has no address to
     * confirm at all, and answering "already verified" for it sends the visitor
     * home rather than to a screen asking for a code that would never be
     * accepted.
     */
    protected function alreadyVerified(Request $request): bool
    {
        $user = $request->user();

        return ! $user instanceof MustVerifyEmail || $user->hasVerifiedEmail();
    }
}
