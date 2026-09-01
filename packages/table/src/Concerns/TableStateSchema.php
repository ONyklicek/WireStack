<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Illuminate\Support\Arr;
use NyonCode\WireTable\Table;

/**
 * Defines the default state schema for table components.
 *
 * Maps all table state paths to their default values.
 * Used by StateContainer to initialize table state.
 */
final class TableStateSchema
{
    /** @var array<string, string>|null Memoized legacy map — __get/__set run per property access. */
    private static ?array $legacyPropertyMap = null;

    /**
     * The state a specific table starts in: {@see defaults()} with everything
     * the table's own configuration decides applied over it.
     *
     * Pure, and that is the point. This used to be eighty-five lines inside
     * `WithTable::mountWithTable()`, where the rules below could only be
     * observed by mounting a Livewire component — and two of them are rules
     * about a silent failure, which is the worst kind to leave unasserted.
     *
     * **Every rendered filter gets a slot, not only the ones with a default.**
     * Non-native filters bind through `$wire.entangle()`, and Livewire's
     * entangle silently no-ops when the path is undefined at render — so a
     * filter without a default would never reach the server at all. A null
     * value stays inactive everywhere: `apply()` ignores it and it does not
     * count as an active filter.
     *
     * **A multi-select column filter must start as an array**, not null, or
     * Livewire treats its header checkboxes as a scalar to replace on each
     * click rather than a group to toggle membership in.
     *
     * @return array<string, mixed>
     */
    public static function initialFor(Table $table): array
    {
        $state = self::defaults();

        // Lazy tables render a placeholder until something asks them to load.
        Arr::set($state, 'ready', ! $table->isLazy());

        if ($table->getDefaultSort()) {
            Arr::set($state, 'sort.column', $table->getDefaultSort());
            Arr::set($state, 'sort.direction', $table->getDefaultSortDirection());
        }

        Arr::set($state, 'pagination.perPage', $table->getPerPage());

        $filters = [];

        foreach ($table->getFilters() as $filter) {
            $default = $filter->getDefault();

            // A hidden filter renders no control to bind, so it only needs a
            // slot when a default actually forces a value into the query.
            if ($default === null && ! $filter->canView()) {
                continue;
            }

            // Arr::set so dotted (relation) filter names nest the same way the
            // live wire:model binding writes them — keeps init and UI in sync.
            Arr::set($filters, $filter->getName(), $filter->wrapValue($default));
        }

        if ($filters !== []) {
            Arr::set($state, 'filters', $filters);
        }

        $hidden = [];

        foreach ($table->getColumns() as $column) {
            if ($column->isToggleable() && ! $column->isVisible()) {
                $hidden[] = $column->getName();
            }
        }

        if ($hidden !== []) {
            Arr::set($state, 'columns.hidden', $hidden);
        }

        foreach ($table->getColumns() as $column) {
            if (! $column->isFilterable()) {
                continue;
            }

            Arr::set(
                $state,
                'columnFilters.'.$column->getName(),
                $column->filterExpectsArray() ? [] : null,
            );
        }

        // Sub-row filter columns need the same up-front slot, for the same
        // entangle reason: an interactive sub-row bar binds each control to
        // rows.subRowFilters.<name>.
        if ($table->isSubRowsFilterable()) {
            foreach ($table->getSubRowColumns() as $column) {
                if (! $column->isFilterable()) {
                    continue;
                }

                Arr::set(
                    $state,
                    'rows.subRowFilters.'.$column->getName(),
                    $column->filterExpectsArray() ? [] : null,
                );
            }
        }

        return $state;
    }

    /**
     * Get the default state values for a table component.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'sort' => [
                'column' => '',
                'direction' => 'asc',
            ],
            'pagination' => [
                'perPage' => 10,
                // Cursor pagination only. Livewire's pagination is page-based, so
                // a cursor has nowhere else to live — see WithTable::setTableCursor().
                'cursor' => null,
            ],
            'search' => null,
            'filters' => [],
            'columnFilters' => [],
            'selection' => [
                // 'keys' → the list below *is* the selection.
                // 'all'  → everything the current filter matches is selected and
                //          the list holds the exclusions instead, so unticking one
                //          row out of 128k stays one entry rather than 127 999.
                'mode' => 'keys',
                'records' => [],
            ],
            'columns' => [
                'hidden' => [],
            ],
            'rows' => [
                // Rows whose expansion *differs* from the baseline below, so a
                // single list serves both polarities.
                'expanded' => [],
                // The page-wide expansion baseline: null follows the table's
                // subRowsDefaultExpanded() config, true/false is the user's own
                // choice (master toggle / view menu) and outlives pagination.
                'expandAll' => null,
                // Group comparison keys the user has collapsed. Named groups
                // rather than row keys, because a collapsed group stays
                // collapsed when its rows change underneath it.
                'collapsedGroups' => [],
                'subRowFilters' => [],
                'subRowSort' => null,
                'subRowsShowAll' => [],
            ],
            'summary' => [
                'scope' => 'query',
            ],
            'modal' => [
                // The live modal stack: one frame per open action modal (the last
                // is the active/top one, the rest render live but click-inert
                // behind it). Each frame carries its own meta + depth-scoped
                // form-data bag, bound via `modal.actions.{depth}.data.*`.
                'actions' => [],
                // Stable visibility flag the modal-host entangles (see WithTable).
                'open' => false,
                'halt' => [
                    'show' => false,
                    'actionName' => null,
                    'recordKey' => null,
                    'config' => [],
                    'formData' => [],
                    'confirmed' => false,
                    'actionType' => null,
                    'context' => [],
                ],
            ],
            'ready' => false,
            'polling' => [
                'active' => true,
                'checksum' => null,
            ],
        ];
    }

    /**
     * Map from legacy property names to state paths.
     *
     * Used for backward compatibility via __get/__set magic methods.
     *
     * @return array<string, string>
     */
    public static function legacyPropertyMap(): array
    {
        return self::$legacyPropertyMap ??= [
            'tableSortColumn' => 'sort.column',
            'tableSortDirection' => 'sort.direction',
            'tablePerPage' => 'pagination.perPage',
            'tableSearch' => 'search',
            'tableFilters' => 'filters',
            'columnFilters' => 'columnFilters',
            'selectedRecords' => 'selection.records',
            'hiddenColumns' => 'columns.hidden',
            'expandedRows' => 'rows.expanded',
            // Flatten mode was a second, redundant "everything is open" flag; it
            // now aliases the expansion baseline that replaced it.
            'flattenMode' => 'rows.expandAll',
            'subRowFilters' => 'rows.subRowFilters',
            // The single-slot `modal.action.*` aliases are intentionally gone:
            // action modals are now a live stack under `modal.actions.{depth}.*`
            // (there is no stable single path to alias). Halt modal is unchanged.
            'showHaltModal' => 'modal.halt.show',
            'haltActionName' => 'modal.halt.actionName',
            'haltRecordKey' => 'modal.halt.recordKey',
            'haltModalConfig' => 'modal.halt.config',
            'haltModalFormData' => 'modal.halt.formData',
            'haltActionConfirmed' => 'modal.halt.confirmed',
            'haltActionType' => 'modal.halt.actionType',
            'haltContext' => 'modal.halt.context',
            'tableReady' => 'ready',
            'tablePollingActive' => 'polling.active',
        ];
    }
}
