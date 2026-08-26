<?php

declare(strict_types=1);

use NyonCode\WireTable\Support\StateInvalidation;

/*
 * What a write to table state invalidates.
 *
 * The rules are not uniform, and the exception is the one that matters: a
 * re-sort leaves the selection alone while a filter does not. Getting that
 * backwards is not a crash — it is a bulk action quietly operating on a
 * different set of records than the user selected, which is the kind of bug
 * that ships.
 *
 * Driven through a Livewire component these branches are hard to separate: the
 * page resets either way, and whether the selection scope went with it takes a
 * second assertion nobody writes.
 */

it('invalidates nothing for a path the query does not depend on', function () {
    // The ordinary answer. Most writes — a modal form field, a selection
    // toggle, an expanded row — leave the query alone.
    expect(StateInvalidation::forPath('modal.actions.0.data.name'))->toBeNull()
        ->and(StateInvalidation::forPath('selection.records'))->toBeNull()
        ->and(StateInvalidation::forPath('rows.expanded'))->toBeNull()
        ->and(StateInvalidation::forPath('summary.scope'))->toBeNull();
});

it('resets the page and clears the cursor for every path it recognises', function () {
    foreach (['search', 'filters', 'columnFilters', 'sort.column', 'sort.direction', 'pagination.perPage'] as $path) {
        $effect = StateInvalidation::forPath($path);

        expect($effect?->resetsPage)->toBeTrue("{$path} should reset the page")
            // A cursor points into an ordering that no longer exists, unlike a
            // page number.
            ->and($effect?->clearsCursor)->toBeTrue("{$path} should clear the cursor")
            ->and($effect?->marksViewChanged)->toBeTrue("{$path} should mark the view changed");
    }
});

it('drops the selection scope when the set is narrowed', function () {
    // "Everything the filter matches" was defined by the filter on screen.
    foreach (['search', 'filters', 'columnFilters'] as $path) {
        expect(StateInvalidation::forPath($path)?->resetsSelectionScope)
            ->toBeTrue("{$path} narrows the set and must drop the scope");
    }
});

it('keeps the selection when the rows are merely rearranged', function () {
    // The same records still match; a user who selected everything and then
    // sorted has not changed their mind about what they selected.
    foreach (['sort.column', 'sort.direction', 'pagination.perPage'] as $path) {
        expect(StateInvalidation::forPath($path)?->resetsSelectionScope)
            ->toBeFalse("{$path} rearranges rather than narrows");
    }
});

it('normalises the page size only for the page size', function () {
    expect(StateInvalidation::forPath('pagination.perPage')?->normalisesPerPage)->toBeTrue()
        ->and(StateInvalidation::forPath('search')?->normalisesPerPage)->toBeFalse()
        ->and(StateInvalidation::forPath('sort.column')?->normalisesPerPage)->toBeFalse();
});

it('treats a write below a recognised path as a write to it', function () {
    // Livewire reports the deepest path that changed, so a filter write arrives
    // as filters.role.value rather than as filters.
    $nested = StateInvalidation::forPath('filters.role.value');

    expect($nested)->not->toBeNull()
        ->and($nested->resetsSelectionScope)->toBeTrue()
        ->and(StateInvalidation::forPath('columnFilters.status')?->resetsPage)->toBeTrue();
});

it('does not match a path that merely starts with the same letters', function () {
    // `searchable` is not `search`, and a prefix match without the separator
    // would reset the page on a path that has nothing to do with the query.
    expect(StateInvalidation::forPath('searchable'))->toBeNull()
        ->and(StateInvalidation::forPath('filtersDraft'))->toBeNull()
        ->and(StateInvalidation::forPath('pagination.perPageOptions'))->toBeNull();
});

it('does not match a path that only ends with a recognised one', function () {
    expect(StateInvalidation::forPath('draft.search'))->toBeNull()
        ->and(StateInvalidation::forPath('modal.filters'))->toBeNull();
});
