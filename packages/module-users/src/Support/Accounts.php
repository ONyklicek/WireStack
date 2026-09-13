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
     * Give the account the role that can reach everything.
     *
     * The name is the permission package's own
     * (`permission-extended.super_admin_role`), because that is the name its
     * gate checks — inventing one here would make an administrator the gate
     * does not recognise.
     *
     * @return string|null The role given, or null where this application has no roles.
     */
    public function makeSuperAdmin(Model $user): ?string
    {
        return $this->assign($user, $this->superAdminRole());
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
        return (string) config('permission-extended.super_admin_role', 'super-admin');
    }

    /**
     * Give the account a role, creating it when this application has not.
     *
     * @return string|null The role given, or null where this application has no roles.
     */
    public function assign(Model $user, string $role): ?string
    {
        if (! Roles::enabled() || ! method_exists($user, 'assignRole')) {
            return null;
        }

        // Where the application scopes roles to teams, the scope is whatever
        // the registrar was last told about — and in a command nothing has told
        // it anything. Assigning then writes a null into a column the permission
        // package made NOT NULL, and what reaches the person is a constraint
        // violation where an explanation belongs.
        if (Teams::enabled()) {
            $team = Teams::currentId($user);

            if ($team === null) {
                throw AccountException::roleNeedsATeam($role);
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

            return $names;
        } catch (Throwable) {
            // The package is installed and its tables are not migrated yet,
            // which is an ordinary moment during a first install rather than
            // something to stop for.
            return [];
        }
    }
}
