<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use NyonCode\WireCore\Core\Resources\Navigation\ActiveNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;

/*
 * Where the reader is — the question a menu used to answer three times, in a
 * Blade file, with three different rules.
 *
 * What is pinned here is the order of those rules and the two edges that used to
 * be invisible: an entry that stays lit across a resource's own pages without
 * claiming to *be* the page (aria-current), and an entry whose author knows
 * better than the convention and says so.
 */

/** The reading for a resource's edit page, as a full page render would give it. */
function activeOnEdit(): ActiveNavigation
{
    return new ActiveNavigation(
        key: 'invoices',
        page: 'edit',
        url: 'http://localhost/invoices/7/edit',
        path: 'invoices/7/edit',
        routeName: 'wire.invoices.edit',
    );
}

it('reads the route being rendered', function () {
    $seen = null;

    Route::get('invoices/{record}/edit', function () use (&$seen) {
        $seen = ActiveNavigation::current();

        return 'ok';
    })->name('wire.invoices.edit');

    Route::getRoutes()->refreshNameLookups();

    $this->get('/invoices/7/edit')->assertOk();

    expect($seen->key)->toBe('invoices')
        ->and($seen->page)->toBe('edit')
        ->and($seen->path)->toBe('invoices/7/edit')
        ->and($seen->routeName)->toBe('wire.invoices.edit')
        ->and($seen->url)->toEndWith('/invoices/7/edit');
});

it('keeps a resource entry active across that resource own pages', function () {
    // The rule that makes a menu usable: you are inside Invoices whether you are
    // looking at the list, one record, or the form that edits it. The entry has
    // no idea which — it carries the index URL — so the key is what answers.
    $item = NavigationItem::make('Invoices')->url('http://localhost/invoices');

    expect(activeOnEdit()->isActive($item, 'invoices'))->toBeTrue()
        ->and(activeOnEdit()->isActive($item, 'orders'))->toBeFalse();
});

it('says page only for the page itself, and true for the branch it sits in', function () {
    // Two `aria-current="page"` on one screen is two answers to one question,
    // and that is exactly what would happen once the record's tabs appeared
    // beside the menu. The menu row drops to `true` the moment it is standing
    // for a page other than the one being rendered.
    $item = NavigationItem::make('Invoices')->url('http://localhost/invoices');

    expect(activeOnEdit()->ariaCurrent($item, 'invoices'))->toBe('true')
        ->and(activeOnEdit()->isExactly($item))->toBeFalse();

    $onIndex = new ActiveNavigation(
        key: 'invoices',
        page: 'index',
        url: 'http://localhost/invoices',
        path: 'invoices',
        routeName: 'wire.invoices.index',
    );

    expect($onIndex->ariaCurrent($item, 'invoices'))->toBe('page')
        ->and($onIndex->isExactly($item))->toBeTrue();
});

it('carries no aria-current at all for an entry that is neither', function () {
    $item = NavigationItem::make('Orders')->url('http://localhost/orders');

    expect(activeOnEdit()->ariaCurrent($item, 'orders'))->toBeNull();
});

it('matches a hand-written entry by its URL, ignoring a trailing slash', function () {
    $onSettings = new ActiveNavigation(
        url: 'http://localhost/settings/general',
        path: 'settings/general',
    );

    expect($onSettings->isActive(NavigationItem::make('General')->url('http://localhost/settings/general/')))->toBeTrue()
        ->and($onSettings->isActive(NavigationItem::make('General')->url('http://localhost/settings/general')))->toBeTrue();
});

it('reads a relative entry and an absolute one as the same place', function () {
    // Not an edge case: `ResolvesPageUrls` answers with `route()`, which is
    // absolute, and an entry written by hand is `->url('/settings')`. Compared
    // as raw strings the two were different on the page they both point at.
    $onSettings = new ActiveNavigation(
        url: 'http://localhost/settings/general',
        path: 'settings/general',
    );

    expect($onSettings->isActive(NavigationItem::make('General')->url('/settings/general')))->toBeTrue()
        // And a query string still tells two entries apart: an "All" and an
        // "Archived" over one list are two entries, not one.
        ->and($onSettings->isActive(NavigationItem::make('Archived')->url('/settings/general?filter=archived')))->toBeFalse();
});

it('does not light an entry for a page underneath it', function () {
    // Deliberate, and the reason is in ActiveNavigation's docblock: a prefix
    // rule cannot tell a section from the shell's own mount path, so `Home`
    // would be highlighted on every page in the application, for ever. The entry
    // that wants the section rule asks for it — the test below.
    $deeper = new ActiveNavigation(
        url: 'http://localhost/settings/general/edit',
        path: 'settings/general/edit',
    );

    expect($deeper->isActive(NavigationItem::make('General')->url('http://localhost/settings/general')))->toBeFalse();
});

it('is never active for an entry that points nowhere', function () {
    // A registered thing this zone does not route. It draws as an unlinked row,
    // and an unlinked row is not a place you can be standing.
    expect(activeOnEdit()->isActive(NavigationItem::make('Documents')))->toBeFalse()
        ->and(activeOnEdit()->isExactly(NavigationItem::make('Documents')))->toBeFalse();
});

it('lets an entry declare when it is active, by path or by route name', function () {
    $item = fn (string $pattern) => NavigationItem::make('Settings')
        ->url('http://localhost/settings/general')
        ->activeWhen($pattern);

    expect(activeOnEdit()->isActive($item('invoices/*')))->toBeTrue()
        // A leading slash is the same sentence.
        ->and(activeOnEdit()->isActive($item('/invoices/*')))->toBeTrue()
        ->and(activeOnEdit()->isActive($item('wire.invoices.*')))->toBeTrue()
        ->and(activeOnEdit()->isActive($item('orders/*')))->toBeFalse();
});

it('takes several patterns, and a list of them', function () {
    $item = NavigationItem::make('Billing')
        ->url('http://localhost/billing')
        ->activeWhen(['orders/*', 'invoices/*']);

    expect(activeOnEdit()->isActive($item))->toBeTrue();
});

it('lets a declaration say no, over a key that would have said yes', function () {
    // The point of replacing the convention rather than adding to it: without
    // this, "never active here" is a sentence nobody can write.
    $item = NavigationItem::make('Invoices')
        ->url('http://localhost/invoices')
        ->activeWhen(fn (): bool => false);

    expect(activeOnEdit()->isActive($item, 'invoices'))->toBeFalse()
        ->and(activeOnEdit()->ariaCurrent($item, 'invoices'))->toBeNull();
});

it('hands the reading to a declared closure', function () {
    $item = NavigationItem::make('Anything')
        ->activeWhen(fn (ActiveNavigation $active): bool => $active->page === 'edit');

    expect(activeOnEdit()->isActive($item))->toBeTrue();
});

it('knows when something under an entry is the page', function () {
    $item = NavigationItem::make('Catalogue')->children([
        NavigationItem::make('Products')->url('http://localhost/products'),
        NavigationItem::make('Categories')->url('http://localhost/invoices/7/edit'),
    ]);

    expect(activeOnEdit()->hasActiveChild($item))->toBeTrue()
        ->and(activeOnEdit()->hasActiveChild(NavigationItem::make('Alone')))->toBeFalse();
});

it('takes the key a host resolved for itself, and keeps the request URL', function () {
    // `<x-wire-admin::sidebar :active-key="…">` — a menu drawn beside a page
    // rather than by it. Only the key is replaced; the URL rule still needs the
    // request the menu is being rendered in.
    $active = activeOnEdit()->withKey('orders');

    expect($active->isActive(NavigationItem::make('Orders'), 'orders'))->toBeTrue()
        ->and($active->isActive(NavigationItem::make('Invoices'), 'invoices'))->toBeFalse()
        ->and($active->url)->toBe('http://localhost/invoices/7/edit')
        ->and($active->page)->toBe('edit');
});
