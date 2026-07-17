<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

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
            ],
            'search' => null,
            'filters' => [],
            'columnFilters' => [],
            'selection' => [
                'records' => [],
            ],
            'columns' => [
                'hidden' => [],
            ],
            'rows' => [
                'expanded' => [],
                'flattenMode' => false,
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
            'flattenMode' => 'rows.flattenMode',
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
