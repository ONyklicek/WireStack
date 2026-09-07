<?php

declare(strict_types=1);

namespace NyonCode\WireModuleSettings;

use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireModuleSettings\Resources\SettingsResource;
use NyonCode\WireModuleSettings\Support\SettingsGroups;
use NyonCode\WireModuleSettings\Support\SettingsRegistry;

/** A module that arrives as a package; see wire-module-users for the shape. */
class WireModuleSettingsServiceProvider extends PackageServiceProvider
{
    /**
     * @throws \Exception
     */
    public function configure(Packager $packager): void
    {
        $packager
            ->name('WireModuleSettings')
            ->hasShortName('wire-module-settings')
            ->registeredPackage(function (): void {
                // `singletonIf`, because a package contributing a settings tab
                // may well have booted first — provider order is composer's
                // discovery order, not a contract — and `SettingsRegistry::
                // instance()` will have bound it already. Replacing that
                // instance here would drop the tab it was registered into.
                $this->app->singletonIf(SettingsRegistry::class);

                $this->app->resolving(PluginManager::class, function (PluginManager $manager): void {
                    if (! $manager->has('settings')) {
                        $manager->register(new SettingsModule);
                    }
                });
            })
            ->hasConfig()
            ->hasViews()
            ->hasMigrations()
            ->hasTranslations()
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->publishConfig()
                    ->publishMigrations()
                    ->afterInstallation(function (InstallCommand $installer): void {
                        $installer->comment('  ✅ Settings are registered as the `settings` module');
                        $installer->comment('  • Run: php artisan migrate');

                        if (SettingsGroups::all() === []) {
                            $installer->comment('  ↩︎  Declare a SettingsGroup and list it in wire-module-settings.groups');
                        }

                        // Named here because the screen is where an application's
                        // behaviour is changed without a deploy, and an unguarded
                        // route to it looks exactly like a guarded one until
                        // somebody who should not have it finds the URL.
                        if (SettingsResource::permission() === null) {
                            $installer->comment('  ↩︎  Nothing guards the screen — name an ability in wire-module-settings.permission');
                        }

                        if (config('wire-module-settings.cache.store') === null && config('cache.default') === 'database') {
                            $installer->comment('  ↩︎  Settings cache in the `database` store — name a memory store in wire-module-settings.cache.store');
                        }
                    });
            })
            ->hasAbout();
    }

    /**
     * @return array<string, string>
     */
    public function aboutData(): array
    {
        return [
            'Settings groups' => (string) count(SettingsGroups::all()),
            'From packages' => (string) count(SettingsRegistry::instance()->all()),
            'Permission' => SettingsResource::permission() ?? 'none',
            'Cache store' => (string) (config('wire-module-settings.cache.store') ?? config('cache.default')),
        ];
    }
}
