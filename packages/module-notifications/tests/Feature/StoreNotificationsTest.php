<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\EnvFile;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupRegistry;
use NyonCode\WireCore\Foundation\Setup\SetupState;
use NyonCode\WireModuleNotifications\Install\StoreNotifications;

/*
 * The driver that writes a notification down, without which the bell is empty.
 *
 * Installed alone this module works perfectly and shows nothing, for ever —
 * because what makes history is a key in another package's config.
 */

/**
 * A console that answers from a script and records what it was told.
 *
 * @param  array<int, string>  $answers
 * @param  array<int, string>  $said
 */
function snConsole(array $answers = [], array &$said = [], bool $interactive = true): SetupConsole
{
    return new class($answers, $said, $interactive) implements SetupConsole
    {
        /**
         * @param  array<int, string>  $answers
         * @param  array<int, string>  $said
         */
        public function __construct(private array $answers, private array &$said, private bool $interactive) {}

        public function ask(string $question, ?string $default = null): string
        {
            return array_shift($this->answers) ?? (string) $default;
        }

        public function secret(string $question): string
        {
            return array_shift($this->answers) ?? '';
        }

        public function confirm(string $question, bool $default = true): bool
        {
            return $default;
        }

        public function choose(string $question, array $options, ?string $default = null): string
        {
            return array_shift($this->answers) ?? (string) $default;
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

function snEnv(string $contents = "APP_NAME=Laravel\n"): EnvFile
{
    $path = sys_get_temp_dir().'/wire-notif-env-'.getmypid().'-'.uniqid().'.env';
    file_put_contents($path, $contents);
    register_shutdown_function(static fn () => @unlink($path));

    return new EnvFile($path);
}

it('is contributed by this module', function () {
    expect(SetupRegistry::instance()->all())->toContain(StoreNotifications::class);
});

it('is done once notifications are stored', function () {
    config()->set('wire-core.notifications.default', ['session', 'database']);

    $step = new StoreNotifications(snEnv());

    expect($step->state())->toBe(SetupState::Done)
        ->and($step->summary())->toBe('notifications are being stored')
        ->and($step->label())->toBe('Stored notifications')
        ->and($step->sort())->toBe(700);
});

it('is pending while nothing is stored', function () {
    config()->set('wire-core.notifications.default', 'session');

    $step = new StoreNotifications(snEnv());

    expect($step->state())->toBe(SetupState::Pending)
        ->and($step->summary())->toContain('the bell has nothing to show');
});

it('adds the driver rather than replacing the one already there', function () {
    // `session` is the toast a person sees now; `database` is what survives the
    // request. Replacing one with the other trades a visible notification for a
    // stored one.
    config()->set('wire-core.notifications.default', 'session');
    $env = snEnv();
    $said = [];

    expect((new StoreNotifications($env))->apply(snConsole([], $said)))->toBe(SetupOutcome::Applied)
        ->and($env->get('WIRE_NOTIFICATIONS_DRIVER'))->toBe('session,database')
        ->and(implode("\n", $said))->toContain('session, database');
});

it('reads a list the environment wrote as one string', function () {
    // An env var carries a string and nothing else, so the list it can express
    // is comma separated — which is what wire-core splits it into.
    config()->set('wire-core.notifications.default', 'session,database');

    expect((new StoreNotifications(snEnv()))->state())->toBe(SetupState::Done);
});

it('keeps a configured array intact when it adds to it', function () {
    config()->set('wire-core.notifications.default', ['session', 'broadcast']);
    $env = snEnv();

    (new StoreNotifications($env))->apply(snConsole());

    expect($env->get('WIRE_NOTIFICATIONS_DRIVER'))->toBe('session,broadcast,database');
});

it('is blocked when there is no .env to change it in', function () {
    config()->set('wire-core.notifications.default', 'session');

    $step = new StoreNotifications(new EnvFile(sys_get_temp_dir().'/absent-'.uniqid().'.env'));

    expect($step->state())->toBe(SetupState::Blocked)
        ->and($step->summary())->toContain('no .env');
});

it('fails rather than pretending, when .env cannot be written', function () {
    config()->set('wire-core.notifications.default', 'session');
    $said = [];
    $step = new StoreNotifications(new EnvFile(sys_get_temp_dir().'/absent-'.uniqid().'.env'));

    expect($step->apply(snConsole([], $said)))->toBe(SetupOutcome::Failed)
        ->and(implode("\n", $said))->toContain('wire-core.notifications.default');
});

it('belongs to its own package, so unticking that package skips it', function () {
    // What the first half of the installer was told, the second half obeys.
    expect((new StoreNotifications(snEnv()))->package())->toBe('nyoncode/wire-module-notifications');
});
