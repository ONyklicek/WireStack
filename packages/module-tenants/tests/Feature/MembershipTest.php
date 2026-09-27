<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use NyonCode\WireModuleTenants\Actions\AcceptInvitation;
use NyonCode\WireModuleTenants\Actions\ChangeMemberRole;
use NyonCode\WireModuleTenants\Actions\DeleteTenant;
use NyonCode\WireModuleTenants\Actions\InviteMember;
use NyonCode\WireModuleTenants\Actions\RemoveMember;
use NyonCode\WireModuleTenants\Enums\MemberRole;
use NyonCode\WireModuleTenants\Exceptions\TenantMembershipException;
use NyonCode\WireModuleTenants\Models\TenantInvitation;
use NyonCode\WireModuleTenants\Notifications\TenantInvitationNotification;
use NyonCode\WireModuleTenants\Support\Membership;

/*
 * Who may change a company's members, and the one rule that holds whoever
 * asks: a company keeps an owner.
 */

beforeEach(function () {
    $this->owner = $this->person('Olga');
    $this->member = $this->person('Max');
    $this->tenant = $this->company('acme', $this->owner);
    $this->tenant->members()->attach($this->member->getKey(), ['role' => 'member']);
});

it('lets only an owner change members, roles and the company', function (Closure $change) {
    $change($this);
})->throws(TenantMembershipException::class, 'Only an owner')->with([
    'invite' => [fn ($t) => (new InviteMember)($t->tenant, 'x@example.com', MemberRole::Member, $t->member)],
    'remove' => [fn ($t) => (new RemoveMember)($t->tenant, $t->owner, $t->member)],
    'role' => [fn ($t) => (new ChangeMemberRole)($t->tenant, $t->member, MemberRole::Owner, $t->member)],
    'delete' => [fn ($t) => (new DeleteTenant)($t->tenant, 'Acme', $t->member)],
]);

it('never lets a company lose its last owner', function (Closure $change) {
    $change($this);
})->throws(TenantMembershipException::class, 'at least one owner')->with([
    'removed' => [fn ($t) => (new RemoveMember)($t->tenant, $t->owner, $t->owner)],
    'demoted' => [fn ($t) => (new ChangeMemberRole)($t->tenant, $t->owner, MemberRole::Member, $t->owner)],
]);

it('changes roles and removes members for an owner', function () {
    (new ChangeMemberRole)($this->tenant, $this->member, MemberRole::Owner, $this->owner);

    expect(Membership::owners($this->tenant))->toBe(2);

    // Now there are two owners, the first may step down.
    (new ChangeMemberRole)($this->tenant, $this->owner, MemberRole::Member, $this->member);
    (new RemoveMember)($this->tenant, $this->owner, $this->member);

    expect(Membership::memberIds($this->tenant))->toBe([$this->member->getKey()])
        ->and(Membership::roleOf($this->tenant, null))->toBeNull();
});

it('deletes a company softly, only once its name is typed', function () {
    expect((new DeleteTenant)($this->tenant, 'acme', $this->owner))->toBeFalse()
        ->and($this->tenant->fresh()->trashed())->toBeFalse()
        ->and((new DeleteTenant)($this->tenant, ' Acme ', $this->owner))->toBeTrue()
        ->and($this->tenant->fresh()->trashed())->toBeTrue();
});

it('invites by e-mail with a signed link that expires', function () {
    Notification::fake();

    $invitation = (new InviteMember)($this->tenant, ' New@Example.com ', MemberRole::Member, $this->owner);

    expect($invitation->email)->toBe('new@example.com')
        ->and($invitation->expires_at->isFuture())->toBeTrue();

    Notification::assertSentOnDemand(TenantInvitationNotification::class, function (TenantInvitationNotification $notification, array $channels, object $notifiable) {
        $mail = $notification->toMail($notifiable);

        return $notifiable->routes['mail'] === 'new@example.com'
            && str_contains($notification->url(), 'signature=')
            && $mail->actionUrl === $notification->url()
            && $notification->via($notifiable) === ['mail'];
    });
});

it('lets the invited address join once, in the invited role', function () {
    $nina = $this->person('Nina');
    $invitation = TenantInvitation::query()->create([
        'tenant_id' => $this->tenant->getKey(), 'email' => 'nina@example.com', 'role' => MemberRole::Owner, 'expires_at' => now()->addDay(),
    ]);

    expect((new AcceptInvitation)($invitation, $this->member))->toBeNull()
        ->and((new AcceptInvitation)($invitation, $nina)?->getKey())->toBe($this->tenant->getKey())
        ->and(Membership::roleOf($this->tenant, $nina))->toBe(MemberRole::Owner)
        ->and((new AcceptInvitation)($invitation->fresh(), $nina))->toBeNull();
});

it('refuses an expired invitation, and keeps an existing member role', function () {
    $expired = TenantInvitation::query()->create([
        'tenant_id' => $this->tenant->getKey(), 'email' => 'max@example.com', 'role' => MemberRole::Owner, 'expires_at' => now()->subDay(),
    ]);

    expect((new AcceptInvitation)($expired, $this->member))->toBeNull();

    $again = TenantInvitation::query()->create([
        'tenant_id' => $this->tenant->getKey(), 'email' => 'max@example.com', 'role' => MemberRole::Owner, 'expires_at' => now()->addDay(),
    ]);

    (new AcceptInvitation)($again, $this->member);

    expect(Membership::roleOf($this->tenant, $this->member))->toBe(MemberRole::Member);
});

it('refuses an invitation to a company that is gone', function () {
    $invitation = TenantInvitation::query()->create([
        'tenant_id' => 999, 'email' => 'max@example.com', 'role' => MemberRole::Member, 'expires_at' => now()->addDay(),
    ]);

    expect((new AcceptInvitation)($invitation, $this->member))->toBeNull();
});

it('accepts through the signed link, and refuses an edited one', function () {
    $nina = $this->person('Nina');
    $invitation = TenantInvitation::query()->create([
        'tenant_id' => $this->tenant->getKey(), 'email' => 'nina@example.com', 'role' => MemberRole::Member, 'expires_at' => now()->addDay(),
    ]);
    $url = (new TenantInvitationNotification($invitation, 'Acme'))->url();

    $this->actingAs($this->member)->get($url)->assertForbidden();
    $this->actingAs($nina)->get($url.'x')->assertForbidden();
    $this->actingAs($nina)->get($url)->assertRedirect(url('app/acme'));
});
