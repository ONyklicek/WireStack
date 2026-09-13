<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAudit\Install;

use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupStep;
use NyonCode\WireCore\Foundation\Setup\EnvFile;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupState;

/**
 * Recording, without which this module is a screen over an empty table.
 *
 * The installer already said it — "wire-core.audit.enabled is off — nothing is
 * being recorded yet" — and that is the one way to install this module and
 * conclude it does not work: the page renders, the filters work, and there is
 * nothing in it, because the switch that fills it lives in another package's
 * config.
 *
 * Written to `.env` rather than to `config/wire-core.php`: the key is
 * `env('WIRE_AUDIT_ENABLED', true)`, so the config file is the package's default
 * and `.env` is where this machine's answer belongs.
 */
final readonly class RecordAuditTrail implements SetupStep
{
    private const KEY = 'WIRE_AUDIT_ENABLED';

    public function __construct(private EnvFile $env) {}

    public function label(): string
    {
        return 'Audit recording';
    }

    public function state(): SetupState
    {
        if (config('wire-core.audit.enabled', true) === true) {
            return SetupState::Done;
        }

        return $this->env->exists() ? SetupState::Pending : SetupState::Blocked;
    }

    public function summary(): string
    {
        if (config('wire-core.audit.enabled', true) === true) {
            return 'changes are being recorded';
        }

        return $this->env->exists()
            ? 'switch recording on, or this module is a screen over an empty table'
            : 'recording is off and there is no .env to turn it on in';
    }

    public function apply(SetupConsole $console): SetupOutcome
    {
        if (! $this->env->set(self::KEY, 'true')) {
            $console->warn('Could not write .env — set '.self::KEY.'=true yourself.');

            return SetupOutcome::Failed;
        }

        $console->note('Recording is on. Add `HasAuditable` to the models you want followed.');

        return SetupOutcome::Applied;
    }

    public function sort(): int
    {
        return 600;
    }
}
