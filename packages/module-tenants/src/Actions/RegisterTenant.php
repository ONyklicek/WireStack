<?php

declare(strict_types=1);

namespace NyonCode\WireModuleTenants\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use NyonCode\WireModuleTenants\Enums\MemberRole;
use NyonCode\WireModuleTenants\Jobs\ProvisionTenantDatabase;
use NyonCode\WireModuleTenants\Support\Membership;

/**
 * Create a company with its first owner — and, under database isolation, its
 * database, in a job, since creating and migrating one is not a request's work.
 */
final class RegisterTenant
{
    public function __invoke(string $name, string $slug, Model $owner): Model
    {
        $model = Membership::tenantModel();

        $tenant = DB::transaction(function () use ($model, $name, $slug, $owner): Model {
            $tenant = $model::query()->create(['name' => $name, 'slug' => $slug]);

            Membership::add($tenant, $owner, MemberRole::Owner);

            return $tenant;
        });

        if (config('wire-core.tenancy.isolation') === 'database') {
            ProvisionTenantDatabase::dispatch($tenant);
        }

        return $tenant;
    }
}
