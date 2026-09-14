<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Laravel\Fortify\FortifyServiceProvider;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupRegistry;
use NyonCode\WireCore\Foundation\Setup\SetupState;
use NyonCode\WireModuleAuth\Install\ConfigureFortify;

/*
 * Fortify, installed and turned down to what this application actually offers.
 *
 * This package answers Fortify's seven view callbacks and owns none of the
 * security behind them — which leaves an installation with a gap nothing
 * reports: the screens are registered, the routes are not, and the sign-in page
 * is a 404 until somebody runs `fortify:install` by hand.
 */

/**
 * A console that answers from a script and records what it was told.
 *
 * @param  array<int, mixed>  $answers
 * @param  array<int, string>  $said
 * @param  array<string, mixed>  $offered  What the last `select()` put on offer.
 */
function cfConsole(array $answers = [], array &$said = [], bool $interactive = true, array &$offered = []): SetupConsole
{
    return new class($answers, $said, $interactive, $offered) implements SetupConsole
    {
        /**
         * @param  array<int, mixed>  $answers
         * @param  array<int, string>  $said
         * @param  array<string, mixed>  $offered
         */
        public function __construct(private array $answers, private array &$said, private bool $interactive, private array &$offered) {}

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
            $this->offered = ['options' => $options, 'default' => $default];
            $answer = array_shift($this->answers);

            return is_array($answer) ? $answer : $default;
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

/** An artisan that reports whatever the test wants of `fortify:install`. */
function cfArtisan(int $exitCode = 0, ?string $writes = null): Kernel
{
    $artisan = Mockery::mock(Kernel::class);
    // The step asks what is registered before it asks anything to run: a
    // package in `vendor` whose provider is not discovered has no command here.
    $artisan->shouldReceive('all')->andReturn(['fortify:install' => true]);
    $artisan->shouldReceive('call')->with('fortify:install')->andReturnUsing(
        static function () use ($exitCode, $writes): int {
            if ($writes !== null) {
                file_put_contents(config_path('fortify.php'), $writes);
            }

            return $exitCode;
        }
    );

    return $artisan;
}

/**
 * The config `fortify:install` publishes — read from Fortify's own stub.
 *
 * Not a copy of it. The copy these tests started with was the config Fortify
 * *merges*, a different file with every feature on one live line, and the step
 * passed against it while a real application offered two features out of five.
 */
function cfShippedConfig(): string
{
    $provider = (new ReflectionClass(FortifyServiceProvider::class))->getFileName();

    return (string) file_get_contents(dirname((string) $provider).'/../stubs/fortify.php');
}

beforeEach(function () {
    @unlink(config_path('fortify.php'));
});

afterEach(function () {
    @unlink(config_path('fortify.php'));
});

it('is registered, so the installer offers it', function () {
    expect(SetupRegistry::instance()->all())->toContain(ConfigureFortify::class);
});

it('is pending while fortify has published nothing', function () {
    $step = new ConfigureFortify(cfArtisan());

    expect($step->state())->toBe(SetupState::Pending)
        ->and($step->summary())->toContain('publish its config')
        ->and($step->label())->toBe('Fortify')
        ->and($step->package())->toBe('nyoncode/wire-module-auth');
});

it('is done once the config is there', function () {
    file_put_contents(config_path('fortify.php'), cfShippedConfig());

    expect((new ConfigureFortify(cfArtisan()))->state())->toBe(SetupState::Done)
        ->and((new ConfigureFortify(cfArtisan()))->summary())->toContain('routes are registered');
});

it('runs before the migrations, because fortify ships one', function () {
    // Fortify's own migration adds the two-factor columns to the users table,
    // and a `migrate` that has already run does not come back for it.
    expect((new ConfigureFortify(cfArtisan()))->sort())->toBeLessThan(100);
});

it('publishes fortify, and says what it did', function () {
    $said = [];
    $step = new ConfigureFortify(cfArtisan(0, cfShippedConfig()));

    expect($step->apply(cfConsole([], $said, false)))->toBe(SetupOutcome::Applied)
        ->and(implode(' ', $said))->toContain('config/fortify.php');
});

it('fails rather than pretending, when fortify:install does not finish', function () {
    $said = [];
    $step = new ConfigureFortify(cfArtisan(1));

    expect($step->apply(cfConsole([], $said, false)))->toBe(SetupOutcome::Failed)
        ->and(implode(' ', $said))->toContain('fortify:install');
});

it('changes nothing when nobody can be asked', function () {
    // Switching a feature without being asked is a sign-in page that has
    // silently lost its password reset. Unattended, the file stays as published.
    $said = [];
    $step = new ConfigureFortify(cfArtisan(0, cfShippedConfig()));
    $step->apply(cfConsole([], $said, false));

    expect((string) file_get_contents(config_path('fortify.php')))->toBe(cfShippedConfig());
});

it('offers every feature the published config lists, ticked as it has them', function () {
    // E-mail verification ships commented out and two-factor and passkeys open
    // an options array over several lines: all five are still a question.
    $said = [];
    $offered = [];
    $step = new ConfigureFortify(cfArtisan(0, cfShippedConfig()));

    $console = cfConsole([], $said, true, $offered);
    $step->apply($console);

    expect(array_keys($offered['options']))->toBe([
        'registration',
        'resetPasswords',
        'emailVerification',
        'twoFactorAuthentication',
        'passkeys',
    ])->and($offered['default'])->toBe(['registration', 'resetPasswords', 'twoFactorAuthentication', 'passkeys']);
});

it('switches features off and on, a whole options block at a time', function () {
    $said = [];
    $step = new ConfigureFortify(cfArtisan(0, cfShippedConfig()));

    $step->apply(cfConsole([['resetPasswords', 'emailVerification', 'twoFactorAuthentication']], $said));

    $config = (string) file_get_contents(config_path('fortify.php'));

    expect($config)->toContain('        // Features::registration(),')
        ->toContain("\n        Features::emailVerification(),")
        ->toContain("        // Features::passkeys([\n        //     'confirmPassword' => true,\n        // ]),")
        ->toContain("\n        Features::twoFactorAuthentication([")
        // The two the profile cards are built on are never offered, so they are
        // never switched off behind somebody's back.
        ->toContain("\n        Features::updateProfileInformation(),")
        ->and(implode(' ', $said))->toContain('Switched on: emailVerification')
        ->and(implode(' ', $said))->toContain('Switched off: registration, passkeys');

    // And the result is still a config file PHP will load.
    expect(fn () => require config_path('fortify.php'))->not->toThrow(Throwable::class);
});

it('leaves an application that rewrote the array alone, and says so', function () {
    // A regular expression that keeps looking through somebody's edited config
    // eventually matches the wrong thing.
    $said = [];
    $step = new ConfigureFortify(cfArtisan(0, "<?php\n\nreturn ['features' => Features::all()];\n"));

    $step->apply(cfConsole([['registration']], $said));

    expect(implode(' ', $said))->toContain('its own feature list');
});

it('does not comment out a comment on a second run', function () {
    $said = [];
    $step = new ConfigureFortify(cfArtisan(0, cfShippedConfig()));

    $step->apply(cfConsole([[]], $said));
    $once = (string) file_get_contents(config_path('fortify.php'));

    (new ConfigureFortify(cfArtisan()))->apply(cfConsole([[]], $said));

    expect((string) file_get_contents(config_path('fortify.php')))->toBe($once);
});

it('puts a block back exactly as it was, comments inside it included', function () {
    // Two-factor ships with `// 'window' => 0,` inside its options. Off and on
    // again must not lose that comment or double it.
    $said = [];
    $step = new ConfigureFortify(cfArtisan(0, cfShippedConfig()));

    $step->apply(cfConsole([['registration', 'resetPasswords', 'passkeys']], $said));
    (new ConfigureFortify(cfArtisan()))->apply(cfConsole([['registration', 'resetPasswords', 'twoFactorAuthentication', 'passkeys']], $said));

    expect((string) file_get_contents(config_path('fortify.php')))->toBe(cfShippedConfig());
});

it('says what to require when fortify is not installed at all', function () {
    // Blocked rather than Pending: the answer is a composer line, never an
    // installer that shells out to composer inside the application it is about
    // to change.
    $artisan = Mockery::mock(Kernel::class);
    $artisan->shouldReceive('all')->andReturn([]);

    $step = new ConfigureFortify($artisan);

    expect($step->state())->toBe(SetupState::Blocked)
        ->and($step->summary())->toContain('composer require laravel/fortify');
});

it('touches nothing when every feature was kept', function () {
    $said = [];
    $step = new ConfigureFortify(cfArtisan(0, cfShippedConfig()));

    $step->apply(cfConsole([[
        'registration',
        'resetPasswords',
        'twoFactorAuthentication',
        'passkeys',
    ]], $said));

    expect((string) file_get_contents(config_path('fortify.php')))->toBe(cfShippedConfig())
        ->and(implode(' ', $said))->not->toContain('Switched');
});

it('says where to switch them when it cannot write the file', function () {
    $said = [];
    $step = new ConfigureFortify(cfArtisan(0, cfShippedConfig()));

    // Published, then made read-only — which is an application whose config is
    // owned by root, or deployed from an image. The second run is handed an
    // artisan that writes nothing, because `fortify:install` would have hit the
    // same wall one step earlier.
    $step->apply(cfConsole([[
        'registration',
        'resetPasswords',
        'twoFactorAuthentication',
        'passkeys',
    ]], $said));

    chmod(config_path('fortify.php'), 0444);
    (new ConfigureFortify(cfArtisan()))->apply(cfConsole([['registration']], $said));
    chmod(config_path('fortify.php'), 0644);

    expect(implode(' ', $said))->toContain('by hand');
})->skipOnWindows();
