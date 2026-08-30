<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Notifications\Drivers;

use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireCore\Notifications\Notification;

/**
 * Several drivers, one notification.
 *
 * The case this exists for is the ordinary one: show a toast **and** keep it in
 * the bell, because a user who was looking at another tab should still find out
 * their import finished. Neither driver has to know about the other, which is
 * why this is composition rather than a flag on each of them —
 * {@see CurrentComponentDriver} wraps for the same reason.
 *
 *   NotificationManager::setDefaultDriver(new StackDriver(
 *       new CurrentComponentDriver(new SessionDriver),
 *       new DatabaseDriver,
 *   ));
 *
 * One driver throwing does not silence the rest: a notification is a courtesy,
 * and a database that is down should not also cost the user their toast. The
 * first failure is re-thrown once every driver has had its turn, so the problem
 * still surfaces rather than being swallowed.
 */
final class StackDriver implements NotificationDriver
{
    /** @var array<int, NotificationDriver> */
    private array $drivers;

    public function __construct(NotificationDriver ...$drivers)
    {
        $this->drivers = $drivers;
    }

    public function send(Notification $notification, mixed $livewireComponent = null): void
    {
        $failure = null;

        foreach ($this->drivers as $driver) {
            try {
                $driver->send($notification, $livewireComponent);
            } catch (\Throwable $e) {
                $failure ??= $e;
            }
        }

        if ($failure !== null) {
            throw $failure;
        }
    }

    /** @return array<int, NotificationDriver> */
    public function drivers(): array
    {
        return $this->drivers;
    }
}
