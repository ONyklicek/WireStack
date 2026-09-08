<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/*
 * The widget generator.
 *
 * A widget counts the application's own rows, so what the package owns is the
 * shape — and the shape is two files, because half of it is useless: a Widget
 * subclass names a view, and a class generated without one throws the first time
 * it is put on a dashboard.
 */

afterEach(function () {
    File::deleteDirectory(app_path('Widgets'));
    File::deleteDirectory(resource_path('views/widgets'));
    File::deleteDirectory(base_path('stubs/wire-core'));
    File::delete(base_path('stubs/widget.stub'));
    File::delete(base_path('stubs/widget-view.stub'));
});

it('generates a widget class in app/Widgets', function () {
    $this->artisan('make:wire-widget', ['name' => 'Revenue'])->assertSuccessful();

    $path = app_path('Widgets/RevenueWidget.php');

    expect(File::exists($path))->toBeTrue();

    expect(File::get($path))
        ->toContain('class RevenueWidget extends Widget')
        ->toContain('namespace App\Widgets;')
        ->toContain('protected function viewName(): string')
        ->toContain("return 'widgets.revenue';");
});

it('generates the Blade view the class names', function () {
    $this->artisan('make:wire-widget', ['name' => 'Revenue'])->assertSuccessful();

    $path = resource_path('views/widgets/revenue.blade.php');

    expect(File::exists($path))->toBeTrue()
        ->and(File::get($path))->toContain('$widget->getHeading()');
});

it('derives the view name from a multi-word class', function () {
    $this->artisan('make:wire-widget', ['name' => 'MonthlyRevenue'])->assertSuccessful();

    expect(File::get(app_path('Widgets/MonthlyRevenueWidget.php')))
        ->toContain("return 'widgets.monthly-revenue';")
        ->and(File::exists(resource_path('views/widgets/monthly-revenue.blade.php')))->toBeTrue();
});

it('adds the suffix so the class reads as what it is', function () {
    $this->artisan('make:wire-widget', ['name' => 'RevenueWidget'])->assertSuccessful();

    expect(File::exists(app_path('Widgets/RevenueWidget.php')))->toBeTrue()
        ->and(File::exists(app_path('Widgets/RevenueWidgetWidget.php')))->toBeFalse();
});

it('never overwrites a view somebody has edited', function () {
    // Re-running the generator is usually about getting the class back. Silently
    // replacing markup is not a trade a generator gets to make.
    File::ensureDirectoryExists(resource_path('views/widgets'));
    File::put(resource_path('views/widgets/revenue.blade.php'), 'MINE');

    $this->artisan('make:wire-widget', ['name' => 'Revenue', '--force' => true])->assertSuccessful();

    expect(File::get(resource_path('views/widgets/revenue.blade.php')))->toBe('MINE');
});

it('writes nothing at all when the class already exists', function () {
    // The generator refuses first and the view is never reached — a half-written
    // pair is worse than neither, because the class it names is not the one on
    // disk.
    $this->artisan('make:wire-widget', ['name' => 'Revenue'])->assertSuccessful();

    File::delete(resource_path('views/widgets/revenue.blade.php'));

    // Laravel reports "already exists" and exits 0, so the outcome to assert is
    // what is on disk, not the status code.
    $this->artisan('make:wire-widget', ['name' => 'Revenue'])
        ->expectsOutputToContain('already exists');

    expect(File::exists(resource_path('views/widgets/revenue.blade.php')))->toBeFalse();
});

it('prefers a published stub over the package one', function () {
    // `stubs/wire-core/`, because that is where `vendor:publish` puts them — the
    // toolkit namespaces published stubs by package. This test used to write to
    // `stubs/` and pass, which is exactly why nobody noticed the generator was
    // reading a path the publish never wrote to.
    File::ensureDirectoryExists(base_path('stubs/wire-core'));
    File::put(base_path('stubs/wire-core/widget.stub'), "<?php\n\nnamespace {{ namespace }};\n\nclass {{ class }} {} // {{ view }}\n");
    File::put(base_path('stubs/wire-core/widget-view.stub'), 'PUBLISHED VIEW');

    $this->artisan('make:wire-widget', ['name' => 'Custom'])->assertSuccessful();

    expect(File::get(app_path('Widgets/CustomWidget.php')))
        ->toContain('class CustomWidget {} // widgets.custom')
        ->not->toContain('extends Widget')
        ->and(File::get(resource_path('views/widgets/custom.blade.php')))->toBe('PUBLISHED VIEW');
});

it('still reads a stub put in stubs/ by hand', function () {
    // Laravel's own `stub:publish` convention, and where somebody who dropped
    // the file there would expect it to be read from.
    File::ensureDirectoryExists(base_path('stubs'));
    File::put(base_path('stubs/widget.stub'), "<?php\n\nclass {{ class }} {} // by hand\n");

    $this->artisan('make:wire-widget', ['name' => 'Custom'])->assertSuccessful();

    expect(File::get(app_path('Widgets/CustomWidget.php')))->toContain('// by hand');
});
