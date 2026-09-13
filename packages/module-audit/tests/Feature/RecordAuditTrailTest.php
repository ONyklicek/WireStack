<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\EnvFile;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupRegistry;
use NyonCode\WireCore\Foundation\Setup\SetupState;
use NyonCode\WireModuleAudit\Install\RecordAuditTrail;

/*
 * Recording, without which this module is a screen over an empty table.
 *
 * The installer already said "wire-core.audit.enabled is off — nothing is being
 * recorded yet", and that is the one way to install this module and conclude it
 * does not work: the page renders, the filters work, and it is empty because the
 * switch that fills it lives in another package's config.
 */

/**
 * A console that answers from a script and records what it was told.
 *
 * @param  array<int, string>  $answers
 * @param  array<int, string>  $said
 */
function ratConsole(array $answers = [], array &$said = [], bool $interactive = true): SetupConsole
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

function ratEnv(string $contents = "APP_NAME=Laravel\n"): EnvFile
{
    $path = sys_get_temp_dir().'/wire-audit-env-'.getmypid().'-'.uniqid().'.env';
    file_put_contents($path, $contents);
    register_shutdown_function(static fn () => @unlink($path));

    return new EnvFile($path);
}

it('is contributed by this module', function () {
    expect(SetupRegistry::instance()->all())->toContain(RecordAuditTrail::class);
});

it('is done while recording is on', function () {
    config()->set('wire-core.audit.enabled', true);

    $step = new RecordAuditTrail(ratEnv());

    expect($step->state())->toBe(SetupState::Done)
        ->and($step->summary())->toBe('changes are being recorded')
        ->and($step->label())->toBe('Audit recording')
        ->and($step->sort())->toBe(600);
});

it('is pending while recording is off', function () {
    config()->set('wire-core.audit.enabled', false);

    $step = new RecordAuditTrail(ratEnv());

    expect($step->state())->toBe(SetupState::Pending)
        ->and($step->summary())->toContain('screen over an empty table');
});

it('is blocked when there is no .env to turn it on in', function () {
    config()->set('wire-core.audit.enabled', false);

    $step = new RecordAuditTrail(new EnvFile(sys_get_temp_dir().'/absent-'.uniqid().'.env'));

    expect($step->state())->toBe(SetupState::Blocked)
        ->and($step->summary())->toContain('no .env');
});

it('writes the switch, and says what is still the developer\'s to do', function () {
    config()->set('wire-core.audit.enabled', false);
    $env = ratEnv();
    $said = [];

    expect((new RecordAuditTrail($env))->apply(ratConsole([], $said)))->toBe(SetupOutcome::Applied)
        ->and($env->get('WIRE_AUDIT_ENABLED'))->toBe('true')
        ->and(implode("\n", $said))->toContain('HasAuditable');
});

it('fails rather than pretending, when .env cannot be written', function () {
    $said = [];
    $step = new RecordAuditTrail(new EnvFile(sys_get_temp_dir().'/absent-'.uniqid().'.env'));

    expect($step->apply(ratConsole([], $said)))->toBe(SetupOutcome::Failed)
        ->and(implode("\n", $said))->toContain('WIRE_AUDIT_ENABLED=true');
});
