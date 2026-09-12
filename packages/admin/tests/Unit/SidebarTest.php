<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use NyonCode\WireAdmin\View\Sidebar;
use NyonCode\WireCore\Core\Plugin\Hooks\NavigationBuildingPayload;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroup;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroups;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\Foundation\Enums\Hook;
use NyonCode\WireCore\Foundation\Routing\Contracts\ResolvesPageUrls;
use NyonCode\WireCore\Foundation\View\Badge;

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

/** A badge with no colour of its own — the default every surface must agree on. */
class SbPlainBadgeResource implements DescribesResource, ProvidesNavigation
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return null;
    }

    public static function navigation(): NavigationItem
    {
        return NavigationItem::make()->group('billing')->sort(99)->badge('7');
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

/** An entry with a second level under it. */
class SbCatalogueResource implements DescribesResource, ProvidesNavigation
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return null;
    }

    public static function navigation(): NavigationItem
    {
        return NavigationItem::make()->group('billing')->children([
            NavigationItem::make('Archived')->url('/admin/sb-catalogues?filter=archived'),
        ]);
    }
}

/** Answers a URL for one key and null for everything else. */
final class SbUrls implements ResolvesPageUrls
{
    public function urlFor(string $key, string $page = 'index', array $parameters = [], ?string $zone = null): ?string
    {
        return match ($key) {
            'sb-invoices' => '/admin/sb-invoices',
            // Routed *and* carrying a submenu, which is the pair the disclosure
            // rule needs: a row with children renders as a button.
            'sb-catalogues' => '/admin/sb-catalogues',
            default => null,
        };
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

it('stays marked on a page of that resource without claiming to be it', function () {
    // The row is where you are *inside*, not the page you are on — and once the
    // record's own tabs render above the form, saying `aria-current="page"` in
    // both places is two answers to one question. `page` is reserved for the
    // entry whose URL is the URL being rendered.
    Route::get('/admin/sb-invoices', fn () => sbRender())->name('wire.sb-invoices.index');
    Route::get('/admin/sb-invoices/{record}/edit', fn () => sbRender())->name('wire.sb-invoices.edit');

    expect($this->get('/admin/sb-invoices')->getContent())
        ->toMatch('/data-resource="sb-invoices"[^>]*aria-current="page"/');

    expect($this->get('/admin/sb-invoices/7/edit')->getContent())
        ->toMatch('/data-resource="sb-invoices"[^>]*data-active="true"/')
        ->toMatch('/data-resource="sb-invoices"[^>]*aria-current="true"/')
        ->not->toContain('aria-current="page"');
});

it('does not call a disclosure button the current page', function () {
    // The row is a `<button>` — it opens the submenu rather than going anywhere,
    // and the row that does go there is the child underneath it. `page` on the
    // button would be a claim that pressing it lands you where you already are.
    app(ResourceRegistry::class)->register(SbCatalogueResource::class);

    Route::get('/admin/sb-catalogues', fn () => sbRender())->name('wire.sb-catalogues.index');

    $html = $this->get('/admin/sb-catalogues')->getContent();

    expect($html)->toMatch('/data-resource="sb-catalogues"[^>]*data-active="true"/')
        ->toMatch('/data-resource="sb-catalogues"[^>]*aria-current="true"/')
        ->not->toContain('aria-current="page"');
});

it('lets an entry say for itself which pages it belongs to', function () {
    // The one thing the conventions cannot answer: a hand-written entry points
    // at a page, and its section has pages underneath it that the entry has no
    // way to name. `activeWhen()` is how it says so — matched against the path
    // or the route name, whichever the author was thinking in.
    app()->forgetInstance(ResourceRegistry::class);
    app(ResourceRegistry::class)->register(SbInvoiceResource::class);

    app(NavigationGroups::class)->register(NavigationGroup::make('billing')->label('Billing'));

    Route::get('/admin/settings/general/edit', fn () => sbRender(
        '<x-wire-admin::sidebar />',
    ))->name('settings.general.edit');

    // The entry arrives through the hook every menu is built through, which is
    // also the honest way an application adds one that is not a resource.
    app(PluginManager::class)->hook(
        Hook::NavigationBuilding,
        function (NavigationBuildingPayload $payload) {
            $payload->items['settings'] = NavigationItem::make('Settings')
                ->group('billing')
                ->url('/admin/settings/general')
                ->activeWhen('admin/settings/*');

            return $payload;
        },
    );

    expect($this->get('/admin/settings/general/edit')->getContent())
        ->toMatch('/data-resource="settings"[^>]*data-active="true"/');
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

it('draws a submenu under an entry that declares children', function () {
    app(ResourceRegistry::class)->register(SbCatalogueResource::class);

    $html = sbRender();

    expect($html)->toContain('data-testid="admin-nav-child"')
        ->and($html)->toContain('href="/admin/sb-catalogues?filter=archived"')
        // The parent stops being a link and becomes the disclosure for its own
        // list: two things to click in one row is what makes a nested menu
        // impossible to hit.
        // Which tag the row is, found by walking back to the one that opened
        // it rather than by a regex: the attributes between the two now include
        // an arrow function, and `[^>]*` stops at its `=>`.
        ->and(Str::afterLast(Str::before($html, 'data-resource="sb-catalogues"'), '<'))->toStartWith('button')
        ->and($html)->toContain('aria-controls="wire-admin-sub-');
});

it('gives an entry with a badge a second, smaller one for the rail', function () {
    // The defect this fixes was visible only in a screenshot: in the collapsed
    // rail the badge sat *beside* the icon, so it pushed the icon off centre and
    // the whole column looked misaligned. It now sits on the icon.
    $html = sbRender();

    expect($html)->toContain('data-testid="admin-nav-badge-dot"')
        ->and($html)->toContain('absolute');
});

it('draws the rail dot in the colour the entry declared', function () {
    // The dot used to be a hard-coded `bg-primary-600`, so a count that was red
    // for being overdue turned brand-blue for being narrow — and in a dot that
    // small the hue is the only thing left saying anything. The fill comes from
    // the canonical solid-fill resolver now, which is what the wide badge and
    // every other solid surface already read.
    $html = sbRender();
    $dot = Str::before(Str::after($html, 'data-testid="admin-nav-badge-dot"'), '>');

    expect($dot)->toContain(Badge::getSolidBgClass('danger'))
        ->and($dot)->not->toContain('bg-primary-600');
});

it('resolves one badge colour for every surface that draws it', function () {
    // Three places draw the badge — the pill, the rail dot, the flyout pill —
    // and an entry that declared no colour used to get `gray` in two of them and
    // `primary` in the third.
    app(ResourceRegistry::class)->register(SbPlainBadgeResource::class);

    $html = sbRender();
    $dot = Str::before(Str::after($html, 'data-testid="admin-nav-badge-dot"'), '>');

    expect($dot)->toContain(Badge::getSolidBgClass('danger'))
        ->and(Str::afterLast($html, 'data-testid="admin-nav-badge-dot"'))
        ->toContain(Badge::getSolidBgClass('gray'));
});

it('gives a row with no children a tooltip, and a row with children a menu', function () {
    // The rule SAP Fiori's side navigation states for a collapsed rail: a tooltip
    // with the label pops up on hover, and subitems appear in a popover. They are
    // different objects, and an earlier attempt here made them the same one — so
    // pointing at an entry with no children answered "what is this icon?" with a
    // menu-sized card holding a single word.
    app(ResourceRegistry::class)->register(SbCatalogueResource::class);

    $html = sbRender();

    expect($html)->toContain('data-testid="admin-nav-tip"')
        ->and($html)->toContain('role="tooltip"')
        ->and($html)->toContain('data-testid="admin-nav-flyout"')
        // The entry with children gets the menu and not the tooltip; the one
        // without gets the tooltip and not the menu.
        ->and(Str::before($html, 'data-resource="sb-catalogues"'))->toContain('data-testid="admin-nav-tip"');
});

it('draws a popover child through the same partial as the one under the parent', function () {
    // Not a second hand-written copy of a child row. The copy this replaced
    // re-encoded the url, the active state, the badge colour and the disabled
    // case, and a copy diverges the first time either side is touched. One
    // definition, drawn in two places: the link appears twice, its markup once.
    app(ResourceRegistry::class)->register(SbCatalogueResource::class);

    $html = sbRender();

    expect(substr_count($html, 'href="/admin/sb-catalogues?filter=archived"'))->toBe(2)
        ->and(substr_count($html, 'data-testid="admin-nav-child"'))->toBe(2);
});

it('opens the popover from a parent row instead of re-expanding the whole menu', function () {
    // The old answer to "show me what is under this" in the rail was
    // `toggleRail()` — it undid the collapse the user had just asked for.
    app(ResourceRegistry::class)->register(SbCatalogueResource::class);

    $html = sbRender();

    expect($html)->toContain('wireFlyout(')
        ->and($html)->toContain('x-teleport="body"')
        ->and($html)->toContain('toggle()')
        ->and($html)->toContain('expanded = ! expanded')
        // Escape has to dismiss rather than close: focus goes back to a row that
        // opens on focus, so a plain close is a close and an immediate reopen.
        ->and($html)->toContain('dismiss()')
        ->and($html)->not->toContain('toggleRail()');
});

it('keeps an accessible name on a row whose label the rail hides', function () {
    // The label is `x-show`n away in the rail, and a hidden element carries no
    // accessible name — so without this every entry announced itself as its own
    // badge, or as nothing at all.
    // Matched over the whole opening tag: the two attributes appear in the order
    // the view happens to list them, and a regex anchored on one of them asserts
    // that order rather than the name it is really about.
    expect(sbRender())->toMatch('/<a[^>]*aria-label="[^"]+"[^>]*data-resource="sb-invoices"/s');
});

it('is a drawer on a phone and a column on a desktop, as one element', function () {
    // One element moved by a transform, not two copies behind media queries.
    // Two copies would put every data-testid in the menu into the document
    // twice, and every test and driver that counts entries would be counting
    // double without saying so.
    $html = sbRender();

    expect(substr_count($html, 'data-testid="admin-sidebar"'))->toBe(1)
        ->and($html)->toContain('data-testid="admin-sidebar-overlay"')
        ->and($html)->toContain('data-testid="admin-sidebar-close"')
        ->and($html)->toContain('-translate-x-full');
});

it('carries the logo in a header, so the band it shares with the top bar is one element', function () {
    // The row under the logo and the rule under the top bar are one line across
    // the page, and compact is where a second copy of "4rem" would show: the
    // token drives `h-16`, so an unpinned row lands at 44.8px against a bar
    // pinned to 64. The bar is pinned by *element name* in wire-core's density
    // partial — so the row holds by being that element, not by repeating the
    // number here.
    $html = sbRender();

    expect($html)->toContain('<header class="flex h-16 shrink-0 items-center')
        ->and(substr_count($html, '<header'))->toBe(1);
});
