<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Plugin\Hooks;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Query\QueryPlan;

/**
 * Typed payload for the 'table.queried' hook.
 *
 * Dispatched after the QueryExecutor has applied all pipes.
 * Plugins can observe or further modify the final query.
 */
final class TableQueriedPayload
{
    /**
     * @param  object  $table  The table configuration object
     * @param  Builder<Model>  $query  The fully built Eloquent builder
     * @param  QueryPlan  $plan  The query plan that was executed
     */
    public function __construct(
        public readonly object $table,
        public readonly Builder $query,
        public readonly QueryPlan $plan,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'table' => $this->table,
            'query' => $this->query,
            'plan' => $this->plan,
        ];
    }
}
