<?php

declare(strict_types=1);

namespace NyonCode\WireTable;

use Livewire\Mechanisms\HandleComponents\HandleComponents;
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
            ->hasConfig()
            ->hasViews()
            ->hasTranslations()
            ->hasAbout();
    }

    /**
     * Boot the package services.
     */
    public function boot(): void
    {
        parent::boot();

        app(HandleComponents::class)->registerPropertySynthesizer(TableStateSynthesizer::class);
    }
}
