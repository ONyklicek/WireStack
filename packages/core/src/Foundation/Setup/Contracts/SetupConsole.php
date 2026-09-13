<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Setup\Contracts;

/**
 * What a setup step may ask, and how it reports back.
 *
 * A contract rather than the artisan command itself, for two reasons that both
 * bite. A step is the interesting thing to test — "does it create the role when
 * the permission package is there" — and a step that takes a `Command` can only
 * be tested through a console; here it takes a stub that answers in order.
 *
 * And it keeps the surface honest. A step handed a `Command` can call
 * `$command->call('migrate:fresh')`, read `$command->option('force')` or write
 * a table; handed this, it can ask four kinds of question and say two kinds of
 * thing. Everything else it wants, it has to have been given.
 *
 * ## Under `--no-interaction`
 *
 * {@see isInteractive()} is false, and then **nothing here may block**. `ask`
 * returns its default, `confirm` returns its default, and `secret` returns an
 * empty string — a step that cannot proceed on defaults has to check
 * `isInteractive()` and decline rather than stand at a prompt nobody is
 * watching. That is the same rule `wire:install` holds for the installers it
 * runs, one level down.
 */
interface SetupConsole
{
    /**
     * Ask for a value, offering a default.
     *
     * Returns the default unattended, so a step that has no sensible default
     * must not call this without checking {@see isInteractive()} first.
     */
    public function ask(string $question, ?string $default = null): string;

    /**
     * Ask for something that must not be echoed, or appear in a shell history.
     *
     * Returns an empty string unattended. A step wanting a password has to
     * decide what that means — generate one and print it, or decline — rather
     * than writing an empty one.
     */
    public function secret(string $question): string;

    /**
     * Ask a yes/no question.
     */
    public function confirm(string $question, bool $default = true): bool;

    /**
     * Ask for one of a fixed set.
     *
     * @param  array<int|string, string>  $options  Value => label, or a plain list.
     */
    public function choose(string $question, array $options, ?string $default = null): string;

    /**
     * Say what happened. One line, already indented by the caller's layout.
     */
    public function note(string $message): void;

    /**
     * Say what went wrong, or what was left half-done.
     */
    public function warn(string $message): void;

    /**
     * Whether there is anybody to answer a question.
     */
    public function isInteractive(): bool;
}
