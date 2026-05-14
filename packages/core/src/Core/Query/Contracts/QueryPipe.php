<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Contracts;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Query\QueryPlan;

/**
 * A single step in the query execution pipeline.
 *
 * Each pipe applies one aspect of the QueryPlan to the Eloquent Builder.
 * Pipes are executed in order by the QueryExecutor.
 */
interface QueryPipe
{
    /**
     * Handle this pipeline step.
     *
     * @param  Builder<Model>  $builder
     * @param  Closure(Builder<Model>, QueryPlan): Builder<Model>  $next
     * @return Builder<Model>
     */
    public function handle(Builder $builder, QueryPlan $plan, Closure $next): Builder;
}
