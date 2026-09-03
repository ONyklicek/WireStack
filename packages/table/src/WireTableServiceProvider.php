<?php

declare(strict_types=1);

namespace NyonCode\WireTable;

use Livewire\LivewireManager;
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Foundation\Assets\Bundle;
use NyonCode\WireTable\Livewire\TableStateSynthesizer;
use NyonCode\WireTable\Support\RecordAction;

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
                // The manager, not `app(HandleComponents::class)`: Livewire 4 moved
                // the synthesizer registry out of that mechanism into
                // `HandleSynths::registerSynth()`, and `registerPropertySynthesizer()`
                // no longer exists there. `propertySynthesizer()` is the supported
                // seam and forwards to the right owner in both 3.x and 4.x. Resolved
                // from the container rather than through the `Livewire` facade
                // because the facade's `@method` list does not carry it — same
                // pattern as core's CurrentComponentDriver.
                app(LivewireManager::class)->propertySynthesizer(TableStateSynthesizer::class);

                $this->registerRecordActionMacros();
                Bundle::serve('wire-table', self::ASSETS_PATH);
            })
            ->hasConfig()
            ->hasViews()
            ->hasAssets('dist', entries: [
                Bundle::make('wire-table-records.js'),
                Bundle::make('wire-table-selection.js'),
                Bundle::make('wire-table-live.js'),
                // The Excel-style fill handle. It lived in `wire-core-dropdown.js`
                // until ADR 0025 § step 10: every wire-core consumer shipped 9 KB of
                // a gesture only a table can trigger. It ships with the rest rather
                // than on request — ADR 0024 forbids delivering an interaction
                // registrar late, and `x-data="wireFillHandle()"` is in the rendered
                // row region, not behind a click.
                Bundle::make('wire-table-fill.js'),
            ])
            ->hasAssetFallback(Bundle::servedByRoute('wire-table'))
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
