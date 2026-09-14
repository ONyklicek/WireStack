<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Support;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Whether this application can manage roles, and through what.
 *
 * **`nyoncode/laravel-permission-extended` is the permission layer of this
 * stack**, and the only one these screens are built against. It is not an
 * alternative to `spatie/laravel-permission` — it requires it and extends it —
 * so what looks like a choice between two packages is one package with the
 * wildcard matching, the super-admin gate, the Blade directives and the
 * permission-change events this framework's screens assume. Detecting bare
 * Spatie and lighting the screens anyway would offer a management UI over an
 * authorization model half of it does not have.
 *
 * The module works without any of it: where the package is absent the role
 * screens and the role field are absent too, rather than present and broken.
 *
 * **Authorization never comes through here.** Every check in this stack is
 * `Gate::allows()`, which the package registers itself into, so a wildcard
 * (`invoices.*`) and a super-admin bypass work without this class knowing they
 * exist. What this answers is a management question: are there roles to edit,
 * and where do they live.
 *
 * Two conditions rather than one, and both were measured rather than assumed:
 * the package can be installed while the application's own `User` never took
 * the trait, in which case every role save fails at its last step.
 */
final class Roles
{
    /** The trait an application's user model takes — this package's, never Spatie's directly. */
    public const EXTENDED_TRAIT = 'NyonCode\\PermissionExtended\\Traits\\HasRoles';

    /** The permission layer's own installer — the toolkit names it after the package. */
    public const INSTALLER = 'permission-extended:install';

    /**
     * The models are Spatie's, and that is not a contradiction.
     *
     * `laravel-permission-extended` extends the *behaviour* on the user model
     * and leaves the role and permission tables where they are, so these are
     * still the classes the rows are read through — taken from the permission
     * package's own config so an application that swapped them keeps its swap.
     */
    public const ROLE_MODEL = 'Spatie\\Permission\\Models\\Role';

    public const PERMISSION_MODEL = 'Spatie\\Permission\\Models\\Permission';

    /** Whether role management should be part of this installation. */
    public static function enabled(): bool
    {
        $setting = config('wire-module-users.roles', 'auto');

        if (is_bool($setting)) {
            return $setting;
        }

        return $setting === 'auto' && self::available();
    }

    /**
     * Whether the package and the model are both actually there.
     *
     * The trait is looked for **on the model**, not merely in the vendor
     * directory: `laravel-permission-extended` arrives as a dependency of
     * several things, and a `User` that never took its `HasRoles` is a user
     * whose every role save fails at `syncRoles()` — after the record was
     * written, which is the worst place to find out.
     */
    public static function available(): bool
    {
        $model = config('wire-module-users.model');

        if (! self::hasExtendedPermissions() || ! is_string($model) || ! class_exists($model)) {
            return false;
        }

        return class_exists(self::roleModel())
            && in_array(self::EXTENDED_TRAIT, class_uses_recursive($model), true);
    }

    /**
     * Whether the user model's file has the trait while this process's class does not.
     *
     * The state `permission-extended:install` leaves a running installer in. It
     * patches `app/Models/User.php` on disk, but the application booted before
     * that and PHP cannot load a class twice, so {@see available()} goes on
     * answering about the source it loaded: no roles, in the very run that set
     * them up. The first administrator made in that run got no role and a 403
     * on the users screen. A caller that sees this has to do the role work in a
     * process that starts after the patch.
     *
     * Read as the import line the permission installer writes, rather than as
     * any mention of the trait, so a comment naming it does not count.
     */
    public static function waitingForRestart(): bool
    {
        $model = config('wire-module-users.model');

        if (config('wire-module-users.roles', 'auto') === false
            || ! self::hasExtendedPermissions()
            || ! is_string($model)
            || ! class_exists($model)
            || self::available()) {
            return false;
        }

        $file = (new ReflectionClass($model))->getFileName();

        return is_string($file)
            && preg_match('/^use\s+'.preg_quote(self::EXTENDED_TRAIT, '/').'\s*;/m', (string) file_get_contents($file)) === 1;
    }

    /**
     * The role that can do everything, as the permission package's gate names it.
     *
     * Null where the application switched the super-admin off
     * (`permission-extended.super_admin_role` = null).
     */
    public static function superAdmin(): ?string
    {
        $role = config('permission-extended.super_admin_role', 'super-admin');

        return is_string($role) && $role !== '' ? $role : null;
    }

    /**
     * The administrator role — ordinary permissions, held globally.
     *
     * Null where the application has none (`wire-module-users.admin_role`).
     */
    public static function admin(): ?string
    {
        $role = config('wire-module-users.admin_role', 'admin');

        return is_string($role) && $role !== '' ? $role : null;
    }

    /**
     * The role of a team's manager, given inside a team.
     *
     * Null where the application has none (`wire-module-users.teams.admin_role`).
     */
    public static function teamAdmin(): ?string
    {
        $role = config('wire-module-users.teams.admin_role', 'team-admin');

        return is_string($role) && $role !== '' ? $role : null;
    }

    /**
     * The permissions this module gives a role it makes, by the role's name.
     *
     * The administrator and team-manager roles carry the abilities of the user
     * and role screens ({@see Permissions::abilities()}) — the one set that
     * makes them what they are called. Every other role starts empty.
     *
     * @return array<int, string>
     */
    public static function defaultPermissions(string $role): array
    {
        return in_array($role, array_filter([self::admin(), self::teamAdmin()]), true)
            ? Permissions::abilities()
            : [];
    }

    /**
     * Whether this person is a super-admin — globally, the only way it counts.
     */
    public static function isSuperAdmin(mixed $actor): bool
    {
        $role = self::superAdmin();

        return $role !== null
            && is_object($actor)
            && method_exists($actor, 'hasGlobalRole')
            && $actor->hasGlobalRole($role);
    }

    /**
     * Whether this person may change or delete this role on the role screens.
     *
     * - The super-admin role — never. It carries no permissions to edit, and
     *   renaming it is how the permission gate stops recognising every
     *   super-admin at once.
     * - The global administrator role — only a super-admin. It is the role that
     *   hands out the others; an administrator editing it grants themselves
     *   whatever they add.
     * - Any other global role, with teams — only somebody who works across
     *   every team. For a team's manager a global role is a template to read.
     * - A role of a team — whoever may see it, which the list has already
     *   narrowed to the current team.
     */
    public static function mayChange(Model $role, mixed $actor = null): bool
    {
        $actor ??= auth()->user();
        $name = $role->getAttribute('name');
        $global = $role->getAttribute(Teams::teamColumn()) === null;

        if ($name === self::superAdmin()) {
            return false;
        }

        if ($global && $name === self::admin()) {
            return self::isSuperAdmin($actor);
        }

        return ! ($global && Teams::enabled())
            || Teams::seesEveryTeam(Permissions::for('roles', 'update'), $actor);
    }

    /**
     * Whether the permission layer's installer is there to run.
     *
     * The command being registered rather than the trait autoloading: a package
     * whose provider is excluded from discovery has the class and not the
     * command, and calling it aborts the whole run.
     */
    public static function installable(Kernel $artisan): bool
    {
        return array_key_exists(self::INSTALLER, $artisan->all());
    }

    /**
     * The role model, taken from the permission package's own config so an
     * application that swapped it keeps its swap.
     *
     * @return class-string
     */
    public static function roleModel(): string
    {
        $configured = config('permission.models.role');

        return is_string($configured) && $configured !== '' ? $configured : self::ROLE_MODEL;
    }

    /** @return class-string */
    public static function permissionModel(): string
    {
        $configured = config('permission.models.permission');

        return is_string($configured) && $configured !== '' ? $configured : self::PERMISSION_MODEL;
    }

    /**
     * Role names, keyed by name, for a select.
     *
     * Names rather than ids: that is what `syncRoles()` takes and what a person
     * reading the form recognises, and it survives a reseeded roles table.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        if (! self::enabled()) {
            return [];
        }

        /** @var class-string<Model> $model */
        $model = self::roleModel();

        // Only the roles this person can see — a team's manager is offered the
        // global roles and their own team's — and of those, only the ones they
        // may hand out (RoleGrants): never the super-admin, the administrator
        // role only to a super-admin, and no role carrying a permission the
        // person does not hold.
        $query = Teams::scopeRoles($model::query())->orderBy('name');

        if (method_exists(new $model, 'permissions')) {
            $query->with('permissions');
        }

        return $query->get()
            ->filter(static fn (Model $role): bool => RoleGrants::mayGrantRole($role))
            ->pluck('name', 'name')
            ->all();
    }

    /**
     * Permission names, keyed by name.
     *
     * @return array<string, string>
     */
    public static function permissionOptions(): array
    {
        if (! self::enabled()) {
            return [];
        }

        /** @var class-string<Model> $model */
        $model = self::permissionModel();

        // Only what this person may put into a role: what they hold themselves.
        return array_filter(
            $model::query()->orderBy('name')->pluck('name', 'name')->all(),
            static fn (string $name): bool => RoleGrants::mayGrantPermission($name),
        );
    }

    /**
     * The same permissions, grouped by the thing they are about.
     *
     * A permission name in this stack reads `resource.ability` — `invoices.view`,
     * `invoices.*` — so the segment before the first dot is the resource, and
     * grouping on it turns a flat alphabetical wall of two hundred checkboxes
     * into the matrix people actually think in.
     *
     * **Only where it helps.** One group is the same list with a heading over
     * it, and an application whose permissions are not dotted (`view invoices`,
     * Spatie's other common convention) would get one group per permission —
     * so both cases answer with an empty array, and the field stays flat.
     *
     * Names without a dot are not dropped; they gather under one heading, because
     * a permission missing from the form is a permission nobody can grant.
     *
     * @return array<string, array<string, string>>
     */
    public static function permissionGroups(): array
    {
        // Read once: this runs while a form is composed, which happens again on
        // every live update a field sends.
        return self::groupPermissions(self::permissionOptions());
    }

    /**
     * The grouping rule itself, over a list somebody already has.
     *
     * Separated from the query because it is the half worth testing and the half
     * an application might want to reuse — and because a rule that can only be
     * exercised through a permissions table gets tested through Eloquent's
     * ordering instead of through itself.
     *
     * @param  array<string, string>  $permissions
     * @return array<string, array<string, string>>
     */
    public static function groupPermissions(array $permissions): array
    {
        $groups = [];

        foreach ($permissions as $name => $label) {
            $resource = str_contains($name, '.')
                ? Str::before($name, '.')
                : __('wire-module-users::messages.other_permissions');

            $groups[$resource][$name] = $label;
        }

        // A grouping that produces one group, or as many groups as permissions,
        // has grouped nothing — and the second is what an undotted convention
        // does. Either way the caller gets a flat list, which is honest.
        return count($groups) > 1 && count($groups) < count($permissions) ? $groups : [];
    }

    /**
     * Whether the permission layer this stack is built against is installed.
     *
     * `trait_exists`, not `class_exists`: the thing being looked for is a trait,
     * and `class_exists()` answers **false** for one — a check that reads right
     * and is wrong every time, which is exactly the kind that ships.
     */
    public static function hasExtendedPermissions(): bool
    {
        return trait_exists(self::EXTENDED_TRAIT);
    }
}
