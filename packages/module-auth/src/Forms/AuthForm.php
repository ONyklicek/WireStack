<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Forms;

use NyonCode\WireCore\Foundation\Enums\Hook;

/**
 * The forms on the way in, named once.
 *
 * A vocabulary rather than a set of strings, for the reason
 * {@see Hook} is one: an application adjusts
 * these from its own provider, and a typo in a string key registers a callback
 * against a form nobody renders — nothing to grep, nothing to fail.
 *
 * Ten cases for nine screens. The two-factor challenge is one `<form>` posting
 * to one URL with a different field depending on what the person has to hand, so
 * its code and its recovery code are two schemas rather than one with a
 * conditional in it — and an application replacing the code input has no reason
 * to inherit whatever it does to the recovery one.
 *
 * The screen with no case is `verify-email`: it has a button and no fields, and
 * a form object for it would be an empty schema with a name.
 */
enum AuthForm: string
{
    /** Sign in — the credentials Fortify's login route reads. */
    case Login = 'login';

    /** Create an account, where `Features::registration()` is on. */
    case Register = 'register';

    /** Ask for a reset link. */
    case ForgotPassword = 'forgot-password';

    /** Set a new password, from the link in the mail. */
    case ResetPassword = 'reset-password';

    /** Confirm the password already signed in with, before something sensitive. */
    case ConfirmPassword = 'confirm-password';

    /** The code from an authenticator app. */
    case TwoFactorCode = 'two-factor-code';

    /** One of the recovery codes saved when two-factor was set up. */
    case TwoFactorRecovery = 'two-factor-recovery';

    /** The address a sign-in code is mailed to, where that flow is on. */
    case LoginCode = 'login-code';

    /**
     * The digits themselves, on whichever screen asked for them.
     *
     * One case for three screens on purpose: signing in without a password, the
     * mailed second factor and confirming an address all render the same field,
     * and an application that makes the code eight digits long means all three.
     */
    case Code = 'code';

    /** A new password, from a mailed code rather than a link. */
    case ResetPasswordCode = 'reset-password-code';
}
