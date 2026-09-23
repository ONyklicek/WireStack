<?php

declare(strict_types=1);

use Illuminate\Support\ViewErrorBag;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Filters\SelectFilter;

it('can be created', function () {
    expect(SelectFilter::make('status'))->toBeInstanceOf(SelectFilter::class);
});

it('can set options', function () {
    $filter = SelectFilter::make('status')->options([
        'active' => 'Aktivní',
        'inactive' => 'Neaktivní',
    ]);

    expect($filter->getOptions())->toBe([
        'active' => 'Aktivní',
        'inactive' => 'Neaktivní',
    ]);
});

it('resolves a closure passed to options (regression: closure threw a TypeError)', function () {
    $filter = SelectFilter::make('status')->options(fn () => [
        'active' => 'Aktivní',
        'inactive' => 'Neaktivní',
    ]);

    expect($filter->getOptions())->toBe([
        'active' => 'Aktivní',
        'inactive' => 'Neaktivní',
    ]);
});

it('renders without error when options are provided as a closure', function () {
    $filter = SelectFilter::make('status')->options(fn () => ['paid' => 'Paid']);

    expect($filter->render('paid'))->toBeString();
});

it('is not native by default so it matches the forms Select surface', function () {
    expect(SelectFilter::make('status')->isNative())->toBeFalse();
});

it('can be set to native', function () {
    expect(SelectFilter::make('status')->native()->isNative())->toBeTrue();
});

it('is not searchable by default', function () {
    expect(SelectFilter::make('status')->isSearchable())->toBeFalse();
});

it('can be set to searchable', function () {
    expect(SelectFilter::make('status')->searchable()->isSearchable())->toBeTrue();
});

it('searchable() opts out of native rendering so it works on its own', function () {
    expect(SelectFilter::make('status')->searchable()->isNative())->toBeFalse();
});

it('an explicit native() after searchable() forces the native element', function () {
    $filter = SelectFilter::make('status')->searchable()->native();

    expect($filter->isSearchable())->toBeTrue()
        ->and($filter->isNative())->toBeTrue();
});

it('renders the combobox by default so it matches the forms Select', function () {
    $html = SelectFilter::make('status')
        ->options(['paid' => 'Paid', 'due' => 'Due'])
        ->render();

    expect($html)
        ->toContain('x-teleport')
        ->toContain("\$wire.entangle('tableState.filters.status.value').live")
        ->not->toContain('<select')
        ->not->toContain('x-ref="searchInput"');
});

it('renders a native select when native() is opted into', function () {
    $html = SelectFilter::make('status')
        ->options(['paid' => 'Paid', 'due' => 'Due'])
        ->native()
        ->render();

    expect($html)
        ->toContain('<select')
        ->toContain('wire:model.live="tableState.filters.status.value"')
        ->not->toContain('x-teleport');
});

it('renders the searchable combobox when searchable (regression: searchable() was a no-op)', function () {
    $html = SelectFilter::make('status')
        ->options(['paid' => 'Paid', 'due' => 'Due'])
        ->searchable()
        ->render();

    expect($html)
        ->toContain('x-teleport')
        ->toContain("\$wire.entangle('tableState.filters.status.value').live")
        ->toContain('id="filter-status"')
        ->toContain('x-ref="searchInput"');
});

it('renders the combobox without a search input when non-native but not searchable', function () {
    // Unified design: a non-native filter uses the same combobox as the searchable
    // one, just without the in-panel search input.
    $html = SelectFilter::make('status')
        ->options(['paid' => 'Paid', 'due' => 'Due'])
        ->native(false)
        ->render();

    expect($html)
        ->toContain('x-teleport')
        ->toContain("\$wire.entangle('tableState.filters.status.value').live")
        ->not->toContain('<select')
        ->not->toContain('x-ref="searchInput"');
});

it('passes the multiple flag through to the searchable combobox', function () {
    $html = SelectFilter::make('status')
        ->options(['paid' => 'Paid', 'due' => 'Due'])
        ->searchable()
        ->multiple()
        ->render();

    expect($html)
        ->toContain('multiple: true')
        ->toContain('aria-multiselectable="true"');
});

it('renders the native element when searchable is overridden by native()', function () {
    $html = SelectFilter::make('status')
        ->options(['paid' => 'Paid', 'due' => 'Due'])
        ->searchable()
        ->native()
        ->render();

    expect($html)
        ->toContain('<select')
        ->not->toContain('x-teleport');
});

it('defaults a searchable filter to a floating dropdown on mobile, plain to a sheet', function () {
    expect(SelectFilter::make('role')->usesSheetOnMobile())->toBeTrue()
        ->and(SelectFilter::make('role')->searchable()->usesSheetOnMobile())->toBeFalse()
        ->and(SelectFilter::make('role')->searchable()->sheetOnMobile()->usesSheetOnMobile())->toBeTrue();
});

// ─── Native on mobile ────────────────────────────────────────────────────────

it('renders the combobox and a native twin when native on mobile', function () {
    $html = SelectFilter::make('status')
        ->options(['paid' => 'Paid', 'due' => 'Due'])
        ->nativeOnMobile()
        ->render();

    expect($html)
        ->toContain('class="relative max-sm:hidden"')
        ->toContain('<div class="hidden max-sm:block">')
        ->toContain('id="filter-status-native"')
        ->toContain('wire:model.live="tableState.filters.status.value"')
        ->toContain("\$wire.entangle('tableState.filters.status.value').live");
});

it('searchable() lifts native() back to the default, not to a pinned custom control', function () {
    config(['wire-core.mobile.native' => true]);

    $filter = SelectFilter::make('status')->native()->searchable();

    expect($filter->isNative())->toBeFalse()
        ->and($filter->isNativeOnMobile())->toBeTrue();
});

it('leaves no placeholder row in a multiple native select', function () {
    $html = SelectFilter::make('status')
        ->options(['paid' => 'Paid'])
        ->multiple()
        ->native()
        ->render();

    expect($html)->toContain('multiple')->not->toContain('<option value="">');
});

// Regression: the header-row partials read the sheet default straight from
// config and never asked the filter, so ->native() and ->sheetOnMobile() on a
// filter changed its panel control and left the header one untouched.
it('honours the filter native choice in the column header row too', function () {
    view()->share('errors', new ViewErrorBag);

    $render = fn (SelectFilter $filter): string => view('wire-table::tables.columns.partials.filter-select', [
        'column' => TextColumn::make('status'),
        'filter' => $filter,
        'value' => null,
        'statePath' => 'tableState.columnFilters.status',
        'controlClasses' => '',
    ])->render();

    $filter = fn () => SelectFilter::make('status')->options(['paid' => 'Paid']);

    expect($render($filter()))->not->toContain('<select')
        ->and($render($filter()->native()))->toContain('<select')->not->toContain('wireSearchableSelect(')
        ->and($render($filter()->nativeOnMobile()))->toContain('<div class="hidden max-sm:block">')
        ->and($render($filter()->sheetOnMobile(false)))->toContain('sheetOnMobile: false');
});

it('puts a multiple filter on the native select on a phone too', function () {
    expect(SelectFilter::make('status')->multiple()->nativeOnMobile()->isNativeOnMobile())->toBeTrue();
});

it('renders the touch sheet in the panel and in the header row alike', function () {
    view()->share('errors', new ViewErrorBag);
    $filter = SelectFilter::make('status')->options(['paid' => 'Paid'])->touchOnMobile();

    $header = view('wire-table::tables.columns.partials.filter-select', [
        'column' => TextColumn::make('status'),
        'filter' => $filter,
        'value' => null,
        'statePath' => 'tableState.columnFilters.status',
        'controlClasses' => '',
    ])->render();

    expect($filter->render())->toContain('select-touch-sheet')
        ->and($header)->toContain('select-touch-sheet');
});
