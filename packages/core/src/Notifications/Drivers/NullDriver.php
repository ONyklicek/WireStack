<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Notifications\Drivers;

use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireCore\Notifications\Notification;

/**
 * Null notification driver — silently discards all notifications.
 *
 * Useful for testing or when the Notifications module is intentionally disabled.
 */
class NullDriver implements NotificationDriver
{
    public function send(Notification $notification, mixed $livewireComponent = null): void
    {
        // Intentionally empty — discard notification
    }
}
