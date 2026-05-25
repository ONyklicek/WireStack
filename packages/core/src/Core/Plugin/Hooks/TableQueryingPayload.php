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
 * Plugins can inspect/modify the plan or set force overrides (e.g. force_sort_column).
 */
final class TableQueryingPayload
{
    /**
     * @param  object  $table  The table configuration object
     * @param  QueryPlan  $plan  The compiled query plan
     * @param  Builder<Model>  $query  The base Eloquent builder
     * @param  string|null  $forceSortColumn  Override sort column (set by plugins like Sortable)
     * @param  string|null  $forceSortDirection  Override sort direction
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
