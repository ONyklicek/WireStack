<?php

declare(strict_types=1);

namespace NyonCode\WireSortable;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Foundation\Assets\Bundle;
use NyonCode\WireCore\Foundation\Icons\IconManager;
use NyonCode\WireTable\Table;

class WireSortableServiceProvider extends PackageServiceProvider
{
    /** Absolute path to the pre-bundled, self-registering sortable assets. */
    public const ASSETS_PATH = __DIR__.'/../dist';

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
                $this->registerIcons();
                Bundle::serve('wire-sortable', self::ASSETS_PATH);
            })
            ->hasConfig()
            ->hasViews()
            ->hasAssets('dist', entries: [
                Bundle::make('wire-sortable.js'),
            ])
            ->hasAssetFallback(Bundle::servedByRoute('wire-sortable'))
            ->hasTranslations()
            ->hasMigrations()
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
     * The grip this package draws its handle with, through the canonical owner.
     *
     * A six-dot grip is in no icon set the framework ships, and the alternative
     * to registering one is an inline `<svg>` in the handle partial — which is
     * the thing the Icons rule exists to prevent. Registered from a `.svg` file
     * rather than a PHP string so the markup stays markup, and prefixed so it
     * cannot collide with a consumer's own `grip`.
     */
    protected function registerIcons(): void
    {
        app(IconManager::class)->registerIconsFromDirectory(
            __DIR__.'/../resources/icons',
            'sortable',
        );
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

        // Canonical owner of the drag-handle markup. The handle SVG lives in a
        // Blade partial instead of a hand-built JS string; the rendered HTML is
        // injected into the sortable Alpine component as config.
        Table::macro('getDragHandleHtml', function (): Htmlable {
            return new HtmlString(view('wire-sortable::partials.drag-handle')->render());
        });
    }
}
