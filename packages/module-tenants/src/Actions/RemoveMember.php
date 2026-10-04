<?php

declare(strict_types=1);

namespace NyonCode\WireModuleTenants\Actions;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireModuleTenants\Exceptions\TenantMembershipException;
use NyonCode\WireModuleTenants\Support\Membership;

/** Take a person out of a company. An owner may; the last owner cannot go. */
final class RemoveMember
{
    public function __invoke(Model $tenant, Model $member, Model $actor): void
    {
        if (! Membership::isOwner($tenant, $actor)) {
            throw TenantMembershipException::notAllowed();
        }

        if (Membership::isLastOwner($tenant, $member)) {
            throw TenantMembershipException::lastOwner();
        }

        Membership::remove($tenant, $member);
    }
}
