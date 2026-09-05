<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Enums;

/**
 * The lifecycle points a plugin can listen to, named once.
 *
 * Hook names used to be bare strings at both ends — the `hook()` call that
 * registers and the `runHook()` call that dispatches — so a typo in either
 * produced a callback that never ran and a lifecycle point nobody listened to,
 * with nothing to grep and nothing to fail. This is the same canonical-vocabulary
 * move `Size`, `Color` and `IconPosition` already make for their domains.
 *
 * Strings stay valid everywhere an enum is accepted, and always will: a package
 * defines hook names of its own without asking this enum for a case, and 2.x
 * plugins were written before it existed.
 *
 * The **payload shape** is part of the name. A case documented as typed carries a
 * payload object through `runTypedHook()`; the seven cases that predate that are
 * dispatched both ways (`runHook()` with an array, then `runTypedHook()` with a
 * DTO) for backwards compatibility, and each callback belongs to exactly one of
 * the two — see `PluginManager::callbackExpectsArray()` for how that is decided.
 * New hooks are typed-only.
 */
enum Hook: string
{
    /**
     * After a table instance is composed for its host, before anything reads it.
     * Typed only.
     *
     * The one that changes what a table **is** — its columns and filters as
     * rendered, searched and sorted. {@see self::TableConfiguring} runs later and
     * on a copy the planner is about to consume, so it can steer a query and
     * cannot add a column anybody sees.
     */
    case TableComposing = 'table.composing';

    /** Before a table's columns and filters are finalized for the query planner. Array + typed. */
    case TableConfiguring = 'table.configuring';

    /** After the query plan is built, before the executor runs. Array + typed. */
    case TableQuerying = 'table.querying';

    /** After every query pipe has been applied. Array + typed. */
    case TableQueried = 'table.queried';

    /** Before a form's schema becomes its config. Typed only. */
    case FormConfiguring = 'form.configuring';

    /** Before validated form data is persisted. Array + typed. */
    case FormSaving = 'form.saving';

    /** After the record has been persisted. Array + typed. */
    case FormSaved = 'form.saved';

    /** Before the action pipeline executes. Array + typed. */
    case ActionExecuting = 'action.executing';

    /** After the action pipeline completes. Array + typed. */
    case ActionExecuted = 'action.executed';

    /**
     * The string a hook name resolves to, whichever form it arrived in.
     *
     * One place decides it, so every entry point — `hook()`, `runHook()`,
     * `runTypedHook()`, `hasHook()` — accepts both without repeating the check.
     */
    public static function name(self|string $hook): string
    {
        return $hook instanceof self ? $hook->value : $hook;
    }

    /**
     * Every shipped hook name.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
