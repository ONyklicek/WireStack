<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Dashboards;

use NyonCode\WirePanels\Resources\Pages\DashboardPage;
use Workbench\App\Dashboards\OverviewDashboard;

class ShowOverview extends DashboardPage
{
    protected static ?string $dashboard = OverviewDashboard::class;
}
