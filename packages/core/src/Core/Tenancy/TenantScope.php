<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Constrains every query on a tenant-scoped model to the current tenant.
 *
 * A global scope rather than a plugin hook, and that is the security decision.
 * The plan proposed hooking `table.querying` at priority -100, but a hook covers
 * one read path and only while a `PluginManager` happens to be bound — it does
 * nothing for `SaveHandler`, for a relation, for an export, for a queued job, or
 * for the plain `Model::find()` in an application's own controller. A global
 * scope covers every query Eloquent builds, including the update and delete a
 * hook would never see. A tenancy that holds in one place and not another is not
 * a tenancy.
 *
 * **The fail-safe is the point.** Tenancy on and no tenant resolved constrains to
 * nothing, never to everything: `whereRaw('0 = 1')`. The ordinary states that
 * produce a null tenant — before login, a worker, a console command — must not
 * be the states in which every row is visible.
 *
 * The column is qualified because a scoped model is routinely joined, and a
 * joined table commonly has a `tenant_id` of its own; an unqualified column
 * there is ambiguous at best and the wrong table's at worst.
 *
 * @implements Scope<Model>
 */
final class TenantScope implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Resolved here, not held from construction. A global scope is added
        // once per model class per process, so a scope carrying a Tenancy would
        // answer with whoever was current the first time that model was touched
        // — wrong on the second request under Octane, wrong for every job after
        // the first on a worker, and wrong for the test that caught it.
        $tenancy = app(Tenancy::class);

        if (! $tenancy->enabled()) {
            return;
        }

        $column = $model->qualifyColumn($tenancy->column());
        $tenant = $tenancy->current();

        if ($tenant === null) {
            // Not `whereNull($column)`: a row whose tenant column is null would
            // then be visible to everyone, which is the leak this exists to
            // prevent. Nothing means nothing.
            $builder->whereRaw('0 = 1');

            return;
        }

        $builder->where($column, $tenant);
    }
}
