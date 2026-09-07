<?php

declare(strict_types=1);

namespace NyonCode\WireModuleNotifications;

use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\WireCore\Core\Plugin\PluginManager;

/** A module that arrives as a package; see wire-module-users for the shape. */
class WireModuleNotificationsServiceProvider extends PackageServiceProvider
{
    /**
     * @throws \Exception
     */
    public function configure(Packager $packager): void
    {
        $packager
            ->name('WireModuleNotifications')
            ->hasShortName('wire-module-notifications')
            ->registeredPackage(function (): void {
                $this->app->resolving(PluginManager::class, function (PluginManager $manager): void {
                    if (! $manager->has('notifications')) {
                        $manager->register(new NotificationsModule);
                    }
                });
            })
            ->hasConfig()
            ->hasViews()
            ->hasTranslations()
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->publishConfig()
                    ->afterInstallation(function (InstallCommand $installer): void {
                        $installer->comment('  ✅ Notifications are registered as the `notifications` module');

                        // Without the database driver there is nothing stored to
                        // list, and a screen that is always empty reads as broken.
                        if (! in_array('database', (array) config('wire-core.notifications.default', []), true)) {
                            $installer->comment('  ⚠️  Add `database` to wire-core.notifications.default, or nothing is stored to show');
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
            'Stored notifications' => in_array('database', (array) config('wire-core.notifications.default', []), true) ? 'on' : 'off',
        ];
    }
}
