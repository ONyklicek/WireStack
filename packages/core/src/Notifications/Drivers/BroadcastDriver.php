<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Notifications\Drivers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Notifications\AuthenticatedNotifiable;
use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireCore\Notifications\Contracts\ResolvesNotifiable;
use NyonCode\WireCore\Notifications\Events\NotificationReceived;
use NyonCode\WireCore\Notifications\Notification;

/**
 * Tells the recipient's open pages that something arrived.
 *
 * The transient drivers deliver to the request being rendered and
 * {@see DatabaseDriver} writes the row; neither reaches a page that is already
 * open somewhere else. A queued export finishing has no component to dispatch to
 * and no session to flash into, and the row it leaves behind is invisible until
 * that tab next talks to the server. This is the driver that closes the gap.
 *
 * **Pair it with `database`.** On its own it announces a notification that was
 * never stored, so the client re-reads and finds nothing — the bell would blink
 * and show the same list. `['session', 'database', 'broadcast']` is the whole
 * arrangement: the toast for the tab that asked, the row for later, and the nudge
 * for every other tab.
 *
 * With no recipient it announces **nothing**, the same fail-quiet choice
 * {@see DatabaseDriver} makes on the write side: a queue worker or a console
 * command has nobody to tell, and a channel named after nobody is one no client
 * is subscribed to.
 */
final class BroadcastDriver implements NotificationDriver
{
    public function __construct(
        private readonly ResolvesNotifiable $notifiable = new AuthenticatedNotifiable,
        private readonly ?Dispatcher $events = null,
    ) {}

    public function send(Notification $notification, mixed $livewireComponent = null): void
    {
        // The notification's own answer first: a queued job addressing three
        // people in a loop has one authenticated user (none) and three
        // recipients, so the resolver cannot be the only way to say who.
        $recipient = $notification->notifiable ?? $this->notifiable->resolve();

        if (! $recipient instanceof Model) {
            return;
        }

        // The notification itself is not on the wire — see NotificationReceived.
        // What the client does with this is re-read, through the same scoping a
        // page render uses.
        ($this->events ?? app(Dispatcher::class))->dispatch(NotificationReceived::for($recipient));
    }
}
