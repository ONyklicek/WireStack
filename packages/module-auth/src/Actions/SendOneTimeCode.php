<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use NyonCode\WireModuleAuth\Contracts\OneTimeCodes;
use NyonCode\WireModuleAuth\Enums\CodePurpose;
use NyonCode\WireModuleAuth\Notifications\OneTimeCodeNotification;

/**
 * Mint a code and put it in the post.
 *
 * The one place the two halves meet, so no flow can issue without sending or
 * send without issuing — the failure that produces a screen asking for a code
 * nobody was given.
 *
 * **Returns whether a code went out, and every "no" looks the same from the
 * outside.** No such user, a resend asked for eight seconds after the last one,
 * a model that cannot be notified: the screen says "if that address is one of
 * ours, the code is on its way" either way, because a reply that distinguishes
 * them is an account enumerator — the same rule Fortify's own forgot-password
 * response follows. The boolean is for the *resend* button, which does have
 * something honest to say: wait a moment.
 */
final class SendOneTimeCode
{
    public function __construct(private readonly OneTimeCodes $codes) {}

    /**
     * @param  string  $identifier  What the code is bound to — the address for a
     *                              flow that starts from one, the user's key for
     *                              a flow that already knows who is signing in.
     * @param  array<string, mixed>  $payload  Carried on the row to the screen
     *                                         that verifies it.
     */
    public function __invoke(CodePurpose $purpose, ?Authenticatable $user, string $identifier, array $payload = []): bool
    {
        // `method_exists` rather than a check for the trait: what matters is
        // that the model can be told something. A user model written from
        // scratch without `Notifiable` is a configuration mistake rather than a
        // rare one, and `notify()` on it is a fatal error on the sign-in screen
        // — a code that is never sent is the better failure, and the screens all
        // say the same thing to the person in front of them either way.
        if ($user === null || ! method_exists($user, 'notify')) {
            return false;
        }

        if ($this->codes->recentlyIssued($purpose, $identifier)) {
            return false;
        }

        $code = $this->codes->issue($purpose, $identifier, $payload);

        // `notify()` rather than the Notification facade, so an application that
        // routes mail per user — a locale, an alternate address, a channel of
        // its own — is asked rather than talked over.
        $user->notify(new OneTimeCodeNotification($code));

        return true;
    }
}
