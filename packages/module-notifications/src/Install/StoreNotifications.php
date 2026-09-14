<?php

declare(strict_types=1);

namespace NyonCode\WireModuleNotifications\Install;

use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupStep;
use NyonCode\WireCore\Foundation\Setup\EnvFile;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupState;

/**
 * The driver that writes a notification down, without which the bell is empty.
 *
 * The installer already said it — "Add `database` to
 * wire-core.notifications.default, or nothing is stored to show" — and it is the
 * same shape as the audit switch: the module is a history screen, and the thing
 * that makes history is a key in another package's config. Installed alone, it
 * works perfectly and shows nothing, for ever.
 *
 * ## Adding, never replacing
 *
 * `session` is the default and it is the toast a person sees now; `database` is
 * what survives the request. Replacing one with the other would trade a visible
 * notification for a stored one, so this adds — the value it writes is the list
 * that was already there with `database` on the end.
 */
final readonly class StoreNotifications implements SetupStep
{
    private const KEY = 'WIRE_NOTIFICATIONS_DRIVER';

    private const DRIVER = 'database';

    public function __construct(private EnvFile $env) {}

    public function label(): string
    {
        return 'Stored notifications';
    }

    public function state(): SetupState
    {
        if (in_array(self::DRIVER, $this->drivers(), true)) {
            return SetupState::Done;
        }

        return $this->env->exists() ? SetupState::Pending : SetupState::Blocked;
    }

    public function summary(): string
    {
        if (in_array(self::DRIVER, $this->drivers(), true)) {
            return 'notifications are being stored';
        }

        return $this->env->exists()
            ? 'store notifications too, or the bell has nothing to show'
            : 'nothing is stored, and there is no .env to change it in';
    }

    public function apply(SetupConsole $console): SetupOutcome
    {
        $drivers = [...$this->drivers(), self::DRIVER];

        if (! $this->env->set(self::KEY, implode(',', $drivers))) {
            $console->warn('Could not write .env — add `'.self::DRIVER.'` to wire-core.notifications.default yourself.');

            return SetupOutcome::Failed;
        }

        $console->note('Notifications are delivered by: '.implode(', ', $drivers).'.');

        return SetupOutcome::Applied;
    }

    public function package(): string
    {
        return 'nyoncode/wire-module-notifications';
    }

    public function sort(): int
    {
        return 700;
    }

    /**
     * What delivers a notification today.
     *
     * The config takes an array or one name, and the environment can only carry
     * a string — so a comma-separated list is read as a list, which is what
     * wire-core's driver resolution does with it.
     *
     * @return array<int, string>
     */
    private function drivers(): array
    {
        $configured = config('wire-core.notifications.default', 'session');

        if (is_array($configured)) {
            return array_values(array_map(strval(...), $configured));
        }

        return array_values(array_filter(array_map(trim(...), explode(',', (string) $configured))));
    }
}
