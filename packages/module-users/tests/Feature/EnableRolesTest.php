<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupRegistry;
use NyonCode\WireCore\Foundation\Setup\SetupState;
use NyonCode\WireModuleUsers\Install\EnableRoles;
use NyonCode\WireModuleUsers\Support\Roles;

/*
 * Roles, which this module has screens for and no implementation of.
 *
 * `wire-module-users.roles` is `auto`, and auto means "on where the permission
 * layer is actually wired up". Miss the trait on the user model and every role
 * save fails at `syncRoles()`, after the record has been written.
 */

/**
 * A console that records what it was told.
 *
 * @param  array<int, string>  $said
 */
function erConsole(array &$said = [], bool $interactive = true): SetupConsole
{
    return new class($said, $interactive) implements SetupConsole
    {
        /** @param array<int, string> $said */
        public function __construct(private array &$said, private bool $interactive) {}

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
            return $this->interactive;
        }
    };
}

function erArtisan(int $exitCode = 0): Kernel
{
    $artisan = Mockery::mock(Kernel::class);
    // Asked what is registered before anything is asked to run: a package in
    // `vendor` whose provider is not discovered has no command here.
    $artisan->shouldReceive('all')->andReturn(['permission-extended:install' => true]);
    $artisan->shouldReceive('call')
        ->with('permission-extended:install', ['--no-interaction' => true])
        ->andReturn($exitCode);

    return $artisan;
}

it('is registered, so the installer offers it', function () {
    expect(SetupRegistry::instance()->all())->toContain(EnableRoles::class);
});

it('names itself and the package it belongs to', function () {
    $step = new EnableRoles(erArtisan());

    expect($step->label())->toBe('Roles & permissions')
        ->and($step->package())->toBe('nyoncode/wire-module-users');
});

it('runs before the migrations, because it publishes one', function () {
    // Spatie's permission tables. A `migrate` that has already run does not come
    // back for a file that appeared afterwards.
    expect((new EnableRoles(erArtisan()))->sort())->toBeLessThan(100);
});

it('is done where the permission layer is wired up', function () {
    // Which is this workbench: the user model carries the extended trait and the
    // tables are migrated, so the role screens are on.
    expect(Roles::available())->toBeTrue()
        ->and((new EnableRoles(erArtisan()))->state())->toBe(SetupState::Done)
        ->and((new EnableRoles(erArtisan()))->summary())->toContain('role screens are on');
});

it('is pending where nothing has been published yet', function () {
    config()->set('wire-module-users.model', stdClass::class);

    $step = new EnableRoles(erArtisan());

    expect($step->state())->toBe(SetupState::Pending)
        ->and($step->summary())->toContain('publish the permission config');
});

it('names the trait once the config is there and the model still is not', function () {
    // The one that costs a written record: `syncRoles()` fails at the last step,
    // after the row has gone in.
    config()->set('wire-module-users.model', stdClass::class);
    file_put_contents(config_path('permission.php'), "<?php\n\nreturn [];\n");

    $step = new EnableRoles(erArtisan());

    expect($step->state())->toBe(SetupState::Pending)
        ->and($step->summary())->toContain('HasRoles');

    @unlink(config_path('permission.php'));
});

it('runs the permission package\'s own installer, rather than a second copy of it', function () {
    // `permission-extended:install` already publishes Spatie's config and
    // migration, publishes its own, and patches the user model. Two answers to
    // "is the model patched" disagree the first time either changes.
    $said = [];

    expect((new EnableRoles(erArtisan()))->apply(erConsole($said)))->toBe(SetupOutcome::Applied)
        ->and(implode(' ', $said))->toContain('HasRoles')
        ->and(implode(' ', $said))->toContain('migrated');
});

it('fails rather than pretending, when that installer does not finish', function () {
    $said = [];

    expect((new EnableRoles(erArtisan(1)))->apply(erConsole($said)))->toBe(SetupOutcome::Failed)
        ->and(implode(' ', $said))->toContain('permission-extended:install');
});

it('says what to require when the permission layer is not installed', function () {
    // Blocked rather than Pending: the answer is a composer line, never an
    // installer that shells out to composer inside the application it is about
    // to change. Bare `spatie/laravel-permission` is deliberately not enough —
    // the wildcard matching, the super-admin gate and the permission-change
    // events these screens assume live only in the extended package.
    $artisan = Mockery::mock(Kernel::class);
    $artisan->shouldReceive('all')->andReturn([]);

    $step = new EnableRoles($artisan);

    expect($step->state())->toBe(SetupState::Blocked)
        ->and($step->summary())->toContain('composer require nyoncode/laravel-permission-extended');
});
