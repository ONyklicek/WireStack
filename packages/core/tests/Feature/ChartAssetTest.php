<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use NyonCode\WireCore\Widgets\ChartWidget;
use NyonCode\WireCore\WireCoreServiceProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/*
 * The chart widget used to emit its controller into @push('scripts'). It was the
 * only @push in the repository and no package layout renders a matching
 * @stack('scripts'), so in a consuming app `wireChart` was never registered at
 * all and every chart widget was dead markup. It now ships as a package bundle
 * delivered through Livewire's @assets directive, like every other controller.
 */

function chartWidget(): ChartWidget
{
    return ChartWidget::make()
        ->heading('Revenue')
        ->type('line')
        ->labels(['Jan', 'Feb'])
        ->datasets([['label' => 'Revenue', 'data' => [1, 2]]]);
}

/** How many times the chart asset partial rendered while $render ran. */
function chartAssetRenders(Closure $render): int
{
    $count = 0;

    View::composer('wire-core::widgets.partials.chart-assets', function () use (&$count): void {
        $count++;
    });

    $render();

    return $count;
}

test('the chart bundle is shipped inside the package', function () {
    $bundle = WireCoreServiceProvider::ASSETS_PATH.'/wire-core-chart.js';

    expect(is_file($bundle))->toBeTrue()
        ->and(file_get_contents($bundle))->toContain('wireChart');
});

test('the package serves the chart bundle without publishing or a build step', function () {
    $response = $this->get('/wire-core/assets/chart.js');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('javascript');
    expect($response->baseResponse)->toBeInstanceOf(BinaryFileResponse::class)
        ->and(file_get_contents($response->baseResponse->getFile()->getPathname()))->toContain('wireChart');
});

test('the chart widget ships its controller through @assets', function () {
    // @assets is hoisted out of the component markup, so the delivery cannot be
    // asserted with assertSee() on the widget — count the partial's renders.
    $renders = chartAssetRenders(fn () => chartWidget()->toHtml());

    expect($renders)->toBe(1);
});

test('the widget markup no longer carries the controller or an alpine:init listener', function () {
    $html = chartWidget()->toHtml();

    // The old inline block registered only from `alpine:init`, which fires once
    // per document: any script arriving after the initial page load — a
    // wire:navigate, a Livewire-loaded modal — registered nothing.
    expect($html)
        ->toContain('x-data="wireChart(')
        ->not->toContain('alpine:init')
        ->not->toContain('Alpine.data(');
});

test('no view in the package depends on a scripts stack that nothing renders', function () {
    $views = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
        dirname(WireCoreServiceProvider::ASSETS_PATH).'/resources/views'
    ));

    $pushing = [];

    foreach ($views as $view) {
        if ($view->isFile() && str_ends_with($view->getFilename(), '.blade.php')
            && preg_match("/@push\(\s*'scripts'/", (string) file_get_contents($view->getPathname()))) {
            $pushing[] = $view->getPathname();
        }
    }

    // No package layout renders @stack('scripts') — the only consumer in the repo
    // is a workbench preview — so a @push there is delivered nowhere.
    expect($pushing)->toBe([]);
});

test('a rendered page carries the chart controller in its document', function () {
    // The acceptance criterion: an ordinary page rendering a chart widget must
    // come back with the controller in it. Livewire injects @assets output into
    // </head> on any 200 text/html response.
    Route::get('/chart-page', fn (): string => Blade::render(
        '<!DOCTYPE html><html><head></head><body>{!! $chart !!}</body></html>',
        ['chart' => chartWidget()->toHtml()],
    ));

    $this->get('/chart-page')
        ->assertOk()
        ->assertSee('x-data="wireChart(', false)
        ->assertSee('/wire-core/assets/chart.js?id=', false);
});

test('the source registers unconditionally so the bundle survives a wire:navigate', function () {
    $source = file_get_contents(
        dirname(WireCoreServiceProvider::ASSETS_PATH).'/resources/js/chart.js'
    );

    // The listener may only be the cold-load fallback for a named, idempotent
    // registrar that also runs straight away when Alpine is already up.
    expect($source)
        ->not->toMatch("/addEventListener\('alpine:init',\s*\(\s*\)\s*=>/")
        ->toMatch("/if \(window\.Alpine\) \{\s*(?:\/\/[^\n]*\n\s*)*registerWireCoreChart\(\)/")
        ->toMatch("/document\.addEventListener\('alpine:init', registerWireCoreChart\)/")
        ->toContain('if (registered || ! window.Alpine) return')
        ->toContain("window.Alpine.data('wireChart', wireChart)");
});

test('the shipped chart bundle carries the SPA-proof registration', function () {
    $bundle = WireCoreServiceProvider::ASSETS_PATH.'/wire-core-chart.js';

    // Minified shape of the idiom above. Fails if the dist drifts from the
    // source because the asset was not recompiled (`npm run build:core-assets`).
    expect(file_get_contents($bundle))
        ->toMatch('/window\.Alpine\?\w+\(\):document\.addEventListener\("alpine:init",\w+\)/')
        ->toMatch('/\w+\|\|!window\.Alpine\|\|\(\w+=!0,/');
});

test('the shipped chart bundle degrades without Chart.js instead of throwing', function () {
    $bundle = WireCoreServiceProvider::ASSETS_PATH.'/wire-core-chart.js';

    // Chart.js is the consuming app's dependency, not this package's. A missing
    // global must leave an empty canvas and one warning — never a ReferenceError,
    // which is why the bundle reads Chart off `window`.
    expect(file_get_contents($bundle))
        ->toContain('window.Chart')
        ->toContain('Chart.js is not loaded');
});
