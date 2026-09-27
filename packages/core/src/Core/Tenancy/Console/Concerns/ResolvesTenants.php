<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Tenancy\Console\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use NyonCode\WireCore\Exceptions\TenancyException;

/**
 * The tenants a `wire:tenants:*` command is asked about: those named — by key
 * or route key — or every one.
 */
trait ResolvesTenants
{
    /**
     * @param  array<int, string>  $named
     * @return Collection<int, Model>
     */
    protected function tenants(array $named): Collection
    {
        $model = config('wire-core.tenancy.model');

        if (! is_string($model) || ! is_a($model, Model::class, true)) {
            throw TenancyException::noTenantModel();
        }

        $query = $model::query()->withoutGlobalScopes();

        if ($named === []) {
            return $query->get();
        }

        $routeKey = (new $model)->getRouteKeyName();

        return $query->where(fn ($where) => $where->whereIn($routeKey, $named)->orWhereIn((new $model)->getKeyName(), $named))->get();
    }
}
