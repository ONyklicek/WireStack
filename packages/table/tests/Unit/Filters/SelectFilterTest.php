<?php

declare(strict_types=1);

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

it('is native by default', function () {
    expect(SelectFilter::make('status')->isNative())->toBeTrue();
});

it('can be set to non-native', function () {
    expect(SelectFilter::make('status')->native(false)->isNative())->toBeFalse();
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

it('renders a native select when not searchable', function () {
    $html = SelectFilter::make('status')
        ->options(['paid' => 'Paid', 'due' => 'Due'])
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
