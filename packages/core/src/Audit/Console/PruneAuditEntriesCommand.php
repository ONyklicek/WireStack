<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Audit\Console;

use Illuminate\Console\Command;
use NyonCode\WireCore\Audit\AuditLogger;
use NyonCode\WireCore\Exceptions\InvalidRetentionException;

/**
 * Deletes audit entries older than the retention period, so retention can be
 * scheduled instead of hand-rolled:
 *
 *   Schedule::command('wire-core:audit-prune')->daily();
 *
 * The period comes from `wire-core.audit.retention_days`, overridable per run
 * with `--days`.
 *
 * **A period that keeps nothing is refused, not run.** `--days=0` used to prune
 * every entry and print `Pruned 41203 audit entries.` as a success; `--days=-30`
 * did the same. So did `--days=` from a scheduler line whose variable was unset,
 * by falling through to whatever was configured. Each of those is refused with
 * nothing deleted and a non-zero exit, because this is the one table where
 * "delete everything" is never what somebody meant — and a scheduled command
 * that fails loudly gets noticed, where one that succeeds does not.
 */
class PruneAuditEntriesCommand extends Command
{
    protected $signature = 'wire-core:audit-prune {--days= : Prune entries older than this many days, at least 1 (overrides wire-core.audit.retention_days)}';

    protected $description = 'Prune audit log entries older than the retention period';

    public function handle(AuditLogger $logger): int
    {
        $option = $this->option('days');
        $days = null;

        if ($option !== null) {
            $days = filter_var($option, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

            if ($days === false) {
                $this->error("--days must be a whole number of days, at least 1; [{$option}] would not be. Nothing was pruned.");

                return self::INVALID;
            }
        }

        if ($days === null && config('wire-core.audit.retention_days') === null) {
            $this->warn('No retention period configured (wire-core.audit.retention_days) and no --days option given; nothing to prune.');

            return self::INVALID;
        }

        try {
            $pruned = $logger->prune($days);
        } catch (InvalidRetentionException $e) {
            // The configured period, since `--days` was checked above.
            $this->error($e->getMessage().' Nothing was pruned.');

            return self::INVALID;
        }

        $this->info("Pruned {$pruned} audit ".($pruned === 1 ? 'entry' : 'entries').'.');

        return self::SUCCESS;
    }
}
