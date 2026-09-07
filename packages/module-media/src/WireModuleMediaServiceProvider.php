<?php

declare(strict_types=1);

namespace NyonCode\WireModuleMedia;

use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Foundation\Assets\Bundle;
use NyonCode\WireCore\Foundation\View\PageChrome;
use NyonCode\WireModuleMedia\Console\MakeThumbnailsCommand;
use NyonCode\WireModuleMedia\Console\SyncUsageCommand;
use NyonCode\WireModuleMedia\Contracts\MakesThumbnails;
use NyonCode\WireModuleMedia\Http\Controllers\MediaController;
use NyonCode\WireModuleMedia\Livewire\MediaPicker;
use NyonCode\WireModuleMedia\Support\GdThumbnailer;

/** A module that arrives as a package; see wire-module-users for the shape. */
class WireModuleMediaServiceProvider extends PackageServiceProvider
{
    /** The editor's controller. This package's first and only JS (ADR 0024). */
    public const ASSETS_PATH = __DIR__.'/../dist';

    /**
     * @throws \Exception
     */
    public function configure(Packager $packager): void
    {
        $packager
            ->name('WireModuleMedia')
            ->hasShortName('wire-module-media')
            ->registeredPackage(function (): void {
                // GD by default, because most PHP builds have it and a fresh
                // installation should get thumbnails without installing
                // anything. `bind`, not `singleton`: an application that would
                // rather use Imagick, Intervention or a resizing CDN replaces
                // this in one line and nothing else here changes.
                $this->app->bind(MakesThumbnails::class, GdThumbnailer::class);

                $this->app->resolving(PluginManager::class, function (PluginManager $manager): void {
                    if (! $manager->has('media')) {
                        $manager->register(new MediaModule);
                    }
                });
            })
            ->hasViews()
            ->hasCommands([MakeThumbnailsCommand::class, SyncUsageCommand::class])
            ->bootedPackage(function (): void {
                $this->registerRoutes();

                // The picker, addressable by name so a Blade file can mount it
                // without importing a class from a package it does not require.
                Livewire::component('wire-media-picker', MediaPicker::class);

                // And rendered once per page by whatever draws the chrome. This
                // is the whole reason PageChrome exists: the shell sits above
                // this package and cannot name its views, and this package
                // cannot reach into the shell's layout.
                $this->app->make(PageChrome::class)->add('wire-module-media::picker-modal');

                Bundle::serve('wire-module-media', self::ASSETS_PATH);
            })
            ->hasAssets('dist', entries: [
                Bundle::make('wire-media-editor.js'),
            ])
            ->hasAssetFallback(Bundle::servedByRoute('wire-module-media'))
            ->hasConfig()
            ->hasMigrations()
            ->hasTranslations()
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->publishConfig()
                    ->publishMigrations()
                    ->afterInstallation(function (InstallCommand $installer): void {
                        $installer->comment('  ✅ The media library is registered as the `media` module');
                        $installer->comment('  • Run: php artisan migrate');

                        // The default disk is `public`, which is a symlink an
                        // application makes once and forgets — and until it does,
                        // every preview is a broken image.
                        if ((string) config('wire-module-media.disk', 'public') === 'public') {
                            $installer->comment('  • Run: php artisan storage:link (the public disk needs it)');
                        }
                    });
            })
            ->hasAbout();
    }

    /**
     * The route that streams a file the disk will not hand out itself.
     *
     * Registered here rather than in a routes file, because whether it exists at
     * all and what middleware it sits behind are the application's configuration
     * — and a routes file cannot be conditional on that without reading config
     * at a point where it may not be loaded yet.
     *
     * Skipped when the application has cached its routes, which is Laravel's own
     * rule: a cached route file is the whole route table, and adding to it at
     * boot would mean two different tables depending on whether a cache exists.
     */
    protected function registerRoutes(): void
    {
        if (! config('wire-module-media.route.enabled', true) || $this->app->routesAreCached()) {
            return;
        }

        Route::middleware((array) config('wire-module-media.route.middleware', ['web', 'auth']))
            ->prefix((string) config('wire-module-media.route.prefix', 'wire-media'))
            ->group(function (): void {
                Route::get('{media}', [MediaController::class, 'show'])->name('wire-media.show');
                Route::get('{media}/download', [MediaController::class, 'download'])->name('wire-media.download');
            });
    }

    /**
     * @return array<string, string>
     */
    public function aboutData(): array
    {
        return [
            'Media disk' => (string) config('wire-module-media.disk', 'public'),
        ];
    }
}
