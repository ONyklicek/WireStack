<?php

declare(strict_types=1);

namespace NyonCode\WireModuleMedia\Console;

use Illuminate\Console\Command;
use NyonCode\WireModuleMedia\Actions\MakeThumbnail;
use NyonCode\WireModuleMedia\Models\Media;

/**
 * `php artisan wire-module-media:thumbnails` — give the files already in the
 * library the preview they were uploaded without.
 *
 * The reason this exists rather than a migration: thumbnails arrived after the
 * library did, so every installation has files with none, and a migration cannot
 * read images. It is also what you run after changing the configured width,
 * with `--force`.
 *
 * Chunked, because a media library is exactly the table somebody has fifty
 * thousand rows in, and loading them all to resize them is how a command that
 * was meant to help takes the server down.
 */
class MakeThumbnailsCommand extends Command
{
    protected $signature = 'wire-module-media:thumbnails
        {--force : Remake thumbnails that already exist}
        {--size= : Fill in one named size only, leaving the others alone}';

    protected $description = 'Make the missing thumbnails for files already in the library';

    public function handle(MakeThumbnail $make): int
    {
        $force = (bool) $this->option('force');
        $size = $this->option('size');
        $size = is_string($size) && $size !== '' ? $size : null;

        if ($size !== null && ! array_key_exists($size, MakeThumbnail::sizes())) {
            $this->error(sprintf('There is no size called "%s". Configured: %s.', $size, implode(', ', array_keys(MakeThumbnail::sizes()))));

            return self::FAILURE;
        }

        // A named size is asked of every row, because the rows that need it are
        // exactly the ones that already have a thumbnail — they were made before
        // the size existed.
        $query = Media::query()->when(! $force && $size === null, fn ($q) => $q->whereNull('thumb_path'));
        $total = $query->count();

        if ($total === 0) {
            $this->info('Every file that can have a thumbnail already has one.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $made = 0;

        $query->chunkById(100, function ($media) use ($make, $force, $size, $bar, &$made): void {
            foreach ($media as $file) {
                // Never throws for a file it cannot handle, so one PDF in the
                // middle of the library does not stop the other forty thousand.
                if ($make($file, $force, $size)) {
                    $made++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        // Both numbers, because the difference is the answer: the rest are files
        // that cannot have a thumbnail — a PDF, an SVG, a file on a remote disk
        // — and that is not a failure to investigate.
        $this->info("Made {$made} thumbnail(s) out of {$total} file(s) looked at.");

        return self::SUCCESS;
    }
}
