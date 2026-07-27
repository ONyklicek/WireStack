<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireSortable\SortablePlugin;
use NyonCode\WireSortable\SortableTable;
use NyonCode\WireTable\Table;

it('has correct plugin id', function () {
    expect((new SortablePlugin)->getId())->toBe('sortable');
});

it('registers table.querying hook', function () {
    $manager = new PluginManager;
    $manager->register(new SortablePlugin);

    expect($manager->hasHook('table.querying'))->toBeTrue();
});

it('does not modify payload for non-sortable tables', function () {
    $manager = new PluginManager;
    $manager->register(new SortablePlugin);

    $table = SortableTable::make();
    $result = $manager->runHook('table.querying', ['table' => $table]);

    expect($result)->not->toHaveKey('force_sort_column');
});

it('does not modify payload for base Table instances', function () {
    $manager = new PluginManager;
    $manager->register(new SortablePlugin);

    $table = Table::make();
    $result = $manager->runHook('table.querying', ['table' => $table]);

    expect($result)->not->toHaveKey('force_sort_column');
});

it('does not force sort when reorderable but not in reorder mode', function () {
    $manager = new PluginManager;
    $manager->register(new SortablePlugin);

    // No livewire component = not in reorder mode
    $table = SortableTable::make()->reorderable('position');
    $result = $manager->runHook('table.querying', ['table' => $table]);

    expect($result)->not->toHaveKey('force_sort_column');
});

it('forces sort when component is in reorder mode', function () {
    $manager = new PluginManager;
    $manager->register(new SortablePlugin);

    $component = new class
    {
        public function isTableReordering(): bool
        {
            return true;
        }
    };

    $table = SortableTable::make()->reorderable('position');
    $table->livewireComponent($component);

    $result = $manager->runHook('table.querying', ['table' => $table]);

    expect($result['force_sort_column'])->toBe('position')
        ->and($result['force_sort_direction'])->toBe('asc');
});

it('passes a payload carrying no table straight through', function () {
    // The hook runs for every table.querying payload in the app, including
    // ones this plugin has no business touching. Anything that is not a Table
    // is handed back untouched rather than reached into.
    $manager = new PluginManager;
    $manager->register(new SortablePlugin);

    $payload = ['records' => ['a', 'b'], 'table' => null];

    expect($manager->runHook('table.querying', $payload))->toBe($payload)
        ->and($manager->runHook('table.querying', ['unrelated' => 1]))->toBe(['unrelated' => 1]);
});
