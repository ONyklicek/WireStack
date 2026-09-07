<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Notifications\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Notifications\Drivers\BroadcastDriver;
use NyonCode\WireCore\Notifications\NotificationCenter;
use NyonCode\WireCore\Notifications\Support\NotificationChannel;

/**
 * A notification landed for someone — told to whatever they have open.
 *
 * The push half of the {@see BroadcastDriver}. Without it the bell learns nothing
 * until the page next talks to the server, which for a queued job finishing
 * twenty minutes later means: not at all, on any tab the user is not clicking in.
 *
 * **It carries no payload, on purpose.** This is a nudge to re-read, not a
 * message to render. The client answers it with `$wire.$refresh()`, so the list
 * and the count come back through {@see NotificationCenter}
 * — through the recipient scoping that is the only thing stopping one user seeing
 * another's rows. A payload on the wire would move that decision to a channel
 * subscription, and put the text of every notification somewhere an intercepted
 * socket can read it. Nothing rides along here but the recipient's own channel
 * name.
 *
 * That is also why a missed one is survivable: the next render is correct
 * regardless. No Echo on the page, a socket that dropped over lunch, a refused
 * subscription — the bell is late, never wrong.
 *
 * `ShouldBroadcastNow`, not `ShouldBroadcast`, and that distinction is the whole
 * feature: a queued broadcast in the very common setup of a configured queue with
 * no worker running for it does nothing at all, silently — and here there is no
 * poll underneath to cover for it. The cost is stated plainly — the write now waits
 * on the broadcaster's HTTP call — and against a broadcaster running beside the
 * app that is sub-millisecond.
 */
final class NotificationReceived implements ShouldBroadcastNow
{
    /**
     * @param  string  $channel  The recipient's channel, without the `private-` prefix.
     */
    public function __construct(public readonly string $channel) {}

    /** The event for a recipient, named by the one owner of the naming. */
    public static function for(Model $notifiable): self
    {
        return new self(NotificationChannel::for($notifiable));
    }

    /**
     * Channel names as strings rather than Channel objects.
     *
     * Laravel casts whatever this returns with `(string)`, and `PrivateChannel`
     * is literally `'private-'.$name`, so the two are the same thing on the wire
     * — while a string keeps `illuminate/broadcasting` out of this package's
     * requirements.
     *
     * @return array<int, string>
     */
    public function broadcastOn(): array
    {
        return ['private-'.$this->channel];
    }

    /** The name the client listens for. Leading dot on the JS side, as ever. */
    public function broadcastAs(): string
    {
        return 'wire-notification.received';
    }

    /**
     * Empty, and asserted to be: see the class note. The default would serialise
     * every public property, which today is the channel name the subscriber
     * already knows and tomorrow is whatever someone adds here.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [];
    }
}
