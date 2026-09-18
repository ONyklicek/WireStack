<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * What a person may hand out: no more than they hold.
 *
 * The one rule against escalation, and its one owner. Without it a team's
 * manager — who may edit their team's roles and assign roles to its members —
 * could tick any permission there is into a role of their team, give themselves
 * that role, and hold what nobody gave them. The same went for assigning a role
 * that carries more than the manager has.
 *
 * - **A permission** may be handed out by somebody who holds it: through a role
 *   of the current team, a global role, or directly. A wildcard name is a name
 *   like any other — holding `invoices.*` is what lets you give `invoices.*`.
 * - **A role** may be handed out by somebody who holds every permission it
 *   carries — never the super-admin, and the administrator role only by a
 *   super-admin.
 * - **A super-admin** may hand out everything but the super-admin itself, which
 *   is given from the command line.
 *
 * It does not refuse a whole save. It narrows it: {@see clampPermissions()} and
 * {@see clampRoles()} apply the part of a change the person may make and leave
 * the rest as it was. A role that already carries a permission its editor
 * lacks keeps it; an account that already has a role its editor may not grant
 * keeps it; a name added by a forged request is not written. The screens offer
 * only what may be handed out, so a person who uses them never sees the
 * difference.
 *
 * The command line is not held to this: somebody with a shell on the server is
 * not somebody a form has to protect the application from.
 */
final class RoleGrants
{
    /**
     * Whether this person may put this permission into a role.
     */
    public static function mayGrantPermission(string $permission, mixed $actor = null): bool
    {
        $actor ??= Auth::user();

        return Roles::isSuperAdmin($actor) || in_array($permission, self::heldPermissions($actor), true);
    }

    /**
     * Whether this person may give somebody this role.
     */
    public static function mayGrantRole(Model|string $role, mixed $actor = null): bool
    {
        $actor ??= Auth::user();
        $name = $role instanceof Model ? $role->getAttribute('name') : $role;

        if ($name === Roles::superAdmin()) {
            return false;
        }

        if (Roles::isSuperAdmin($actor)) {
            return true;
        }

        if ($name === Roles::admin()) {
            return false;
        }

        $role = $role instanceof Model ? $role : self::visibleRole($name, $actor);

        if ($role === null || ! method_exists($role, 'permissions')) {
            return false;
        }

        $carries = $role->relationLoaded('permissions')
            ? $role->getRelation('permissions')->pluck('name')
            : $role->permissions()->pluck('name');

        return $carries->diff(self::heldPermissions($actor))->isEmpty();
    }

    /**
     * The permissions a role ends up with: the change this person may make, and nothing else.
     *
     * @param  array<int, string>  $before  What the role carries now.
     * @param  array<int, mixed>  $selected  What the form asked for.
     * @return array<int, string>
     */
    public static function clampPermissions(array $before, array $selected, mixed $actor = null): array
    {
        $actor ??= Auth::user();

        return self::clamp($before, $selected, static fn (string $name): bool => self::mayGrantPermission($name, $actor));
    }

    /**
     * The roles an account ends up with: the change this person may make, and nothing else.
     *
     * @param  array<int, string>  $before  The roles the account has now.
     * @param  array<int, mixed>  $selected  What the form asked for.
     * @return array<int, string>
     */
    public static function clampRoles(array $before, array $selected, mixed $actor = null): array
    {
        $actor ??= Auth::user();

        return self::clamp($before, $selected, static fn (string $name): bool => self::mayGrantRole($name, $actor));
    }

    /**
     * What was asked for among the names this person may change, and what was
     * there among the names they may not.
     *
     * @param  array<int, string>  $before
     * @param  array<int, mixed>  $selected
     * @param  callable(string): bool  $mayChange
     * @return array<int, string>
     */
    private static function clamp(array $before, array $selected, callable $mayChange): array
    {
        $asked = array_values(array_filter($selected, is_string(...)));
        $kept = array_filter($before, static fn (string $name): bool => ! $mayChange($name));
        $changed = array_filter($asked, $mayChange);

        return array_values(array_unique([...$kept, ...$changed]));
    }

    /**
     * Every permission name this person holds, in the team they are working in.
     *
     * @return array<int, string>
     */
    private static function heldPermissions(mixed $actor): array
    {
        if (! is_object($actor) || ! method_exists($actor, 'getAllPermissions')) {
            return [];
        }

        return $actor->getAllPermissions()->pluck('name')->all();
    }

    /**
     * The role of this name that this person can see — a global one, or their team's.
     */
    private static function visibleRole(string $name, mixed $actor): ?Model
    {
        /** @var class-string<Model> $model */
        $model = Roles::roleModel();

        return Teams::scopeRoles($model::query(), $actor)->where('name', $name)->first();
    }
}
