<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use NyonCode\WireAdmin\View\Sidebar;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroup;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroups;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\Foundation\Routing\Contracts\ResolvesPageUrls;

/*
 * The menu, rendered.
 *
 * Everything under this file already existed — Catalog, Workspace, the groups,
 * ResolvesPageUrls. What did not exist anywhere in the repository was markup
 * that draws it, so every application wrote its own. These assertions are about
 * the arrangement surviving all the way into HTML, which is the only place the
 * defects V2.6 found (empty labels, dead rows) were visible.
 */

class SbInvoiceResource implements DescribesResource, ProvidesNavigation
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return null;
    }

    public static function navigation(): NavigationItem
    {
        return NavigationItem::make()->group('billing')->badge('3', 'danger');
    }
}

/** Registered, in the menu, and routed nowhere. */
class SbReportResource implements DescribesResource, ProvidesNavigation
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return null;
    }

    public static function navigation(): NavigationItem
    {
        return NavigationItem::make()->group('billing');
    }
}

/** Registered and deliberately not in the menu. */
class SbInternalResource implements DescribesResource
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return null;
    }
}

/** Answers a URL for one key and null for everything else. */
final class SbUrls implements ResolvesPageUrls
{
    public function urlFor(string $key, string $page = 'index', array $parameters = [], ?string $zone = null): ?string
    {
        return $key === 'sb-invoices' ? '/admin/sb-invoices' : null;
    }
}

function sbRender(string $blade = '<x-wire-admin::sidebar />'): string
{
    return Blade::render($blade);
}

beforeEach(function () {
    app(ResourceRegistry::class)->registerMany([
        SbInvoiceResource::class,
        SbReportResource::class,
        SbInternalResource::class,
    ]);

    app(NavigationGroups::class)->register(
        NavigationGroup::make('billing')->label('Billing')->icon('outline:banknotes'),
    );

    app()->bind(ResolvesPageUrls::class, SbUrls::class);
});

it('draws the registered menu, grouped and labelled', function () {
    $html = sbRender();

    expect($html)->toContain('data-testid="admin-sidebar"')
        ->and($html)->toContain('data-group="billing"')
        ->and($html)->toContain('Billing')
        ->and($html)->toContain('data-resource="sb-invoices"')
        ->and($html)->toContain('data-resource="sb-reports"');
});

it('leaves out what declares no navigation', function () {
    // Registered is not listed: an internal resource says so by not
    // implementing ProvidesNavigation, and the menu never learns about it.
    expect(sbRender())->not->toContain('data-resource="sb-internals"');
});

it('links what is routed and draws what is not as a row without a link', function () {
    // The half-routed application. A registered entry with no page is not a
    // dead link and not a missing row — it is a row you cannot click, which is
    // the only honest thing to show.
    $html = sbRender();

    expect($html)->toContain('href="/admin/sb-invoices"')
        ->and($html)->toContain('aria-disabled="true"');
});

it('drops unreachable entries when the caller asks for linked entries only', function () {
    $html = sbRender('<x-wire-admin::sidebar :linked-only="true" />');

    expect($html)->toContain('data-resource="sb-invoices"')
        ->and($html)->not->toContain('data-resource="sb-reports"');
});

it('carries a badge from the entry into the menu', function () {
    expect(sbRender())->toContain('data-testid="admin-nav-badge"');
});

it('marks the entry whose page is being rendered', function () {
    // Active state comes from the route name, through the one place that parses
    // it. Reading it here — while the page renders — is what makes it right:
    // inside a Livewire update the name is `livewire.update` and there is no key.
    Route::get('/admin/sb-invoices', fn () => sbRender())->name('wire.sb-invoices.index');

    $html = $this->get('/admin/sb-invoices')->getContent();

    expect($html)->toContain('data-resource="sb-invoices"')
        ->and($html)->toMatch('/data-resource="sb-invoices"[^>]*data-active="true"/');
});

it('says so when nothing is registered at all', function () {
    // An empty column reads as a menu that lost its rows. V2.6 step 1 found
    // exactly that failure with two blank entries out of three.
    app()->forgetInstance(ResourceRegistry::class);
    app()->forgetInstance(NavigationGroups::class);

    expect(sbRender())->toContain('data-testid="admin-nav-empty"');
});

it('reads its zone and its active key once, at construction', function () {
    // Not per render, and not from inside a Livewire update. The component takes
    // both as arguments so a host that kept them can pass them back.
    $sidebar = new Sidebar(zone: 'business.', activeKey: 'sb-invoices');

    expect($sidebar->zone)->toBe('business.')
        ->and($sidebar->activeKey)->toBe('sb-invoices')
        ->and(array_keys($sidebar->groups()))->toBe(['billing']);
});
