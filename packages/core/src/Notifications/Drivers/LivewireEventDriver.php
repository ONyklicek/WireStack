<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Notifications\Drivers;

use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireCore\Notifications\Notification;

/**
 * Livewire event-based notification driver.
 *
 * Dispatches a Livewire browser event with the full notification payload.
 * Your frontend toast component listens for this event and renders accordingly.
 *
 * Usage:
 *   NotificationManager::setDefaultDriver(
 *       new LivewireEventDriver('toast-notification')
 *   );
 *
 * Alpine.js listener example:
 *   <div x-data @toast-notification.window="showToast($event.detail)">
 */
class LivewireEventDriver implements NotificationDriver
{
    public function __construct(
        public string $eventName = 'table-notification',
    ) {}

    public function send(Notification $notification, mixed $livewireComponent = null): void
    {
        if (! $livewireComponent || ! method_exists($livewireComponent, 'dispatch')) {
            // Fallback: store in session so it's not lost
            session()->flash('table-notification', $notification->toArray());

            return;
        }

        $livewireComponent->dispatch($this->eventName, ...$notification->toArray());
    }
}
