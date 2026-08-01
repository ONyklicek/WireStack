<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use NyonCode\WireTable\Support\LiveChannel;
use NyonCode\WireTable\Table;

/**
 * Something wrote to the records a table lists — told to every other session.
 *
 * The push half of {@see Table::live()}. Polling already
 * gets another user's change onto the screen; this only decides *when*, by
 * replacing "on the next tick" with "as soon as the write commits". A table with
 * `live()` keeps polling either way, so a missing broadcast connection, a
 * dropped socket or a client with no Echo degrades to the interval instead of to
 * nothing — which is why this carries no data. It is a nudge to re-read, not a
 * payload to apply: a client that acts on it re-renders through the table's own
 * query and therefore through its own authorization, filters and page.
 *
 * That also keeps the channel free of anything worth intercepting. The scope
 * name is the only thing on it, and the app authorizes the channel as it would
 * any other.
 *
 * Which broadcaster carries it is entirely the application's business. This is a
 * plain Laravel broadcast event with string channel names, and the client half
 * calls nothing but `window.Echo.private()` and `window.Echo.leave()`, so
 * whichever broadcaster Echo drives should carry it unchanged and none of them is
 * a dependency of this package.
 *
 * Stated as the expectation it is: Reverb is the only one this has actually been
 * run against (`verify-live-broadcast-real.mjs`, on demand, not in CI). Pusher
 * and Ably follow from the surface above rather than from anyone having watched
 * them, which is why `BroadcasterAgnosticTest` pins the surface.
 *
 * Dispatched wherever a write retires the query cache, so the two answers to
 * "the data moved" cannot drift apart.
 *
 * `ShouldBroadcastNow`, not `ShouldBroadcast`, and that distinction is the whole
 * feature. A queued broadcast is dispatched to the app's queue, so in the very
 * common setup of a configured queue with no worker running for it, the push
 * half silently does nothing at all — the table still refreshes, on the interval,
 * and the broadcast looks like it is working because polling covers for it. Even
 * with a worker it adds queue latency to the one thing here whose entire purpose
 * is to be faster than the next tick.
 *
 * The usual reasons to queue a broadcast do not apply: this carries no payload to
 * serialise, it is idempotent, and losing one costs a few hundred milliseconds
 * rather than data, because the interval is still running underneath. The cost is
 * stated plainly: the write now waits on the broadcaster's HTTP call before it
 * answers. Against one running beside the app that is sub-millisecond; against a
 * hosted service on a bad day it is added to every write, and a table that cannot
 * afford that should turn `broadcast` off and keep the interval.
 */
final class TableRecordsChanged implements ShouldBroadcastNow
{
    /**
     * @param  string  $scope  What changed — the model class, normally. See
     *                         WithTable::queryCacheScope().
     */
    public function __construct(public readonly string $scope) {}

    /**
     * Channel names as strings rather than Channel objects.
     *
     * Laravel casts whatever this returns with `(string)`, and `PrivateChannel`
     * is literally `'private-'.$name`, so the two are the same thing on the
     * wire — while a string keeps `illuminate/broadcasting` out of this
     * package's requirements.
     *
     * @return array<int, string>
     */
    public function broadcastOn(): array
    {
        return ['private-'.self::channelFor($this->scope)];
    }

    /**
     * The name the client listens for. Leading dot on the JS side, as ever.
     */
    public function broadcastAs(): string
    {
        return 'wire-table.changed';
    }

    /**
     * The channel a scope broadcasts on, without the `private-` prefix Echo adds.
     *
     * Delegated to {@see LiveChannel}, which owns the naming so that authorizing
     * a channel and broadcasting on it cannot disagree — and so an app can
     * authorize all of them with one wildcard rather than a hand-written line
     * per model.
     */
    public static function channelFor(string $scope): string
    {
        return LiveChannel::for($scope);
    }
}
