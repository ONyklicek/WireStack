<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireSortable\Concerns\WithSortable;
use NyonCode\WireSortable\WireSortableServiceProvider;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * The drag controller's morph guards, and the contract they are written
 * against.
 *
 * wireSortable stops a Livewire morph in two cases: a drag in progress, and the
 * cell the user is typing in. Both guards used to ask "is ANY input inside the
 * table focused?" — and they asked it of every node from the sortable wrapper
 * down. `skip()` takes the whole subtree with it and `contains()` is inclusive,
 * so the answer came back yes at the wrapper and the morph never entered the
 * table: the search box is an input inside the table, so typing in it silenced
 * exactly the render it had asked for. Search stopped working on every
 * reorderable table, silently.
 *
 * What the browser does with a morph is verified by
 * workbench/scripts/verify-sortable-morph.mjs. What can be asserted here is the
 * shape of the guard, that the shipped bundle carries it, and the markup
 * contract it targets — which lives in wire-table, one package down, and would
 * otherwise be free to rename itself out from under the guard without anything
 * failing.
 */
class MgTask extends Model
{
    protected $table = 'mg_tasks';

    protected $guarded = [];

    public $timestamps = false;
}

/** Column-reorderable — which alone renders the wrapper — with an editable cell. */
class MgHost extends Component
{
    use WithSortable;
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(MgTask::class)
            ->columnReorderable()
            ->columns([
                TextColumn::make('title')->searchable(),
                TextInputColumn::make('owner_name'),
            ])
            ->paginated(false);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

/** The same table with neither kind of reordering asked for. */
class MgPlainHost extends Component
{
    use WithSortable;
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(MgTask::class)
            ->columns([
                TextColumn::make('title')->searchable(),
                TextInputColumn::make('owner_name'),
            ])
            ->paginated(false);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

function mgSource(): string
{
    return file_get_contents(
        dirname(WireSortableServiceProvider::ASSETS_PATH).'/resources/js/sortable.js'
    );
}

beforeEach(function () {
    Schema::create('mg_tasks', function (Blueprint $t) {
        $t->id();
        $t->string('title')->nullable();
        $t->string('owner_name')->nullable();
        $t->integer('sort_order')->nullable();
    });

    MgTask::create(['title' => 'Ada', 'owner_name' => 'Amelia', 'sort_order' => 1]);
});

afterEach(fn () => Schema::dropIfExists('mg_tasks'));

test('the morph guard names the one cell it protects instead of the whole table', function () {
    // The regression, as source: a guard keyed on the focused element's TAG
    // NAME matches the search box, the filter inputs and the per-page select as
    // readily as an editable cell — and it was answered for the wrapper, whose
    // subtree is the table.
    expect(mgSource())
        ->toContain("active.closest('[data-record-key][data-column-name]')")
        // Exactly that node, not "somewhere below it": a skip on the wrapper is
        // a skip on everything.
        ->toContain('if (cell && el === cell) skip();')
        ->not->toContain("active.tagName === 'INPUT'")
        ->not->toContain("active.tagName === 'TEXTAREA'")
        ->not->toContain("active.tagName === 'SELECT'");
});

test('a drag in progress still stops the morph outright', function () {
    // The other half of the guard is untouched and must stay that way: a drag
    // moves rows the server render knows nothing about, so the whole subtree
    // has to be left alone until the drop lands.
    expect(mgSource())->toMatch('/if \(this\.isDragging\) \{\s*skip\(\);\s*return;\s*\}/');
});

test('re-initialisation is deferred while a cell is being edited, and coalesced otherwise', function () {
    // setup() rebuilds the drag-handle <td> cells, which drops focus — so not
    // during an edit. And `morph.updated` fires for every patched node, so the
    // rebuild is queued once per morph rather than once per node.
    expect(mgSource())
        ->toContain('if (this.editingCell()) return;')
        ->toContain('if (this._setupQueued) return;')
        ->toMatch('/scheduleSetup\(\) \{/');
});

test('the morph hooks are installed once for the page, not once per table', function () {
    $source = mgSource();

    // `Livewire.hook()` has no off switch, so a pair registered from init()
    // stacks a fresh pair every time a controller initialises — a second
    // reorderable table, a wire:navigate, a table inside a lazily loaded modal
    // — and every stacked copy goes on running against a component that no
    // longer exists. wire-table's record-actions guard and the fill handle's
    // both solve this at module scope; this is the same shape.
    expect($source)
        ->toContain('let morphGuardsInstalled = false;')
        ->toContain('if (morphGuardsInstalled || !window.Livewire) return;')
        // Registered by wrapper element: Alpine calls destroy() with a merge
        // proxy of the scope rather than the instance init() saw, so a
        // controller cannot remove itself by identity — and keying by the
        // element means a replacement takes over the entry instead of joining
        // it, even if destroy() never runs at all.
        ->toContain('controllers.set(this.$root, this);')
        ->toContain('controllers.delete(this.$root);')
        ->and(substr_count($source, 'Livewire.hook('))->toBe(2);

    // And none of them from init(), which is what stacked them.
    preg_match('/\n        init\(\) \{(.*?)\n        \},/s', $source, $matches);

    expect($matches[1] ?? '')->not->toBeEmpty()
        ->and($matches[1])->not->toContain('hook(');
});

test('the shipped bundle carries the narrowed guard', function () {
    // Fails if the dist drifts from source (needs `npm run build:sortable-assets`).
    expect(file_get_contents(WireSortableServiceProvider::ASSETS_PATH.'/wire-sortable.js'))
        ->toContain('data-record-key')
        ->toContain('data-column-name');
});

test('an editable cell really does carry the attribute pair the guard looks for', function () {
    // The cross-package half of the contract: the selector lives in
    // wire-sortable, the markup in wire-table. If the cell stops rendering the
    // pair, the guard silently protects nothing and an edit in flight is
    // overwritten by the next poll tick — with no error anywhere.
    $html = Livewire::test(MgHost::class)->html();

    expect($html)
        ->toContain('wire-sortable-wrapper')
        ->toMatch('/data-record-key="[^"]+"\s+data-column-name="owner_name"/');
});

test('the wrapper carrying the guards comes from reordering, not from the trait', function () {
    // Scope of the bug, in markup: the wrapper — and with it the global morph
    // hooks — is rendered for `reorderable()` OR `columnReorderable()`, so both
    // were equally affected. `WithSortable` alone renders no wrapper and never
    // was.
    expect(Livewire::test(MgHost::class)->html())
        ->toContain('x-data="wireSortable(')
        ->and(Livewire::test(MgPlainHost::class)->html())
        ->not->toContain('wire-sortable-wrapper');
});
