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
    File::deleteDirectory(base_path('stubs/wire-core'));
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
    // `stubs/wire-core/`, because that is where `vendor:publish` puts them.
    // This wrote to `stubs/` and passed, which is why nobody noticed the
    // generator was reading a path the publish never wrote to.
    File::ensureDirectoryExists(base_path('stubs/wire-core'));
    File::put(base_path('stubs/wire-core/dashboard.stub'), "<?php\n\nnamespace {{ namespace }};\n\nclass {{ class }} {}\n");

    $this->artisan('make:wire-dashboard', ['name' => 'Custom'])->assertSuccessful();

    expect(File::get(app_path('Dashboards/CustomDashboard.php')))
        ->toContain('class CustomDashboard {}')
        ->not->toContain('extends Dashboard');
});

// ─── The page half ───────────────────────────────────────────────────────────

/*
 * A dashboard declaring no pages is routed nowhere: it appears in the menu as an
 * entry with no link and answers 404 at every address, which is what a generated
 * one used to be. The page that mounts it is wire-panels' class, so wire-panels
 * generates it — this package's half is naming it and asking, by command name.
 */

it('declares the page that makes it reachable', function () {
    $this->artisan('make:wire-dashboard', ['name' => 'Sales'])->assertSuccessful();

    $contents = File::get(app_path('Dashboards/SalesDashboard.php'));

    expect($contents)->toContain('implements ProvidesNavigation, ProvidesPages')
        ->toContain('public static function pages(): array')
        ->toContain("return ['index' => ShowSales::class];")
        // The import, so the reference resolves wherever the app's root
        // namespace is.
        ->toContain('use App\Livewire\Dashboards\ShowSales;');
});

it('names the page after the dashboard, suffix or not', function () {
    $this->artisan('make:wire-dashboard', ['name' => 'SalesDashboard'])->assertSuccessful();

    expect(File::get(app_path('Dashboards/SalesDashboard.php')))
        ->toContain('ShowSales::class');
});

it('says what to install when nothing can generate the page', function () {
    // wire-panels is not installed in this package's own test application, so
    // this is the path an application without it takes: the dashboard is still
    // written, and `pages()` with it — nothing reads that until something
    // routes, so it is inert now and correct the moment the package arrives.
    $this->artisan('make:wire-dashboard', ['name' => 'Sales'])
        ->expectsOutputToContain('nyoncode/wire-panels')
        ->assertSuccessful();

    expect(File::exists(app_path('Dashboards/SalesDashboard.php')))->toBeTrue();
});

it('writes the dashboard alone when asked to', function () {
    $this->artisan('make:wire-dashboard', ['name' => 'Sales', '--no-page' => true])
        ->doesntExpectOutputToContain('nyoncode/wire-panels')
        ->assertSuccessful();

    // The declaration still names its page: what `--no-page` skips is generating
    // one, not pretending the dashboard needs none.
    expect(File::get(app_path('Dashboards/SalesDashboard.php')))->toContain('ShowSales::class');
});
