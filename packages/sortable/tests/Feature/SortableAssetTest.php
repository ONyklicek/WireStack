<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireSortable\Concerns\WithSortable;
use NyonCode\WireSortable\WireSortableServiceProvider;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/*
 * Delivery of the drag controller: the bundle exists, the package serves it
 * without a build step or a CDN, and the table partial injects it through
 * Livewire's @assets so it survives a morph.
 */

class SaTask extends Model
{
    protected $table = 'sa_tasks';

    protected $guarded = [];

    public $timestamps = false;
}

class SaHost extends Component
{
    use WithSortable;
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(SaTask::class)
            ->columnReorderable()
            ->reorderable()
            ->columns([TextColumn::make('title')])
            ->paginated(false);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

/**
 * A whole page carrying the reorderable table, as the browser receives it.
 *
 * Livewire hoists `@assets` out of the component markup and injects it into the
 * document head of the HTML response, so only a real request shows what was
 * delivered — `assertSee()` on a component test never sees it.
 */
function saPageHtml(): string
{
    // Livewire tracks "this asset already ran" in process-wide statics, and the
    // whole suite shares one process: without this, the second page in a run
    // skips the @assets block and gets the first page's markup injected instead.
    Livewire::flushState();

    Livewire::component('sa-host', SaHost::class);

    Route::get('/sa-page', fn () => Blade::render(
        '<html><head></head><body><livewire:sa-host /></body></html>'
    ));

    return test()->get('/sa-page')->assertOk()->getContent();
}

beforeEach(function () {
    Schema::create('sa_tasks', function (Blueprint $t) {
        $t->id();
        $t->string('title')->nullable();
        $t->integer('sort_order')->nullable();
    });

    SaTask::create(['title' => 'Ada', 'sort_order' => 1]);
});

afterEach(fn () => Schema::dropIfExists('sa_tasks'));

test('the sortable bundle is shipped inside the package', function () {
    $bundle = WireSortableServiceProvider::ASSETS_PATH.'/wire-sortable.js';

    expect(is_file($bundle))->toBeTrue()
        ->and(file_get_contents($bundle))->toContain('wireSortable');
});

test('the package serves the sortable bundle without publishing or a build step', function () {
    $response = $this->get('/wire-sortable/assets/sortable.js');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('javascript');
    expect($response->baseResponse)->toBeInstanceOf(BinaryFileResponse::class)
        ->and(file_get_contents($response->baseResponse->getFile()->getPathname()))->toContain('wireSortable');
});

test('the named asset route resolves', function () {
    expect(route('wire-sortable.asset', ['asset' => 'sortable'], false))
        ->toBe('/wire-sortable/assets/sortable.js');
});

test('unknown assets return 404', function () {
    $this->get('/wire-sortable/assets/does-not-exist.js')->assertNotFound();
});

test('SortableJS is compiled into the bundle instead of fetched from a CDN', function () {
    $bundle = WireSortableServiceProvider::ASSETS_PATH.'/wire-sortable.js';

    // The drag library itself must be inside the file: an app that runs offline
    // or under a strict CSP has no way to reach cdn.jsdelivr.net. Fails if the
    // dist drifts from source (needs `npm run build:sortable-assets`).
    expect(file_get_contents($bundle))
        ->toContain('Sortable')
        ->toContain('sortable-fallback')
        // Markers of the real library, not just the options we pass it.
        ->toContain('revertOnSpill')
        ->toContain('swapThreshold')
        ->toContain('AutoScroll')
        ->not->toContain('cdn.jsdelivr.net');
});

test('the sortable source registers unconditionally so it survives a wire:navigate', function () {
    $source = file_get_contents(
        dirname(WireSortableServiceProvider::ASSETS_PATH).'/resources/js/sortable.js'
    );

    // `alpine:init` fires exactly once per document, so a bundle arriving with
    // the first reorderable table after a `wire:navigate` must not depend on it:
    // the listener is only the cold-load fallback for an idempotent registrar
    // that runs immediately when Alpine is already up.
    expect($source)
        ->not->toMatch("/addEventListener\('alpine:init',\s*\(\s*\)\s*=>/")
        ->toMatch("/if \(window\.Alpine\) \{\s*(?:\/\/[^\n]*\n\s*)*register\(\);/")
        ->toMatch("/document\.addEventListener\('alpine:init', register\);/")
        ->toContain('if (registered || !window.Alpine) return;')
        // window.Alpine, not the bare global the inline script used to read.
        ->not->toMatch('/(?<!window\.)\bAlpine\.data\(/');
});

test('the shipped sortable bundle carries the SPA-proof registration', function () {
    $bundle = WireSortableServiceProvider::ASSETS_PATH.'/wire-sortable.js';

    // Minified shape of the idiom above:
    //   window.Alpine?g():document.addEventListener("alpine:init",g)
    //   function g(){y||!window.Alpine||(y=!0, …)}
    // Fails if the dist drifts from source (needs `npm run build:sortable-assets`).
    expect(file_get_contents($bundle))
        ->toMatch('/window\.Alpine\?\w+\(\):document\.addEventListener\("alpine:init",\w+\)/')
        ->toMatch('/\w+\|\|!window\.Alpine\|\|\(\w+=!0,/');
});

test('the shipped bundle keeps the morph guards and the name-matched column mirror', function () {
    $bundle = WireSortableServiceProvider::ASSETS_PATH.'/wire-sortable.js';

    // A drag must survive a Livewire morph (skip while dragging or while an
    // input inside the table has focus), the cell widths are locked for the
    // duration, polling is paused, and the body columns are mirrored by
    // data-column NAME — never by the header index, which does not line up with
    // a body row. Fails if the dist drifts from source.
    expect(file_get_contents($bundle))
        ->toContain('morph.updating')
        ->toContain('morph.updated')
        ->toContain('data-column')
        ->toContain('data-row-key')
        ->toContain('reorderRows')
        ->toContain('reorderColumns')
        ->toContain('wire:poll');
});

test('the table injects the bundle through @assets with a cache-buster', function () {
    expect(saPageHtml())
        ->toContain('/wire-sortable/assets/sortable.js?id=')
        // The CSS rides along: Tailwind never sees these JS-applied classes.
        ->toContain('.wire-sortable-handle')
        ->toContain('.wire-sortable-ghost');
});

test('no CDN script is emitted by default', function () {
    expect(config('wire-sortable.sortablejs_cdn'))->toBeNull()
        ->and(saPageHtml())->not->toContain('cdn.jsdelivr.net');
});

test('an application that still sets sortablejs_cdn keeps getting its CDN copy', function () {
    // Backwards compatibility: the key stays honoured, in addition to (not
    // instead of) the bundle the controller itself imports.
    config()->set('wire-sortable.sortablejs_cdn', 'https://cdn.example.test/Sortable.min.js');

    expect(saPageHtml())
        ->toContain('https://cdn.example.test/Sortable.min.js')
        ->toContain('/wire-sortable/assets/sortable.js');
});
