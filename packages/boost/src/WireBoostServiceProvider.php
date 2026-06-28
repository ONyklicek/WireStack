<?php

declare(strict_types=1);

namespace NyonCode\WireBoost;

use Laravel\Mcp\Facades\Mcp;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\WireBoost\Console\InstallCommand;
use NyonCode\WireBoost\Console\McpCommand;
use NyonCode\WireBoost\Console\UpdateCommand;
use NyonCode\WireBoost\Mcp\WireBoostServer;
use NyonCode\WireBoost\Support\ComponentScanner;
use NyonCode\WireBoost\Support\DocsIndex;
use NyonCode\WireBoost\Support\TypeCatalog;

class WireBoostServiceProvider extends PackageServiceProvider
{
    /**
     * @throws \Exception
     */
    public function configure(Packager $packager): void
    {
        $packager
            ->name('WireBoost')
            ->hasShortName('wire-boost')
            ->registeredPackage(function (): void {
                $this->app->singleton(TypeCatalog::class);
                $this->app->singleton(ComponentScanner::class);
                $this->app->singleton(DocsIndex::class, fn (): DocsIndex => DocsIndex::default());
            })
            ->bootedPackage(function (): void {
                Mcp::local('wire-boost', WireBoostServer::class);
            })
            ->hasConfig()
            ->hasAbout()
            ->hasCommands([
                InstallCommand::class,
                UpdateCommand::class,
                McpCommand::class,
            ]);
    }
}
