<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Import\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use NyonCode\WireTable\Exceptions\ImportException;
use NyonCode\WireTable\Export\Jobs\RunExportJob;
use NyonCode\WireTable\Import\CsvImporter;
use NyonCode\WireTable\Import\ImportResult;
use NyonCode\WireTable\Import\TableImport;

/**
 * Imports an uploaded file on a worker, then reports what it did.
 *
 * The mirror of {@see RunExportJob}, and much the smaller of the two: an import
 * was already path-in, result-out — {@see TableImport::import()}
 * has no response in the way — so nothing in the import pipeline had to change.
 * What a queued run adds is the three things a job cannot borrow from a request:
 * a file that outlives it, a result that has somewhere to go, and a failure that
 * is visible.
 *
 * **It carries a disk path, not a real path.** A Livewire temp upload is a local
 * file on the web node, and the worker is entitled to be a different machine;
 * the file is pulled down to a temp path there because
 * {@see CsvImporter} opens with `fopen()`, which does
 * not speak S3.
 */
class RunImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  class-string  $host  The table component the rows are imported into.
     * @param  string  $path  Path on the disk, not on the local filesystem.
     * @param  string|null  $disk  Filesystem disk; the default when null.
     */
    public function __construct(
        public readonly string $host,
        public readonly string $path,
        public readonly ?string $disk = null,
    ) {}

    public function handle(): void
    {
        $host = app($this->host);

        if (! method_exists($host, 'importTable')) {
            return;
        }

        // Mounting builds the table, which is where the ImportAction — and with
        // it the mapping and the authorization gate — is declared.
        if (method_exists($host, 'mountWithTable')) {
            $host->mountWithTable();
        }

        $local = $this->pullDown();

        try {
            $this->announce($host->importTable($local));
        } finally {
            @unlink($local);
        }
    }

    /**
     * Copy the upload off the disk to somewhere `fopen()` can reach.
     */
    private function pullDown(): string
    {
        $disk = $this->disk ?? (string) config('filesystems.default');
        $storage = Storage::disk($disk);

        if (! $storage->exists($this->path)) {
            throw ImportException::fileNotFound($this->path, $disk);
        }

        $local = (string) tempnam(sys_get_temp_dir(), 'wire-import-');
        file_put_contents($local, $storage->get($this->path));

        return $local;
    }

    /**
     * Say what happened, in counts.
     *
     * By class name for the reason {@see RunExportJob::announce()} gives: a table
     * may be installed without a notification surface, and an import that ran
     * should not fail because nothing was listening.
     */
    private function announce(ImportResult $result): void
    {
        $manager = 'NyonCode\\WireCore\\Notifications\\NotificationManager';

        // @codeCoverageIgnoreStart
        if (! class_exists($manager)) {
            return;
        }
        // @codeCoverageIgnoreEnd

        $message = __('wire-table::messages.import_result', [
            'imported' => $result->getImported(),
            'failed' => $result->getFailedCount(),
        ]);

        $result->hasFailures() ? $manager::warning($message) : $manager::success($message);
    }
}
