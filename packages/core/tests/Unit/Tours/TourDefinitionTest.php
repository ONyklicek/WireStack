<?php

declare(strict_types=1);

use NyonCode\WireCore\Exceptions\TourDefinitionException;
use NyonCode\WireCore\Foundation\Contracts\WireException;
use NyonCode\WireCore\Tours\Tour;
use NyonCode\WireCore\Tours\TourStep;

/**
 * A tour's definition: what it points at, who it is for, and where it runs.
 *
 * The theme running through these is that every mistake here has a *silent*
 * runtime. A step whose element is missing from the page is skipped on purpose
 * — that is what lets a tour survive an application hiding a control — so a
 * typo in an anchor name looks exactly like a screen that legitimately does not
 * have that element, and would shorten the tour without saying so. The checks
 * are therefore at definition time, where the two can still be told apart.
 */
it('refuses an anchor that is not an element-hook name', function (string $anchor) {
    expect(fn () => TourStep::make($anchor))
        ->toThrow(TourDefinitionException::class);
})->with([
    'a CSS class' => '.table-search',
    'a selector' => '[data-wire="table-search"]',
    'screaming case' => 'Table-Search',
    'snake case' => 'table_search',
    'a double hyphen' => 'table--search',
    'a trailing hyphen' => 'table-search-',
    'a leading digit' => '1table',
    'empty' => '',
]);

it('accepts the kebab-case names the framework actually ships', function (string $anchor) {
    expect(TourStep::make($anchor)->getAnchor())->toBe($anchor);
})->with([
    'table-search',
    'admin-nav-item',
    'admin-sidebar',
    'two-factor',
    'table-column-toggle',
]);

it('builds a selector from the hook attribute rather than a literal', function () {
    expect(TourStep::make('table-search')->getSelector())
        ->toBe('[data-wire="table-search"]');
});

it('narrows to one element among many carrying the same hook', function () {
    $selector = TourStep::make('admin-nav-item')
        ->where('resource', 'orders')
        ->getSelector();

    expect($selector)->toBe('[data-wire="admin-nav-item"][data-resource="orders"]');
});

it('keeps narrowing attributes in the order they were declared', function () {
    $selector = TourStep::make('admin-nav-item')
        ->where('resource', 'orders')
        ->where('zone', 'sales')
        ->getSelector();

    expect($selector)->toBe('[data-wire="admin-nav-item"][data-resource="orders"][data-zone="sales"]');
});

it('refuses a narrowing attribute that is not usable in a selector', function () {
    expect(fn () => TourStep::make('admin-nav-item')->where('data resource', 'orders'))
        ->toThrow(TourDefinitionException::class);
});

/**
 * A narrowing value is application data — a resource key, a record id — so it
 * can carry a quote. Unescaped, it would not merely fail to match: it would end
 * the attribute early and leave the remainder as selector syntax.
 */
it('escapes a quote in a narrowing value instead of ending the attribute', function () {
    $selector = TourStep::make('admin-nav-item')
        ->where('resource', 'ord"er')
        ->getSelector();

    expect($selector)->toBe('[data-wire="admin-nav-item"][data-resource="ord\"er"]');
});

it('escapes a backslash in a narrowing value', function () {
    expect(TourStep::make('admin-nav-item')->where('resource', 'a\\b')->getSelector())
        ->toBe('[data-wire="admin-nav-item"][data-resource="a\\\\b"]');
});

it('carries the step content it was given', function () {
    $step = TourStep::make('table-search')
        ->heading('Search')
        ->text('Type to filter the rows.')
        ->placement('bottom-start');

    expect($step->getHeading())->toBe('Search')
        ->and($step->getText())->toBe('Type to filter the rows.')
        ->and($step->getPlacement())->toBe('bottom-start');
});

it('defaults a step to bottom placement and no content', function () {
    $step = TourStep::make('table-search');

    expect($step->getPlacement())->toBe('bottom')
        ->and($step->getHeading())->toBeNull()
        ->and($step->getText())->toBeNull();
});

it('lets step content be cleared again', function () {
    $step = TourStep::make('table-search')->heading('Search')->text('…')->heading(null)->text(null);

    expect($step->getHeading())->toBeNull()
        ->and($step->getText())->toBeNull();
});

it('refuses a tour with no id, since that is what an acknowledgement is stored against', function (string $id) {
    expect(fn () => Tour::make($id))->toThrow(TourDefinitionException::class);
})->with([
    'empty' => '',
    'whitespace' => '   ',
]);

it('defaults a tour to version 1 and sort 0', function () {
    $tour = Tour::make('getting-started');

    expect($tour->getId())->toBe('getting-started')
        ->and($tour->getVersion())->toBe(Tour::DEFAULT_VERSION)
        ->and($tour->getSort())->toBe(0)
        ->and($tour->getSteps())->toBe([]);
});

it('carries the version, sort and steps it was given', function () {
    $step = TourStep::make('table-search');

    $tour = Tour::make('getting-started')->since('2.2')->sort(-10)->steps([$step]);

    expect($tour->getVersion())->toBe('2.2')
        ->and($tour->getSort())->toBe(-10)
        ->and($tour->getSteps())->toBe([$step]);
});

it('reindexes steps so a filtered list is still a list', function () {
    $steps = [TourStep::make('a-one'), TourStep::make('a-two'), TourStep::make('a-three')];

    $tour = Tour::make('t')->steps(array_filter($steps, fn ($_, int $i): bool => $i !== 1, ARRAY_FILTER_USE_BOTH));

    expect(array_keys($tour->getSteps()))->toBe([0, 1]);
});

it('refuses to register a tour with nothing to show', function () {
    expect(fn () => Tour::make('empty')->assertUsable())
        ->toThrow(TourDefinitionException::class);
});

/**
 * A tour is assembled a method at a time, so it is legitimately stepless while
 * being built. The check belongs at registration, which is the first moment the
 * definition is finished.
 */
it('does not judge a tour that is still being built', function () {
    $tour = Tour::make('getting-started');

    expect($tour->getSteps())->toBe([]);

    $tour->steps([TourStep::make('table-search')])->assertUsable();

    expect($tour->getSteps())->toHaveCount(1);
});

/**
 * Asserted by hand rather than with `toThrow()`: Pest resolves a class-string
 * argument with `class_exists()`, which is false for an interface, and silently
 * falls back to matching the string against the exception *message*. The test
 * then passes or fails for a reason that has nothing to do with the hierarchy.
 */
it('is catchable as a wire failure alongside the rest of the stack', function () {
    try {
        Tour::make('');
    } catch (Throwable $e) {
        expect($e)->toBeInstanceOf(WireException::class)
            ->and($e)->toBeInstanceOf(InvalidArgumentException::class);

        return;
    }

    $this->fail('An empty tour id should not be accepted.');
});
