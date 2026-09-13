<?php

declare(strict_types=1);

namespace NyonCode\Wire\Install;

use Illuminate\Console\Command;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * The console a setup step is handed, over the command actually running.
 *
 * The adapter exists so a step can be tested without one. `wire-module-users`
 * owns "create the first administrator" and that is worth a test with a real
 * database and no terminal anywhere near it — so the step takes
 * {@see SetupConsole}, and this is the only class in the stack that knows the
 * answers come from a person.
 *
 * **Unattended, nothing here blocks.** Laravel Prompts would happily stand at a
 * question with no tty until something killed it, so every method short-circuits
 * to its default when the input is not interactive. A step that cannot proceed
 * on defaults is expected to check {@see isInteractive()} and decline — which is
 * the same rule `wire:install` holds for the installers it runs.
 */
final readonly class CommandConsole implements SetupConsole
{
    /**
     * @param  bool  $interactive  Taken once rather than read back off the
     *                             command: `Illuminate\Console\Command` keeps
     *                             its input protected, and whether somebody is
     *                             there to answer does not change mid-run.
     */
    public function __construct(private Command $command, private bool $interactive) {}

    public function ask(string $question, ?string $default = null): string
    {
        if (! $this->isInteractive()) {
            return (string) $default;
        }

        // A placeholder, not a default. Prompts pre-fills `default:` into the
        // buffer and leaves the cursor after it, so somebody who types a name
        // gets `AdministratorOndrej Nyklicek` — measured, on the first run that
        // ever reached this prompt. The fallback happens here instead, where an
        // empty answer means "the one you offered".
        $answer = trim(text(label: $question, placeholder: $default ?? ''));

        return $answer !== '' ? $answer : (string) $default;
    }

    public function secret(string $question): string
    {
        if (! $this->isInteractive()) {
            return '';
        }

        return password(label: $question);
    }

    public function confirm(string $question, bool $default = true): bool
    {
        if (! $this->isInteractive()) {
            return $default;
        }

        return confirm(label: $question, default: $default);
    }

    public function choose(string $question, array $options, ?string $default = null): string
    {
        if (! $this->isInteractive()) {
            return (string) $default;
        }

        return (string) select(label: $question, options: $options, default: $default);
    }

    public function note(string $message): void
    {
        $this->command->line("    <fg=gray>{$message}</>");
    }

    public function warn(string $message): void
    {
        $this->command->line("    <fg=yellow>{$message}</>");
    }

    public function isInteractive(): bool
    {
        return $this->interactive;
    }
}
