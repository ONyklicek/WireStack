<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Install;

use Illuminate\Contracts\Console\Kernel;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupStep;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupState;
use NyonCode\WireModuleUsers\Support\Roles;

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
    public function __construct(private Kernel $artisan) {}

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
        if ($this->artisan->call(Roles::INSTALLER, ['--no-interaction' => true]) !== 0) {
            $console->warn('php artisan '.Roles::INSTALLER.' did not finish — run it yourself to see why.');

            return SetupOutcome::Failed;
        }

        // Said rather than checked. `class_uses_recursive` reads a class this
        // process loaded before the file was patched, so asking again here
        // would answer about the old source and report a failure that is not
        // one. The next run is where it reads true.
        $console->note('Published the permission config, migrated its tables and put `HasRoles` on your user model.');

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

    private function published(): bool
    {
        return is_file(config_path('permission.php'));
    }
}
