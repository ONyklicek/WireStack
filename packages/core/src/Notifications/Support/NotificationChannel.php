<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Notifications\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Broadcast;

/**
 * The private channel one recipient's notifications arrive on.
 *
 * Canonical owner of the name and of authorizing it — the two have to agree, and
 * an application reproducing the convention by hand is where they come apart. A
 * mistyped channel raises nothing: the subscription is refused, the push stops
 * arriving, and the bell still works because it re-reads on its own whenever the
 * page does. Nobody learns the live half is dead.
 *
 * Two segments, `{notifiable}.{key}`, because a notification is addressed to a
 * *record*, not to a class. Laravel compiles a `{placeholder}` to `([^\.]+)`, so
 * the morph class cannot travel with its dots or its backslashes: `\` becomes
 * `-`, which is legal in a Pusher-protocol channel name where a backslash is not.
 *
 * Nothing is ever decoded back. A morph map alias may legitimately contain `-`
 * (`'blog-post' => BlogPost::class`), so `-` → `\` is ambiguous the moment an
 * application uses one — and it does not need to be reversible: authorizing
 * compares the viewer's own encoded morph class against the segment, which is an
 * equality test in the encoded space and cannot be tricked by an alias.
 */
final class NotificationChannel
{
    /**
     * The pattern an app authorizes ONCE, covering every recipient it has.
     *
     * Without the `private-` prefix: `Broadcast::channel()` names channels the
     * way Echo subscribes to them, and the prefix is added on the wire.
     */
    public const PATTERN = 'wire-notifications.{notifiable}.{key}';

    /** The channel this recipient's notifications are announced on. */
    public static function for(Model $notifiable): string
    {
        return 'wire-notifications.'
            .self::encode($notifiable->getMorphClass())
            .'.'.$notifiable->getKey();
    }

    /**
     * Is this viewer the recipient the channel names?
     *
     * The whole authorization rule, and deliberately the strictest one there is:
     * a notification is addressed to somebody, so the only person entitled to be
     * told one arrived is that somebody. An application wanting a supervisor to
     * watch another user's channel writes its own callback — that is a policy
     * decision, and it is not this package's to make quietly.
     */
    public static function matches(?Model $viewer, string $notifiable, string $key): bool
    {
        if (! $viewer instanceof Model) {
            return false;
        }

        return self::encode($viewer->getMorphClass()) === $notifiable
            && (string) $viewer->getKey() === $key;
    }

    /**
     * Authorize every wire notification channel with one callback.
     *
     * Called with no argument it installs {@see self::matches()}, which is what
     * `wire-core` registers for an application that configured the `broadcast`
     * driver and said nothing further. Pass a closure to decide differently:
     *
     *     // routes/channels.php
     *     NotificationChannel::authorize(
     *         fn ($user, string $notifiable, string $key) => $user->isAdmin()
     *             || NotificationChannel::matches($user, $notifiable, $key),
     *     );
     *
     * The two segments arrive from the client and are handed on exactly as they
     * came — encoded, unresolved, and not to be trusted as a class name.
     */
    public static function authorize(?Closure $callback = null): void
    {
        Broadcast::channel(
            self::PATTERN,
            $callback ?? static fn ($user, string $notifiable, string $key): bool => self::matches($user, $notifiable, $key),
        );
    }

    /** `App\Models\User` → `App-Models-User`. Dot-free, so a wildcard can match it. */
    private static function encode(string $morphClass): string
    {
        return str_replace('\\', '-', $morphClass);
    }
}
