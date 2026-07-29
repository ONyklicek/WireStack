<?php

declare(strict_types=1);

namespace NyonCode\WireSortable;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Foundation\Assets\AssetManager;
use NyonCode\WireCore\Foundation\Assets\Js;
use NyonCode\WireTable\Table;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
                $this->registerAssetRoutes();
                $this->registerAssets();
            })
            ->hasConfig()
            ->hasViews()
            ->hasAssets('dist')
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
     * Declare the drag controller to the canonical asset registry, so an app that
     * puts one `@wireStackScripts` in its layout gets reordering on every page —
     * including one reached by `wire:navigate`, where a bundle arriving with the
     * new document can lose the race on the cached Back/Forward path.
     *
     * Registration is push-based from this provider: `wire-core` owns the registry
     * but never learns that `wire-sortable` exists, which keeps the dependency
     * graph pointing one way. The per-surface `@assets` partial stays as the
     * fallback for apps that do not add the directive.
     */
    protected function registerAssets(): void
    {
        app(AssetManager::class)->register([
            Js::make('sortable', self::ASSETS_PATH.'/wire-sortable.js')
                ->navigateTrack()
                ->navigateOnce(),
        ], 'wire-sortable');
    }

    /**
     * Serve the package's pre-bundled drag controller (SortableJS included)
     * directly so consumers get row and column reordering without running npm,
     * a build step, `vendor:publish`, or a runtime CDN request. Mirrors the
     * wire-core and wire-table delivery.
     */
    protected function registerAssetRoutes(): void
    {
        Route::get('/wire-sortable/assets/{asset}.js', function (string $asset): BinaryFileResponse {
            // The package ships a single bundle, `dist/wire-sortable.js`, so the
            // route segment is the package suffix (`…/assets/sortable.js`)
            // rather than a per-bundle name as in wire-core / wire-table.
            $file = self::ASSETS_PATH.'/wire-'.basename($asset).'.js';

            abort_unless(is_file($file), 404);

            return response()
                ->file($file, ['Content-Type' => 'application/javascript; charset=utf-8'])
                ->setPublic()
                ->setMaxAge(31536000);
        })
            ->where('asset', '[A-Za-z0-9_-]+')
            ->name('wire-sortable.asset');
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
