<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Export\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use NyonCode\WireCore\Actions\Jobs\RunActionJob;
use NyonCode\WireTable\Export\Contracts\Exporter;
use NyonCode\WireTable\Export\ExportFormat;
use NyonCode\WireTable\Export\TableExport;

/**
 * Writes an export to a disk, then says where it is.
 *
 * A queued export is a **different delivery**, not the same one moved: a
 * download is a response, and a job has none to return. So this writes the file
 * and reports the path — which is the whole reason the exporters grew
 * {@see Exporter::writeTo()} first, rather
 * than this job growing a second copy of "turn records into a file".
 *
 * **What it carries is a component class and a format**, for the reason
 * {@see RunActionJob} carries names and keys: a
 * query is closures and a builder, neither of which survives serialization, and
 * an export of ten thousand rows serialized into a payload would be the thing it
 * was meant to avoid. The host is rebuilt and asked for its filtered query, so
 * the export is over the data as it is when the job runs.
 *
 * The completion notification is why N came first: by the time a large export
 * finishes there is no request left to flash into.
 */
class RunExportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  class-string  $host  The table component whose filtered query is exported.
     * @param  string  $format  An {@see ExportFormat} value.
     * @param  string|null  $disk  Filesystem disk; the default when null.
     * @param  array<string, mixed>  $state  The table state the export was asked for under.
     */
    public function __construct(
        public readonly string $host,
        public readonly string $format = 'csv',
        public readonly ?string $disk = null,
        public readonly string $directory = 'exports',
        public readonly array $state = [],
    ) {}

    public function handle(): void
    {
        $host = app($this->host);

        if (! method_exists($host, 'buildTableExport')) {
            return;
        }

        // Mounted, then given back the state it was queued under. Mounting seeds
        // the defaults every component needs; replacing puts the user's filters
        // over them.
        if (method_exists($host, 'mountWithTable')) {
            $host->mountWithTable();
        }

        if ($this->state !== [] && isset($host->tableState)) {
            $host->tableState->replace($this->state);
        }

        /** @var array{0: TableExport, 1: mixed, 2: array<int, mixed>} $prepared */
        $prepared = $host->buildTableExport(ExportFormat::from($this->format));

        [$export, $query, $columns] = $prepared;

        $path = $export->store($query, $columns, $this->disk, $this->directory);

        $this->announce($path);
    }

    /**
     * Say it is ready, and where.
     *
     * Reaches Notifications by class name rather than importing it: wire-table
     * may be installed without a notification surface bound, and an export that
     * finished should not fail because nothing was listening.
     */
    private function announce(string $path): void
    {
        $manager = 'NyonCode\\WireCore\\Notifications\\NotificationManager';

        // @codeCoverageIgnoreStart
        if (! class_exists($manager)) {
            return;
        }
        // @codeCoverageIgnoreEnd

        $manager::success(__('wire-table::messages.export_ready', ['file' => basename($path)]));
    }
}
