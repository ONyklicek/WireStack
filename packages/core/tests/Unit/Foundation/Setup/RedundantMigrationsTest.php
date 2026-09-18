<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use NyonCode\WireCore\Foundation\Setup\MigrationFootprint;
use NyonCode\WireCore\Foundation\Setup\RedundantMigrations;

/*
 * Migrations an installer publishes that the application already has.
 *
 * `vendor:publish` knows a migration by its stamped file name, so every
 * installer that publishes one writes it into applications that already have
 * its tables — and the next `migrate` stops on "table already exists".
 */

function rmMigration(string $table, string $body = '$table->id();', string $call = 'create'): string
{
    return "<?php\n\nreturn new class {\n    public function up(): void\n    {\n        Schema::{$call}('{$table}', function (Blueprint \$table) {\n            {$body}\n        });\n    }\n\n    public function down(): void\n    {\n        Schema::dropIfExists('{$table}');\n    }\n};\n";
}

function rmDirectory(): string
{
    $directory = sys_get_temp_dir().'/wire-redundant-'.uniqid();
    mkdir($directory);

    return $directory;
}

afterEach(function () {
    foreach (glob(database_path('migrations/2099_*.php')) ?: [] as $file) {
        @unlink($file);
    }
});

// ---------------------------------------------------------------------------
// Footprint
// ---------------------------------------------------------------------------

it('reads the tables a migration creates and the columns it adds, from up() alone', function () {
    $footprint = MigrationFootprint::read(<<<'PHP'
        <?php
        return new class {
            public function up(): void
            {
                Schema::create('teams', function (Blueprint $table) {
                    $table->id();
                    $table->string('name');
                    $table->morphs('owner');
                    $table->unique('name');
                });

                Schema::table('users', function (Blueprint $table) {
                    $table->text('two_factor_secret')->after('password');
                    $table->foreignId('team_id')->nullable();
                    $table->index('team_id');
                    $table->dropColumn('legacy');
                    $table->renameColumn('a', 'b');
                });
            }

            public function down(): void
            {
                Schema::table('users', fn (Blueprint $table) => $table->string('restored'));
            }
        };
        PHP);

    expect($footprint->creates)->toBe(['teams'])
        ->and($footprint->adds)->toBe(['users' => ['two_factor_secret', 'team_id']])
        ->and($footprint->columns)->toBe(['teams' => ['name'], 'users' => ['two_factor_secret', 'team_id']])
        ->and($footprint->isEmpty())->toBeFalse();
});

it('knows nothing about a table named from config, and nothing is never already there', function () {
    $footprint = MigrationFootprint::read("<?php\nreturn new class { public function up(): void { Schema::create(\$tableNames['roles'], fn (\$t) => \$t->id()); } };");

    expect($footprint->isEmpty())->toBeTrue()
        ->and($footprint->coveredBy([MigrationFootprint::read(rmMigration('roles'))]))->toBeFalse()
        ->and(MigrationFootprint::read('<?php // not a migration')->isEmpty())->toBeTrue();
});

it('is covered only when the others make all of it', function () {
    $fortify = MigrationFootprint::read(rmMigration('users', "\$table->text('two_factor_secret'); \$table->text('two_factor_recovery_codes');", 'table'));

    expect($fortify->coveredBy([MigrationFootprint::read(rmMigration('users', "\$table->id(); \$table->text('two_factor_secret'); \$table->text('two_factor_recovery_codes');"))]))->toBeTrue()
        ->and($fortify->coveredBy([MigrationFootprint::read(rmMigration('users', "\$table->text('two_factor_secret');", 'table'))]))->toBeFalse()
        ->and(MigrationFootprint::read(rmMigration('passkeys'))->coveredBy([MigrationFootprint::read(rmMigration('teams'))]))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Leaving out
// ---------------------------------------------------------------------------

it('leaves out a published migration whose table is already in the database', function () {
    // `schema:dump --prune`: the tables are there, the files that made them are not.
    Schema::create('wire_redundant_probe', fn (Blueprint $table) => $table->id());
    $left = [];

    $result = app(RedundantMigrations::class)->around(
        function (): string {
            file_put_contents(database_path('migrations/2099_01_01_000000_create_wire_redundant_probe_table.php'), rmMigration('wire_redundant_probe'));

            return 'published';
        },
        function (string $name) use (&$left): void {
            $left[] = $name;
        },
    );

    expect($result)->toBe('published')
        ->and($left)->toBe(['create_wire_redundant_probe_table'])
        ->and(glob(database_path('migrations/2099_*.php')))->toBe([]);
});

it('leaves out a migration another registered one already carries, by name or by schema', function () {
    $own = rmDirectory();
    file_put_contents($own.'/2026_05_26_099000_create_permission_tables.php', '<?php // the workbench copy');
    file_put_contents($own.'/2026_01_01_000000_create_passkeys_table.php', rmMigration('passkeys'));
    app('migrator')->path($own);
    $left = [];

    app(RedundantMigrations::class)->around(function (): void {
        file_put_contents(database_path('migrations/2099_01_01_000000_create_permission_tables.php'), '<?php // Spatie');
        file_put_contents(database_path('migrations/2099_01_01_000001_create_webauthn_passkeys_table.php'), rmMigration('passkeys'));
    }, function (string $name) use (&$left): void {
        $left[] = $name;
    });

    expect($left)->toEqualCanonicalizing(['create_permission_tables', 'create_webauthn_passkeys_table'])
        ->and(glob(database_path('migrations/2099_*.php')))->toBe([]);

    array_map('unlink', glob($own.'/*.php') ?: []);
    rmdir($own);
});

it('keeps what nothing else makes, what only partly overlaps, and every file that was there before', function () {
    Schema::create('wire_redundant_half', fn (Blueprint $table) => $table->id());
    $before = database_path('migrations/2099_01_01_000000_create_wire_redundant_half_table.php');
    file_put_contents($before, rmMigration('wire_redundant_half'));
    $left = [];

    app(RedundantMigrations::class)->around(function (): void {
        file_put_contents(database_path('migrations/2099_01_01_000001_create_wire_redundant_new_table.php'), rmMigration('wire_redundant_new'));
        file_put_contents(database_path('migrations/2099_01_01_000002_add_bits_to_wire_redundant_half.php'), rmMigration('wire_redundant_half', "\$table->string('id'); \$table->string('missing');", 'table'));
    }, function (string $name) use (&$left): void {
        $left[] = $name;
    });

    expect($left)->toBe([])
        ->and(glob(database_path('migrations/2099_*.php')))->toHaveCount(3);
});

it('leaves one out the moment its tag is published, before the same command can migrate', function () {
    // The permission package's installer publishes and then runs `migrate`
    // itself: by the time it returns, the duplicate has already failed.
    Schema::create('wire_redundant_tagged', fn (Blueprint $table) => $table->id());
    $source = rmDirectory();
    file_put_contents($source.'/2020_01_01_000000_create_wire_redundant_tagged_table.php', rmMigration('wire_redundant_tagged'));
    ServiceProvider::$publishes['WireRedundantProbe'] = [$source => database_path('migrations')];
    ServiceProvider::$publishGroups['wire-redundant-probe'] = [$source => database_path('migrations')];
    $seenDuringCommand = null;
    $leftDuringCommand = null;
    $left = [];

    app(RedundantMigrations::class)->around(function () use (&$seenDuringCommand, &$leftDuringCommand, &$left): void {
        Artisan::call('vendor:publish', ['--tag' => 'wire-redundant-probe']);
        $seenDuringCommand = glob(database_path('migrations/*_create_wire_redundant_tagged_table.php'));
        $leftDuringCommand = $left;
    }, function (string $name) use (&$left): void {
        $left[] = $name;
    });

    expect($leftDuringCommand)->toBe(['create_wire_redundant_tagged_table'])
        ->and($seenDuringCommand)->toBe([]);

    unset(ServiceProvider::$publishes['WireRedundantProbe'], ServiceProvider::$publishGroups['wire-redundant-probe']);
    array_map('unlink', glob($source.'/*.php') ?: []);
    rmdir($source);
});

it('takes the footprint it is told for a migration it cannot read', function () {
    Schema::create('wire_redundant_roles', fn (Blueprint $table) => $table->id());
    $migrations = app(RedundantMigrations::class);
    $file = database_path('migrations/2099_01_01_000000_create_wire_redundant_roles_tables.php');
    file_put_contents($file, "<?php // Schema::create(\$tableNames['roles'])");

    expect($migrations->alreadyThere($file))->toBeFalse()
        ->and($migrations->alreadyThere($file, ['create_wire_redundant_roles_tables' => new MigrationFootprint(creates: ['wire_redundant_roles'])]))->toBeTrue();
});

it('does not take no database for a table that is there', function () {
    config()->set('database.connections.nowhere', ['driver' => 'sqlite', 'database' => '/nowhere/at/all.sqlite']);
    config()->set('database.default', 'nowhere');
    $file = database_path('migrations/2099_01_01_000000_create_wire_redundant_nowhere_table.php');
    file_put_contents($file, rmMigration('wire_redundant_nowhere'));

    expect(app(RedundantMigrations::class)->alreadyThere($file))->toBeFalse();
});

it('names a migration by what is under its stamp', function () {
    $migrations = app(RedundantMigrations::class);

    expect($migrations->name('/x/2026_09_15_120000_create_audit_logs_table.php'))->toBe('create_audit_logs_table')
        ->and($migrations->name('/x/create_audit_logs_table.php'))->toBe('create_audit_logs_table');
});
