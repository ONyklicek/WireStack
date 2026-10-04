<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Notifications\Support;

/**
 * The driver names `config('wire-core.notifications.default')` lists.
 *
 * The value is a list in a published config and a comma-separated string when
 * it comes from the environment — `WIRE_NOTIFICATIONS_DRIVER=session,database`,
 * which is what `wire:install` writes, because an env var carries a string and
 * nothing else. Every reader asks here, so "is `database` on?" has one answer:
 * an `(array)` cast of that string is the one-element list `['session,database']`,
 * which answers no to everything.
 */
final class ConfiguredDrivers
{
    /**
     * @return list<string>
     */
    public static function names(): array
    {
        $configured = config('wire-core.notifications.default', 'session');

        $names = is_array($configured)
            ? array_map(static fn (mixed $name): string => trim((string) $name), $configured)
            : explode(',', (string) $configured);

        return array_values(array_filter(array_map(trim(...), $names), static fn (string $name): bool => $name !== ''));
    }

    public static function includes(string $driver): bool
    {
        return in_array($driver, self::names(), true);
    }
}
