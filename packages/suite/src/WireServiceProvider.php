<?php

declare(strict_types=1);

namespace NyonCode\Wire;

use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\Wire\Install\Catalogue;
use NyonCode\Wire\Install\Setup;
use NyonCode\Wire\Install\Steps\RunMigrations;
use NyonCode\Wire\Install\Steps\SendSignInToPanel;
use NyonCode\Wire\Install\WireInstallCommand;
use NyonCode\WireCore\Foundation\Setup\SetupRegistry;

/**
 * The whole stack in one require.
 *
 * It ships no runtime code of its own — no views, no config, no contracts. What
 * it has is a dependency list and one command, which is the entire reason to
 * install it: `composer require nyoncode/wire-suite` on a clean Laravel brings
 * the stack, and `php artisan wire:install` turns it into a working admin.
 *
 * Modules stay `suggest` rather than `require`: an application that wants users
 * and nothing else should not carry a media library, and a meta-package that
 * decides otherwise is the reason people avoid meta-packages.
 */
class WireServiceProvider extends PackageServiceProvider
{
    /**
     * @throws \Exception
     */
    public function configure(Packager $packager): void
    {
        $packager
            ->name('Wire')
            ->hasShortName('wire')
            // Bound rather than left to be auto-resolved, so the catalogue an
            // application substitutes replaces one thing rather than racing the
            // container's guess — and so counting the stack for `about` does not
            // build it twice.
            ->registeredPackage(function (): void {
                $this->app->singleton(Catalogue::class);
                $this->app->singleton(Setup::class);

                // The suite contributes the one step that is nobody's package
                // in particular: three modules each ended their installer with
                // "Run: php artisan migrate", and migrating is about the
                // application rather than any of them.
                SetupRegistry::instance()->register(RunMigrations::class);

                // And the one that needs two packages that may not know each
                // other: Fortify's `home` is module-auth's file, the admin's
                // root is wire-panels' route. See the step for why.
                SetupRegistry::instance()->register(SendSignInToPanel::class);
            })
            ->hasCommand(WireInstallCommand::class)
            ->hasAbout();
    }

    /**
     * Extra rows for this package's `php artisan about` section.
     *
     * Closures, because the toolkit evaluates this at boot — on web requests
     * too, not only in the console — and counting the stack means asking the
     * autoloader about a dozen classes for a number nothing but `about` reads.
     *
     * @return array<string, \Closure>
     */
    public function aboutData(): array
    {
        return [
            'Installed parts' => fn (): string => (string) count($this->app->make(Catalogue::class)->installed()),
            'Available parts' => fn (): string => (string) count($this->app->make(Catalogue::class)->components()),
        ];
    }
}
