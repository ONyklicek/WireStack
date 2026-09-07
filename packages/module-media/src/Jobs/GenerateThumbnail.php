<?php

declare(strict_types=1);

namespace NyonCode\WireModuleMedia\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use NyonCode\WireModuleMedia\Actions\MakeThumbnail;
use NyonCode\WireModuleMedia\Models\Media;

/**
 * Making a thumbnail somewhere other than the request that uploaded the file.
 *
 * Resizing a large photograph is seconds, and they are seconds the person who
 * dropped it spends watching a spinner. Off by default all the same: a queue
 * nobody is working would mean thumbnails that never appear, which is worse than
 * an upload that takes a moment. `wire-module-media.thumbnails.queue` is the
 * switch, and it is worth throwing wherever a worker is actually running.
 *
 * **The row is already saved by the time this runs**, so a failed job costs a
 * thumbnail and nothing else — the file is on the disk, the library shows it
 * scaled by the browser, and `wire-module-media:thumbnails` picks it up later.
 * That is the same fallback every other reason a thumbnail cannot be made uses.
 */
class GenerateThumbnail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public Media $media, public bool $force = false) {}

    public function handle(MakeThumbnail $make): void
    {
        $make($this->media, $this->force);
    }

    /**
     * A media row deleted before the job ran takes the job with it.
     *
     * `SerializesModels` throws `ModelNotFoundException` for a missing model,
     * which would retry and fail a job whose whole subject is gone. Deleting a
     * file two seconds after uploading it is a thing people do.
     */
    public function failed(): void
    {
        // Nothing to undo: the row and the file are independent of this, and a
        // missing thumbnail is a state every view already handles.
    }
}
