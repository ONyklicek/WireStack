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
     * The recipient's newest notifications.
     *
     * Strictly newest first, and it used to sort unread first — for a good
     * reason that has since been solved better. The bell showed one short list,
     * so a burst of reads could push a three-day-old unread item off the end of
     * it; putting unread first kept it visible. The panel now has an **Unread
     * tab**, which answers that question directly and completely, while the
     * ordering only ever answered it for the first ten.
     *
     * What the ordering cost was a list nobody could read as a timeline: with
     * unread floated to the top, Monday sits above Thursday and day headings
     * repeat. Newest-first is what "all" means, and {@see unread()} is what
     * "unread" means.
     *
     * @return Collection<int, DatabaseNotification>
     */
    public function latest(int $limit = 10): Collection
    {
        $recipient = $this->notifiable->resolve();

        if (! $recipient instanceof Model) {
            return new Collection;
        }

        // forNotifiable() already orders by created_at then id — both, in that
        // order, because a bulk job lands five in the same second and the ULID
        // is what breaks the tie.
        return DatabaseNotification::forNotifiable($recipient)->limit($limit)->get();
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

    /**
     * Whether this recipient has any notifications at all, read or not.
     *
     * What the bell needs to tell "nothing has ever happened" from "you have
     * seen everything" — two states that look identical on a bell with no badge,
     * and only one of which is worth showing a mark for. `exists()` rather than a
     * count, because the answer is a boolean and counting rows to discover
     * whether there is one is work nobody asked for.
     */
    public function hasAny(): bool
    {
        $recipient = $this->notifiable->resolve();

        if (! $recipient instanceof Model) {
            return false;
        }

        return DatabaseNotification::forNotifiable($recipient)->reorder()->exists();
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
     * One notification of this recipient's, by id.
     *
     * The single scoped lookup every verb below goes through, and that is the
     * whole reason it exists: an id arriving from a Livewire action is user
     * input, and one unscoped `find()` anywhere in here would let one user read,
     * mark or delete another's notification. One owner, one place to get it
     * wrong, one place it is right.
     */
    public function find(string $id): ?DatabaseNotification
    {
        $recipient = $this->notifiable->resolve();

        if (! $recipient instanceof Model) {
            return null;
        }

        return DatabaseNotification::forNotifiable($recipient)->find($id);
    }

    /** Mark one notification read. Scoped — see {@see find()}. */
    public function markAsRead(string $id): bool
    {
        $notification = $this->find($id);

        $notification?->markAsRead();

        return $notification !== null;
    }

    /**
     * Mark one notification unread again.
     *
     * The verb every inbox has, and the reason it has it: "I will deal with
     * this later" is a decision a person makes after opening something, and
     * without this the only way back is to remember it existed.
     */
    public function markAsUnread(string $id): bool
    {
        $notification = $this->find($id);

        $notification?->markAsUnread();

        return $notification !== null;
    }

    /**
     * Delete one notification.
     *
     * Delete rather than archive, and that is a decision rather than an
     * omission: the table is deliberately Laravel's own `notifications` shape,
     * so an application can point this at the table it already has. An
     * `archived_at` column would end that, and it would end it for every
     * application in order to give one of them a third state between "unread"
     * and "gone". An application that wants an archive has a table of its own.
     */
    public function delete(string $id): bool
    {
        $notification = $this->find($id);

        return $notification !== null && (bool) $notification->delete();
    }

    /**
     * Clear everything already read. @return int How many went.
     *
     * The bulk verb that is safe to offer: what has been read is what the user
     * has already seen, so this cannot lose them something they have not looked
     * at — which "delete all" can, and is why there is no button for that.
     */
    public function deleteRead(): int
    {
        $recipient = $this->notifiable->resolve();

        if (! $recipient instanceof Model) {
            return 0;
        }

        return DatabaseNotification::forNotifiable($recipient)
            ->reorder()
            ->whereNotNull('read_at')
            ->delete();
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
