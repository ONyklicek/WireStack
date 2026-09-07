<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Notifications\Console;

use Illuminate\Console\Command;
use NyonCode\WireCore\Notifications\DatabaseNotification;

/**
 * Deletes stored notifications past their retention period, so retention can be
 * scheduled instead of hand-rolled:
 *
 *   Schedule::command('wire-core:notifications-prune')->daily();
 *
 * A notification is the one kind of row that is *designed* to stop mattering:
 * the bell shows the newest few, the inbox is read newest-first, and the export
 * that finished in March is of interest to nobody in September. Without this the
 * table grows for the life of the application, and the query behind an unread
 * count is the one paying for it.
 *
 * Two periods, because "read" and "never looked at" are not the same claim:
 * `retention_days` covers everything, while `read_retention_days` may clear what
 * the user has already seen sooner. Either may be null, which means keep.
 */
class PruneNotificationsCommand extends Command
{
    protected $signature = 'wire-core:notifications-prune
        {--days= : Prune notifications older than this many days (overrides wire-core.notifications.database.retention_days)}
        {--read-days= : Prune READ notifications older than this many days (overrides …database.read_retention_days)}';

    protected $description = 'Prune stored notifications past their retention period';

    public function handle(): int
    {
        $all = $this->period('days', 'retention_days');
        $read = $this->period('read-days', 'read_retention_days');

        if ($all === null && $read === null) {
            $this->warn('No retention period configured (wire-core.notifications.database.retention_days) and no --days option given; nothing to prune.');

            return self::INVALID;
        }

        $pruned = 0;

        // Read-only first, and it is not merely an optimisation: the two windows
        // overlap, and pruning the wider one afterwards means a row is counted
        // once however many rules would have caught it.
        if ($read !== null) {
            $pruned += DatabaseNotification::query()
                ->whereNotNull('read_at')
                ->where('created_at', '<', now()->subDays($read))
                ->delete();
        }

        if ($all !== null) {
            $pruned += DatabaseNotification::query()
                ->where('created_at', '<', now()->subDays($all))
                ->delete();
        }

        $this->info("Pruned {$pruned} ".($pruned === 1 ? 'notification' : 'notifications').'.');

        return self::SUCCESS;
    }

    /** The option if it is a number, else the configured period, else null. */
    private function period(string $option, string $key): ?int
    {
        $given = $this->option($option);

        if (is_numeric($given)) {
            return (int) $given;
        }

        $configured = config("wire-core.notifications.database.{$key}");

        return is_numeric($configured) ? (int) $configured : null;
    }
}
