<?php

declare(strict_types=1);

namespace NyonCode\WireTable;

use Illuminate\Support\Facades\Route;
use Livewire\Mechanisms\HandleComponents\HandleComponents;
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireTable\Livewire\TableStateSynthesizer;
use NyonCode\WireTable\Support\RecordAction;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WireTableServiceProvider extends PackageServiceProvider
{
    /** Absolute path to the pre-bundled, self-registering table assets. */
    public const ASSETS_PATH = __DIR__.'/../dist';

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

                $this->registerRecordActionMacros();
                $this->registerAssetRoutes();
            })
            ->hasConfig()
            ->hasViews()
            ->hasAssets('dist')
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
     * Register the fluent record-action triggers on `Action` so a row action can
     * be declared as `Action::make('edit')->onDoubleClick()`.
     *
     * The macros only promote the action into a table-owned {@see RecordAction};
     * no trigger state lives on the shared `Action`, and `wire-core` need not know
     * the table exists. Registered here — the package's cross-package extension
     * seam — rather than in the core Action class.
     */
    protected function registerRecordActionMacros(): void
    {
        foreach (['onClick', 'onDoubleClick', 'onContextMenu'] as $trigger) {
            Action::macro($trigger, function () use ($trigger): RecordAction {
                /** @var Action $this */
                return RecordAction::make($this)->{$trigger}();
            });
        }

        Action::macro('onKey', function (string $key): RecordAction {
            /** @var Action $this */
            return RecordAction::make($this)->onKey($key);
        });

        Action::macro('on', function (string $type): RecordAction {
            /** @var Action $this */
            return RecordAction::make($this)->on($type);
        });
    }

    /**
     * Serve the package's pre-bundled record-action JS directly so the table view
     * can inject it via `@assets` without the consumer running npm or publishing
     * assets. Mirrors the wire-forms delivery.
     */
    protected function registerAssetRoutes(): void
    {
        Route::get('/wire-table/assets/{asset}.js', function (string $asset): BinaryFileResponse {
            $file = self::ASSETS_PATH.'/wire-table-'.basename($asset).'.js';

            abort_unless(is_file($file), 404);

            return response()
                ->file($file, ['Content-Type' => 'application/javascript; charset=utf-8'])
                ->setPublic()
                ->setMaxAge(31536000);
        })
            ->where('asset', '[A-Za-z0-9_-]+')
            ->name('wire-table.asset');
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
