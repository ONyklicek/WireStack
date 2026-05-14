<?php

declare(strict_types=1);

use NyonCode\WireTable\Filters\TernaryFilter;

it('can be created', function () {
    expect(TernaryFilter::make('active'))->toBeInstanceOf(TernaryFilter::class);
});

// ─── Labels ─────────────────────────────────────────────────────────────────

it('has default labels from translation', function () {
    $filter = TernaryFilter::make('active');

    expect($filter->getTrueLabel())->toBe('filter_yes')
        ->and($filter->getFalseLabel())->toBe('filter_no')
        ->and($filter->getAllLabel())->toBe('filter_all');
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
