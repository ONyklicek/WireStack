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
     * Before an infolist's schema is read for rendering. Typed only.
     *
     * The read-only half of {@see self::FormConfiguring}. A resource's detail
     * page is built inside the package that ships it, so this is what lets an
     * application add an entry to one without owning the class.
     */
    case InfolistConfiguring = 'infolist.configuring';

    /**
     * Before a host filters its widgets by visibility. Typed only.
     *
     * The list as declared, keys not yet stamped — so a widget added here is
     * numbered with the rest rather than colliding with one.
     */
    case WidgetConfiguring = 'widget.configuring';

    /**
     * After a table has resolved what it would export, before a row is read. Typed only.
     *
     * One point for both deliveries: a streamed download and a queued file go
     * through the same `buildTableExport()`, so an export that changed depending
     * on how it was delivered is not expressible.
     */
    case ExportConfiguring = 'export.configuring';

    /**
     * After the menu's entries are collected, before they are grouped or sorted. Typed only.
     *
     * Runs on the flat, keyed list, so it feeds `navigation()` and `items()`
     * alike — the alternative was two dispatch sites that would answer the same
     * question differently.
     */
    case NavigationBuilding = 'navigation.building';

    /**
     * When a resource page has finished mounting. Typed only.
     *
     * Last, deliberately: Livewire calls a component's own `mount()` before the
     * trait hooks, so the record is resolved and the form seeded by the time this
     * runs — which is what makes it useful rather than merely early.
     *
     * What a callback changes is the page's **public** surface; a page answers
     * every request after the mount from a snapshot that carries nothing else.
     */
    case PageMounting = 'page.mounting';

    /**
     * Before one resource's global-search query runs. Typed only.
     *
     * Per resource, not per term, so the payload can carry the builder — and so
     * `for:` names the resource whose search is being narrowed.
     */
    case SearchQuerying = 'search.querying';

    /**
     * After a table has resolved what it would import, before a row is read. Typed only.
     *
     * The other half of {@see self::ExportConfiguring}, and it needed no new
     * composition point: a queued import already re-enters through the same
     * `importTable()` the streamed one uses.
     */
    case ImportConfiguring = 'import.configuring';

    /**
     * Before an inline cell edit is written. Typed only.
     *
     * The one write path a table had with no mutating seam — `CellUpdating` and
     * `CellUpdated` are events, so they could watch a cell change and not stop
     * it. Runs after the column's own permission, conflict and validation
     * checks, so a callback narrows what is written and cannot widen past them.
     */
    case CellUpdating = 'cell.updating';

    /**
     * Before a form is filled from a record. Typed only.
     *
     * Forms could intercept the way out (`form.saving`) and not the way in: an
     * application could add a field to a module's form and not change what an
     * existing one arrives holding.
     */
    case FormFilling = 'form.filling';

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
