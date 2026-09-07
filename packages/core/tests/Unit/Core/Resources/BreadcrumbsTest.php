<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;

/*
 * The trail above a page.
 *
 * It lives in core rather than in the shell so a page renders its own — which is
 * what lets `wire-panels` have breadcrumbs without depending on `wire-admin`,
 * a direction the package graph forbids, and what gives an application with its
 * own chrome the same thing.
 */

function bcRender(array $items): string
{
    return Blade::render('<x-wire::breadcrumbs :items="$items" />', ['items' => $items]);
}

it('links every crumb but the one you are on', function () {
    $html = bcRender([
        NavigationItem::make('Invoices')->url('/admin/invoices'),
        NavigationItem::make('INV-1')->url('/admin/invoices/1'),
    ]);

    expect($html)->toContain('href="/admin/invoices"')
        // A link to the page you are already on is noise for everyone and a trap
        // for a screen reader.
        ->and($html)->not->toContain('href="/admin/invoices/1"')
        ->and($html)->toContain('aria-current="page"');
});

it('renders a crumb that has no URL as text', function () {
    // What an unrouted resource looks like — the same honesty the menu applies.
    $html = bcRender([
        NavigationItem::make('Reports'),
        NavigationItem::make('Quarterly'),
    ]);

    expect($html)->toContain('Reports')
        ->and($html)->toContain('Quarterly')
        ->and($html)->not->toContain('<a ');
});

it('draws nothing for a trail of one, or none', function () {
    // One crumb says "you are here", which the page heading under it already
    // says. Two is the first trail that carries information.
    expect(trim(bcRender([])))->toBe('')
        ->and(trim(bcRender([NavigationItem::make('Invoices')])))->toBe('');
});

it('ignores anything in the list that is not a crumb', function () {
    $html = bcRender([NavigationItem::make('Invoices')->url('/admin/invoices'), 'not a crumb', NavigationItem::make('INV-1')]);

    expect($html)->toContain('Invoices')
        ->and($html)->toContain('INV-1')
        ->and($html)->not->toContain('not a crumb');
});
