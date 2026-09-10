<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Enums;

/**
 * What a one-time code was issued for.
 *
 * **Part of the lookup, never a label on the row.** Four flows share one table,
 * and without this they would share one code: a six-digit number mailed to
 * confirm an address would open the sign-in challenge, which is the whole of the
 * second factor given away by a mail nobody thinks of as a credential
 * (ADR 0037 §1).
 *
 * The value is what lands in the database and in the translation key for the
 * mail, so it is stable: renaming a case rewrites rows that are still live.
 */
enum CodePurpose: string
{
    /** Signing in with no password at all. */
    case Login = 'login';

    /** The second factor, for someone whose password was already right. */
    case SecondFactor = 'second-factor';

    /** Confirming that the address on the account can be read. */
    case VerifyEmail = 'verify-email';

    /** Standing in for the token in a password-reset link. */
    case ResetPassword = 'reset-password';

    /**
     * The subject and body the mail is written from.
     *
     * One key per purpose rather than one mail with a variable in it: "here is
     * your sign-in code" and "confirm this address" are different sentences in
     * every language, and a shared template that tries to be both is a mail that
     * reads like neither.
     */
    public function translationKey(): string
    {
        return 'wire-module-auth::messages.code_mail.'.str_replace('-', '_', $this->value);
    }
}
