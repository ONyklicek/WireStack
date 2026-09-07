<?php

declare(strict_types=1);

namespace NyonCode\Wire;

use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\Wire\Install\WireInstallCommand;

/**
 * The whole stack in one require.
 *
 * It ships no runtime code of its own — no views, no config, no contracts. What
 * it has is a dependency list and one command, which is the entire reason to
 * install it: `composer require nyoncode/wire` on a clean Laravel brings the
 * stack, and `php artisan wire:install` turns it into a working admin.
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
            ->hasCommand(WireInstallCommand::class)
            ->hasAbout();
    }

    /**
     * @return array<string, string>
     */
    public function aboutData(): array
    {
        return [
            'Installed parts' => (string) count(app(Install\Catalogue::class)->installed()),
            'Available parts' => (string) count(app(Install\Catalogue::class)->components()),
        ];
    }
}
