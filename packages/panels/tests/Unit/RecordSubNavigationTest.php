<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireCore\Foundation\Routing\RoutePage;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Infolists\Contracts\ProvidesResourceInfolist;
use NyonCode\WireCore\Infolists\Infolist;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;
use NyonCode\WirePanels\Resources\Navigation\RecordPages;
use NyonCode\WirePanels\Resources\Pages\ListPage;
use NyonCode\WirePanels\Resources\Pages\ViewPage;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;

/*
 * The other pages of the record you are looking at.
 *
 * The way across, which nothing else in the framework could offer: the menu
 * knows resources rather than records, and breadcrumbs lead up. Until this
 * existed the only route from an edit screen to the read-only one was back
 * through the list.
 *
 * Nothing new is declared for it. `pages()` is already the list and the router
 * already knows which of those take a record, so what is pinned here is that the
 * derivation stays honest at the edges: a page kind with no record is not a tab,
 * a page the reader may not open is not a tab, and a single tab is not a tab bar.
 */

class SnRow extends Model
{
    protected $table = 'sn_rows';

    protected $guarded = [];

    public $timestamps = false;
}

class SnResource implements DescribesResource, ProvidesPages, ProvidesResourceInfolist, ProvidesResourceTable
{
    use DescribesRecords;

    public static function pages(): array
    {
        return [
            // Neither of these is about one record, so neither is a tab.
            'index' => SnListRows::class,
            'create' => SnViewRow::class,

            'view' => SnViewRow::class,
            'edit' => RoutePage::make(SnViewRow::class)->sort(10),

            // A page of the resource's own, a tab because its URI says so.
            'history' => RoutePage::make(SnViewRow::class)->uri('{record}/history')->icon('outline:clock')->sort(30),

            // Also its own, and not a tab: no record in the URI.
            'archive' => RoutePage::make(SnListRows::class),

            // A tab only for whoever may open it — the same declaration the
            // route is guarded by.
            'audit' => RoutePage::make(SnViewRow::class)->uri('{record}/audit')->permission('sn.audit')->label('Trail'),
        ];
    }

    public static function modelClass(): ?string
    {
        return SnRow::class;
    }

    public static function key(): string
    {
        return 'sn-rows';
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([TextEntry::make('name')]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')]);
    }
}

class SnListRows extends ListPage
{
    protected static ?string $resource = SnResource::class;
}

class SnViewRow extends ViewPage
{
    protected static ?string $resource = SnResource::class;
}

/** A resource with exactly one record page — one tab is no tab bar. */
class SnLoneResource implements DescribesResource, ProvidesPages, ProvidesResourceInfolist
{
    use DescribesRecords;

    public static function pages(): array
    {
        return ['index' => SnListRows::class, 'view' => SnLoneView::class];
    }

    public static function modelClass(): ?string
    {
        return SnRow::class;
    }

    public static function key(): string
    {
        return 'sn-lone';
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([TextEntry::make('name')]);
    }
}

class SnLoneView extends ViewPage
{
    protected static ?string $resource = SnLoneResource::class;
}

beforeEach(function () {
    Schema::create('sn_rows', function (Blueprint $t) {
        $t->id();
        $t->string('name');
    });

    SnRow::insert([['id' => 1, 'name' => 'One']]);

    app(ResourceRegistry::class)->register(SnResource::class);
    app(ResourceRegistry::class)->register(SnLoneResource::class);

    // A full-page component needs a layout and the framework deliberately does
    // not supply one — see BreadcrumbsTest for the same two lines.
    View::addLocation(__DIR__.'/../fixtures/views');
    config()->set('livewire.component_layout', 'plain-layout');
});

/** What an application that routes its resources has done. Per test, so that the one which routes nothing simply does not call it. */
function snRoutes(?string $zone = null): void
{
    $group = Route::middleware('web');

    if ($zone !== null) {
        $group = $group->name($zone.'.')->prefix($zone);
    }

    $group->group(fn () => Route::wireResources(only: ['sn-rows', 'sn-lone']));
}

afterEach(function () {
    Schema::dropIfExists('sn_rows');
});

it('lists only the pages that are about one record', function () {
    // `index` and `create` show no record, and `archive` declared a URI without
    // one however ordinary its name looks. The URI is what the router builds a
    // parameter from, so the URI is what answers.
    expect(array_keys(RecordPages::of(SnResource::class)))
        ->toEqualCanonicalizing(['view', 'edit', 'history', 'audit']);
});

it('orders them by what they declared, and keeps declaration order otherwise', function () {
    // `view` and `audit` both declare no order and keep the order they were
    // written in — which is what makes `sort()` optional rather than mandatory.
    // `edit` (10) and `history` (30) asked, and go after them.
    $pages = RecordPages::of(SnResource::class);

    expect(array_keys($pages))->toBe(['view', 'audit', 'edit', 'history'])
        ->and($pages['history']->getIcon())->toBe('outline:clock');
});

it('names a page that named itself nothing', function () {
    $pages = RecordPages::of(SnResource::class);

    expect($pages['view']->getLabel())->toBe('View')
        ->and($pages['edit']->getLabel())->toBe('Edit')
        // No translation for a key the application invented: humanised, which is
        // a readable tab rather than `history`.
        ->and($pages['history']->getLabel())->toBe('History')
        // And what the declaration said, untouched.
        ->and($pages['audit']->getLabel())->toBe('Trail');
});

it('names them in the locale the page is rendered in', function () {
    app()->setLocale('cs');

    expect(RecordPages::of(SnResource::class)['view']->getLabel())->toBe('Detail');
});

it('leaves the declaration alone so a second reading answers the same', function () {
    // `pages()` is free to hand back the same objects, and a naming that filled
    // them in place would make the second call differ from the first.
    RecordPages::of(SnResource::class);

    expect(RecordPages::of(SnResource::class)['view']->getLabel())->toBe('View');
});

it('answers nothing for a class that declares no pages at all', function () {
    expect(RecordPages::of(null))->toBe([])
        ->and(RecordPages::of(SnRow::class))->toBe([]);
});

it('draws a tab per record page on the page itself', function () {
    snRoutes();

    $html = Livewire::test(SnViewRow::class, ['record' => 1])->html();

    expect($html)->toContain('data-testid="panels-sub-nav"')
        ->and($html)->toContain('data-page="view"')
        ->and($html)->toContain('data-page="edit"')
        ->and($html)->toContain('data-page="history"')
        ->and($html)->toContain(route('wire.sn-rows.edit', 1))
        // The list and the create screen are not tabs, and neither is a page
        // whose URI takes no record.
        ->and($html)->not->toContain('data-page="index"')
        ->and($html)->not->toContain('data-page="create"')
        ->and($html)->not->toContain('data-page="archive"');
});

it('leaves out a page this reader may not open', function () {
    snRoutes();

    // The same ability the route is guarded with, asked before the link is
    // drawn: a tab that lands on a 403 is strictly worse than no tab.
    expect(Livewire::test(SnViewRow::class, ['record' => 1])->html())
        ->not->toContain('data-page="audit"');

    Gate::define('sn.audit', fn ($user = null): bool => true);

    expect(Livewire::test(SnViewRow::class, ['record' => 1])->html())
        ->toContain('data-page="audit"');
});

it('draws nothing at all when a record has only one page', function () {
    snRoutes();

    // One tab is the page's own heading written a second time — the rule the
    // breadcrumb trail already follows for a trail of length one.
    expect(Livewire::test(SnLoneView::class, ['record' => 1])->html())
        ->not->toContain('data-testid="panels-sub-nav"');
});

it('draws nothing on the pages that show no record', function () {
    snRoutes();

    expect(Livewire::test(SnListRows::class)->html())
        ->not->toContain('data-testid="panels-sub-nav"');
});

it('draws nothing while the application routes none of it', function () {
    // A tab whose URL cannot be built is dropped rather than drawn dead: unlike
    // a menu row, which honestly says "registered, not routed here", a tab that
    // goes nowhere is just broken. Nothing calls snRoutes() here — that is the
    // whole of the setup.
    expect(Livewire::test(SnViewRow::class, ['record' => 1])->html())
        ->not->toContain('data-testid="panels-sub-nav"');
});

it('marks the tab whose page is being rendered', function () {
    snRoutes();

    $html = $this->get(route('wire.sn-rows.view', 1))->assertOk()->getContent();

    expect($html)->toContain('data-page="view"')
        // By page kind, off the route name — not by comparing URL strings, where
        // a trailing slash or a query string decides whether a tab lights up.
        ->and($html)->toMatch('/data-page="view"[^>]*data-active="true"|data-active="true"[^>]*data-page="view"/')
        ->and($html)->toContain('aria-current="page"');
});

it('keeps the current page across a Livewire update', function () {
    // ADR 0027: during an update the route name is `livewire.update`, so a tab
    // bar that re-derived its kind would mark the current tab on the first paint
    // and nothing afterwards.
    snRoutes();

    $component = Livewire::test(SnViewRow::class, ['record' => 1])->set('currentPage', 'edit');

    expect($component->html())->toContain('data-page="edit"')
        ->and($component->get('currentPage'))->toBe('edit');
});

it('stays inside the zone the page was opened in', function () {
    // The same resource, mounted twice, has two sets of URLs — and a page knows
    // which mount point it was opened in because it read it once (ADR 0027).
    snRoutes();
    snRoutes('admin');

    $component = Livewire::test(SnViewRow::class, ['record' => 1])->set('breadcrumbZone', 'admin');

    expect($component->html())->toContain('/admin/sn-rows/1/edit');
});
