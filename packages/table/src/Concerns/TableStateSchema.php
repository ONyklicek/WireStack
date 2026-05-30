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
                'action' => [
                    'show' => false,
                    'name' => null,
                    'recordKey' => null,
                    'isBulk' => false,
                    'formData' => [],
                    'isHeaderAction' => false,
                ],
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
        return [
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
            'showActionModal' => 'modal.action.show',
            'actionModalName' => 'modal.action.name',
            'actionModalRecordKey' => 'modal.action.recordKey',
            'actionModalIsBulk' => 'modal.action.isBulk',
            'actionModalFormData' => 'modal.action.formData',
            'actionModalIsHeaderAction' => 'modal.action.isHeaderAction',
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
