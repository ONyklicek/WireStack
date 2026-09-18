<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Setup;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Events\VendorTagPublished;
use Throwable;

/**
 * Migrations an installer publishes that the application already has.
 *
 * `vendor:publish` answers "is this migration here" by its file name, and a
 * published migration's name carries the time it was published. So every
 * installer that publishes one — `fortify:install`, the permission package's,
 * this stack's own — writes it into an application that may already have its
 * tables, and the next `migrate` stops on "table already exists" or "duplicate
 * column name". Measured in real applications, three ways:
 *
 * - the schema came from another migration: the application's own, a package's
 *   registered path, the workbench's copies;
 * - the schema is in the database and the migration file is not, because
 *   `schema:dump --prune` deleted it;
 * - the same package published it before, under another stamp.
 *
 * This is the one owner of that question. A setup step wraps its publishing in
 * {@see around()}, and every migration the publish writes is compared with what
 * is already there — the moment its tag is published, before anything in the
 * same command gets to run `migrate` — and removed when its work is done.
 *
 * ## Only what it just wrote
 *
 * A file that was on disk before the publish is never touched, whatever it
 * says: it is the application's, not the installer's. And a migration is only
 * left out when *everything* it makes is already there ({@see MigrationFootprint}),
 * so a partial overlap still runs, and still fails where somebody can see it.
 */
final readonly class RedundantMigrations
{
    public function __construct(
        private Dispatcher $events,
        private Migrator $migrator,
        private DatabaseManager $database,
    ) {}

    /**
     * Run `$publish`, leaving out each migration it writes that the application already has.
     *
     * @template T
     *
     * @param  Closure(): T  $publish
     * @param  Closure(string): void  $leftOut  Told the name, under its stamp, of each migration removed.
     * @param  array<string, MigrationFootprint>  $known  Name under the stamp => its footprint, for
     *                                                    migrations whose tables come from config.
     * @return T
     */
    public function around(Closure $publish, Closure $leftOut, array $known = []): mixed
    {
        $state = new class
        {
            public bool $active = true;

            /** @var array<int, string> */
            public array $seen = [];
        };

        $state->seen = $this->files();

        $sweep = function () use ($state, $leftOut, $known): void {
            foreach (array_diff($this->files(), $state->seen) as $file) {
                if ($this->alreadyThere($file, $known)) {
                    @unlink($file);
                    $leftOut($this->name($file));
                }
            }

            $state->seen = $this->files();
        };

        // On every tag, not only at the end: an installer that publishes and
        // then runs `migrate` itself — the permission package's does — has
        // already failed by the time the command returns.
        $this->events->listen(VendorTagPublished::class, static function () use ($state, $sweep): void {
            if ($state->active) {
                $sweep();
            }
        });

        try {
            return $publish();
        } finally {
            $state->active = false;
            $sweep();
        }
    }

    /**
     * Whether what this migration makes is already in the application.
     *
     * Another migration the migrator will run under the same name, or making
     * all of the same schema; or the database having all of it already. The
     * file itself is not "another", so a package source on a registered path
     * does not answer for itself.
     *
     * @param  array<string, MigrationFootprint>  $known
     */
    public function alreadyThere(string $migration, array $known = []): bool
    {
        $self = realpath($migration);
        $name = $this->name($migration);
        $others = array_values(array_filter($this->files(), static fn (string $file): bool => $file !== $self));

        foreach ($others as $other) {
            if ($this->name($other) === $name) {
                return true;
            }
        }

        $footprint = $known[$name] ?? MigrationFootprint::read((string) @file_get_contents($migration));

        if ($footprint->isEmpty()) {
            return false;
        }

        return $this->inDatabase($footprint) || $footprint->coveredBy(array_map(
            static fn (string $file): MigrationFootprint => MigrationFootprint::read((string) file_get_contents($file)),
            $others,
        ));
    }

    /**
     * Every migration file the migrator will run: the application's own and the paths registered with it.
     *
     * @return array<int, string>
     */
    public function files(): array
    {
        $files = [];

        foreach (array_unique([database_path('migrations'), ...$this->migrator->paths()]) as $path) {
            foreach (glob(rtrim((string) $path, '/').'/*.php') ?: [] as $file) {
                $files[] = (string) realpath($file);
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * The migration's name under its stamp — the only identity a published copy keeps.
     */
    public function name(string $migration): string
    {
        return (string) preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', basename($migration, '.php'));
    }

    private function inDatabase(MigrationFootprint $footprint): bool
    {
        try {
            $schema = $this->database->connection()->getSchemaBuilder();

            foreach ($footprint->creates as $table) {
                if (! $schema->hasTable($table)) {
                    return false;
                }
            }

            foreach ($footprint->adds as $table => $columns) {
                if (! $schema->hasColumns($table, $columns)) {
                    return false;
                }
            }

            return true;
        } catch (Throwable) {
            // No database to ask yet — a fresh application's normal state. Not
            // being able to look is not having found it.
            return false;
        }
    }
}
