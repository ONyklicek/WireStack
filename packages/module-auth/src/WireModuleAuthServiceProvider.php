<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth;

use Illuminate\Support\Facades\Blade;
use Laravel\Fortify\Fortify;
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\WireCore\Core\Modules\Module;
use NyonCode\WireCore\Foundation\View\PageChrome;
use NyonCode\WireModuleAuth\Install\LayoutScaffold;
use NyonCode\WireModuleAuth\Support\Frame;
use NyonCode\WireModuleAuth\Support\Screens;
use NyonCode\WireModuleAuth\View\Screen;

/**
 * The signed-out surface, as a package.
 *
 * **Laravel Fortify owns authentication, and this package owns none of it.**
 * The credential check, the login throttle keyed on address and IP, the session
 * regeneration that closes fixation, the reset tokens and their expiry, the
 * signed verification links, the TOTP window and the recovery codes are a
 * security surface with a maintained owner. Re-implementing them here would buy
 * this framework nothing and cost it every CVE.
 *
 * What Fortify deliberately does not have is a screen. It is headless: it ships
 * the routes and asks the application for the markup, through seven view
 * callbacks. Before this package, a wire application answered them itself — or
 * more often did not, and installed the whole panel with no way to sign in to
 * it. That is the gap, and it is exactly seven views wide.
 *
 * So this is not a {@see Module}, and the
 * distinction is worth keeping: a module is a manifest of resources, dashboards
 * and a navigation group (ADR 0029), and this package registers none of those —
 * there is no admin page for authentication, only the pages on the way in. It
 * ships in the same catalogue and under the same name because that is what an
 * installer offers, not because it declares the same things.
 */
class WireModuleAuthServiceProvider extends PackageServiceProvider
{
    /**
     * @throws \Exception
     */
    public function configure(Packager $packager): void
    {
        $packager
            ->name('WireModuleAuth')
            ->hasShortName('wire-module-auth')
            ->hasConfig()
            ->hasViews()
            ->hasTranslations()
            ->bootedPackage(function (): void {
                Blade::component('wire-module-auth::screen', Screen::class);

                $this->registerScreens();
                $this->registerSignOut();
            })
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->publishConfig()
                    ->publishTranslations()
                    ->afterInstallation(fn (InstallCommand $installer) => $this->reportEnvironment($installer));
            })
            ->hasAbout();
    }

    /**
     * Answer Fortify's seven view callbacks.
     *
     * All seven, unconditionally, rather than one per enabled feature: a view
     * for a feature that is off is never routed to, so gating them here would be
     * a second copy of `fortify.features` that can disagree with the first.
     *
     * In **booted**, so an application's own provider — which boots after every
     * package's — can name a view of its own for any of them and win. That is
     * the documented way to keep six of these and replace the seventh.
     */
    protected function registerScreens(): void
    {
        if (! config('wire-module-auth.views', true)) {
            return;
        }

        Fortify::loginView('wire-module-auth::login');
        Fortify::registerView('wire-module-auth::register');
        Fortify::requestPasswordResetLinkView('wire-module-auth::forgot-password');
        Fortify::resetPasswordView('wire-module-auth::reset-password');
        Fortify::verifyEmailView('wire-module-auth::verify-email');
        Fortify::confirmPasswordView('wire-module-auth::confirm-password');
        Fortify::twoFactorChallengeView('wire-module-auth::two-factor-challenge');
    }

    /**
     * Put the way out in the user menu.
     *
     * Registered on the config alone, and **not** on whether the shell is here:
     * provider order is composer's discovery order, so a shell that boots after
     * this would fail the check and the entry would be missing from a menu that
     * exists — installed, silent, empty, which is the failure ADR 0029 spent a
     * decision on. Whether there is a shell to draw a row for is a question the
     * view asks at render, where the answer is final.
     *
     * Sorted after the profile link the users module contributes, for the same
     * reason: "Sign out" above "Profile" reads as a bug, and neither package can
     * see the other to avoid it.
     */
    protected function registerSignOut(): void
    {
        if (! config('wire-module-auth.user_menu', true)) {
            return;
        }

        $this->app->make(PageChrome::class)->add(
            'wire-module-auth::user-menu',
            PageChrome::USER_MENU,
            sort: 100,
        );
    }

    /**
     * Say what this installation actually got, and what it still owes.
     *
     * Both halves are things an application would otherwise find out from a
     * user: a login screen with no frame to render in, and a panel whose routes
     * let anyone in.
     */
    protected function reportEnvironment(InstallCommand $command): void
    {
        $command->comment('  ✅ Fortify\'s screens are answered — login, reset, verification, two-factor');

        $this->scaffoldLayout($command);

        foreach ($this->routeGuardReport() as $line) {
            $command->comment($line);
        }
    }

    /**
     * Write the application's own signed-out layout, where there is a frame for
     * it to name.
     *
     * Only with the shell installed, because the stub names the shell's frame.
     * Without one there is nothing to write that would be true, so the line an
     * application needs is the config key — which is what it gets instead of a
     * file it would have to rewrite.
     *
     * Allowed to abort the run: the toolkit deliberately does not wrap install
     * hooks in a try/catch, and an installer that cannot write the one file the
     * screens' styling hangs on should stop rather than print a summary.
     */
    protected function scaffoldLayout(InstallCommand $command): void
    {
        if (! Frame::hasShell()) {
            $command->comment('  ↩︎  No shell: name your own layout in config/wire-module-auth.php — the screens and the sign-out entry render in it');

            return;
        }

        $command->comment((new LayoutScaffold($this->app->basePath()))->write()
            ? '  ✅ Wrote '.LayoutScaffold::PATH.' — your stylesheet loads on the sign-in screens'
            : '  ↩︎  '.LayoutScaffold::PATH.' already exists — left as it is');
    }

    /**
     * Whether anything actually stands between a visitor and the panel.
     *
     * Measured off the routing config rather than assumed: a login screen in
     * front of an unguarded panel is decoration, and every diagnostic short of
     * visiting the URL signed out reports success.
     *
     * @return array<int, string>
     */
    protected function routeGuardReport(): array
    {
        if (! config('wire-panels.routes.enabled', false)) {
            // The routes are the application's own, written in its route file
            // inside whatever group it chose. Nothing here can read that, and
            // guessing would be worse than the line below.
            return ['  ↩︎  Your pages are routed by hand — make sure the group they are in has the `auth` middleware'];
        }

        $middleware = (array) config('wire-panels.routes.middleware', []);

        return [
            in_array('auth', $middleware, true)
                ? '  ✅ The panel routes require a signed-in user'
                : '  ⚠️  wire-panels.routes.middleware has no `auth` — your panel is reachable signed out',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function aboutData(): array
    {
        return [
            'Screens' => config('wire-module-auth.views', true) ? 'answered by wire-module-auth' : 'left to the application',
            'Frame' => Frame::hasApplicationLayout() || Frame::hasShell()
                ? Frame::component()
                : (string) config('wire-module-auth.layout'),
            'Registration' => Screens::canRegister() ? 'open' : 'closed',
            'Two-factor' => Screens::hasTwoFactor() ? 'enabled' : 'off',
        ];
    }
}
