<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use NyonCode\WireCore\Core\Query\Contracts\QueryPipe;
use NyonCode\WireCore\Core\Query\QueryPlan;

/**
 * Handles soft delete scope based on the QueryPlan configuration.
 *
 * When withSoftDeletes is true, removes the SoftDeletingScope to include trashed records.
 */
final class ApplySoftDeletes implements QueryPipe
{
    /** {@inheritDoc} */
    public function handle(Builder $builder, QueryPlan $plan, Closure $next): Builder
    {
        if ($plan->withSoftDeletes) {
            $builder->withoutGlobalScope(SoftDeletingScope::class);
        }

        return $next($builder, $plan);
    }
}
