<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Notifications\Contracts;

use NyonCode\WireCore\Notifications\Notification;
use NyonCode\WireCore\Notifications\NotificationManager;

/**
 * Contract for pluggable notification drivers.
 *
 * Implement this interface to integrate any notification system
 * (Toastr, Notyf, Flasher, SweetAlert, custom Livewire toast, etc.)
 * with the wire-core notification system.
 *
 * Register globally:
 *   NotificationManager::setDefaultDriver(new ToastrDriver());
 *
 * Or per-table:
 *   $table->notificationDriver(new CustomDriver());
 *
 * @see NotificationManager
 */
interface NotificationDriver
{
    /**
     * Send a notification to the user.
     *
     * @param  Notification  $notification  The notification to send
     * @param  mixed  $livewireComponent  The Livewire component instance (for dispatching events, etc.)
     */
    public function send(Notification $notification, mixed $livewireComponent = null): void;
}
