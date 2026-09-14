<?php

declare(strict_types=1);

namespace NyonCode\Wire\Install;

use Illuminate\Console\Command;
use Laravel\Prompts\Prompt;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
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
        $this->reclaim();

        $answer = trim(text(label: $question, placeholder: $default ?? ''));

        return $answer !== '' ? $answer : (string) $default;
    }

    public function secret(string $question): string
    {
        if (! $this->isInteractive()) {
            return '';
        }

        $this->reclaim();

        return password(label: $question);
    }

    public function confirm(string $question, bool $default = true): bool
    {
        if (! $this->isInteractive()) {
            return $default;
        }

        $this->reclaim();

        return confirm(label: $question, default: $default);
    }

    public function choose(string $question, array $options, ?string $default = null): string
    {
        if (! $this->isInteractive()) {
            return (string) $default;
        }

        $this->reclaim();

        return (string) select(label: $question, options: $options, default: $default);
    }

    public function select(string $question, array $options, array $default = []): array
    {
        if (! $this->isInteractive()) {
            return $default;
        }

        $this->reclaim();

        return multiselect(
            label: $question,
            options: $options,
            default: $default,
            // Same reason the installer's own lists are: a list that scrolls
            // hides half of what is already ticked, and the ticks are the point.
            scroll: 15,
            hint: 'Space unticks one, enter confirms.',
        );
    }

    public function note(string $message): void
    {
        $this->command->line("    <fg=gray>{$message}</>");
    }

    public function warn(string $message): void
    {
        $this->command->line("    <fg=yellow>{$message}</>");
    }

    /**
     * Point Laravel Prompts back at this command's terminal, and at a person.
     *
     * Prompts keeps two statics that every command run sets to its own: where
     * to draw, and whether anybody is there. A step that calls another command
     * through `Artisan::call()` — `fortify:install`, the package installers
     * before them — leaves both as that call left them, because only
     * `Command::call()` puts them back. Two failures, both measured on the first
     * runs in a real application:
     *
     * - the output pointed at the call's buffer, so every later question was
     *   drawn where nobody could see it and the wizard sat at it;
     * - after `permission-extended:install --no-interaction`, Prompts believed
     *   nobody was there, so every later question answered itself with its
     *   default — the routes were written without asking, and the first
     *   administrator was skipped for want of an e-mail address.
     *
     * Only called on the interactive path, where the input already said a
     * person is at a terminal.
     */
    private function reclaim(): void
    {
        Prompt::setOutput($this->command->getOutput());
        Prompt::interactive(true);
    }

    public function isInteractive(): bool
    {
        return $this->interactive;
    }
}
