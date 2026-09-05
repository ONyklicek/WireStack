<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\Core\Resources\Workspace;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireCore\Foundation\Routing\Contracts\ResolvesPageUrls;
use NyonCode\WirePanels\Routing\RegisteredPageUrls;

/*
 * The soft seam, joined up (ADR 0026 §4).
 *
 * `wire-core` asks where a key's page is and answers "nowhere" on its own;
 * `wire-panels` owns the URL convention and rebinds the answer. Each half has
 * tests of its own — what neither can show is that the binding happens, which is
 * the part that silently does not, because every consumer renders fine without
 * a URL.
 */

/** In the menu and declaring a page, which is what makes an entry a link. */
class PuOrderResource implements DescribesResource, ProvidesNavigation, ProvidesPages
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return null;
    }

    public static function key(): string
    {
        return 'pu-orders';
    }

    public static function pages(): array
    {
        return ['index' => RtListPage::class];
    }

    public static function navigation(): NavigationItem
    {
        return NavigationItem::make('Orders');
    }
}

it('rebinds the resolver core left null', function () {
    expect(app(ResolvesPageUrls::class))->toBeInstanceOf(RegisteredPageUrls::class);
});

it('hands a menu entry the url of the page its resource declared', function () {
    // End to end: a resource declares pages, the macro registers them, the
    // workspace fills the entry from the same key. Nothing in between writes a
    // path, which is what every application used to do by hand.
    app(ResourceRegistry::class)->register(PuOrderResource::class);

    Route::prefix('admin')->group(function (): void {
        Route::wireResources();
    });

    Route::getRoutes()->refreshNameLookups();

    $items = app(Workspace::class)->items();

    expect($items['pu-orders']->getUrl())->toEndWith('/admin/pu-orders');
});

it('leaves a registered resource that declares no pages unlinked', function () {
    // Deliberately unrouted, and a menu entry without an href already renders —
    // so null has to survive the whole chain rather than becoming a broken link.
    app(ResourceRegistry::class)->register(RtInternalResource::class);

    Route::wireResources();
    Route::getRoutes()->refreshNameLookups();

    expect(app(ResolvesPageUrls::class)->urlFor('rt-internals'))->toBeNull();
});
