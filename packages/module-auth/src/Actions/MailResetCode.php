<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Actions;

use Illuminate\Contracts\Auth\CanResetPassword;
use NyonCode\WireModuleAuth\Contracts\OneTimeCodes;
use NyonCode\WireModuleAuth\Enums\CodePurpose;
use NyonCode\WireModuleAuth\Http\Controllers\PasswordResetCodeController;
use NyonCode\WireModuleAuth\Notifications\OneTimeCodeNotification;
use NyonCode\WireModuleAuth\Support\Codes;

/**
 * Turn the reset link into a reset code, at the one point the token exists.
 *
 * Registered as `ResetPassword::toMailUsing()`, which is Laravel's own seam and
 * the only place the broker's token is handed to anybody. Nothing about the
 * reset itself changes: the broker still mints the token, still expires it,
 * still refuses it twice. What changes is what the mail says — six digits — and
 * where the person types them ({@see PasswordResetCodeController}).
 *
 * ## The token is not kept
 *
 * It used to be: the code's payload carried `['token' => $token]`, so the
 * screen could swap one for the other. But Laravel files that token **hashed**
 * (`DatabaseTokenRepository::create()` runs it through the hasher, and marks the
 * argument `#[\SensitiveParameter]`), and a payload column carrying the readable
 * original undoes that for as long as the row lives. Anybody who could read one
 * table — a replica, a dump, a query log — could post the address and the token
 * straight to Fortify and never need the six digits at all, which is every
 * guard on this flow bypassed at once.
 *
 * So the token handed here is used to build the mail and then dropped. The code
 * proves possession of the mailbox; the token the reset actually runs on is
 * minted fresh at that moment, inside the one request that redeems it
 * ({@see PasswordResetCodeController::store()}).
 *
 * The mail is built by {@see OneTimeCodeNotification::toMail()} rather than here,
 * so an application that replaced the code mail replaced this one too, and the
 * four flows keep sounding like each other.
 *
 * The notifiable is typed as `CanResetPassword` because that is what Laravel's
 * broker hands to `sendPasswordResetNotification()`, and the address has to come
 * from the same method the token was filed under — reading a column called
 * `email` instead would file the code where the form will not look for it.
 */
final class MailResetCode
{
    public function __construct(private readonly OneTimeCodes $codes) {}

    /**
     * @param  string  $token  The broker's token. Deliberately unused — see the
     *                         class docblock. Kept in the signature because
     *                         `ResetPassword::toMailUsing()` passes it, and a
     *                         closure that refuses the argument would fatal.
     */
    public function __invoke(CanResetPassword $notifiable, #[\SensitiveParameter] string $token): mixed
    {
        $code = $this->codes->issue(
            CodePurpose::ResetPassword,
            Codes::identifierFor((string) $notifiable->getEmailForPasswordReset()),
        );

        return (new OneTimeCodeNotification($code))->toMail($notifiable);
    }
}
