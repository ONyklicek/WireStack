<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Install;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupStep;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupState;
use NyonCode\WireModuleUsers\Support\Roles;
use Throwable;

/**
 * Somebody to sign in as.
 *
 * The gap nothing in this framework covered. Every package installed, every
 * migration run, the shell scaffolded, the routes registered — and then the
 * login screen, with no account behind it and no command anywhere in the stack
 * that makes one. The documented answer was `php artisan tinker`.
 *
 * It belongs to this module and not to the installer that asks, because what a
 * user *is* here is the application's own model, its own column names
 * (`wire-module-users.fields`) and its own roles — three things `wire-suite` has
 * no business knowing.
 *
 * ## Only ever the first
 *
 * {@see state()} is Done the moment the table has a row in it. This creates the
 * account that gets you in; it is not a user-management command, and an
 * installer that offered to add another administrator on every run would be one
 * nobody could run twice safely.
 */
final class CreateFirstAdministrator implements SetupStep
{
    public function label(): string
    {
        return 'First administrator';
    }

    public function state(): SetupState
    {
        $model = $this->model();

        if ($model === null || ! class_exists($model)) {
            return SetupState::Blocked;
        }

        try {
            if (! Schema::hasTable($this->table($model))) {
                return SetupState::Blocked;
            }

            return $model::query()->exists() ? SetupState::Done : SetupState::Pending;
        } catch (Throwable) {
            // No database yet. The step above this one says so in its own words;
            // saying it twice would be noise, so this just stands aside.
            return SetupState::Blocked;
        }
    }

    public function summary(): string
    {
        $model = $this->model();

        if ($model === null || ! class_exists($model)) {
            return 'no user model — point wire-module-users.model at yours';
        }

        try {
            if (! Schema::hasTable($this->table($model))) {
                return 'the users table is not there yet — run the migrations first';
            }

            if ($model::query()->exists()) {
                return 'an account already exists, so you can sign in';
            }
        } catch (Throwable) {
            return 'no database connection yet';
        }

        return 'create an account to sign in with'.(Roles::enabled() ? ', and give it the super-admin role' : '');
    }

    public function apply(SetupConsole $console): SetupOutcome
    {
        if (! $console->isInteractive()) {
            // A generated password printed into a deploy log is a credential in
            // a log, and an empty one is an account anybody can use. Neither is
            // better than saying so and leaving it.
            $console->warn('Needs a name, an e-mail and a password — run wire:install again without --no-interaction.');

            return SetupOutcome::Skipped;
        }

        /** @var class-string<Model> $model */
        $model = $this->model();
        $fields = $this->fields();

        $name = $console->ask('Name', 'Administrator');
        $email = $console->ask('E-mail address');
        $password = $console->secret('Password');

        if ($email === '' || $password === '') {
            $console->warn('No e-mail or no password — nothing was created.');

            return SetupOutcome::Skipped;
        }

        try {
            $user = $model::query()->create([
                $fields['name'] => $name,
                $fields['email'] => $email,
                $fields['password'] => Hash::make($password),
            ]);
        } catch (Throwable $e) {
            $console->warn('Could not create the account: '.$e->getMessage());

            return SetupOutcome::Failed;
        }

        $console->note("Created {$email}.");

        $this->makeSuperAdmin($user, $console);

        return SetupOutcome::Applied;
    }

    public function sort(): int
    {
        // After the tables exist, and after the shell and the routes — this is
        // the last thing that has to be true before somebody can sign in.
        return 400;
    }

    /**
     * Give the account the role that can reach everything.
     *
     * Silently absent where roles are: the module works without
     * `nyoncode/laravel-permission-extended`, and an account with no role in an
     * application with no roles is a complete account rather than a half-done
     * one.
     *
     * The role name is the permission package's own
     * (`permission-extended.super_admin_role`), because that is the name its
     * gate checks — inventing one here would make an administrator the gate
     * does not recognise.
     */
    private function makeSuperAdmin(Model $user, SetupConsole $console): void
    {
        if (! Roles::enabled() || ! method_exists($user, 'assignRole')) {
            return;
        }

        $name = (string) config('permission-extended.super_admin_role', 'super-admin');
        $role = Roles::roleModel();

        try {
            $user->assignRole($role::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => (string) config('auth.defaults.guard', 'web'),
            ]));

            $console->note("Gave it the `{$name}` role.");
        } catch (Throwable $e) {
            // The account is made and usable; only the role is missing, and
            // that is a screen away rather than a reason to call the step
            // failed and leave somebody wondering whether the user exists.
            $console->warn("Created the account, but not the `{$name}` role: ".$e->getMessage());
        }
    }

    /** @return class-string<Model>|null */
    private function model(): ?string
    {
        $model = config('wire-module-users.model');

        return is_string($model) && $model !== '' ? $model : null;
    }

    /**
     * The columns this application calls its own.
     *
     * `wire-module-users.fields` exists because a `users` table is the one
     * table every application has changed, and a step that wrote `name` into a
     * schema calling it `full_name` would fail at the last moment.
     *
     * @return array{name: string, email: string, password: string}
     */
    private function fields(): array
    {
        /** @var array<string, string> $fields */
        $fields = (array) config('wire-module-users.fields', []);

        return [
            'name' => $fields['name'] ?? 'name',
            'email' => $fields['email'] ?? 'email',
            'password' => $fields['password'] ?? 'password',
        ];
    }

    /** @param  class-string<Model>  $model */
    private function table(string $model): string
    {
        return (new $model)->getTable();
    }
}
