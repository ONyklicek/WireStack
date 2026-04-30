<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Notifications\Drivers;

use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireCore\Notifications\Notification;

/**
 * Default notification driver — backwards-compatible.
 *
 * Uses session()->flash() + Livewire event dispatch,
 * exactly like the original hard-coded implementation.
 */
class SessionDriver implements NotificationDriver
{
    public function __construct(
        public string $sessionKey = 'table-notification',
        public string $eventName = 'table-notification',
    ) {}

    public function send(Notification $notification, mixed $livewireComponent = null): void
    {
        session()->flash($this->sessionKey, [
            'type' => $notification->type,
            'message' => $notification->message,
        ]);

        if ($livewireComponent && method_exists($livewireComponent, 'dispatch')) {
            $livewireComponent->dispatch(
                $this->eventName,
                type: $notification->type,
                message: $notification->message,
            );
        }
    }
}
