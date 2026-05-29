<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Plugin\Hooks;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Query\QueryPlan;

/**
 * Typed payload for the 'table.querying' hook.
 *
 * Dispatched after the QueryPlan is built but before the QueryExecutor runs.
 * Use this hook for read-only plan inspection or observation (e.g. logging, metrics).
 *
 * To force a sort override, use the array-based 'table.querying' hook instead
 * and set 'force_sort_column' in the returned payload. That hook runs before
 * QueryPlanner, so the sort is applied in a single planning pass.
 */
final class TableQueryingPayload
{
    /**
     * @param  object  $table  The table configuration object
     * @param  QueryPlan  $plan  The compiled query plan
     * @param  Builder<Model>  $query  The base Eloquent builder
     * @param  string|null  $forceSortColumn  @deprecated Not consumed by the built-in pipeline.
     *                                        Use the array hook's 'force_sort_column' key instead.
     * @param  string|null  $forceSortDirection  @deprecated See $forceSortColumn.
     */
    public function __construct(
        public readonly object $table,
        public readonly QueryPlan $plan,
        public readonly Builder $query,
        public ?string $forceSortColumn = null,
        public ?string $forceSortDirection = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'table' => $this->table,
            'plan' => $this->plan,
            'query' => $this->query,
            'force_sort_column' => $this->forceSortColumn,
            'force_sort_direction' => $this->forceSortDirection,
        ];
    }
}
