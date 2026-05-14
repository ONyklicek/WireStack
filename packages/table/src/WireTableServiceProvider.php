<?php

declare(strict_types=1);

namespace NyonCode\WireTable;

use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;

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
}
