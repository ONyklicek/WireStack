<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Plugin\Hooks;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Plugin\Contracts\HasHookTarget;
use NyonCode\WireCore\Core\Plugin\HookTarget;
use NyonCode\WireCore\Core\Query\QueryPlan;

/**
 * Typed payload for the 'table.querying' hook.
 *
 * Dispatched after the QueryPlan is built but before the QueryExecutor runs, so
 * this is the hook for reading a finished plan — logging, metrics, assertions.
 *
 * **A sort override does not belong here and cannot be made to.** It goes on the
 * array-based 'table.querying' hook, which runs *before* QueryPlanner and is
 * applied in the same planning pass; by the time this payload exists the plan is
 * built, and honouring an override would mean planning the query twice. Until
 * 2.0 this class carried `$forceSortColumn` / `$forceSortDirection` for that —
 * two properties nothing ever filled and nothing ever read, so a plugin setting
 * one watched its sort be ignored in silence. They are gone; the array hook's
 * `force_sort_column` key is the whole of it.
 */
final class TableQueryingPayload implements HasHookTarget
{
    /**
     * @param  object  $table  The table configuration object
     * @param  QueryPlan  $plan  The compiled query plan
     * @param  Builder<Model>  $query  The base Eloquent builder
     */
    public function __construct(
        public readonly object $table,
        public readonly QueryPlan $plan,
        public readonly Builder $query,
        public readonly ?HookTarget $target = null,
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
        ];
    }

    public function hookTarget(): ?HookTarget
    {
        return $this->target;
    }
}
