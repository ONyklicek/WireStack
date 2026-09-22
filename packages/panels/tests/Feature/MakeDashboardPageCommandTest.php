<?php

declare(strict_types=1);

use App\Dashboards\ReportsDashboard;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use NyonCode\WireCore\Widgets\DashboardRegistry;

/*
 * The page half of a dashboard, and the proof that the pair is reachable.
 *
 * `make:wire-dashboard` used to write a declaration and nothing else. A
 * dashboard that declares no pages is routed nowhere — it appears in the menu as
 * an entry with no link and answers 404 at every address — so the generator
 * produced something that looked finished and could not be opened.
 *
 * The page extends `DashboardPage`, which is this package's class, so this
 * package generates it; core calls the command by name. What is asserted below
 * is the whole of that contract: the file, the dashboard it names, and — the
 * only assertion that would have failed before — that the two together give the
 * router something to register.
 */

afterEach(function () {
    File::deleteDirectory(app_path('Livewire'));
    File::deleteDirectory(app_path('Dashboards'));
    File::deleteDirectory(base_path('stubs/wire-panels'));
});

it('generates the page that mounts a dashboard', function () {
    $this->artisan('make:wire-dashboard-page', ['name' => 'Sales'])->assertSuccessful();

    $path = app_path('Livewire/Dashboards/ShowSales.php');

    expect(File::exists($path))->toBeTrue();

    expect(File::get($path))->toContain('class ShowSales extends DashboardPage')
        ->toContain('namespace App\Livewire\Dashboards;')
        ->toContain('protected static ?string $dashboard = SalesDashboard::class;')
        ->toContain('use App\Dashboards\SalesDashboard;');
});

it('takes the dashboard s name however it is spelled', function () {
    // The caller names the thing they are building a page *for*; making them
    // spell the page's own name would be asking them to repeat a convention the
    // command already knows.
    foreach (['Sales', 'SalesDashboard', 'ShowSales'] as $spelling) {
        $this->artisan('make:wire-dashboard-page', ['name' => $spelling, '--force' => true])->assertSuccessful();

        expect(File::get(app_path('Livewire/Dashboards/ShowSales.php')))
            ->toContain('SalesDashboard::class');
    }
});

it('prefers a published stub over the package one', function () {
    File::ensureDirectoryExists(base_path('stubs/wire-panels'));
    File::put(base_path('stubs/wire-panels/dashboard-page.stub'), "<?php\n\nnamespace {{ namespace }};\n\nclass {{ class }} {}\n");

    $this->artisan('make:wire-dashboard-page', ['name' => 'Custom'])->assertSuccessful();

    expect(File::get(app_path('Livewire/Dashboards/ShowCustom.php')))
        ->toContain('class ShowCustom {}')
        ->not->toContain('DashboardPage');
});

it('generates a pair the router can actually register', function () {
    // The assertion the gap was: generate both halves, load them, register the
    // dashboard and ask the router. Before, `pages()` did not exist and
    // `wireResources()` had nothing to route — the menu entry stayed unlinked
    // and the address 404ed, with no error anywhere to say why.
    $this->artisan('make:wire-dashboard', ['name' => 'Reports'])->assertSuccessful();

    require_once app_path('Dashboards/ReportsDashboard.php');
    require_once app_path('Livewire/Dashboards/ShowReports.php');

    app(DashboardRegistry::class)->register(ReportsDashboard::class);

    Route::wireResources();
    Route::getRoutes()->refreshNameLookups();

    $route = Route::getRoutes()->getByName('wire.reports.index');

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('reports')
        ->and($route->getActionName())->toContain('ShowReports');
});
