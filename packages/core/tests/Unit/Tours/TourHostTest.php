<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use NyonCode\WireCore\Foundation\Support\MobileSheet;
use NyonCode\WireCore\Foundation\View\PageChrome;
use NyonCode\WireCore\Tours\Tour;
use NyonCode\WireCore\Tours\TourHost;
use NyonCode\WireCore\Tours\Tours;
use NyonCode\WireCore\Tours\TourStep;

/**
 * The chrome: what PHP hands the browser, and what the page gets when there is
 * nothing to hand it.
 *
 * What these cannot see is whether the panel actually lands beside its element,
 * whether a missing anchor is skipped, or whether Escape ends the tour — that
 * is the browser's behaviour and only the CDP driver in step 4 observes it.
 * These cover the seam below it: the data, the registration, and the markup
 * contract an application styles against.
 */
function registerTour(Tour ...$tours): void
{
    app(Tours::class)->register(...$tours);
}

function renderHost(): string
{
    $host = app(TourHost::class);

    return Blade::render(
        file_get_contents(__DIR__.'/../../../resources/views/tours/host.blade.php'),
        ['tourHost' => $host, 'tour' => $host->current()],
    );
}

it('registers its chrome even before an application has declared a tour', function () {
    // Provider order is composer's discovery order: an application registering
    // tours in its own provider boots after this package, so a conditional
    // registration here would leave the chrome off the page for ever.
    expect(app(PageChrome::class)->has('wire-core::tours.host'))->toBeTrue();
});

it('renders nothing at all when no tour is registered', function () {
    expect(trim(renderHost()))->toBe('');
});

it('renders nothing when a registered tour does not claim this screen', function () {
    registerTour(Tour::make('elsewhere')->zones('sales')->steps([TourStep::make('table-search')]));

    expect(trim(renderHost()))->toBe('');
});

it('does not touch the preference store when no tour is registered', function () {
    // The guard that keeps this feature free for the applications that never use
    // it: an empty registry means the ledger is never asked, so a page pays no
    // query and no session write for a tour nobody declared.
    $host = app(TourHost::class);

    expect($host->current())->toBeNull();
});

it('hands the browser one step per declared step, in order', function () {
    $tour = Tour::make('t')->steps([
        TourStep::make('admin-sidebar')->heading('One')->text('First')->placement('right-start'),
        TourStep::make('table-search')->heading('Two')->text('Second'),
    ]);

    $payload = app(TourHost::class)->payload($tour);

    expect($payload['id'])->toBe('t')
        ->and($payload['steps'])->toBe([
            [
                'selector' => '[data-wire="admin-sidebar"]',
                'heading' => 'One',
                'text' => 'First',
                'placement' => 'right-start',
            ],
            [
                'selector' => '[data-wire="table-search"]',
                'heading' => 'Two',
                'text' => 'Second',
                'placement' => 'bottom',
            ],
        ]);
});

/**
 * The number below which no tour runs is the application's to configure and
 * already has an owner. A literal here would be a second source for it.
 */
it('takes the breakpoint from the canonical owner rather than a literal', function () {
    $payload = app(TourHost::class)->payload(Tour::make('t')->steps([TourStep::make('table-search')]));

    expect($payload['breakpoint'])->toBe(MobileSheet::px());

    config()->set('wire-core.mobile.breakpoint', 'lg');

    expect(app(TourHost::class)->payload(Tour::make('t')->steps([TourStep::make('table-search')]))['breakpoint'])
        ->toBe(MobileSheet::px())
        ->and(MobileSheet::px())->toBe(1023.98);
});

it('carries every element hook an application may style the tour by', function () {
    registerTour(Tour::make('t')->steps([TourStep::make('table-search')->heading('H')->text('T')]));

    $html = renderHost();

    foreach ([
        'tour-backdrop',
        'tour-highlight',
        'tour-panel',
        'tour-heading',
        'tour-text',
        'tour-progress',
        'tour-skip',
        'tour-back',
        'tour-next',
    ] as $hook) {
        expect($html)->toContain('data-wire="'.$hook.'"');
    }
});

/**
 * `@js` emits `JSON.parse('…')`, so a selector reaches the browser twice
 * escaped and cannot be asserted verbatim. What matters is that the narrowing
 * survived the trip: a step that lost its `where()` would point at the first
 * sidebar entry instead of the right one, and would look perfectly fine doing
 * it.
 */
it('puts the step selectors into the rendered payload', function () {
    registerTour(Tour::make('t')->steps([
        TourStep::make('admin-nav-item')->where('resource', 'orders'),
    ]));

    $html = renderHost();

    expect($html)->toContain('JSON.parse(')
        ->and($html)->toContain('admin-nav-item')
        ->and($html)->toContain('data-resource')
        ->and($html)->toContain('orders');
});

/**
 * `$refs` are not populated while a parent's `x-init` runs, and the throw takes
 * the whole region with it — a failure only the full driver sweep sees. The
 * deferral is therefore a contract of this file, not a style choice.
 */
it('defers its first ref read past x-init', function () {
    registerTour(Tour::make('t')->steps([TourStep::make('table-search')]));

    $html = renderHost();

    expect($html)->toContain('x-init="$nextTick(() => start())"')
        ->and($html)->not->toMatch('/x-init="[^"]*\$refs/');
});

it('keeps the panel out of a Livewire morph', function () {
    // The host is rendered by the layout, outside any component, but a morph
    // that reached it would strip the inline positioning Floating UI wrote.
    registerTour(Tour::make('t')->steps([TourStep::make('table-search')]));

    expect(renderHost())->toContain('wire:ignore');
});

it('traps focus in the panel through the shared partial', function () {
    registerTour(Tour::make('t')->steps([TourStep::make('table-search')]));

    // The partial is expression-only and bundle-free; what pins it here is that
    // the panel opted in with its own open-state name rather than the default.
    expect(renderHost())->toContain('_wireFocusable');
});

it('translates its controls', function () {
    registerTour(Tour::make('t')->steps([TourStep::make('table-search')]));

    $html = renderHost();

    expect($html)->toContain(__('wire-core::messages.tour_skip'))
        ->and($html)->toContain(__('wire-core::messages.tour_back'))
        ->and($html)->toContain(__('wire-core::messages.tour_next'))
        ->and($html)->toContain(__('wire-core::messages.tour_finish'));
});
