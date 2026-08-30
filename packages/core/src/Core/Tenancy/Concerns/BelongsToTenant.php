<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Tenancy\Concerns;

use Illuminate\Database\Eloquent\Builder;
use NyonCode\WireCore\Core\Tenancy\Tenancy;
use NyonCode\WireCore\Core\Tenancy\TenantScope;
use NyonCode\WireCore\Exceptions\TenancyException;

/**
 * Marks a model as belonging to a tenant.
 *
 * Opt-in per model, because the framework cannot know which of an application's
 * tables are tenant-owned and guessing would be a guess about who may see what.
 * Adding the trait is the application saying "this one is".
 *
 *   class Invoice extends Model
 *   {
 *       use BelongsToTenant;
 *   }
 *
 * It does two things, and the second is the one that is easy to forget:
 *
 *  - **reads and writes are scoped** through {@see TenantScope}, so a query,
 *    an update and a delete all see only the current tenant's rows;
 *  - **a create is attributed**, so a new row cannot be written without a
 *    tenant. Attempting one with no tenant resolved throws rather than storing
 *    a row that every scoped query will then hide from everybody.
 *
 * An explicitly set tenant column is left alone — an application seeding across
 * tenants, or moving a record deliberately, is not something to override.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model): void {
            // Resolved per event, for the reason TenantScope::apply() gives:
            // boot runs once per class per process, and the tenant does not.
            $tenancy = app(Tenancy::class);

            if (! $tenancy->enabled()) {
                return;
            }

            $column = $tenancy->column();

            // Already set: a seeder or a deliberate cross-tenant move said so,
            // and overriding that would be the framework second-guessing an
            // explicit instruction.
            if ($model->getAttribute($column) !== null) {
                return;
            }

            $tenant = $tenancy->current();

            if ($tenant === null) {
                throw TenancyException::noTenantToAssign($model::class);
            }

            $model->setAttribute($column, $tenant);
        });
    }

    /**
     * Read across every tenant.
     *
     * Deliberately verbose to write and easy to grep for: an admin report or a
     * console command has a real need for it, and a review should be able to
     * find every place that claimed one.
     *
     * @return Builder<static>
     */
    public static function acrossAllTenants(): Builder
    {
        return static::query()->withoutGlobalScope(TenantScope::class);
    }
}
