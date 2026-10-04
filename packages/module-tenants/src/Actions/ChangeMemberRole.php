<?php

declare(strict_types=1);

namespace NyonCode\WireModuleTenants\Actions;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireModuleTenants\Enums\MemberRole;
use NyonCode\WireModuleTenants\Exceptions\TenantMembershipException;
use NyonCode\WireModuleTenants\Support\Membership;

/** Make a member an owner, or an owner a member — never the last owner. */
final class ChangeMemberRole
{
    public function __invoke(Model $tenant, Model $member, MemberRole $role, Model $actor): void
    {
        if (! Membership::isOwner($tenant, $actor)) {
            throw TenantMembershipException::notAllowed();
        }

        if ($role === MemberRole::Member && Membership::isLastOwner($tenant, $member)) {
            throw TenantMembershipException::lastOwner();
        }

        Membership::changeRole($tenant, $member, $role);
    }
}
