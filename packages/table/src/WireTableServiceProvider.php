<?php

declare(strict_types=1);

namespace NyonCode\WireTable;

use Livewire\Mechanisms\HandleComponents\HandleComponents;
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\WireTable\Livewire\TableStateSynthesizer;

class WireTableServiceProvider extends PackageServiceProvider
{
    /**
     * Configure the package.
     *
     * @throws \Exception
     */
    public function configure(Packager $packager): void
    {
        $packager
            ->name('WireTable')
            ->hasShortName('wire-table')
            ->bootedPackage(function ($packager) {
                app(HandleComponents::class)
                    ->registerPropertySynthesizer(TableStateSynthesizer::class);
            })
            ->hasConfig()
            ->hasViews()
            ->hasMigrations()
            ->hasTranslations()
            ->hasAbout()
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfig()
                    ->publishMigrations()
                    ->publishViews()
                    ->publishTranslations();
            });
    }

    /**
     * Extra rows for this package's `php artisan about` section (the toolkit
     * already prepends "Version"). Values are closures so config resolves at
     * boot, not at declaration time.
     *
     * @return array<string, string|\Closure>
     */
    public function aboutData(): array
    {
        return [
            'Per page' => fn (): string => (string) config('wire-table.defaults.per_page', 10),
            'Preferences' => fn (): string => (string) config('wire-table.preferences.default', 'null'),
        ];
    }
}
