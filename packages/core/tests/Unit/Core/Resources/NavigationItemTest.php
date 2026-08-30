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
