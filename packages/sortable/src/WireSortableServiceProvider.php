<?php

declare(strict_types=1);

namespace NyonCode\WireSortable;

use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireTable\Table;

class WireSortableServiceProvider extends PackageServiceProvider
{
    /**
     * @throws \Exception
     */
    public function configure(Packager $packager): void
    {
        $packager
            ->name('WireSortable')
            ->hasShortName('wire-sortable')
            ->registeredPackage(function ($packager) {
                $this->app->resolving(PluginManager::class, function (PluginManager $manager) {
                    if (! $manager->has('sortable')) {
                        $manager->register(new SortablePlugin);
                    }
                });
            })
            ->bootedPackage(function ($packager) {
                $this->registerTableMacros();
            })
            ->hasConfig()
            ->hasViews()
            ->hasTranslations()
            ->hasMigrations()
            ->hasAbout()
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfig()
                    ->publishMigrations();
            });
    }

    protected function registerTableMacros(): void
    {
        Table::macro('reorderable', function (?string $orderColumn = null, bool $condition = true): static {
            $this->sortableReorderable = $condition;

            if ($orderColumn !== null) {
                $this->sortableOrderColumn = $orderColumn;
            }

            return $this;
        });

        Table::macro('isReorderable', function (): bool {
            return $this->sortableReorderable ?? false;
        });

        Table::macro('alwaysReorderable', function (?string $orderColumn = null): static {
            $this->sortableReorderable = true;
            $this->sortableAlwaysReorderable = true;

            if ($orderColumn !== null) {
                $this->sortableOrderColumn = $orderColumn;
            }

            return $this;
        });

        Table::macro('isAlwaysReorderable', function (): bool {
            return $this->sortableAlwaysReorderable ?? false;
        });

        Table::macro('getOrderColumn', function (): string {
            if (isset($this->sortableOrderColumn)) {
                return $this->sortableOrderColumn;
            }

            return app()->bound('config')
                ? config('wire-sortable.order_column', 'sort_order')
                : 'sort_order';
        });

        Table::macro('paginatedWhileReordering', function (bool $enabled = true): static {
            $this->sortablePaginatedWhileReordering = $enabled;

            return $this;
        });

        Table::macro('isPaginatedWhileReordering', function (): bool {
            return $this->sortablePaginatedWhileReordering ?? false;
        });

        Table::macro('columnReorderable', function (bool $enabled = true): static {
            $this->sortableColumnReorderable = $enabled;

            return $this;
        });

        Table::macro('isColumnReorderable', function (): bool {
            return $this->sortableColumnReorderable ?? false;
        });
    }
}
