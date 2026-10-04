<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Support;

use Closure;

/**
 * A setting that is `true`, `false` or `'auto'` — roles, teams, avatars, two
 * factor and passkeys all answer whether they are on this way.
 *
 * One reading for all five, because each used to read it as "a bool, else
 * `'auto'`" and the environment speaks neither: `env()` turns only the words
 * `true` and `false` into booleans, so `WIRE_USERS_ROLES=1` arrived as the
 * string "1", was not `'auto'`, and quietly meant off. Here `1`/`0`, `on`/`off`
 * and `yes`/`no` read as what they say.
 */
final class AutoSwitch
{
    /**
     * Whether the switch at `$key` is on: its own answer when it gives one,
     * `$detected` when it says `'auto'`, and off for anything it cannot read.
     *
     * @param  Closure(): bool  $detected
     */
    public static function on(string $key, Closure $detected): bool
    {
        $setting = self::read($key);

        return $setting === 'auto' ? $detected() : $setting === true;
    }

    /**
     * The switch as a boolean, `'auto'`, or null when it is neither.
     */
    public static function read(string $key): bool|string|null
    {
        $setting = config($key, 'auto');

        if (is_bool($setting)) {
            return $setting;
        }

        if (is_int($setting)) {
            return $setting !== 0;
        }

        if (! is_string($setting)) {
            return null;
        }

        if (strtolower(trim($setting)) === 'auto') {
            return 'auto';
        }

        return filter_var(trim($setting), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }
}
