<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Audit\Console;

use Illuminate\Console\Command;
use NyonCode\WireCore\Audit\AuditLogger;

/**
 * Deletes audit entries older than the retention period, so retention can be
 * scheduled instead of hand-rolled:
 *
 *   Schedule::command('wire-core:audit-prune')->daily();
 *
 * The period comes from `wire-core.audit.retention_days`, overridable per run
 * with `--days`.
 */
class PruneAuditEntriesCommand extends Command
{
    protected $signature = 'wire-core:audit-prune {--days= : Prune entries older than this many days (overrides wire-core.audit.retention_days)}';

    protected $description = 'Prune audit log entries older than the retention period';

    public function handle(AuditLogger $logger): int
    {
        $option = $this->option('days');
        $days = is_numeric($option) ? (int) $option : null;

        if ($days === null && config('wire-core.audit.retention_days') === null) {
            $this->warn('No retention period configured (wire-core.audit.retention_days) and no --days option given; nothing to prune.');

            return self::INVALID;
        }

        $pruned = $logger->prune($days);

        $this->info("Pruned {$pruned} audit ".($pruned === 1 ? 'entry' : 'entries').'.');

        return self::SUCCESS;
    }
}
