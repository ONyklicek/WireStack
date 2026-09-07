<?php

declare(strict_types=1);

namespace NyonCode\WireAdmin;

use Illuminate\Support\Facades\Blade;
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\WireAdmin\Exceptions\AdminInstallException;
use NyonCode\WireAdmin\Install\InstallOutcome;
use NyonCode\WireAdmin\Install\InstallScaffold;
use NyonCode\WireCore\Core\Resources\Workspace;

/**
 * The optional admin shell.
 *
 * The top of the graph — it requires `wire-panels` and everything under it, and
 * nothing requires it. That direction is the opt-in: three ADRs (0020, 0026,
 * 0027) held that the owner layer holds no shell, so an application installing
 * `wire-panels` for its pages and its routing macro keeps getting exactly that.
 * `composer require` is the only switch, and it needs no config key to turn off.
 *
 * Nothing here sets `livewire.component_layout`. Installing the package must not
 * be the same act as adopting its chrome — an application may want the sidebar
 * inside a layout of its own, and which layout a page renders in stays its
 * decision (ADR 0028 §2).
 *
 * What it ships is markup: `<x-wire-admin::layout>` and
 * `<x-wire-admin::sidebar>`, over the seams that already existed. There is no
 * `Panel` object and no registry — the shell reads {@see Workspace}, and
 * `vendor:publish` is how an application changes any of it.
 */
class WireAdminServiceProvider extends PackageServiceProvider
{
    /**
     * @throws \Exception
     */
    public function configure(Packager $packager): void
    {
        $packager
            ->name('WireAdmin')
            ->hasShortName('wire-admin')
            ->bootedPackage(function (): void {
                // Class-based, the way core registers its own tags: the layout
                // and the sidebar both resolve services, and a component class
                // is where that belongs rather than in a Blade file.
                Blade::componentNamespace('NyonCode\\WireAdmin\\View', 'wire-admin');
            })
            ->hasViews()
            ->hasTranslations()
            // Brand only. Everything else the shell does is a slot, and this is
            // the one thing a slot cannot carry: the logo has to be known by the
            // sidebar, which an application does not render itself (ADR 0028 §1b
            // refuses a shell *object*, not a file naming an image).
            ->hasConfig()
            // A provider the consumer owns, the way Cashier and Fortify ship one:
            // it holds the single line that names the layout. Shipped as a .stub
            // so this package's own test suite, PHPStan and coverage never load a
            // template that references App\ classes.
            ->hasProviders(['../stubs/WireAdminServiceProvider.stub'])
            // `php artisan wire-admin:install` — the point at which an
            // application *asks* for the shell. The package itself still adopts
            // nothing on `composer require` (ADR 0028 §2); running this is a
            // different act, and it does the two things composer cannot: write
            // the application's own layout view, and register the provider that
            // names it.
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->publishConfig()
                    ->publishTranslations()
                    ->publishProviders()
                    ->afterInstallation(fn (InstallCommand $installer) => $this->scaffoldApplication($installer));
            })
            ->hasAbout();
    }

    /**
     * Write the three things that are the application's rather than the
     * package's, and say what happened to each.
     *
     * Never overwrites, so running the installer twice is safe. The layout is
     * allowed to abort the run — the toolkit deliberately does not wrap hooks in
     * a try/catch, and an installer that cannot write the one file the whole
     * feature hangs on should stop rather than print a summary.
     *
     * The other two are caught on purpose. An application that keeps its
     * providers somewhere else is not broken, it is a Laravel 10 application or
     * one with its own convention; an application whose stylesheet is not where
     * Laravel puts it is not broken either. Both get the line to add, as a
     * warning, and everything else this command did still stands.
     */
    protected function scaffoldApplication(InstallCommand $command): void
    {
        $scaffold = new InstallScaffold($this->app->basePath());

        $command->comment(match ($scaffold->layout()) {
            InstallOutcome::Created => '  ✅ Wrote resources/views/components/layouts/admin.blade.php',
            InstallOutcome::AlreadyPresent => '  ↩︎  resources/views/components/layouts/admin.blade.php already exists — left as it is',
        });

        try {
            $command->comment(match ($scaffold->stylesheetSources()) {
                InstallOutcome::Created => '  ✅ Pointed Tailwind at the packages'."'".' views in resources/css/app.css',
                InstallOutcome::AlreadyPresent => '  ↩︎  Tailwind already scans vendor/nyoncode',
            });
        } catch (AdminInstallException $e) {
            $command->warn('  ⚠️  '.$e->getMessage());
        }

        try {
            $command->comment(match ($scaffold->stylesheetPrimary()) {
                InstallOutcome::Created => '  ✅ Set `primary` to Tailwind blue in resources/css/app.css',
                InstallOutcome::AlreadyPresent => '  ↩︎  `primary` is already defined — left as it is',
            });
        } catch (AdminInstallException $e) {
            $command->warn('  ⚠️  '.$e->getMessage());
        }

        try {
            $command->comment(match ($scaffold->registerProvider()) {
                InstallOutcome::Created => '  ✅ Registered App\\Providers\\WireAdminServiceProvider in bootstrap/providers.php',
                InstallOutcome::AlreadyPresent => '  ↩︎  App\\Providers\\WireAdminServiceProvider is already registered',
            });
        } catch (AdminInstallException $e) {
            $command->warn('  ⚠️  '.$e->getMessage());
        }

        $command->comment('');
        $command->comment('  Your pages render in the shell as soon as they are routed:');
        $command->comment('  Route::wireResources() in routes/web.php, or wire-panels.routes.enabled in config.');
    }

    /**
     * @return array<string, string>
     */
    public function aboutData(): array
    {
        return [
            'Navigation groups' => (string) count(app(Workspace::class)->navigation()),
        ];
    }
}
