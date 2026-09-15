<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Install;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupStep;
use NyonCode\WireCore\Foundation\Setup\MigrationFootprint;
use NyonCode\WireCore\Foundation\Setup\RedundantMigrations;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupState;
use NyonCode\WireModuleUsers\Support\Roles;
use Throwable;

/**
 * Roles, which this module has screens for and no implementation of.
 *
 * `wire-module-users.roles` is `auto`, and auto means "on where the permission
 * layer is actually wired up" — the package installed, `config/permission.php`
 * published, the tables migrated, and the application's own `User` carrying
 * `HasRoles`. Miss the last of those and every role save fails at `syncRoles()`,
 * after the record has been written. Miss any of them and the role screens are
 * simply absent, which is the failure people report as "the roles page is
 * missing" without knowing where to look.
 *
 * ## It runs the permission package's own installer
 *
 * `nyoncode/laravel-permission-extended` already publishes Spatie's config and
 * migration, publishes its own config, and patches the user model — that is
 * `permission-extended:install`, and calling it is the whole of this step's
 * work. A second copy of that logic here would be a second answer to "is the
 * model patched", and the two would disagree the first time either changed.
 *
 * ## Blocked, not Pending, without the package
 *
 * The extended package is a suggestion rather than a dependency: this module
 * works without roles, with the role screens and the role field absent rather
 * than present and broken. So the answer to a missing package is a
 * `composer require` line, never an installer that shells out to composer
 * inside the application it is changing.
 *
 * **Bare `spatie/laravel-permission` is deliberately not enough.** The wildcard
 * matching, the super-admin gate and the permission-change events these screens
 * assume live only in the extended package, so a user model on Spatie's own
 * trait is not detected and the surfaces stay off.
 */
final readonly class EnableRoles implements SetupStep
{
    public function __construct(private Kernel $artisan, private RedundantMigrations $migrations) {}

    public function label(): string
    {
        return 'Roles & permissions';
    }

    public function state(): SetupState
    {
        if (! $this->installed()) {
            return SetupState::Blocked;
        }

        return Roles::available() ? SetupState::Done : SetupState::Pending;
    }

    public function summary(): string
    {
        if (! $this->installed()) {
            return 'run `composer require nyoncode/laravel-permission-extended` for role management';
        }

        if (Roles::available()) {
            return 'roles are wired up, so the role screens are on';
        }

        return $this->published()
            ? 'add `HasRoles` to your user model, or every role save fails at the last step'
            : 'publish the permission config and patch your user model';
    }

    public function apply(SetupConsole $console): SetupOutcome
    {
        // Not interactive, and deliberately: the installer being called asks
        // before it patches somebody's model, and its prompt would be drawn
        // inside this command's own listing where there is nobody to answer it.
        // Its defaults are yes, which is what saying yes to this step meant.
        //
        // The installer publishes Spatie's migration unless `database/migrations`
        // has one by that name, and then runs `migrate` itself — so an
        // application whose permission tables came from anywhere else (a schema
        // dump, a registered path) stopped on "table roles already exists" before
        // its user model was ever patched. The copy is left out as it is
        // published, which is before that `migrate`.
        $code = $this->migrations->around(
            fn (): int => $this->artisan->call(Roles::INSTALLER, ['--no-interaction' => true]),
            static fn (string $name) => $console->note("Left out the {$name} migration — this application already has the permission tables."),
            ['create_permission_tables' => $this->permissionTables()],
        );

        if ($code !== 0) {
            $console->warn('php artisan '.Roles::INSTALLER.' did not finish — run it yourself to see why.');

            return SetupOutcome::Failed;
        }

        // The model is said rather than checked. `class_uses_recursive` reads a
        // class this process loaded before the file was patched, so asking
        // again here would answer about the old source and report a failure
        // that is not one. The next run is where it reads true.
        //
        // The tables are checked: the installer's own `migrate` asks before
        // touching a production database, and unattended that answer is no.
        $console->note($this->tablesExist()
            ? 'Published the permission config, migrated its tables and put `HasRoles` on your user model.'
            : 'Published the permission config and put `HasRoles` on your user model. Its tables are migrated with the rest.');

        return SetupOutcome::Applied;
    }

    public function package(): string
    {
        return 'nyoncode/wire-module-users';
    }

    public function sort(): int
    {
        // Before the migrations step, because it publishes one — Spatie's
        // permission tables — and after teams, because the installer it runs
        // ends in a `migrate` of its own, which reads the teams switch as the
        // tables are made.
        return 50;
    }

    /**
     * Whether there is anything here to run.
     *
     * The installer being registered, rather than the trait existing in
     * `vendor`: a package can be there while the application has excluded its
     * provider from discovery, and then the trait is autoloadable, the command
     * is not, and calling it aborts the whole run with a message about Symfony's
     * console.
     */
    private function installed(): bool
    {
        return Roles::installable($this->artisan);
    }

    /**
     * What Spatie's migration makes, which it names from config rather than in its source.
     */
    private function permissionTables(): MigrationFootprint
    {
        $names = (array) config('permission.table_names', []);
        $tables = [];

        foreach (['roles', 'permissions', 'model_has_permissions', 'model_has_roles', 'role_has_permissions'] as $key) {
            $tables[] = (string) ($names[$key] ?? $key);
        }

        return new MigrationFootprint(creates: $tables);
    }

    private function tablesExist(): bool
    {
        try {
            return Schema::hasTable((string) config('permission.table_names.roles', 'roles'));
        } catch (Throwable) {
            // No database to ask: nothing was migrated into it either.
            return false;
        }
    }

    private function published(): bool
    {
        return is_file(config_path('permission.php'));
    }
}
