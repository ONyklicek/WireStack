<?php

declare(strict_types=1);

namespace NyonCode\WireModuleMedia\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireModuleMedia\Concerns\SyncsMediaUsage;
use NyonCode\WireModuleMedia\Support\ContentMedia;
use NyonCode\WireModuleMedia\Support\MediaUsage;

/**
 * `php artisan wire-module-media:usage --model="App\Models\Post"` — work out
 * where files are used in content written before the library kept the id.
 *
 * The editor now writes `data-media-id` on every picture it inserts, so
 * everything written from here on records itself on save. What already sits in
 * the database is a bare `<img src>`, and this is the one pass that reads it:
 * the stored paths of the library matched against the URLs in the content.
 *
 * **Best effort, and deliberately so.** A URL that matches nothing in the
 * library finds nothing, which is the honest answer rather than a wrong one —
 * and every screen that shows a count says it is a floor for exactly this
 * reason. See ADR 0034.
 */
class SyncUsageCommand extends Command
{
    protected $signature = 'wire-module-media:usage
        {--model= : The model class to scan, e.g. "App\\Models\\Post"}
        {--dry-run : Report what would be linked without writing anything}';

    protected $description = 'Find where media files are used in content written before the id was kept';

    public function handle(): int
    {
        $class = (string) $this->option('model');

        if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            $this->error('Pass a model class with --model, e.g. --model="App\\Models\\Post".');

            return self::FAILURE;
        }

        $instance = new $class;

        // Named rather than inferred: the trait is what says which attributes
        // hold written content, and without it there is nothing to scan.
        if (! in_array(SyncsMediaUsage::class, class_uses_recursive($class), true)) {
            $this->error(class_basename($class).' does not use SyncsMediaUsage, so it has not said which attributes hold content.');

            return self::FAILURE;
        }

        $attributes = $instance->mediaContentAttributes();

        if ($attributes === []) {
            $this->error(class_basename($class).' uses SyncsMediaUsage but its $mediaContent is empty.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $lookup = ContentMedia::lookup();

        if ($lookup === []) {
            $this->info('The library is empty, so there is nothing to match against.');

            return self::SUCCESS;
        }

        $scanned = 0;
        $linked = 0;
        $records = 0;

        // Chunked, because the table being scanned is the application's own and
        // may be the largest one it has.
        $class::query()->chunkById(200, function ($rows) use ($attributes, $lookup, $dry, &$scanned, &$linked, &$records): void {
            foreach ($rows as $record) {
                $scanned++;
                $ids = [];

                foreach ($attributes as $attribute) {
                    $value = $record->getAttribute($attribute);
                    $html = is_string($value) ? $value : null;

                    // Both, because a body may be half old and half new: an
                    // article edited once since the upgrade carries ids on the
                    // pictures added then and bare URLs on the rest.
                    $ids = [...$ids, ...ContentMedia::idsIn($html), ...ContentMedia::idsByUrl($html, $lookup)];
                }

                $ids = array_values(array_unique($ids));

                if ($ids === []) {
                    continue;
                }

                $records++;
                $linked += count($ids);

                if (! $dry) {
                    $record->syncMedia($ids, MediaUsage::CONTENT);
                }
            }
        });

        $this->info(sprintf(
            '%s %d link(s) across %d of %d %s record(s).',
            $dry ? 'Would write' : 'Wrote',
            $linked,
            $records,
            $scanned,
            class_basename($class),
        ));

        // Said out loud: the difference is not a bug to chase, it is the rows
        // whose pictures were never in this library.
        if ($scanned > $records) {
            $this->line(sprintf('%d record(s) pointed at nothing the library holds.', $scanned - $records));
        }

        return self::SUCCESS;
    }
}
