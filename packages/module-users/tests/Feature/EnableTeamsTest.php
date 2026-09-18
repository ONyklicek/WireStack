<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\EnvFile;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupRegistry;
use NyonCode\WireCore\Foundation\Setup\SetupState;
use NyonCode\WireModuleUsers\Install\EnableRoles;
use NyonCode\WireModuleUsers\Install\EnableTeams;

/*
 * Teams, which is a switch in the permission layer rather than a feature here.
 *
 * The team column is added by Spatie's own migration, which reads
 * `config('permission.teams')` **as it runs**. Answer this after `migrate` and
 * the config says teams while the tables say otherwise — roles save against a
 * column that is not there.
 */

/**
 * A console that answers from a script and records what it was told.
 *
 * @param  array<int, string>  $answers
 * @param  array<int, string>  $said
 */
function etConsole(array $answers = [], array &$said = [], bool $interactive = true, bool $yes = true): SetupConsole
{
    return new class($answers, $said, $interactive, $yes) implements SetupConsole
    {
        /**
         * @param  array<int, string>  $answers
         * @param  array<int, string>  $said
         */
        public function __construct(
            private array $answers,
            private array &$said,
            private bool $interactive,
            private bool $yes,
        ) {}

        public function ask(string $question, ?string $default = null): string
        {
            return (string) (array_shift($this->answers) ?? $default);
        }

        public function secret(string $question): string
        {
            return '';
        }

        public function confirm(string $question, bool $default = true): bool
        {
            return $this->yes;
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
            return $this->interactive;
        }
    };
}

/** An `.env` of its own, so a test never writes the one this suite runs on. */
function etEnv(): EnvFile
{
    $path = sys_get_temp_dir().'/wire-teams-'.getmypid().'-'.uniqid().'.env';
    file_put_contents($path, "APP_NAME=Laravel\n");

    register_shutdown_function(static fn () => @unlink($path));

    return new EnvFile($path);
}

/** The step over the real console kernel, where the permission layer is registered. */
function etStep(?EnvFile $env = null): EnableTeams
{
    return new EnableTeams($env ?? etEnv(), app(Kernel::class));
}

/** Spatie's permission config, in the shape it ships. */
function etPublishPermissionConfig(): void
{
    file_put_contents(config_path('permission.php'), "<?php\n\nreturn [\n    'teams' => false,\n];\n");
}

beforeEach(function () {
    @unlink(config_path('permission.php'));
    // The workbench's own permission tables are migrated, and a step that finds
    // them says so rather than switching teams on underneath them. Pointed at a
    // table nobody made, so the interesting half is reachable.
    config()->set('permission.table_names.roles', 'roles_that_do_not_exist');
});

afterEach(function () {
    @unlink(config_path('permission.php'));
});

it('is registered, so the installer offers it', function () {
    expect(SetupRegistry::instance()->all())->toContain(EnableTeams::class);
});

it('names itself and the package it belongs to', function () {
    $step = etStep();

    expect($step->label())->toBe('Teams')
        ->and($step->package())->toBe('nyoncode/wire-module-users');
});

it('runs before the roles step, whose installer migrates, and before the migrations', function () {
    // `permission-extended:install` ends in a `migrate` of its own. Asked after
    // it, this found the tables made on every real run and could only refuse.
    $roles = app(EnableRoles::class)->sort();

    expect(etStep()->sort())->toBeLessThan($roles)
        ->and(etStep()->sort())->toBeLessThan(100);
});

it('waits, rather than offering, where the permission layer is not installed', function () {
    $artisan = Mockery::mock(Kernel::class);
    $artisan->shouldReceive('all')->andReturn([]);

    $step = new EnableTeams(etEnv(), $artisan);

    expect($step->state())->toBe(SetupState::Blocked)
        ->and($step->summary())->toContain('composer require nyoncode/laravel-permission-extended');
});

it('is offered before anything is published, because it comes first', function () {
    expect(etStep()->state())->toBe(SetupState::Pending)
        ->and(etStep()->summary())->toContain('scope roles to teams');
});

it('publishes the permission config itself, then switches teams on in it and in this process', function () {
    // The migration that makes the tables reads the config this process loaded
    // at boot. A switch written only to the file would make them without the
    // column in the very run that asked for it.
    $env = etEnv();
    $said = [];

    expect(etStep($env)->apply(etConsole([stdClass::class, 'teams'], $said)))->toBe(SetupOutcome::Applied)
        ->and(is_file(config_path('permission.php')))->toBeTrue()
        ->and((string) file_get_contents(config_path('permission.php')))->toContain("'teams' => true,")
        ->and(config('permission.teams'))->toBeTrue()
        ->and(config('wire-module-users.teams.model'))->toBe(stdClass::class);
});

it('is pending once the config is there and teams are off', function () {
    etPublishPermissionConfig();

    $step = etStep();

    expect($step->state())->toBe(SetupState::Pending)
        ->and($step->summary())->toContain('scope roles to teams');
});

it('is done where the permission layer already has teams on', function () {
    etPublishPermissionConfig();
    config()->set('permission.teams', true);
    config()->set('wire-module-users.teams.model', stdClass::class);

    $step = etStep();

    expect($step->state())->toBe(SetupState::Done)
        ->and($step->summary())->toContain('roles are scoped to');
});

it('asks nobody and changes nothing when unattended', function () {
    // The honest default for "does this application have teams" is no.
    etPublishPermissionConfig();

    $said = [];

    expect(etStep()->apply(etConsole([], $said, false)))->toBe(SetupOutcome::Skipped)
        ->and((string) file_get_contents(config_path('permission.php')))->toContain("'teams' => false,");
});

it('leaves it alone when the answer is no', function () {
    etPublishPermissionConfig();
    $said = [];

    expect(etStep()->apply(etConsole([], $said, true, false)))->toBe(SetupOutcome::Skipped)
        ->and((string) file_get_contents(config_path('permission.php')))->toContain("'teams' => false,");
});

it('switches teams on, and remembers which model a team is', function () {
    etPublishPermissionConfig();

    $env = etEnv();
    $said = [];

    expect(etStep($env)->apply(etConsole([stdClass::class, 'teams'], $said)))->toBe(SetupOutcome::Applied)
        ->and((string) file_get_contents(config_path('permission.php')))->toContain("'teams' => true,")
        ->and($env->get('WIRE_USERS_TEAM_MODEL'))->toBe(stdClass::class)
        ->and(implode(' ', $said))->toContain('when they are migrated');
});

it('says so when the class named is not there yet', function () {
    etPublishPermissionConfig();
    $said = [];

    etStep()->apply(etConsole(['App\\Models\\Team', 'teams'], $said));

    expect(implode(' ', $said))->toContain('does not exist yet');
});

it('names the file to edit when the relation is not the default and the config is not published', function () {
    // `teams.relation` has no env behind it, so an application that never
    // published this module's config is told the line to change rather than
    // having a file invented for it.
    etPublishPermissionConfig();
    @unlink(config_path('wire-module-users.php'));
    $said = [];

    etStep()->apply(etConsole([stdClass::class, 'squads'], $said));

    expect(implode(' ', $said))->toContain("Set `teams.relation` to 'squads'");
});

it('writes the relation into the published config when there is one', function () {
    etPublishPermissionConfig();
    file_put_contents(config_path('wire-module-users.php'), "<?php\n\nreturn [\n    'teams' => [\n        'relation' => 'teams',\n    ],\n];\n");
    $said = [];

    etStep()->apply(etConsole([stdClass::class, 'squads'], $said));

    expect((string) file_get_contents(config_path('wire-module-users.php')))->toContain("'relation' => 'squads',")
        ->and(implode(' ', $said))->not->toContain('Set `teams.relation`');

    @unlink(config_path('wire-module-users.php'));
});

it('declines rather than lying, when the tables are already made without the column', function () {
    // The switch would say teams while the schema says otherwise, and the error
    // names a pivot table nobody has heard of.
    etPublishPermissionConfig();

    Schema::create('roles_already_made', function (Blueprint $table): void {
        $table->id();
    });

    config()->set('permission.table_names.roles', 'roles_already_made');
    $said = [];

    expect(etStep()->apply(etConsole([], $said)))->toBe(SetupOutcome::Failed)
        ->and(implode(' ', $said))->toContain('Roll them back')
        ->and((string) file_get_contents(config_path('permission.php')))->toContain("'teams' => false,");
});

it('says which file to edit when it cannot write the switch itself', function () {
    file_put_contents(config_path('permission.php'), "<?php\n\nreturn [];\n");
    $said = [];

    expect(etStep()->apply(etConsole([], $said)))->toBe(SetupOutcome::Failed)
        ->and(implode(' ', $said))->toContain('by hand');
});

it('says so when it cannot write the .env either', function () {
    etPublishPermissionConfig();
    $said = [];
    $absent = new EnvFile(sys_get_temp_dir().'/wire-teams-absent-'.uniqid().'.env');

    etStep($absent)->apply(etConsole([stdClass::class, 'teams'], $said));

    expect(implode(' ', $said))->toContain('WIRE_USERS_TEAM_MODEL');
});

it('takes tables made without teams as the answer, and does not ask again', function () {
    // "No" is an answer. Pending here would put the question to every later
    // run of an application that decided against teams long ago.
    etPublishPermissionConfig();

    Schema::create('roles_made_without_teams', function (Blueprint $table): void {
        $table->id();
    });

    config()->set('permission.table_names.roles', 'roles_made_without_teams');

    expect(etStep()->state())->toBe(SetupState::Done)
        ->and(etStep()->summary())->toContain('not scoped to teams');
});

it('names the missing model where teams are on and the tables are made', function () {
    etPublishPermissionConfig();

    Schema::create('roles_made_with_teams', function (Blueprint $table): void {
        $table->id();
    });

    config()->set('permission.table_names.roles', 'roles_made_with_teams');
    config()->set('permission.teams', true);
    config()->set('wire-module-users.teams.model', 'App\\Models\\NoSuchTeam');

    expect(etStep()->state())->toBe(SetupState::Done)
        ->and(etStep()->summary())->toContain('App\\Models\\NoSuchTeam` is not a class yet');
});

it('treats a database it cannot reach as one with no tables in it', function () {
    // This runs before `migrate` on a fresh application, where there may be no
    // connection to ask at all — and no connection is not a table.
    etPublishPermissionConfig();
    config()->set('database.connections.nowhere', ['driver' => 'sqlite', 'database' => '/nowhere/at/all.sqlite']);
    config()->set('database.default', 'nowhere');

    expect(etStep()->state())->toBe(SetupState::Pending)
        ->and(etStep()->summary())->toContain('scope roles to teams');
});

it('asks again for a relation that would break the config it is written into', function () {
    // The relation is PHP source in the published config: a quote in it left the
    // file a parse error.
    etPublishPermissionConfig();
    file_put_contents(config_path('wire-module-users.php'), "<?php\n\nreturn [\n    'teams' => [\n        'relation' => 'teams',\n    ],\n];\n");
    $said = [];

    etStep()->apply(etConsole([stdClass::class, "squad's", 'squads'], $said));

    expect((string) file_get_contents(config_path('wire-module-users.php')))->toContain("'relation' => 'squads',")
        ->and(implode(' ', $said))->toContain("Not a relation name: squad's");

    @unlink(config_path('wire-module-users.php'));
});

it('writes no relation after three it cannot use, and still switches teams on', function () {
    etPublishPermissionConfig();
    file_put_contents(config_path('wire-module-users.php'), "<?php\n\nreturn [\n    'teams' => [\n        'relation' => 'teams',\n    ],\n];\n");
    $said = [];

    expect(etStep()->apply(etConsole([stdClass::class, 'a b', 'c-d', 'e.f'], $said)))->toBe(SetupOutcome::Applied)
        ->and((string) file_get_contents(config_path('wire-module-users.php')))->toContain("'relation' => 'teams',");

    @unlink(config_path('wire-module-users.php'));
});

it('asks again for a team class that is not a class name, and gives up after three', function () {
    etPublishPermissionConfig();
    $env = etEnv();
    $said = [];

    etStep($env)->apply(etConsole(['Team Model', '\\App\\Models\\Team', 'teams'], $said));

    expect($env->get('WIRE_USERS_TEAM_MODEL'))->toBe('App\\Models\\Team')
        ->and(implode(' ', $said))->toContain('Not a class name: Team Model');

    $said = [];
    $env = etEnv();

    etStep($env)->apply(etConsole(['a b', 'c-d', "e'f"], $said));

    expect($env->get('WIRE_USERS_TEAM_MODEL'))->toBeNull()
        ->and(implode(' ', $said))->toContain('Set WIRE_USERS_TEAM_MODEL');
});
