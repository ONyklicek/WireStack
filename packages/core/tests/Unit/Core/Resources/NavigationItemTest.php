<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;

/*
 * One menu entry.
 *
 * Built on the canonical Foundation concerns — HasLabel, HasIcon, HasVisibility
 * — rather than on properties of its own, so what is worth asserting is that it
 * really does answer through them, and that the three things a *menu* needs and
 * a component does not (group, sort, badge) behave.
 */

it('answers label, icon and visibility through the canonical concerns', function () {
    $item = NavigationItem::make('Orders')->icon('outline:cart');

    expect($item->getLabel())->toBe('Orders')
        ->and($item->getIcon())->toBe('outline:cart')
        ->and($item->isVisible())->toBeTrue()
        ->and($item->getIconPosition())->toBe('before');
});

it('takes closures for the things that change', function () {
    $count = 2;

    $item = NavigationItem::make(fn () => 'Orders')
        ->group(fn () => 'Sales')
        ->badge(function () use (&$count) {
            return $count;
        }, 'danger');

    expect($item->getBadge())->toBe('2');

    // Resolved per read, not stored: a badge that says how many orders are
    // unshipped is wrong the moment it is cached.
    $count = 5;

    expect($item->getBadge())->toBe('5')
        ->and($item->getLabel())->toBe('Orders')
        ->and($item->getGroup())->toBe('Sales')
        ->and($item->getBadgeColor())->toBe('danger');
});

it('has no badge when there is nothing to show', function () {
    // Zero unshipped orders should render no badge rather than a "0" chip, and
    // an empty string is the same statement.
    expect(NavigationItem::make('Orders')->getBadge())->toBeNull()
        ->and(NavigationItem::make('Orders')->badge(null)->getBadge())->toBeNull()
        ->and(NavigationItem::make('Orders')->badge('')->getBadge())->toBeNull()
        ->and(NavigationItem::make('Orders')->badge(0)->getBadge())->toBe('0');
});

it('sits at the top level and sorts neutrally by default', function () {
    $item = NavigationItem::make('Settings');

    expect($item->getGroup())->toBeNull()
        ->and($item->getSort())->toBe(0)
        ->and($item->getBadgeColor())->toBeNull();
});

it('can be hidden by a condition', function () {
    expect(NavigationItem::make('Audit')->visible(false)->isVisible())->toBeFalse()
        ->and(NavigationItem::make('Audit')->hidden(fn () => true)->isVisible())->toBeFalse();
});

it('carries children, visible ones only and in sort order', function () {
    $item = NavigationItem::make('Catalogue')->children([
        NavigationItem::make('Categories')->sort(20),
        NavigationItem::make('Archived')->sort(5)->visible(false),
        NavigationItem::make('Products')->sort(10),
    ]);

    expect($item->hasChildren())->toBeTrue()
        // Filtered here rather than in whatever draws the menu, so every surface
        // agrees about what is in a submenu without repeating the rule.
        ->and(array_map(fn (NavigationItem $c): ?string => $c->getLabel(), $item->getChildren()))
        ->toBe(['Products', 'Categories']);
});

it('resolves children per read, so what a user may see is never decided once', function () {
    $allowed = false;

    // By reference, because an arrow function would capture the flag's value at
    // the moment the entry was declared — which is the very thing this asserts
    // does not decide the answer.
    $item = NavigationItem::make('Billing')->children(function () use (&$allowed): array {
        return $allowed ? [NavigationItem::make('Invoices')] : [];
    });

    expect($item->hasChildren())->toBeFalse();

    $allowed = true;

    expect($item->hasChildren())->toBeTrue();
});

it('has no children until it is given some, and ignores what is not one', function () {
    expect(NavigationItem::make('Orders')->hasChildren())->toBeFalse()
        ->and(NavigationItem::make('Orders')->children([])->getChildren())->toBe([])
        // A Closure that answers with something else is a caller's mistake, and
        // an empty submenu is a better answer to it than a fatal while a menu
        // renders.
        ->and(NavigationItem::make('Orders')->children(fn (): ?array => null)->getChildren())->toBe([])
        ->and(NavigationItem::make('Orders')->children(['not an item'])->getChildren())->toBe([]);
});
