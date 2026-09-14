<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Schema;
use NyonCode\Wire\Install\Steps\RunMigrations;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupState;

/*
 * The tables the stack needs, which four packages publish and nobody ran.
 *
 * `wire-module-settings`, `wire-module-media` and `wire-module-audit` each ended
 * their installer with the same sentence — "Run: php artisan migrate" — because
 * migrating is about the application rather than any one package. This is that
 * sentence with something behind it.
 */

function rmStep(): RunMigrations
{
    return app(RunMigrations::class);
}

/** A console that answers with defaults and says nothing — a step under test needs no terminal. */
function wiSilentConsole(): SetupConsole
{
    return new class implements SetupConsole
    {
        public function ask(string $question, ?string $default = null): string
        {
            return (string) $default;
        }

        public function secret(string $question): string
        {
            return '';
        }

        public function confirm(string $question, bool $default = true): bool
        {
            return $default;
        }

        public function choose(string $question, array $options, ?string $default = null): string
        {
            return (string) $default;
        }

        /**
         * @param  array<int|string, string>  $options
         * @param  array<int, int|string>  $default
         * @return array<int, int|string>
         */
        public function select(string $question, array $options, array $default = []): array
        {
            return $default;
        }

        public function note(string $message): void {}

        public function warn(string $message): void {}

        public function isInteractive(): bool
        {
            return false;
        }
    };
}

/**
 * A migration of this name, in the application's own directory.
 *
 * Planted by the test rather than borrowed from whatever the skeleton happens
 * to hold, so what "one pending migration" means here is one, always.
 */
function rmPlant(string $table): string
{
    $path = database_path('migrations/2024_01_01_000000_create_'.$table.'_table.php');

    $body = <<<'PHP'
        <?php
        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;
        return new class extends Migration {
            public function up(): void { Schema::create('TABLE', fn (Blueprint $t) => $t->id()); }
            public function down(): void { Schema::dropIfExists('TABLE'); }
        };
        PHP;

    file_put_contents($path, str_replace('TABLE', $table, $body));

    return $path;
}

/**
 * The silent console, but keeping what it was told.
 *
 * @param  array<int, string>  $said
 */
function wiSayingConsole(array &$said): SetupConsole
{
    return new class($said) implements SetupConsole
    {
        /** @param array<int, string> $said */
        public function __construct(private array &$said) {}

        public function ask(string $question, ?string $default = null): string
        {
            return (string) $default;
        }

        public function secret(string $question): string
        {
            return '';
        }

        public function confirm(string $question, bool $default = true): bool
        {
            return $default;
        }

        public function choose(string $question, array $options, ?string $default = null): string
        {
            return (string) $default;
        }

        /**
         * @param  array<int|string, string>  $options
         * @param  array<int, int|string>  $default
         * @return array<int, int|string>
         */
        public function select(string $question, array $options, array $default = []): array
        {
            return $default;
        }

        public function note(string $message): void
        {
            $this->said[] = $message;
        }

        public function warn(string $message): void
        {
            $this->said[] = $message;
        }

        public function isInteractive(): bool
        {
            return false;
        }
    };
}

it('is pending while a migration has not run, and says how many', function () {
    rmIsolatingMigrations(function () {
        rmPlant('rm_things');

        expect(rmStep()->state())->toBe(SetupState::Pending)
            ->and(rmStep()->summary())->toBe('run 1 pending migration');

        rmPlant('rm_others');

        expect(rmStep()->summary())->toBe('run 2 pending migrations');
    });
});

/**
 * Run something with `database/migrations` emptied and put back.
 *
 * Under Testbench that directory is the skeleton's, shared by every suite in
 * the monorepo, and whatever a previous run published into it is what `migrate`
 * would execute here. A test that migrates the shared directory passes or fails
 * on what ran before it.
 */
function rmIsolatingMigrations(Closure $body): void
{
    $directory = database_path('migrations');
    $moved = [];

    foreach (glob($directory.'/*.php') ?: [] as $path) {
        $moved[$path] = (string) file_get_contents($path);
        unlink($path);
    }

    try {
        $body();
    } finally {
        // Both halves: what was moved aside goes back, and whatever the body
        // planted goes away. Restoring only the first half is how two
        // `create_rm_things_table` files ended up in the skeleton for every
        // later suite to migrate.
        foreach (glob($directory.'/*.php') ?: [] as $path) {
            if (! array_key_exists($path, $moved)) {
                unlink($path);
            }
        }

        foreach ($moved as $path => $contents) {
            file_put_contents($path, $contents);
        }
    }
}

it('runs them, and is done afterwards', function () {
    rmIsolatingMigrations(function () {
        expect(rmStep()->apply(wiSilentConsole()))->toBe(SetupOutcome::Applied)
            ->and(Schema::hasTable('migrations'))->toBeTrue()
            ->and(rmStep()->state())->toBe(SetupState::Done)
            ->and(rmStep()->summary())->toBe('every migration has run');
    });
});

it('counts every migration as outstanding when the table does not exist yet', function () {
    // The state a fresh application is actually in, and the one
    // `migrate:status --pending` gets wrong: with no migrations table it prints
    // "Migration table not found." and no rows at all, so counting its output
    // would answer zero for the application that needs this step most.
    rmIsolatingMigrations(function () {
        rmPlant('rm_things');

        expect(Schema::hasTable('migrations'))->toBeFalse()
            ->and(rmStep()->state())->toBe(SetupState::Pending)
            ->and(rmStep()->summary())->toBe('run 1 pending migration');
    });
});

it('is blocked, not offered, when there is no database to migrate into', function () {
    // A fresh `laravel new` points at a database that does not exist yet, which
    // is the normal state of the application this command is for. Offering
    // "run migrations?" there means the answer is yes and the run dies inside a
    // task spinner with a PDO exception.
    $database = Mockery::mock(DatabaseManager::class);
    $database->shouldReceive('connection')->andThrow(new PDOException('could not find driver'));

    $step = new RunMigrations($database, app('migrator'), app(Kernel::class));

    expect($step->state())->toBe(SetupState::Blocked)
        ->and($step->summary())->toContain('no database connection');
});

it('fails rather than pretending, when migrate does not finish', function () {
    $artisan = Mockery::mock(Kernel::class);
    $artisan->shouldReceive('call')->with('migrate', ['--force' => true])->andReturn(1);

    $step = new RunMigrations(app(DatabaseManager::class), app('migrator'), $artisan);

    expect($step->apply(wiSilentConsole()))->toBe(SetupOutcome::Failed);
});

it('runs before anything that would write a row', function () {
    // Order is correctness: the first administrator has nowhere to be written
    // until this has run.
    expect(rmStep()->sort())->toBe(100)
        ->and(rmStep()->label())->toBe('Database tables');
});

it('reports a migration that will not run, rather than dying on it', function () {
    // `migrate` answers a broken migration by throwing, not with an exit code —
    // a table that already exists, a column that does not, a duplicate published
    // copy of one the application already ran. Before this, the exception went
    // straight out of `wire:install` and printed a stack trace over the listing,
    // which is the exact failure `Blocked` exists to keep away from a spinner.
    rmIsolatingMigrations(function () {
        $body = (string) file_get_contents(rmPlant('rm_clash'));
        file_put_contents(database_path('migrations/2025_01_01_000000_create_rm_clash_table.php'), $body);

        $said = [];

        expect(rmStep()->apply(wiSayingConsole($said)))->toBe(SetupOutcome::Failed)
            ->and(implode("\n", $said))->toContain('already exists')
            ->and(implode("\n", $said))->toContain('Run php artisan migrate yourself');
    });
});

it('belongs to its own package, so unticking that package skips it', function () {
    // What the first half of the installer was told, the second half obeys.
    expect((new RunMigrations(app(DatabaseManager::class), app('migrator'), app(Kernel::class)))->package())->toBe('nyoncode/wire-suite');
});
