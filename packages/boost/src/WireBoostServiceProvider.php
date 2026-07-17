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
use NyonCode\WireBoost\Support\Docs\DocsCorpus;
use NyonCode\WireBoost\Support\Docs\DocsIndex;
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
                // Singleton so the corpus is parsed and scored once per MCP
                // process rather than on every tool call.
                $this->app->singleton(DocsIndex::class, fn (): DocsIndex => DocsIndex::default());
                $this->app->singleton(DocsCorpus::class, fn ($app): DocsCorpus => $app->make(DocsIndex::class)->corpus());
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
