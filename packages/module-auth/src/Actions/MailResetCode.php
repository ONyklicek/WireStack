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
 * still refuses it twice. What changes is what the mail says — six digits, whose
 * row carries the token — and where the person types them
 * ({@see PasswordResetCodeController}).
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
     * @param  string  $token  The broker's token, the only time it is visible.
     */
    public function __invoke(CanResetPassword $notifiable, string $token): mixed
    {
        $code = $this->codes->issue(
            CodePurpose::ResetPassword,
            Codes::identifierFor((string) $notifiable->getEmailForPasswordReset()),
            ['token' => $token],
        );

        return (new OneTimeCodeNotification($code))->toMail($notifiable);
    }
}
