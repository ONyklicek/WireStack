<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireTable\Table;

it('registers the sortable plugin when the plugin manager resolves', function () {
    $manager = app(PluginManager::class);

    expect($manager->has('sortable'))->toBeTrue();
});

it('registers reorderable table macros at boot', function () {
    foreach ([
        'reorderable', 'isReorderable', 'alwaysReorderable', 'isAlwaysReorderable',
        'getOrderColumn', 'paginatedWhileReordering', 'isPaginatedWhileReordering',
        'columnReorderable', 'isColumnReorderable',
    ] as $macro) {
        expect(Table::hasMacro($macro))->toBeTrue();
    }
});

it('reorderable macro toggles state and order column', function () {
    $table = Table::make()->reorderable('position');

    expect($table->isReorderable())->toBeTrue()
        ->and($table->getOrderColumn())->toBe('position');
});

it('reorderable defaults the order column from config', function () {
    expect(Table::make()->reorderable()->getOrderColumn())->toBe('sort_order');
});

it('alwaysReorderable forces reordering on', function () {
    $table = Table::make()->alwaysReorderable('pos');

    expect($table->isReorderable())->toBeTrue()
        ->and($table->isAlwaysReorderable())->toBeTrue()
        ->and($table->getOrderColumn())->toBe('pos');
});

it('paginatedWhileReordering and columnReorderable toggles', function () {
    $table = Table::make()->paginatedWhileReordering()->columnReorderable();

    expect($table->isPaginatedWhileReordering())->toBeTrue()
        ->and($table->isColumnReorderable())->toBeTrue();
});

it('defaults the reordering toggles to false', function () {
    $table = Table::make();

    expect($table->isReorderable())->toBeFalse()
        ->and($table->isAlwaysReorderable())->toBeFalse()
        ->and($table->isPaginatedWhileReordering())->toBeFalse()
        ->and($table->isColumnReorderable())->toBeFalse();
});
