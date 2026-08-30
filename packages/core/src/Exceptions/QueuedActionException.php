<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Exceptions;

use NyonCode\WireCore\Foundation\Contracts\WireException;
use RuntimeException;

/**
 * A queued action asked for something only a browser can give.
 *
 * Every case here is the same one: the action's callback was written for the
 * synchronous path and reaches for the modal it no longer has. Refusing loudly
 * is the point — a no-op `$close()` would look like it worked, and the developer
 * would find out when a user reported that the modal never closed.
 */
final class QueuedActionException extends RuntimeException implements WireException
{
    public static function noBrowser(string $action, string $binding): self
    {
        return new self(
            "The queued action [{$action}] called \$".$binding.'(), which needs the '.
            'browser it no longer has: a queued action runs on a worker, with no '.
            'modal to write into and no page to close. Report back with a '.
            'notification instead — the database driver exists so one survives '.
            'the job — or drop ->queue() from this action.'
        );
    }

    public static function unresolvableHost(string $host): self
    {
        return new self(
            "A queued action names [{$host}] as its host, which could not be built. ".
            'The job rebuilds the host to find the action by name, so the class '.
            'must be constructible without a request — a component whose '.
            'constructor needs one cannot queue its actions.'
        );
    }

    public static function actionGone(string $action, string $host): self
    {
        return new self(
            "The queued action [{$action}] is no longer declared by [{$host}]. ".
            'The job carries the name, not the action, so an action renamed or '.
            'removed between dispatch and run has nothing to execute.'
        );
    }
}
