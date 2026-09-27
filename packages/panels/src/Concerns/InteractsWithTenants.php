<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use NyonCode\WirePanels\Contracts\HasTenants;
use NyonCode\WirePanels\Exceptions\TenancyConfigurationException;

/**
 * {@see HasTenants} over a many-to-many, the ordinary shape.
 *
 * The related model is `wire-core.tenancy.model`, the pivot table
 * `wire-core.tenancy.members_table` (`tenant_user`), and the pivot columns
 * follow Laravel's convention for the two models — `user_id` and, for a
 * `Company`, `company_id`. A membership shaped otherwise overrides `tenants()`;
 * the three answers are all asked of it.
 *
 * @phpstan-require-extends Model
 */
trait InteractsWithTenants
{
    /** @return BelongsToMany<Model, $this> */
    public function tenants(): BelongsToMany
    {
        $model = config('wire-core.tenancy.model');

        if (! is_string($model) || ! is_a($model, Model::class, true)) {
            throw TenancyConfigurationException::noTenantModel();
        }

        return $this->belongsToMany($model, (string) config('wire-core.tenancy.members_table', 'tenant_user'));
    }

    /** @return iterable<int, Model> */
    public function getTenants(): iterable
    {
        return $this->tenants()->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $tenant instanceof ($this->tenants()->getRelated())
            && $this->tenants()->whereKey($tenant->getKey())->exists();
    }

    public function getDefaultTenant(): ?Model
    {
        return $this->tenants()->first();
    }
}
