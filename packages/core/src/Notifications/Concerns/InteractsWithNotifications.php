<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Notifications\Concerns;

use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireCore\Notifications\Notification;
use NyonCode\WireCore\Notifications\NotificationManager;

/**
 * Trait for Livewire components that send notifications.
 *
 * Provides convenience methods for sending notifications through
 * the pluggable notification driver system.
 *
 * Usage in a Livewire component:
 *   use InteractsWithNotifications;
 *
 *   public function approve(): void
 *   {
 *       // ...
 *       $this->notify(Notification::success('Schváleno'));
 *   }
 */
trait InteractsWithNotifications
{
    protected ?NotificationDriver $notificationDriver = null;

    /**
     * Send a notification through the resolved driver.
     */
    public function notify(Notification $notification): void
    {
        NotificationManager::send($notification, $this->notificationDriver, $this);
    }

    /**
     * Send a success notification.
     */
    public function notifySuccess(string $message): void
    {
        $this->notify(Notification::success($message));
    }

    /**
     * Send an error notification.
     */
    public function notifyError(string $message): void
    {
        $this->notify(Notification::error($message));
    }

    /**
     * Send a warning notification.
     */
    public function notifyWarning(string $message): void
    {
        $this->notify(Notification::warning($message));
    }

    /**
     * Send an info notification.
     */
    public function notifyInfo(string $message): void
    {
        $this->notify(Notification::info($message));
    }

    /**
     * Set the notification driver for this component.
     */
    public function setNotificationDriver(NotificationDriver $driver): void
    {
        $this->notificationDriver = $driver;
    }
}
