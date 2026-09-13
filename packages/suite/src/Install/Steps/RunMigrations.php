<?php

declare(strict_types=1);

namespace NyonCode\Wire\Install\Steps;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Migrations\Migrator;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupStep;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupState;
use Throwable;

/**
 * The tables the stack needs, which four packages publish and nobody runs.
 *
 * `wire-module-settings`, `wire-module-media` and `wire-module-audit` each ended
 * their installer with "Run: php artisan migrate" — three packages saying the
 * same sentence, none of them able to act on it, because migrating is about the
 * application rather than any one package. So it is the suite's, and it runs
 * once for all of them.
 *
 * ## Why this one has to be able to say Blocked
 *
 * A fresh `laravel new` has a `.env` pointing at a database that does not exist
 * yet — which is the *normal* state of the application this command is for. Ask
 * "run migrations?" there and the answer is yes and the run dies inside a task
 * spinner with a PDO exception and a stack trace through Composer's autoloader.
 * So the connection is opened first, and a failure to open it is reported as
 * something to go and fix rather than offered as something to do.
 */
final readonly class RunMigrations implements SetupStep
{
    public function __construct(
        private DatabaseManager $database,
        private Migrator $migrator,
        private Kernel $artisan,
    ) {}

    public function label(): string
    {
        return 'Database tables';
    }

    public function state(): SetupState
    {
        if (! $this->connects()) {
            return SetupState::Blocked;
        }

        return $this->pending() === 0 ? SetupState::Done : SetupState::Pending;
    }

    public function summary(): string
    {
        if (! $this->connects()) {
            return 'no database connection — check .env';
        }

        $pending = $this->pending();

        return $pending === 0
            ? 'every migration has run'
            : 'run '.$pending.' pending '.($pending === 1 ? 'migration' : 'migrations');
    }

    public function apply(SetupConsole $console): SetupOutcome
    {
        // `--force` because an installer run against a production application
        // has already been confirmed once, at the question above this, and
        // `migrate` would otherwise stop to ask again — from inside a step,
        // where the answer goes to a prompt this command is not driving.
        try {
            $code = $this->artisan->call('migrate', ['--force' => true]);
        } catch (Throwable $e) {
            // `migrate` reports a broken migration by throwing, not by an exit
            // code: a table that already exists, a column that does not, a
            // duplicate published copy of a migration the application already
            // ran. The first line is the useful one — the rest is a stack trace
            // through the query builder.
            $console->warn(explode("\n", $e->getMessage())[0]);
            $console->warn('Run php artisan migrate yourself to see the whole of it.');

            return SetupOutcome::Failed;
        }

        if ($code !== 0) {
            $console->warn('php artisan migrate did not finish — run it yourself to see why.');

            return SetupOutcome::Failed;
        }

        $console->note('Tables are up to date.');

        return SetupOutcome::Applied;
    }

    public function sort(): int
    {
        // First of everything. A step that writes a row — the first
        // administrator — has nowhere to write it until this has run.
        return 100;
    }

    /**
     * Whether there is a database on the other end of the configured connection.
     *
     * Catching {@see Throwable} rather than a driver exception on purpose: the
     * failure modes here are a missing PDO extension, an unreadable sqlite path
     * and a refused TCP connection, and they do not share a parent.
     */
    private function connects(): bool
    {
        try {
            $this->database->connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * How many migrations have not run.
     *
     * Asked of the migrator rather than read off `migrate:status`, and that is
     * not a preference. With no migrations table that command prints
     * "Migration table not found." and returns a failure — it does not throw
     * and it prints no rows, so counting the word "Pending" in its output
     * answers **zero** for the one application that needs this step most: the
     * fresh one where nothing has ever run.
     *
     * Where there is no repository yet, every file on disk is pending.
     *
     * The application's own `database/migrations` is added to whatever paths
     * were registered, because `Migrator::paths()` holds only the extra ones —
     * the same merge `migrate` itself does, and without it this counts a
     * package's registered migrations and none of the application's.
     */
    private function pending(): int
    {
        $files = $this->migrator->getMigrationFiles(
            array_merge($this->migrator->paths(), [database_path('migrations')])
        );

        if (! $this->migrator->repositoryExists()) {
            return count($files);
        }

        return count(array_diff(array_keys($files), $this->migrator->getRepository()->getRan()));
    }
}
