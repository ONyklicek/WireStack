<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use Closure;
use Illuminate\Support\Facades\Broadcast;

/**
 * The broadcast channel a live table announces its writes on.
 *
 * Canonical owner of the name, of turning it back into the thing it names, and
 * of authorizing it — the three have to agree, and an application that had to
 * reproduce the naming convention by hand would be the place they came apart.
 *
 * The scope is ONE channel segment, and that is the whole design. Laravel
 * compiles a `{placeholder}` in a channel pattern to `([^\.]+)`, so a name
 * carrying a dotted class (`wire-table.App.Models.Invoice`) cannot be matched by
 * a wildcard at all: every model would need its own hand-written
 * `Broadcast::channel('wire-table.App.Models.Invoice', …)` line, spelled
 * correctly, forever. Backslashes are not an option either — Pusher-protocol
 * channel names allow `[A-Za-z0-9_\-=@,.;]` and a backslash is not in it. So the
 * separator is `-`, which is legal there and cannot occur in a PHP class name,
 * making the encoding reversible without ambiguity.
 *
 * That failure mode is the reason this is worth a class. A mistyped channel name
 * does not raise anything: the subscription is refused, the push silently stops
 * arriving, and polling covers for it — so the table still works and nobody ever
 * learns the broadcast half is dead.
 */
final class LiveChannel
{
    /**
     * The pattern an app authorizes ONCE, covering every live table it has.
     *
     * Without the `private-` prefix: `Broadcast::channel()` names channels the
     * way Echo subscribes to them, and the prefix is added on the wire.
     */
    public const PATTERN = 'wire-table.{scope}';

    /** The channel name for a data source — the model class, normally. */
    public static function for(string $scope): string
    {
        return 'wire-table.'.self::encode($scope);
    }

    /** The class name a channel segment stands for. */
    public static function scopeFrom(string $segment): string
    {
        return str_replace('-', '\\', $segment);
    }

    /**
     * Authorize every wire-table live channel with one callback.
     *
     * The callback receives the authenticated user and the **class name** the
     * channel belongs to — already decoded, so an app never handles the wire
     * format:
     *
     *     // routes/channels.php
     *     LiveChannel::authorize(fn ($user, string $model) => $user->can('viewAny', $model));
     *
     * Returning false (or null) refuses, exactly as any channel callback does.
     * An app that wants per-model rules branches on `$model` rather than
     * registering a line per table.
     */
    public static function authorize(Closure $callback): void
    {
        Broadcast::channel(self::PATTERN, function ($user, string $scope) use ($callback) {
            $model = self::scopeFrom($scope);

            // The segment arrives from the client, so what comes out of the
            // decode is a class-SHAPED string, not a class. An app callback is
            // entitled to treat its argument as real — the documented one hands
            // it to `Gate`, and a plausible one does `$model::query()` — so
            // anything that does not name a loadable class is refused here rather
            // than passed on for the app to be careless with.
            return class_exists($model) ? $callback($user, $model) : false;
        });
    }

    /**
     * `App\Models\Invoice` → `App-Models-Invoice`.
     *
     * Dot-free so a wildcard can match it, and reversible because `-` cannot
     * appear in a PHP class name while `\` cannot appear in a channel name.
     */
    private static function encode(string $scope): string
    {
        return str_replace('\\', '-', $scope);
    }
}
