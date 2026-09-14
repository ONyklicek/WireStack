<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * What a person may do to somebody else's account on the user screens.
 *
 * The companion of {@see RoleGrants}: that one decides what may be handed out,
 * this one decides which accounts may be touched and how. Three rules, each
 * closing a way to take over an installation from inside it:
 *
 * - **A super-admin's account is a super-admin's to change.** An administrator
 *   who could edit one could set its password and sign in as it, or delete it
 *   and be the most powerful account left.
 * - **The last super-admin is not deleted** — not by another account, and not
 *   by itself from its own profile. An installation without one has nobody who
 *   can change the administrator role or put a super-admin back.
 * - **A team's manager manages membership, not people.** An account may belong
 *   to several teams, so the manager of one does not delete it (they remove it
 *   from their team) and does not change the e-mail address or password it signs
 *   in with (they send it a reset link). Its name is theirs to correct.
 *
 * Somebody who works across every team — a super-admin, or an administrator
 * whose ability is global — is not a team's manager, and the command line is not
 * held to any of this.
 */
final class AccountGuard
{
    /**
     * Whether this account is a super-admin the person is not.
     */
    public static function isProtected(Model $account, mixed $actor = null): bool
    {
        $actor ??= Auth::user();

        return Roles::isSuperAdmin($account) && ! Roles::isSuperAdmin($actor);
    }

    /**
     * Whether the person may open this account's edit page and save it.
     */
    public static function mayEdit(Model $account, mixed $actor = null): bool
    {
        return ! self::isProtected($account, $actor);
    }

    /**
     * Whether the person may change the e-mail address and password this account signs in with.
     */
    public static function mayChangeCredentials(Model $account, mixed $actor = null): bool
    {
        return self::mayEdit($account, $actor) && ! self::managesOneTeam($actor);
    }

    /**
     * Whether the person may delete this account.
     */
    public static function mayDelete(Model $account, mixed $actor = null): bool
    {
        return self::mayEdit($account, $actor)
            && ! self::managesOneTeam($actor)
            && ! self::isLastSuperAdmin($account);
    }

    /**
     * Whether the person may take this account out of the team they are working in.
     */
    public static function mayRemoveFromTeam(Model $account, mixed $actor = null): bool
    {
        $actor ??= Auth::user();
        $team = Teams::currentId($actor);

        return self::managesOneTeam($actor)
            && self::mayEdit($account, $actor)
            && $team !== null
            && Teams::isMember($team, $account);
    }

    /**
     * Whether this account is the only super-admin there is.
     */
    public static function isLastSuperAdmin(Model $account): bool
    {
        $role = Roles::superAdmin();

        if ($role === null || ! Roles::isSuperAdmin($account)) {
            return false;
        }

        /** @var class-string<Model> $model */
        $model = Roles::roleModel();
        $roles = (new $model)->getTable();
        $pivot = (string) config('permission.table_names.model_has_roles');
        $pivotRole = (string) (config('permission.column_names.role_pivot_key') ?: 'role_id');
        $morphKey = (string) config('permission.column_names.model_morph_key');

        $holders = DB::table($pivot)
            ->join($roles, "{$roles}.id", '=', "{$pivot}.{$pivotRole}")
            ->where("{$roles}.name", $role)
            ->where("{$pivot}.model_type", $account->getMorphClass())
            ->when((bool) config('permission.teams'), static fn ($q) => $q->where(
                "{$pivot}.".Teams::teamColumn(),
                config('permission-extended.global_team_id', 0),
            ))
            ->distinct()
            ->count("{$pivot}.{$morphKey}");

        return $holders <= 1;
    }

    /**
     * Whether the person manages the current team only, rather than every team.
     */
    private static function managesOneTeam(mixed $actor): bool
    {
        return Teams::enabled() && ! Teams::seesEveryTeam(Permissions::for('users', 'update'), $actor);
    }
}
