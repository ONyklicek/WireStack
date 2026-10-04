<?php

declare(strict_types=1);

namespace NyonCode\WireModuleNotifications\Exceptions;

use InvalidArgumentException;
use NyonCode\WireCore\Foundation\Contracts\WireException;
use NyonCode\WireCore\Notifications\DatabaseNotification;

/**
 * A configuration this module was given and cannot work from.
 *
 * The value came from `config/wire-module-notifications.php`, so it is a bad
 * argument rather than a bad state.
 */
final class NotificationsModuleException extends InvalidArgumentException implements WireException
{
    public static function modelIsNotADatabaseNotification(string $class): self
    {
        $base = DatabaseNotification::class;

        return new self(
            "[{$class}] is configured as `wire-module-notifications.model`, but it does not extend [{$base}]. ".
            'The list and the detail page read a stored notification back through that model\'s '.
            '`toNotification()`, its read state and its recipient — extend it, or leave the setting at core\'s own model.'
        );
    }
}
