<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use NyonCode\WireAdmin\Enums\NavigationShape;
use NyonCode\WireAdmin\Exceptions\NavigationShapeException;
use NyonCode\WireCore\Core\Plugin\Hooks\NavigationBuildingPayload;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroup;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroups;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Foundation\Enums\Hook;

/*
 * The menu as a bar under the header (navigation-surfaces.md § 4).
 *
 * The count assertions are the point: the drawer stays in the document for a
 * phone, so a bar that reused the drawer's names would have every test and
 * driver counting each row twice.
 */
function tnGet(string $view): string
{
    View::addLocation(__DIR__.'/../fixtures/views');
    Route::get('/tn-probe', fn () => view($view));

    return test()->get('/tn-probe')->getContent();
}

beforeEach(function () {
    app(NavigationGroups::class)->register(NavigationGroup::make('billing')->label('Billing'));

    app(PluginManager::class)->hook(Hook::NavigationBuilding, function (NavigationBuildingPayload $payload) {
        $payload->items['orders'] = NavigationItem::make('Orders')->url('/orders')->group('billing');
        $payload->items['invoices'] = NavigationItem::make('Invoices')->url('/invoices')->group('billing')
            ->children([NavigationItem::make('Overdue')->url('/invoices/overdue')]);
        $payload->items['home'] = NavigationItem::make('Home')->url('/home');
        $payload->items['reports'] = NavigationItem::make('Reports')->url('/reports')->group('insights');

        return $payload;
    });
});

it('draws the bar and keeps the column only as the phone drawer', function () {
    $html = tnGet('top');

    expect($html)->toContain('data-testid="admin-topnav"')
        ->toContain('x-data="wireTopNav"')
        ->toMatch('/data-testid="admin-sidebar"[^>]*lg:hidden|class="[^"]*lg:hidden[^"]*"[^>]*\n?[^>]*data-testid="admin-sidebar"/s');
});

it('draws each row once in the bar and once in the drawer, never twice under one name', function () {
    $html = tnGet('top');

    $count = fn (string $testid, string $key): int => preg_match_all('/data-testid="'.$testid.'"\s+data-resource="'.$key.'"/', $html);

    expect($count('admin-topnav-item', 'orders'))->toBe(1)
        ->and($count('admin-topnav-more-item', 'orders'))->toBe(1)
        ->and(substr_count($html, 'data-testid="admin-nav-item"'))->toBe(4);
});

it('makes a group a button and a group of one a plain link', function () {
    $html = tnGet('top');

    expect($html)->toContain('data-testid="admin-topnav-group"')
        ->toMatch('/data-testid="admin-topnav-group"[^>]*data-group="billing"/s')
        // Insights holds one entry: a panel of one row is a click for nothing.
        ->not->toMatch('/data-testid="admin-topnav-group"[^>]*data-group="insights"/s')
        ->toMatch('/data-testid="admin-topnav-item"\s+data-resource="reports"/');
});

it('draws a child one level down inside the panel', function () {
    expect(tnGet('top'))->toContain('data-testid="admin-topnav-item-child"');
});

it('draws no rail toggle and no collapse shortcut in the bar shape', function () {
    $html = tnGet('top');

    expect($html)->not->toContain('data-testid="admin-rail-toggle"');
});

it('keeps the column by default', function () {
    $html = tnGet('bare');

    expect($html)->not->toContain('data-testid="admin-topnav"')
        ->toContain('data-testid="admin-rail-toggle"');
});

it('takes the shape from config', function () {
    config()->set('wire-admin.layout.navigation', 'top');

    expect(tnGet('bare'))->toContain('data-testid="admin-topnav"');
});

it('refuses a shape that is not one', function () {
    expect(fn () => NavigationShape::resolve('topbar'))->toThrow(NavigationShapeException::class, "'sidebar', 'top'")
        ->and(NavigationShape::resolve(null))->toBe(NavigationShape::Sidebar)
        ->and(NavigationShape::resolve(NavigationShape::Top))->toBe(NavigationShape::Top);

    $this->withoutExceptionHandling();
    tnGet('bogus-shape');
})->throws(Exception::class, 'is not a navigation shape');

it('gives a lone entry with children a panel, so its children have a way in', function () {
    app(PluginManager::class)->hook(Hook::NavigationBuilding, function (NavigationBuildingPayload $payload) {
        $payload->items['catalog'] = NavigationItem::make('Catalog')->url('/catalog')
            ->children([NavigationItem::make('Archive')->url('/catalog/archive')]);

        return $payload;
    });

    expect(tnGet('top'))->toMatch('/data-testid="admin-topnav-group"[^>]*data-group="catalog"/s');
});
