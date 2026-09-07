<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Concerns\HasItemExpansion;
use NyonCode\WireCore\Foundation\Enums\ItemExpansion;
use NyonCode\WireCore\Foundation\Support\EvaluatesClosures;

/**
 * `CanBeCollapsed` asked of a list: which of N items start open.
 *
 * The host is a bare stand-in rather than a Repeater, because the point of the
 * concern is that nothing about it is forms-specific — it composes
 * `CanBeCollapsed`, takes no schema and never counts its own items.
 */
class ExpandableList
{
    use EvaluatesClosures;
    use HasItemExpansion;
}

it('starts every item open, and says so as a policy', function () {
    $list = new ExpandableList;

    expect($list->getItemExpansion())->toBe(ItemExpansion::All)
        ->and($list->isItemCollapsedByDefault(0, 3))->toBeFalse()
        ->and($list->isItemCollapsedByDefault(2, 3))->toBeFalse();
});

it('reads collapsed() as the None policy, so a host that never heard of this keeps working', function () {
    $list = (new ExpandableList)->collapsed();

    expect($list->getItemExpansion())->toBe(ItemExpansion::None)
        ->and($list->isItemCollapsedByDefault(0, 3))->toBeTrue();
});

it('opens only the first item under expandFirst()', function () {
    $list = (new ExpandableList)->expandFirst();

    expect($list->isItemCollapsedByDefault(0, 3))->toBeFalse()
        ->and($list->isItemCollapsedByDefault(1, 3))->toBeTrue()
        ->and($list->isItemCollapsedByDefault(2, 3))->toBeTrue();
});

it('opens only the last item under expandLast()', function () {
    $list = (new ExpandableList)->expandLast();

    expect($list->isItemCollapsedByDefault(0, 3))->toBeTrue()
        ->and($list->isItemCollapsedByDefault(1, 3))->toBeTrue()
        ->and($list->isItemCollapsedByDefault(2, 3))->toBeFalse();
});

it('implies collapsible for every policy that folds something', function () {
    expect((new ExpandableList)->expandFirst()->isCollapsible())->toBeTrue()
        ->and((new ExpandableList)->expandLast()->isCollapsible())->toBeTrue()
        ->and((new ExpandableList)->collapseAll()->isCollapsible())->toBeTrue()
        // expandAll() *is* the unfolded default, so it asserts nothing about
        // whether folding is on offer.
        ->and((new ExpandableList)->expandAll()->isCollapsible())->toBeFalse();
});

it('lets the last call win in both directions', function () {
    // Without collapsed() dropping the positional policy, the first row would
    // still open — the two are one setting spelled two ways.
    expect((new ExpandableList)->expandFirst()->collapsed()->getItemExpansion())
        ->toBe(ItemExpansion::None)
        ->and((new ExpandableList)->collapsed()->expandFirst()->getItemExpansion())
        ->toBe(ItemExpansion::First);
});

it('reports All for a list that cannot fold, whatever was asked for', function () {
    // Mirrors isCollapsed()'s own guard: never render something folded that the
    // user has no control to open.
    $list = (new ExpandableList)->expandFirst()->collapsible(false);

    expect($list->getItemExpansion())->toBe(ItemExpansion::All)
        ->and($list->isItemCollapsedByDefault(1, 3))->toBeFalse();
});

it('folds nothing in an empty list', function () {
    expect(ItemExpansion::Last->collapses(0, 0))->toBeFalse()
        ->and(ItemExpansion::First->collapses(0, 0))->toBeFalse();
});
