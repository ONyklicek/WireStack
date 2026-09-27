<?php

declare(strict_types=1);

namespace NyonCode\WireModuleTenants;

use Livewire\Livewire;
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireModuleTenants\Livewire\RegisterTenant;
use NyonCode\WireModuleTenants\Pages\CompanyProfile;
use NyonCode\WireModuleTenants\Pages\ListMembers;

/**
 * Companies as a ready-made area (ADR 0040 §8): registering one, its profile,
 * and its members.
 *
 * What it assumes of the application is what the tenant zone already needs:
 * a user model implementing `HasTenants` (`InteractsWithTenants` reads this
 * module's `tenant_user` pivot), and a tenant zone routing the module's two
 * resources. It fills the two settings a tenant zone reads when the
 * application has not: the tenant model, and the page for somebody in no
 * company.
 */
class WireModuleTenantsServiceProvider extends PackageServiceProvider
{
    /**
     * @throws \Exception
     */
    public function configure(Packager $packager): void
    {
        $packager
            ->name('WireModuleTenants')
            ->hasShortName('wire-module-tenants')
            ->registeredPackage(function (): void {
                $this->app->resolving(PluginManager::class, function (PluginManager $manager): void {
                    if (! $manager->has('tenants')) {
                        $manager->register(new TenantsModule);
                    }
                });

                $this->app->booted(function (): void {
                    // Defaults, never overrides: an application that named its
                    // own tenant model or its own no-company page keeps it.
                    if (config('wire-core.tenancy.model') === null) {
                        config()->set('wire-core.tenancy.model', config('wire-module-tenants.model'));
                    }

                    if (config('wire-panels.routes.tenant_entry.view') === null) {
                        config()->set('wire-panels.routes.tenant_entry.view', 'wire-module-tenants::no-tenant');
                    }
                });
            })
            ->bootedPackage(function (): void {
                // Outside the App\Livewire namespace, so the update round trip
                // needs the names registered.
                Livewire::component('wire-module-tenants.register-tenant', RegisterTenant::class);
                Livewire::component('wire-module-tenants.company-profile', CompanyProfile::class);
                Livewire::component('wire-module-tenants.list-members', ListMembers::class);
            })
            ->hasConfig()
            ->hasViews()
            ->hasMigrations()
            ->hasTranslations()
            ->hasRoutes()
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->publishConfig()
                    ->publishMigrations()
                    ->afterInstallation(function (InstallCommand $installer): void {
                        $installer->comment('  ✅ Companies are registered as the `tenants` module');
                        $installer->comment('  • Run: php artisan migrate');
                        $installer->comment('  ↩︎  Give the user model HasTenants + InteractsWithTenants (wire-panels)');
                        $installer->comment("  ↩︎  Route a tenant zone — 'tenant' => 'path' on a zone in config/wire-panels.php");
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
            'Tenant model' => (string) config('wire-core.tenancy.model'),
            'Registration' => var_export(config('wire-module-tenants.registration'), true),
            'Isolation' => (string) config('wire-core.tenancy.isolation', 'column'),
        ];
    }
}
