<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Notifications\Drivers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use NyonCode\WireCore\Notifications\AuthenticatedNotifiable;
use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireCore\Notifications\Contracts\ResolvesNotifiable;
use NyonCode\WireCore\Notifications\DatabaseNotification;
use NyonCode\WireCore\Notifications\Notification;

/**
 * Writes the notification down instead of handing it to the page.
 *
 * The transient drivers deliver to the request being rendered, which is the
 * right answer for "saved" and the wrong one for a queued export that finishes
 * twenty minutes later: by then there is no component to dispatch to and no
 * session to flash into. This one survives that boundary.
 *
 * Deliberately not a replacement for a toast — see {@see StackDriver}. A user
 * usually wants both: the toast now, and the record in the bell for when they
 * were looking elsewhere.
 *
 * With no recipient it writes **nothing**. A notification stored against
 * nobody cannot be read by anyone, so persisting it would only grow a table
 * whose rows are unreachable; the resolver answering null is the ordinary state
 * on a queue worker or in a console command.
 */
class DatabaseDriver implements NotificationDriver
{
    public function __construct(
        private readonly ResolvesNotifiable $notifiable = new AuthenticatedNotifiable,
    ) {}

    public function send(Notification $notification, mixed $livewireComponent = null): void
    {
        $recipient = $this->notifiable->resolve();

        if (! $recipient instanceof Model) {
            return;
        }

        DatabaseNotification::query()->create([
            // A ULID rather than a UUID, in a column Laravel's schema calls uuid:
            // both are strings that fit, but a ULID sorts by the time it was
            // made. Five notifications from one bulk job land in the same second,
            // and `created_at DESC` alone then orders them arbitrarily — the bell
            // showed the oldest two of five. The id is the tiebreak that makes
            // "newest first" mean it.
            'id' => (string) Str::ulid(),
            'type' => $notification->type,
            'notifiable_type' => $recipient->getMorphClass(),
            'notifiable_id' => (string) $recipient->getKey(),
            // The whole payload, not just type + message: a stored notification
            // has to render through the same view as a live one, and a title or
            // an icon dropped here is dropped for good.
            'data' => $notification->toArray(),
            'read_at' => null,
        ]);
    }
}
