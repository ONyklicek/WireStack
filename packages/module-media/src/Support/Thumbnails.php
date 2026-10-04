<?php

declare(strict_types=1);

namespace NyonCode\WireModuleMedia\Support;

use NyonCode\WireModuleMedia\Actions\MakeThumbnail;
use NyonCode\WireModuleMedia\Jobs\GenerateThumbnail;
use NyonCode\WireModuleMedia\Models\Media;

/**
 * Where a thumbnail is made: in this request, or on the queue
 * `wire-module-media.thumbnails.queue` names.
 *
 * One owner, because three places make one — a new upload, the create page and
 * a replaced original — and each used to decide for itself; the replacement
 * resized inline whatever the key said. The key also arrives as a string from
 * the environment, where `WIRE_MEDIA_THUMBNAIL_QUEUE=true` is the word "true",
 * and read literally that is a queue named "true" that no worker listens to.
 */
final class Thumbnails
{
    /**
     * Whether thumbnails go on a queue, and which: null makes them inline, an
     * empty string is the connection's default queue, anything else names one.
     */
    public static function queue(): ?string
    {
        $queue = config('wire-module-media.thumbnails.queue', false);

        if (is_string($queue)) {
            $flag = filter_var(trim($queue), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            if ($flag !== null || trim($queue) === '') {
                $queue = $flag ?? false;
            }
        }

        return match (true) {
            $queue === true => '',
            is_string($queue) => trim($queue),
            default => null,
        };
    }

    public static function queued(): bool
    {
        return self::queue() !== null;
    }

    /**
     * Make the thumbnail now, or hand it to the queue.
     *
     * Allowed to do nothing: a file with no pixels, a server without GD or a
     * remote disk all leave `thumb_path` null, and every view falls back to the
     * original. `$force` remakes one that exists — a replaced original's
     * thumbnail is of the old picture.
     */
    public static function make(Media $media, bool $force = false): void
    {
        $queue = self::queue();

        if ($queue === null) {
            app(MakeThumbnail::class)($media, force: $force);

            return;
        }

        $job = GenerateThumbnail::dispatch($media, $force);

        if ($queue !== '') {
            $job->onQueue($queue);
        }
    }
}
