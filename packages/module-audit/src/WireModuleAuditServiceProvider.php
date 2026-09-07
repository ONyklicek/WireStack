<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAudit;

use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireModuleAudit\Support\AuditLog;

/**
 * A module that arrives as a package — the same shape wire-module-users has.
 *
 * `resolving`, in the register phase: the module has to be in the manager's list
 * before it boots and before the core provider spreads declarations into the
 * registries. Later is refused, because a module that arrives then looks
 * installed and does nothing.
 */
class WireModuleAuditServiceProvider extends PackageServiceProvider
{
    /**
     * @throws \Exception
     */
    public function configure(Packager $packager): void
    {
        $packager
            ->name('WireModuleAudit')
            ->hasShortName('wire-module-audit')
            ->registeredPackage(function (): void {
                $this->app->resolving(PluginManager::class, function (PluginManager $manager): void {
                    if (! $manager->has('audit')) {
                        $manager->register(new AuditModule);
                    }
                });
            })
            ->hasConfig()
            ->hasTranslations()
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->publishConfig()
                    ->afterInstallation(function (InstallCommand $installer): void {
                        $installer->comment('  ✅ The audit log is registered as the `audit` module');

                        // Two things this package does not own, and both of them
                        // decide whether the screen has anything on it. Said at
                        // install time because the alternative is an empty log
                        // that reads as a broken package.

                        // The table is core's migration, and core publishes it on
                        // demand — so an installation can have the screen and no
                        // table at all.
                        if (! AuditLog::available()) {
                            $installer->comment('  ⚠️  No `audit_logs` table — run: php artisan vendor:publish --tag=wire-core::migrations && php artisan migrate');
                        }

                        // And the recording itself is core's switch.
                        if (! config('wire-core.audit.enabled', false)) {
                            $installer->comment('  ⚠️  wire-core.audit.enabled is off — nothing is being recorded yet');
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
            'Recording' => config('wire-core.audit.enabled', false) ? 'on' : 'off',
            'Log table' => AuditLog::available() ? 'ready' : 'missing',
        ];
    }
}
