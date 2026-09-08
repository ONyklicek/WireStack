<?php

declare(strict_types=1);

use NyonCode\WireSortable\Concerns\WithSortable;
use NyonCode\WireSortable\SortableTable;
use NyonCode\WireTable\Table;

/*
 * The toolbar's reorder toggle.
 *
 * It was a PHP string until the markup moved into `partials.reorder-toggle` and
 * the icon to the canonical owner — button, inline <svg> and both <path>s were
 * concatenated in `getTableToolbarWidgets()`. What that move has to preserve is
 * asserted here: one widget, the click binding, the two states, and an icon that
 * came out of IconManager rather than being hand-written.
 *
 * A Feature test rather than a Unit one because the toggle's title goes through
 * the translator, which needs the application.
 */
class ToggleHost
{
    use WithSortable;

    public function __construct(private readonly SortableTable $sortableTable) {}

    public function getTable(): Table
    {
        return $this->sortableTable;
    }
}

it('offers the reorder toggle only where reordering is a choice', function () {
    $plain = new ToggleHost(SortableTable::make());
    $reorderable = new ToggleHost(SortableTable::make()->reorderable());
    // alwaysReorderable() has no off state, so there is nothing to toggle.
    $always = new ToggleHost(SortableTable::make()->alwaysReorderable());

    expect($plain->getTableToolbarWidgets())->toBe([])
        ->and($always->getTableToolbarWidgets())->toBe([])
        ->and($reorderable->getTableToolbarWidgets())->toHaveCount(1);
});

it('renders the toggle through the partial and the canonical icon owner', function () {
    $host = new ToggleHost(SortableTable::make()->reorderable());

    [$idle] = $host->getTableToolbarWidgets();

    $host->isReordering = true;
    [$active] = $host->getTableToolbarWidgets();

    expect($idle)->toContain('wire:click="toggleReordering"')
        // The bars icon invites reordering; the check ends it.
        ->toContain('M3.75 6.75h16.5')
        ->and($active)->toContain('m4.5 12.75 6 6 9-13.5')
        ->and($active)->toContain('bg-primary-100')
        ->and($idle)->not->toContain('bg-primary-100')
        // Both come out of IconManager, which stamps every icon it resolves.
        ->and($idle)->toContain('aria-hidden="true"')
        ->and($active)->toContain('aria-hidden="true"');
});
