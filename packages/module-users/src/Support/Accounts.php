<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireModuleUsers\Console\WireUserCommand;
use NyonCode\WireModuleUsers\Exceptions\AccountException;
use NyonCode\WireModuleUsers\Install\CreateFirstAdministrator;
use Throwable;

/**
 * Making an account, over whatever this application calls a user.
 *
 * Two callers ask for the same three things — {@see CreateFirstAdministrator},
 * which offers it once during `wire:install`, and {@see WireUserCommand}, which
 * offers it whenever. What they do *not* share is when to offer it, and that is
 * the only thing they each keep.
 *
 * Everything here is about the application rather than the package: its model
 * (`wire-module-users.model`), its column names (`.fields`, because a `users`
 * table is the one table every application has changed) and its roles. None of
 * that belongs in an installer, which is why this is the module's.
 */
final class Accounts
{
    /**
     * The class this application calls a user, when it named one that exists.
     *
     * @return class-string<Model>|null
     */
    public function model(): ?string
    {
        $model = config('wire-module-users.model');

        return is_string($model) && $model !== '' && class_exists($model) ? $model : null;
    }

    /**
     * The columns to write, as this application names them.
     *
     * @return array{name: string, email: string, password: string}
     */
    public function fields(): array
    {
        /** @var array<string, string> $fields */
        $fields = (array) config('wire-module-users.fields', []);

        return [
            'name' => $fields['name'] ?? 'name',
            'email' => $fields['email'] ?? 'email',
            'password' => $fields['password'] ?? 'password',
        ];
    }

    /**
     * Whether there is a table to write a user into.
     *
     * False rather than an exception for a database that is not there at all:
     * both callers have something better to say about that than a stack trace.
     */
    public function ready(): bool
    {
        $model = $this->model();

        if ($model === null) {
            return false;
        }

        try {
            return Schema::hasTable((new $model)->getTable());
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Whether anybody can already sign in.
     */
    public function any(): bool
    {
        $model = $this->model();

        if ($model === null) {
            return false;
        }

        try {
            return $model::query()->exists();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Write the account.
     *
     * The password is hashed here rather than left to a cast, because the model
     * is the application's and this module cannot assume it has one.
     */
    public function create(string $name, string $email, string $password): Model
    {
        /** @var class-string<Model> $model */
        $model = $this->model();
        $fields = $this->fields();

        return $model::query()->create([
            $fields['name'] => $name,
            $fields['email'] => $email,
            $fields['password'] => Hash::make($password),
        ]);
    }

    /**
     * The account that signs in with this address, if there is one.
     */
    public function find(string $email): ?Model
    {
        /** @var class-string<Model> $model */
        $model = $this->model();

        return $model::query()->where($this->fields()['email'], $email)->first();
    }

    /**
     * The address an account signs in with, as this application names the column.
     */
    public function emailOf(Model $user): string
    {
        return (string) $user->getAttribute($this->fields()['email']);
    }

    /**
     * Make the account a super-admin: it can do everything, in every team.
     *
     * Always a global assignment ({@see assignGlobalRole()}), never a role of a
     * team — with teams on, the permission package's gate honours nothing else.
     * So it needs no team, which is also what lets the first administrator of a
     * brand-new installation, who belongs to none, be one.
     *
     * The role is found or made as a role of no team; one of the same name that
     * a team owns is not it. The name is the permission package's own
     * (`permission-extended.super_admin_role`), because that is what its gate
     * checks.
     *
     * @return string|null The role given, or null where this application has no
     *                     roles or no super-admin.
     */
    public function makeSuperAdmin(Model $user): ?string
    {
        $role = Roles::superAdmin();

        if ($role === null || ! Roles::enabled() || ! method_exists($user, 'assignGlobalRole')) {
            return null;
        }

        $model = Roles::roleModel();
        $query = $model::query();

        if ((bool) config('permission.teams')) {
            $query->whereNull((string) config('permission.column_names.team_foreign_key', 'team_id'));
        }

        $user->assignGlobalRole($query->firstOrCreate([
            'name' => $role,
            'guard_name' => (string) config('auth.defaults.guard', 'web'),
        ]));

        return $role;
    }

    /**
     * What a super-admin is, in the words a question about making one should use.
     *
     * "In every team" only where there are teams — said to an application
     * without them it names a thing that does not exist.
     */
    public function superAdminMeaning(): string
    {
        return (bool) config('permission.teams')
            ? 'It can do everything, in every team.'
            : 'It can do everything.';
    }

    /**
     * The name the permission package's own gate checks.
     *
     * Exposed rather than read at each call site: a caller that invented one
     * would make an administrator the gate does not recognise, and a caller
     * reporting a failure still has to be able to name it.
     */
    public function superAdminRole(): string
    {
        return Roles::superAdmin() ?? 'super-admin';
    }

    /**
     * Give the account a role, creating it when this application has not.
     *
     * Any role but the super-admin, which is {@see makeSuperAdmin()} and nothing
     * else.
     *
     * @param  int|string|null  $team  Where roles are scoped to teams, the one to give it in —
     *                                 one the account belongs to. Null means the team it is in now.
     * @return string|null The role given, or null where this application has no roles.
     */
    public function assign(Model $user, string $role, int|string|null $team = null): ?string
    {
        if (! Roles::enabled() || ! method_exists($user, 'assignRole')) {
            return null;
        }

        if ($role === Roles::superAdmin()) {
            throw AccountException::superAdminIsNotARole($role, $this->emailOf($user));
        }

        // Where the application scopes roles to teams, the scope is whatever
        // the registrar was last told about — and in a command nothing has told
        // it anything. Assigning then writes a null into a column the permission
        // package made NOT NULL, and what reaches the person is a constraint
        // violation where an explanation belongs.
        if (Teams::enabled()) {
            if ($team === null) {
                $team = Teams::currentId($user) ?? throw AccountException::roleNeedsATeam($role, $this->emailOf($user));
            } elseif (! Teams::isMember($team, $user)) {
                throw AccountException::notInTeam($this->emailOf($user), $team);
            }

            Teams::apply($team);
        }

        $model = Roles::roleModel();

        $user->assignRole($model::query()->firstOrCreate([
            'name' => $role,
            'guard_name' => (string) config('auth.defaults.guard', 'web'),
        ]));

        return $role;
    }

    /**
     * The roles this application already has, for something to choose from.
     *
     * @return array<int, string>
     */
    public function roles(): array
    {
        if (! Roles::enabled()) {
            return [];
        }

        try {
            $model = Roles::roleModel();

            /** @var array<int, string> $names */
            $names = $model::query()->pluck('name')->all();

            // The super-admin is its own question, never one to tick beside others.
            return array_values(array_diff($names, [Roles::superAdmin()]));
        } catch (Throwable) {
            // The package is installed and its tables are not migrated yet,
            // which is an ordinary moment during a first install rather than
            // something to stop for.
            return [];
        }
    }
}
