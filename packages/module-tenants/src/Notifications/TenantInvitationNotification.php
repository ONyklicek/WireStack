<?php

declare(strict_types=1);

namespace NyonCode\WireModuleTenants\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use NyonCode\WireModuleTenants\Models\TenantInvitation;

/**
 * The e-mail an invitation sends: a signed link that works until the
 * invitation expires, for the address it was sent to.
 */
final class TenantInvitationNotification extends Notification
{
    public function __construct(
        public readonly TenantInvitation $invitation,
        public readonly string $company,
    ) {}

    /** @return array<int, string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function url(): string
    {
        return URL::temporarySignedRoute(
            'wire-module-tenants.invitations.accept',
            $this->invitation->expires_at,
            ['invitation' => $this->invitation->getKey()],
        );
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('wire-module-tenants::messages.invitation_subject', ['company' => $this->company]))
            ->line(__('wire-module-tenants::messages.invitation_line', ['company' => $this->company]))
            ->action(__('wire-module-tenants::messages.invitation_action'), $this->url())
            ->line(__('wire-module-tenants::messages.invitation_expires', ['date' => $this->invitation->expires_at->toFormattedDateString()]));
    }
}
