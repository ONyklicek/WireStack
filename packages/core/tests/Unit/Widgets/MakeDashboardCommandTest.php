<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/*
 * The dashboard generator.
 *
 * A dashboard is application code — it counts the application's own rows — so
 * what the package owns is the shape, and the shape is a stub. These pin the
 * three things that make it usable: the class lands where the config example
 * says it does, the suffix is not something the developer has to remember, and
 * a published stub wins over the package's.
 */

afterEach(function () {
    File::deleteDirectory(app_path('Dashboards'));
    File::delete(base_path('stubs/dashboard.stub'));
});

it('generates a dashboard class in app/Dashboards', function () {
    $this->artisan('make:wire-dashboard', ['name' => 'Sales'])->assertSuccessful();

    $path = app_path('Dashboards/SalesDashboard.php');

    expect(File::exists($path))->toBeTrue();

    $contents = File::get($path);

    expect($contents)->toContain('class SalesDashboard extends Dashboard')
        ->toContain('namespace App\Dashboards;')
        ->toContain('public function widgets(): array')
        ->toContain('NavigationItem::make()');
});

it('adds the suffix so the derived key is the one the docs describe', function () {
    // Dashboard::key() strips a trailing "Dashboard"; a class generated without
    // it would key itself after a name that reads like a page.
    $this->artisan('make:wire-dashboard', ['name' => 'SalesDashboard'])->assertSuccessful();

    expect(File::exists(app_path('Dashboards/SalesDashboard.php')))->toBeTrue()
        ->and(File::exists(app_path('Dashboards/SalesDashboardDashboard.php')))->toBeFalse();
});

it('prefers a published stub over the package one', function () {
    // The whole point of shipping the template as a publishable stub: an
    // application changes what the generator produces without forking anything.
    File::ensureDirectoryExists(base_path('stubs'));
    File::put(base_path('stubs/dashboard.stub'), "<?php\n\nnamespace {{ namespace }};\n\nclass {{ class }} {}\n");

    $this->artisan('make:wire-dashboard', ['name' => 'Custom'])->assertSuccessful();

    expect(File::get(app_path('Dashboards/CustomDashboard.php')))
        ->toContain('class CustomDashboard {}')
        ->not->toContain('extends Dashboard');
});
