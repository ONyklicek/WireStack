<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Setup\Contracts;

use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupState;

/**
 * One thing an application still has to do before the stack works.
 *
 * Every package installer in this framework already knows what is missing and
 * says so — "run migrate", "name an ability in `wire-module-users.permissions`",
 * "`wire-core.audit.enabled` is off — nothing is being recorded yet". Nine such
 * lines across seven packages, each a diagnosis with no action behind it. This
 * contract is the action: the package that can already tell says so through
 * {@see state()}, and then gets to fix it through {@see apply()}.
 *
 * The package owns both halves, because the package is what knows. The suite
 * only collects steps, orders them and asks — it never learns what a media disk
 * or a super-admin role is.
 *
 * ## Detect, ask, apply — in that order, always
 *
 * {@see state()} runs first and runs on every invocation, so a step is offered
 * only while it is needed and a second run of the installer is quiet. It must
 * not change anything: an installer that writes while answering "what is
 * missing" cannot be asked with `--dry-run`.
 *
 * {@see apply()} is reached only for {@see SetupState::Pending}, and only after
 * somebody said yes.
 *
 * ## Blocked is not Pending
 *
 * A step that cannot run — no database connection, no user model, a package
 * whose table is not migrated yet — answers {@see SetupState::Blocked} and says
 * why in {@see summary()}. It is reported and never offered. This is the
 * difference between an installer that explains itself and one that throws a
 * stack trace out of a question, and it is why `state()` may not assume the
 * thing it is about exists.
 */
interface SetupStep
{
    /**
     * What this step is called, in the listing and in the question.
     *
     * A noun phrase naming the outcome — "First administrator", "Database
     * tables" — rather than an imperative. It is read in a list of things that
     * are done as often as in a list of things to do.
     */
    public function label(): string;

    /**
     * Whether there is anything to do, and whether it can be done.
     *
     * Called on every run, including under `--dry-run`, so it must only look.
     */
    public function state(): SetupState;

    /**
     * One line: what this would do, or why it cannot.
     *
     * Read aloud after the label, so it completes the sentence rather than
     * repeating it — and for {@see SetupState::Blocked} it carries the whole
     * explanation, because nothing else will be printed.
     */
    public function summary(): string;

    /**
     * Do it.
     *
     * Reached only when {@see state()} said {@see SetupState::Pending} and the
     * step was chosen. Anything it needs to ask, it asks through the console it
     * is handed; anything it wants to report, it reports the same way.
     */
    public function apply(SetupConsole $console): SetupOutcome;

    /**
     * Where this sits among the others. Lower runs first.
     *
     * Order is correctness here, not presentation: creating the first
     * administrator before the tables exist is a step that cannot work, and
     * `100` apart leaves room for a package to slot between two of these
     * without anyone renumbering.
     */
    public function sort(): int;
}
