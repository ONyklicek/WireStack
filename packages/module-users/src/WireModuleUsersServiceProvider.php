<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Eloquent\Model;
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Foundation\View\PageChrome;
use NyonCode\WireModuleUsers\Http\Middleware\SetCurrentTeam;
use NyonCode\WireModuleUsers\Support\Avatars;
use NyonCode\WireModuleUsers\Support\EmailVerification;
use NyonCode\WireModuleUsers\Support\Permissions;
use NyonCode\WireModuleUsers\Support\Roles;
use NyonCode\WireModuleUsers\Support\Teams;
use NyonCode\WireModuleUsers\Support\TwoFactor;

/**
 * A module that arrives as a package.
 *
 * The reference implementation of the path ADR 0029 describes: an application
 * installs this and gets a users area — it edits no config, lists no class, and
 * the module registers itself.
 *
 * **`resolving`, in the register phase.** The callback runs while the container
 * builds the manager, so the module is in the list before `PluginManager::boot()`
 * and before the core provider spreads declarations into the registries.
 * Registering any later is refused outright, because a module that arrives then
 * looks installed and does nothing. The `has()` guard keeps a provider that
 * boots twice — tests do — idempotent.
 */
class WireModuleUsersServiceProvider extends PackageServiceProvider
{
    /**
     * @throws \Exception
     */
    public function configure(Packager $packager): void
    {
        $packager
            ->name('WireModuleUsers')
            ->hasShortName('wire-module-users')
            ->registeredPackage(function (): void {
                $this->app->resolving(PluginManager::class, function (PluginManager $manager): void {
                    if (! $manager->has('users')) {
                        $manager->register(new UsersModule);
                    }
                });
            })
            ->hasConfig()
            ->hasViews()
            ->hasTranslations()
            ->bootedPackage(function (): void {
                $this->bootTeams();
                $this->bootUserMenu();
                $this->bootEmailVerification();
            })
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->publishConfig()
                    ->afterInstallation(fn (InstallCommand $installer) => $this->reportEnvironment($installer));
            })
            ->hasAbout();
    }

    /**
     * Keep a verified flag tied to the address it was granted for.
     *
     * Two Eloquent events rather than a form hook, for the reason
     * {@see EmailVerification} gives: three screens in this module write the
     * address, an application may add a fourth, and `Form::afterSave()` holds
     * one closure — a rule installed there is a rule the next hook removes.
     *
     * `updating` clears the flag in the same statement that writes the address,
     * so the row is never briefly a verified stranger. `updated` asks for the
     * new address to be proven, and only then: a notification sent before the
     * write would advertise an address that a failed save left unwritten.
     *
     * Registered on the configured model rather than globally — this is a rule
     * about *this* application's user, and a listener on `Model` would clear a
     * column on anything that happened to have one.
     */
    protected function bootEmailVerification(): void
    {
        if (! EmailVerification::resetsOnChange()) {
            return;
        }

        $model = config('wire-module-users.model');

        if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
            return;
        }

        $model::updating(static function (Model $user): void {
            EmailVerification::forget($user);
        });

        $model::updated(static function (Model $user): void {
            if ($user->wasChanged(EmailVerification::column())) {
                EmailVerification::requestProof($user);
            }
        });
    }

    /**
     * Everything teams need, and only where this application has them.
     *
     * Two registrations, and they are not the same kind of thing. The
     * middleware is correctness: with `permission.teams` on, every permission
     * read is scoped by whatever team the registrar was last told about, so a
     * request that never tells it sees the previous one — which in a worker is
     * somebody else's. The switcher is a view, and it reaches the shell's top
     * bar through the registry rather than by editing a layout this package does
     * not own.
     *
     * Both are skipped outright where teams are off, because a middleware on
     * every web request and a component in every top bar are not free.
     */
    protected function bootTeams(): void
    {
        if (! Teams::enabled()) {
            return;
        }

        // Pushed onto the group rather than aliased for a route to opt into:
        // the team scopes authorization, and a page that forgot the alias would
        // authorize against the wrong one while looking correct.
        $this->app->make(Kernel::class)->appendMiddlewareToGroup('web', SetCurrentTeam::class);

        $this->app->make(PageChrome::class)->add(
            'wire-module-users::team-switcher',
            PageChrome::TOPBAR,
        );
    }

    /**
     * Put the link to the profile page in the user's own menu.
     *
     * This module owns the page, so this module contributes the link. The shell
     * draws a menu and can name no module's views; before the `USER_MENU` region
     * existed, the only place for this link was a layout slot every application
     * wrote by hand — reaching into this package's translations from a file this
     * package cannot see.
     *
     * Registered whether or not the shell has booted, because provider order is
     * composer's discovery order: a check for the shell here would answer false
     * for one that boots afterwards, and the entry would be missing from a menu
     * that exists. Whether the page is routed at all is the view's question, and
     * it is asked at render because zones give it several answers.
     *
     * The sort keeps it above the auth module's way out. Neither package can see
     * the other, and "Sign out" first reads as a bug.
     */
    protected function bootUserMenu(): void
    {
        if (! config('wire-module-users.profile.menu_item', true)) {
            return;
        }

        $this->app->make(PageChrome::class)->add(
            'wire-module-users::profile-menu-item',
            PageChrome::USER_MENU,
            sort: 10,
        );
    }

    /**
     * Say what this installation actually got.
     *
     * Roles are the half that depends on the application: the module works
     * without them, and an installer that stayed silent about it would leave
     * someone looking for a screen that was never going to appear.
     */
    protected function reportEnvironment(InstallCommand $command): void
    {
        $command->comment('  ✅ Users are registered as the `users` module');

        $this->reportPermissions($command);

        foreach ($this->optionalFeatures() as $line) {
            $command->comment($line);
        }

        if (Roles::enabled()) {
            $command->comment('  ✅ Roles found — role management is part of this installation');

            return;
        }

        // Named where it is actionable rather than as a nested aside: this is the
        // installation that has no roles, and this is the line that gives it some.
        $command->comment('  ↩︎  No roles: install nyoncode/laravel-permission-extended and use ITS HasRoles trait on your user model (not Spatie\'s)');
    }

    /**
     * Everything optional this installation did or did not get.
     *
     * Reported rather than left to be discovered: an avatar upload that never
     * appears because a column is missing, and a two-factor card that never
     * appears because Fortify is not installed, are both indistinguishable from
     * a broken package until somebody says so.
     *
     * @return array<int, string>
     */
    protected function optionalFeatures(): array
    {
        return [
            Avatars::enabled()
                ? '  ✅ Avatars: the `'.Avatars::column().'` column is there, so the profile has a photo'
                : '  ↩︎  No avatars: add a nullable `'.Avatars::column().'` string column to your users table',

            TwoFactor::enabled()
                ? '  ✅ Two-factor: Fortify is installed and its feature is on — the profile card drives it'
                : '  ↩︎  No two-factor: install laravel/fortify and enable its twoFactorAuthentication feature',

            Teams::enabled()
                ? '  ✅ Teams: permission.teams is on — the top bar gets a switcher'
                : '  ↩︎  No teams: install nyoncode/laravel-permission-extended, set permission.teams to true, and point wire-module-users.teams.model at your team model',
        ];
    }

    /**
     * Say which screens are guarded, and say it loudest when none are.
     *
     * These abilities ship with real defaults precisely so this is a non-event
     * on a normal install. The line that matters is the other one: an
     * installation that set them to null has opened the screen that assigns
     * roles, and that decision should be visible at install time rather than
     * discovered later.
     */
    protected function reportPermissions(InstallCommand $command): void
    {
        $open = [];

        foreach (['users', 'roles'] as $resource) {
            foreach (['viewAny', 'view', 'create', 'update'] as $page) {
                if (Permissions::for($resource, $page) === null) {
                    $open[] = "{$resource}.{$page}";
                }
            }
        }

        if ($open === []) {
            $command->comment('  ✅ User and role screens require an ability — see wire-module-users.permissions');

            return;
        }

        $command->comment('  ⚠️  Open to anyone the panel admits: '.implode(', ', $open));
        $command->comment('     Name an ability in wire-module-users.permissions — these screens set passwords and assign roles');
    }

    /** How the `about` row puts it: guarded, partly guarded, or not at all. */
    protected static function permissionSummary(): string
    {
        $named = 0;
        $total = 0;

        foreach (['users', 'roles'] as $resource) {
            foreach (['viewAny', 'view', 'create', 'update'] as $page) {
                $total++;

                if (Permissions::for($resource, $page) !== null) {
                    $named++;
                }
            }
        }

        return match (true) {
            $named === $total => 'all screens guarded',
            $named === 0 => 'OPEN — no ability required',
            default => "{$named} of {$total} screens guarded",
        };
    }

    /**
     * @return array<string, string>
     */
    public function aboutData(): array
    {
        return [
            'User model' => (string) config('wire-module-users.model'),
            'Permissions' => self::permissionSummary(),
            'Roles' => Roles::enabled() ? 'enabled' : 'off',
            'Avatars' => Avatars::enabled() ? 'enabled' : 'off',
            'Two-factor' => TwoFactor::enabled() ? 'enabled' : 'off',
            'Teams' => Teams::enabled() ? 'enabled' : 'off',
        ];
    }
}
