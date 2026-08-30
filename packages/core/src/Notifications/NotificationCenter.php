<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Notifications;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Notifications\Contracts\ResolvesNotifiable;

/**
 * The read side of persisted notifications: what the bell shows.
 *
 * A plain service rather than a Livewire component, because the questions it
 * answers — how many unread, the latest few, mark this one read — are the same
 * whether a bell, a console command or a JSON endpoint is asking. The component
 * that renders a dropdown is the application's; this is what it reads.
 *
 * Every method is scoped to the resolved recipient and returns empty when there
 * is none. That is the same fail-quiet choice {@see Drivers\DatabaseDriver}
 * makes on the write side, and for the same reason: a notification belongs to
 * somebody, and "nobody" is a real state on a queue worker or before login —
 * not an error, and certainly not a licence to show another user's rows.
 */
class NotificationCenter
{
    public function __construct(
        private readonly ResolvesNotifiable $notifiable = new AuthenticatedNotifiable,
    ) {}

    /**
     * The recipient's newest notifications, unread first.
     *
     * Unread first rather than strictly newest first: the bell exists to show
     * what has not been seen, and a burst of reads would otherwise push a
     * three-day-old unread item off the list.
     *
     * @return Collection<int, DatabaseNotification>
     */
    public function latest(int $limit = 10): Collection
    {
        $recipient = $this->notifiable->resolve();

        if (! $recipient instanceof Model) {
            return new Collection;
        }

        return DatabaseNotification::forNotifiable($recipient)
            // Unread first rather than strictly newest: the bell exists to show
            // what has not been seen, and a burst of reads would otherwise push
            // a three-day-old unread item off the list.
            ->reorder()
            ->orderByRaw('read_at is not null')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, DatabaseNotification>
     */
    public function unread(int $limit = 10): Collection
    {
        $recipient = $this->notifiable->resolve();

        if (! $recipient instanceof Model) {
            return new Collection;
        }

        return DatabaseNotification::unreadFor($recipient)->limit($limit)->get();
    }

    /** The number on the bell. */
    public function unreadCount(): int
    {
        $recipient = $this->notifiable->resolve();

        if (! $recipient instanceof Model) {
            return 0;
        }

        return DatabaseNotification::unreadFor($recipient)->count();
    }

    /**
     * Mark one notification read.
     *
     * Scoped to the recipient rather than looked up by id alone: an id arriving
     * from a Livewire action is user input, and reading it unscoped would let
     * one user mark another's notification read.
     */
    public function markAsRead(string $id): bool
    {
        $recipient = $this->notifiable->resolve();

        if (! $recipient instanceof Model) {
            return false;
        }

        $notification = DatabaseNotification::forNotifiable($recipient)->find($id);

        if ($notification === null) {
            return false;
        }

        $notification->markAsRead();

        return true;
    }

    /** @return int How many were still unread. */
    public function markAllAsRead(): int
    {
        $recipient = $this->notifiable->resolve();

        if (! $recipient instanceof Model) {
            return 0;
        }

        return DatabaseNotification::unreadFor($recipient)
            ->reorder()
            ->update(['read_at' => now()]);
    }
}
