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
