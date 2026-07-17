<?php

declare(strict_types=1);

use NyonCode\WireTable\Filters\TernaryFilter;

it('can be created', function () {
    expect(TernaryFilter::make('active'))->toBeInstanceOf(TernaryFilter::class);
});

// ─── Labels ─────────────────────────────────────────────────────────────────

it('has default labels from translation', function () {
    $filter = TernaryFilter::make('active');

    expect($filter->getTrueLabel())->toBe('Yes')
        ->and($filter->getFalseLabel())->toBe('No')
        ->and($filter->getAllLabel())->toBe('All');
});

it('can set custom labels', function () {
    $filter = TernaryFilter::make('active')
        ->trueLabel('Yes')
        ->falseLabel('No')
        ->allLabel('All');

    expect($filter->getTrueLabel())->toBe('Yes')
        ->and($filter->getFalseLabel())->toBe('No')
        ->and($filter->getAllLabel())->toBe('All');
});

// ─── Nullable ───────────────────────────────────────────────────────────────

it('is not nullable by default', function () {
    expect(TernaryFilter::make('active')->isNullable())->toBeFalse();
});

it('can be set to nullable', function () {
    expect(TernaryFilter::make('active')->nullable()->isNullable())->toBeTrue();
});

// ─── Select surface ─────────────────────────────────────────────────────────

it('exposes the true/false states as options, with "all" left to the placeholder', function () {
    $filter = TernaryFilter::make('active')->trueLabel('Yes')->falseLabel('No');

    expect($filter->getOptions())->toBe(['true' => 'Yes', 'false' => 'No']);
});

it('is not native by default so it matches the select filter and forms Select', function () {
    expect(TernaryFilter::make('active')->isNative())->toBeFalse();
});

it('can be set to native', function () {
    expect(TernaryFilter::make('active')->native()->isNative())->toBeTrue();
});

it('renders the combobox by default', function () {
    $html = TernaryFilter::make('active')->trueLabel('Yes')->falseLabel('No')->render();

    expect($html)
        ->toContain('x-teleport')
        ->toContain("\$wire.entangle('tableState.filters.active.value').live")
        ->toContain('id="filter-active"')
        ->not->toContain('<select');
});

it('renders a native select when native() is opted into', function () {
    $html = TernaryFilter::make('active')->trueLabel('Yes')->falseLabel('No')->native()->render();

    expect($html)
        ->toContain('<select')
        ->toContain('wire:model.live="tableState.filters.active.value"')
        ->not->toContain('x-teleport');
});

it('marks the matching option selected on the native path for every truthy shape', function () {
    $filter = TernaryFilter::make('active')->trueLabel('Yes')->falseLabel('No')->native();

    expect($filter->render('true'))->toMatch('/value="true"\s+selected/')
        ->and($filter->render('1'))->toMatch('/value="true"\s+selected/')
        ->and($filter->render(true))->toMatch('/value="true"\s+selected/')
        ->and($filter->render('false'))->toMatch('/value="false"\s+selected/')
        ->and($filter->render('0'))->toMatch('/value="false"\s+selected/')
        ->and($filter->render(false))->toMatch('/value="false"\s+selected/');
});

it('selects the "all" placeholder option when no value is set on the native path', function () {
    $html = TernaryFilter::make('active')->allLabel('Any')->native()->render();

    expect($html)->toMatch('/value=""\s+selected/');
});
