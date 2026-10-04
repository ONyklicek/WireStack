<?php

declare(strict_types=1);

namespace NyonCode\WireModuleTenants\Actions;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireModuleTenants\Models\TenantInvitation;
use NyonCode\WireModuleTenants\Support\Membership;

/**
 * Join the company an invitation is for.
 *
 * Only while it is open, and only for the address it was sent to: a link
 * forwarded to somebody else is a link somebody else cannot use. Someone
 * already a member keeps the role they have.
 */
final class AcceptInvitation
{
    /** @return Model|null The company joined, or null when this person may not use this invitation. */
    public function __invoke(TenantInvitation $invitation, Model $user): ?Model
    {
        $email = mb_strtolower((string) $user->getAttribute('email'));

        if (! $invitation->isOpen() || $email !== $invitation->email) {
            return null;
        }

        $tenant = $invitation->tenant()->first();

        if ($tenant === null) {
            return null;
        }

        if (Membership::roleOf($tenant, $user) === null) {
            Membership::add($tenant, $user, $invitation->role);
        }

        $invitation->forceFill(['accepted_at' => now()])->save();

        return $tenant;
    }
}
