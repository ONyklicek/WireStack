<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Preferences;

use NyonCode\WireCore\Core\State\StateContainer;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/**
 * What a saved view is, as a list of state paths.
 *
 * The table's state is one bag with everything in it — the sort, the filters,
 * the open modal, the current selection — and only some of that is "how I like
 * to look at this table". This class is the one place that says which, so the
 * answer cannot differ between saving and restoring.
 *
 * ## What is deliberately not in it
 *
 * - `selection` — a selection is about records, not about a view, and restoring
 *   someone's saved selection would tick boxes they never ticked in this
 *   session. Worse, a saved `mode: all` would mean "everything the filter
 *   matches" against a filter set that has since moved on.
 * - `modal` — an open modal is where the user is standing, not a layout.
 * - `pagination.cursor` and `rows.expanded` — a position and a per-record
 *   toggle. Both name records that may not be in the result set any more.
 * - `ready` — the lazy-load latch, which belongs to this request only.
 *
 * Everything captured is sanitised on the way back in, because a saved view can
 * outlive the columns it names: {@see applyTo()}.
 */
final class TableViewPayload
{
    /**
     * The state paths a view carries.
     *
     * @var array<int, string>
     */
    public const PATHS = [
        'sort.column',
        'sort.direction',
        'pagination.perPage',
        'search',
        'filters',
        'columnFilters',
        'columns.hidden',
        'rows.expandAll',
        'rows.subRowFilters',
        'rows.subRowSort',
        'summary.scope',
    ];

    /**
     * Read the current view out of the table state.
     *
     * Paths that were never set are left out rather than stored as null, so
     * restoring a view only touches what the view actually chose.
     *
     * @return array<string, mixed>
     */
    public static function capture(StateContainer $state): array
    {
        $payload = [];

        foreach (self::PATHS as $path) {
            if ($state->has($path)) {
                $payload[$path] = $state->get($path);
            }
        }

        return $payload;
    }

    /**
     * Put a stored view back onto the state.
     *
     * The hidden-column set is intersected with the columns that exist and are
     * still toggleable, the same way {@see WithTable::loadViewPreferences()}
     * does it: a view saved a year ago can name a column that has since been
     * renamed, removed, or made non-toggleable, and hiding by a stale name would
     * either do nothing or hide the wrong thing.
     *
     * Unknown paths in a stored payload are ignored rather than written. A bag
     * from a newer version of this class is then merely incomplete here, instead
     * of seeding state the table does not understand.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function applyTo(array $payload, StateContainer $state, Table $table): void
    {
        foreach (self::PATHS as $path) {
            if (! array_key_exists($path, $payload)) {
                continue;
            }

            $value = $payload[$path];

            if ($path === 'columns.hidden') {
                $value = self::sanitiseHidden(is_array($value) ? $value : [], $table);
            }

            $state->set($path, $value);
        }
    }

    /**
     * Keep only the names that still belong to a toggleable, viewable column.
     *
     * @param  array<int, mixed>  $hidden
     * @return array<int, string>
     */
    private static function sanitiseHidden(array $hidden, Table $table): array
    {
        $toggleable = [];

        foreach ($table->getColumns() as $column) {
            if ($column->isToggleable() && $column->canView()) {
                $toggleable[] = $column->getName();
            }
        }

        return array_values(array_intersect($hidden, $toggleable));
    }
}
