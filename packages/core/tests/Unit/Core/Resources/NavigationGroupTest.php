<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroup;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroups;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;

/*
 * A heading in the menu, and the registry an application declares them in.
 *
 * The whole class exists because a bare string was four things at once — the
 * identity a resource points at, the text a heading draws, the position among
 * the other groups, and whether the group shows at all — so the tests here are
 * about those four staying separable.
 */

it('reads its heading from its key until something says otherwise', function () {
    // A key that was never declared still has to read well, or every group
    // would need registering before it could be drawn.
    expect(NavigationGroup::make('billing')->getLabel())->toBe('Billing')
        ->and(NavigationGroup::make('back-office')->getLabel())->toBe('Back Office')
        ->and(NavigationGroup::make('billing')->label('Fakturace')->getLabel())->toBe('Fakturace');
});

it('keeps its key when the heading is translated', function () {
    // The point of the whole type: `->group(__('nav.billing'))` used to make the
    // translation the array key, so the menu was keyed differently per locale.
    $group = NavigationGroup::make('billing')->label('Fakturace');

    expect($group->getKey())->toBe('billing')
        ->and($group->getName())->toBe('billing');
});

it('speaks the canonical icon, visibility and sort vocabulary', function () {
    $group = NavigationGroup::make('billing')
        ->icon('outline:banknotes')
        ->sort(30)
        ->hidden(fn (): bool => true);

    expect($group->getIcon())->toBe('outline:banknotes')
        ->and($group->getSort())->toBe(30)
        ->and($group->isVisible())->toBeFalse();
});

it('resolves a closure heading on every read', function () {
    $heading = 'Billing';
    $group = NavigationGroup::make('billing')->label(function () use (&$heading): string {
        return $heading;
    });

    expect($group->getLabel())->toBe('Billing');

    $heading = 'Invoicing';

    expect($group->getLabel())->toBe('Invoicing');
});

it('hands out a copy when it is filled with entries', function () {
    $group = NavigationGroup::make('billing');
    $filled = $group->withItems(['invoices' => NavigationItem::make('Invoices')]);

    expect($group->hasItems())->toBeFalse()
        ->and($group->getItems())->toBe([])
        ->and($filled)->not->toBe($group)
        ->and($filled->hasItems())->toBeTrue()
        ->and(array_keys($filled->getItems()))->toBe(['invoices'])
        ->and($filled->getKey())->toBe('billing');
});

it('registers groups by key and answers for them', function () {
    $registry = new NavigationGroups;
    $registry->register(NavigationGroup::make('billing'));
    $registry->register(NavigationGroup::make('operations'));

    expect(array_keys($registry->all()))->toBe(['billing', 'operations'])
        ->and($registry->has('billing'))->toBeTrue()
        ->and($registry->has('nothing'))->toBeFalse()
        ->and($registry->find('operations')?->getKey())->toBe('operations')
        ->and($registry->find('nothing'))->toBeNull();
});

it('lets a later registration replace an earlier one', function () {
    // Deliberate, unlike a duplicate resource key: an application adjusts the
    // order or the heading of a group a module shipped without editing it, and
    // both registrations mean the same group because nothing routes on it.
    $registry = new NavigationGroups;
    $registry->register(NavigationGroup::make('billing')->label('Billing')->sort(10));
    $registry->register(NavigationGroup::make('billing')->label('Finance')->sort(50));

    expect($registry->all())->toHaveCount(1)
        ->and($registry->find('billing')?->getLabel())->toBe('Finance')
        ->and($registry->find('billing')?->getSort())->toBe(50);
});

it('ignores anything that is not a group', function () {
    // Same rule as ResourceRegistry::registerMany(): application config with a
    // stray value should not take the application down at boot.
    $registry = new NavigationGroups;
    $registry->registerMany(['billing', null, 42, NavigationGroup::make('operations')]);
    $registry->registerMany('not iterable at all');

    expect(array_keys($registry->all()))->toBe(['operations']);
});
