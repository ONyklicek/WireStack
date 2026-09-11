<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Support;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireModuleUsers\Resources\UserResource;

/**
 * A verified flag belongs to the address it was granted for.
 *
 * Fortify keeps that promise in `UpdateUserProfileInformation`: change the
 * address and it nulls `email_verified_at` and mails a fresh notification. This
 * module replaces that action with its own form, and the promise did not come
 * with it — a person could open their profile, type an address they do not
 * control, and stay flagged verified on it. Anything behind Laravel's `verified`
 * middleware, or any policy keyed on `hasVerifiedEmail()`, then applied to an
 * address nobody had proven.
 *
 * **Model-level rather than a form hook, and that is a decision.** Three screens
 * write this column — the profile page, the admin edit form, the create form —
 * and `Form::afterSave()` holds exactly one closure, so a rule installed there
 * is a rule the next page to add a hook silently removes. An application's own
 * page, or a console command, would never have been covered at all. The rule is
 * a property of the record, so it lives with the record.
 *
 * It is deliberately narrow. It fires only when the address actually changed,
 * and it **stands aside whenever the caller set the flag itself** — a seeder
 * writing both columns, an admin tool marking an address verified on purpose,
 * a migration backfilling. Explicit intent wins; this only fills the silence.
 */
final class EmailVerification
{
    /** Whether this installation re-verifies an address that changed. */
    public static function resetsOnChange(): bool
    {
        return (bool) config('wire-module-users.reverify_on_email_change', true);
    }

    /** The column the address lives in, as the module's field map names it. */
    public static function column(): string
    {
        return UserResource::field('email');
    }

    /**
     * Clear the flag, unless the caller had an opinion about it.
     *
     * Called on the way *in* to the write, so the address and the flag land in
     * one statement — a record is never briefly a verified stranger, not even
     * inside the transaction.
     */
    public static function forget(Model $user): void
    {
        if (! self::applies($user)) {
            return;
        }

        $user->setAttribute('email_verified_at', null);
    }

    /**
     * Whether this save is the one that invalidates the flag.
     *
     * Both halves matter. `isDirty($email)` keeps every other save — a name, an
     * avatar, a password — from touching the column. `! isDirty('email_verified_at')`
     * is the stand-aside: a caller writing both columns in one save has said
     * what it wants, and overruling it here would make "mark this address
     * verified" impossible to express.
     */
    public static function applies(Model $user): bool
    {
        if (! $user instanceof MustVerifyEmail || ! self::resetsOnChange()) {
            return false;
        }

        return $user->isDirty(self::column())
            && ! $user->isDirty('email_verified_at')
            && $user->getAttribute('email_verified_at') !== null;
    }

    /**
     * Ask for the new address to be proven, where anything can ask.
     *
     * No `method_exists` guard, and that is deliberate rather than an oversight:
     * `sendEmailVerificationNotification()` is one of the four methods
     * `MustVerifyEmail` declares, so the `instanceof` above already guarantees
     * it. A check for it would be a branch no test could ever enter — which is
     * how it was found.
     *
     * The flag is cleared before this runs, so the security half of the fix
     * never depends on the mail going out.
     */
    public static function requestProof(Model $user): void
    {
        if (! $user instanceof MustVerifyEmail || $user->hasVerifiedEmail()) {
            return;
        }

        $user->sendEmailVerificationNotification();
    }
}
