<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireCore\Foundation\Routing\RoutePage;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;
use NyonCode\WirePanels\Resources\Pages\ListPage;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;

/*
 * Where a resource's pages are, asked by a page rather than by the resource.
 *
 * Two things a list needs before it can link to anything, and neither of them
 * is the resource's to answer:
 *
 *   - **the URL**, which depends on the zone this list was opened in. The same
 *     resource mounted twice has two sets of URLs, and a resource cannot know
 *     which mount point it is being drawn in. The page read it in `mount()` and
 *     kept it, because during a Livewire update `Route::currentRouteName()` is
 *     `livewire.update` — and a table re-renders on every search keystroke.
 *   - **the ability**, which the resource *does* declare, on the page. Derived
 *     rather than restated: `ResourceRoutes` turns the same declaration into
 *     `can:` middleware, so a button hidden by one and a route guarded by the
 *     other cannot drift apart.
 */

class PpRow extends Model
{
    protected $table = 'pp_rows';

    protected $guarded = [];

    public $timestamps = false;
}

class PpResource implements DescribesResource, ProvidesPages, ProvidesResourceTable
{
    use DescribesRecords;

    public static function pages(): array
    {
        return [
            'index' => PpListRows::class,
            // One guarded, one not: a resource is free to declare either, and
            // the button has to follow whichever it declared.
            'edit' => RoutePage::make(PpEditRow::class)->permission('pp.update'),
            'view' => PpViewRow::class,
        ];
    }

    public static function modelClass(): ?string
    {
        return PpRow::class;
    }

    public static function key(): string
    {
        return 'pp-rows';
    }

    public function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')]);
    }
}

class PpListRows extends ListPage
{
    protected static ?string $resource = PpResource::class;

    /** @return array{url: string|null, permission: string|null} */
    public function probe(string $page, ?PpRow $record = null): array
    {
        return [
            'url' => $this->pageUrl($page, $record),
            'permission' => $this->pagePermission($page),
        ];
    }
}

class PpEditRow extends PpListRows {}

class PpViewRow extends PpListRows {}

beforeEach(function () {
    Schema::create('pp_rows', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    app(ResourceRegistry::class)->register(PpResource::class);
});

it('answers with the URL of a record page', function () {
    Route::middleware('web')->group(fn () => Route::wireResources());

    $row = PpRow::query()->create(['name' => 'One']);

    $probe = Livewire::test(PpListRows::class)->instance()->probe('edit', $row);

    expect($probe['url'])->toContain('pp-rows/'.$row->getKey().'/edit');
});

it('answers null for a page this application does not route', function () {
    // Null is a real answer: a list with no link beats a link that 404s.
    $row = PpRow::query()->create(['name' => 'One']);

    expect(Livewire::test(PpListRows::class)->instance()->probe('edit', $row)['url'])->toBeNull();
});

it('answers null for a page kind the resource never declared', function () {
    Route::middleware('web')->group(fn () => Route::wireResources());

    expect(Livewire::test(PpListRows::class)->instance()->probe('nonsense')['url'])->toBeNull();
});

it('carries the zone the page was opened in', function () {
    // The whole reason this is not the resource's answer: the same resource,
    // mounted twice, has two sets of URLs.
    Route::name('admin.')->prefix('admin')->group(fn () => Route::wireResources());

    $row = PpRow::query()->create(['name' => 'One']);

    $component = Livewire::test(PpListRows::class);
    $component->set('breadcrumbZone', 'admin');

    expect($component->instance()->probe('edit', $row)['url'])->toContain('/admin/pp-rows/');
});

it('reads the ability off the same declaration the route is guarded by', function () {
    $probe = Livewire::test(PpListRows::class)->instance();

    expect($probe->probe('edit')['permission'])->toBe('pp.update')
        // Declared as a bare class string: the route requires nothing, and
        // neither does the button.
        ->and($probe->probe('view')['permission'])->toBeNull()
        ->and($probe->probe('nonsense')['permission'])->toBeNull();
});

/** A list page that names no resource at all — the standalone path (ADR 0020). */
class PpStandaloneList extends PpListRows
{
    protected static ?string $resource = null;

    public function table(Table $table): Table
    {
        return $table->model(PpRow::class)->columns([TextColumn::make('name')]);
    }
}

it('answers nothing for a page that names no resource', function () {
    // Both paths are first class: a page may write its own table and belong to
    // no resource, and asking such a page where "its" record pages are has one
    // honest answer.
    Route::middleware('web')->group(fn () => Route::wireResources());

    $probe = Livewire::test(PpStandaloneList::class)->instance();

    expect($probe->probe('edit', PpRow::query()->create(['name' => 'One']))['url'])->toBeNull()
        ->and($probe->probe('edit')['permission'])->toBeNull();
});
