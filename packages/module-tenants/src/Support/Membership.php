<?php

declare(strict_types=1);

namespace NyonCode\WireModuleTenants\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\DB;
use NyonCode\WireCore\Core\Tenancy\CurrentTenant;
use NyonCode\WireModuleTenants\Enums\MemberRole;
use NyonCode\WireModuleTenants\Models\Tenant;

/**
 * Who belongs to a company, and as what — read from the membership pivot
 * (`wire-core.tenancy.members_table`, `tenant_user` by default), with its `role`.
 *
 * One owner of the question for every screen of the module, so the members
 * list, the profile page and the actions on them cannot disagree about who may
 * do what. The rule worth stating once: **a company always keeps an owner** —
 * the last one cannot be removed or made a member, the same rule the users
 * module holds for the last super-admin.
 */
final class Membership
{
    /** The pivot membership is read from — the one `InteractsWithTenants` reads too. */
    public static function table(): string
    {
        return (string) config('wire-core.tenancy.members_table', 'tenant_user');
    }

    /**
     * The company model — core's `wire-core.tenancy.model`, the one a tenant zone
     * finds a company through, and this module's own setting only when core names
     * none.
     *
     * Asked here by everything that creates or reads a company. Registration used
     * to read only the module's key, so an application that set core's — where the
     * tenancy docs say to — registered companies into one table while the zone
     * looked them up in another.
     *
     * @return class-string<Model>
     */
    public static function tenantModel(): string
    {
        $model = config('wire-core.tenancy.model') ?? config('wire-module-tenants.model');

        return is_string($model) && is_a($model, Model::class, true) ? $model : Tenant::class;
    }

    /**
     * The pivot column holding the company, by Laravel's convention for the
     * company model — `tenant_id` for this module's `Tenant`, `company_id` for a
     * `Company`. The convention `InteractsWithTenants` relies on, so both read
     * the same column.
     */
    public static function tenantKey(): string
    {
        $model = self::tenantModel();

        return (new $model)->getForeignKey();
    }

    /** The pivot column holding the person, by the same convention: `user_id`. */
    public static function userKey(): string
    {
        $model = self::userModel();

        return (new $model)->getForeignKey();
    }

    /** Put somebody in a company, in a role. */
    public static function add(Model $tenant, Model $user, MemberRole $role): void
    {
        DB::table(self::table())->insert([
            self::tenantKey() => $tenant->getKey(),
            self::userKey() => $user->getKey(),
            'role' => $role->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function changeRole(Model $tenant, Model $user, MemberRole $role): void
    {
        self::of($tenant)->where(self::userKey(), $user->getKey())->update(['role' => $role->value, 'updated_at' => now()]);
    }

    public static function remove(Model $tenant, Model $user): void
    {
        self::of($tenant)->where(self::userKey(), $user->getKey())->delete();
    }

    /** The pivot rows of one company. */
    private static function of(Model $tenant): Builder
    {
        return DB::table(self::table())->where(self::tenantKey(), $tenant->getKey());
    }

    /** @return class-string<Model> */
    public static function userModel(): string
    {
        $model = config('auth.providers.users.model', User::class);

        return is_string($model) && is_a($model, Model::class, true) ? $model : User::class;
    }

    /** The company being worked in. */
    public static function current(): ?Model
    {
        return app(CurrentTenant::class)->get();
    }

    public static function roleOf(Model $tenant, mixed $user): ?MemberRole
    {
        if (! $user instanceof Model) {
            return null;
        }

        $role = self::of($tenant)
            ->where(self::userKey(), $user->getKey())
            ->value('role');

        return is_string($role) ? MemberRole::tryFrom($role) : null;
    }

    public static function isOwner(Model $tenant, mixed $user): bool
    {
        return self::roleOf($tenant, $user) === MemberRole::Owner;
    }

    public static function owners(Model $tenant): int
    {
        return self::of($tenant)
            ->where('role', MemberRole::Owner->value)
            ->count();
    }

    /** Whether this person is the company's only owner — the one who cannot go. */
    public static function isLastOwner(Model $tenant, mixed $user): bool
    {
        return self::isOwner($tenant, $user) && self::owners($tenant) <= 1;
    }

    /** @return array<int, int|string> */
    public static function memberIds(Model $tenant): array
    {
        return self::of($tenant)->pluck(self::userKey())->all();
    }
}
