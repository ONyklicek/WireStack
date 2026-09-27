<?php

declare(strict_types=1);

namespace NyonCode\WireModuleTenants\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use NyonCode\WireModuleTenants\Enums\MemberRole;
use NyonCode\WireModuleTenants\Exceptions\TenantMembershipException;
use NyonCode\WireModuleTenants\Models\TenantInvitation;
use NyonCode\WireModuleTenants\Notifications\TenantInvitationNotification;
use NyonCode\WireModuleTenants\Support\Membership;

/** Invite an e-mail address into a company. Only an owner may. */
final class InviteMember
{
    public function __invoke(Model $tenant, string $email, MemberRole $role, Model $inviter): TenantInvitation
    {
        if (! Membership::isOwner($tenant, $inviter)) {
            throw TenantMembershipException::notAllowed();
        }

        $invitation = TenantInvitation::query()->create([
            'tenant_id' => $tenant->getKey(),
            'email' => mb_strtolower(trim($email)),
            'role' => $role,
            'invited_by' => $inviter->getKey(),
            'expires_at' => now()->addDays((int) config('wire-module-tenants.invitations.expire_days', 7)),
        ]);

        Notification::route('mail', $invitation->email)
            ->notify(new TenantInvitationNotification($invitation, (string) $tenant->getAttribute('name')));

        return $invitation;
    }
}
