<?php

declare(strict_types=1);

namespace Workbench\App\Modules;

use NyonCode\WireCore\Core\Modules\DomainModule;
use NyonCode\WireCore\Core\Plugin\Contracts\HasDependencies;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroup;
use Workbench\App\Dashboards\OverviewDashboard;
use Workbench\App\Resources\DocumentResource;
use Workbench\App\Resources\TaskResource;

/**
 * The second module, and the one that makes the axis mean something.
 *
 * It brings two resources and a dashboard, and it **depends on billing** — its
 * overview counts invoices, so installing operations without billing would give
 * a dashboard that cannot answer its own question. `dependencies()` is the
 * plugin system's, unchanged: `PluginManager::register()` refuses a module whose
 * dependency is not registered yet, which is the ordering guarantee V2.6 wanted
 * and did not have to build.
 */
final class OperationsModule extends DomainModule implements HasDependencies
{
    public function getId(): string
    {
        return 'operations';
    }

    public function dependencies(): array
    {
        return ['billing'];
    }

    public function resources(): array
    {
        return [TaskResource::class, DocumentResource::class];
    }

    public function dashboards(): array
    {
        return [OverviewDashboard::class];
    }

    public function navigation(): ?NavigationGroup
    {
        return NavigationGroup::make('operations')
            ->label('Operations')
            ->icon('outline:wrench-screwdriver')
            ->sort(10);
    }
}
