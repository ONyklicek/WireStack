<?php

declare(strict_types=1);

namespace NyonCode\WireModuleTenants\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\DB;
use NyonCode\WireCore\Core\Tenancy\CurrentTenant;
use NyonCode\WireModuleTenants\Enums\MemberRole;

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

        $role = DB::table(self::table())
            ->where('tenant_id', $tenant->getKey())
            ->where('user_id', $user->getKey())
            ->value('role');

        return is_string($role) ? MemberRole::tryFrom($role) : null;
    }

    public static function isOwner(Model $tenant, mixed $user): bool
    {
        return self::roleOf($tenant, $user) === MemberRole::Owner;
    }

    public static function owners(Model $tenant): int
    {
        return DB::table(self::table())
            ->where('tenant_id', $tenant->getKey())
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
        return DB::table(self::table())->where('tenant_id', $tenant->getKey())->pluck('user_id')->all();
    }
}
