<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\WireCoreServiceProvider;
use NyonCode\WireSortable\Concerns\WithSortable;
use NyonCode\WireSortable\WireSortableServiceProvider;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * Row reordering × `Table::rowPartials()` — a combination that had no test of
 * any kind before this one (`grep -rn "rowPartials\|wire:partial"
 * packages/sortable/` returned nothing).
 *
 * The bug: the drag handle `<td>` is not server markup. wire-sortable creates it
 * in the browser and prepends it to every `<tr>`, rebuilding it from Livewire's
 * `morph.updated` hook. A row partial is morphed by wire-core's own applier,
 * which drives `Alpine.morph()` directly and never reaches that hook — so an
 * inline save in reorder mode replaced a three-cell row with the server's
 * two-cell one and left that row undraggable. No error, no failing assertion
 * anywhere, and only for rows somebody had edited.
 *
 * wire-core now announces `wire:partials-applied` after a batch is morphed in,
 * and wire-sortable re-adds the missing handles. Firing Livewire's own
 * `morph.updating`/`morph.updated` instead would have been the smaller change
 * and the wrong one: the guard in `onMorphUpdating` `skip()`s the cell being
 * typed in, which is right for a whole-table morph and exactly backwards for a
 * partial, whose entire purpose is to carry that cell's saved value back.
 *
 * What a browser does with it is `workbench/scripts/verify-sortable-partials.mjs`
 * — it is the only thing that can see a handle go missing. What is assertable
 * here is that the server offers the combination at all, and that both halves of
 * the contract are in the shipped bundles: the announcement lives one package up
 * and would otherwise be free to rename itself out from under the listener,
 * which is the same reason `MorphGuardTest` pins wire-table's cell markup.
 */
class SpTask extends Model
{
    protected $table = 'sp_tasks';

    protected $guarded = [];

    public $timestamps = false;
}

class SpHost extends Component
{
    use WithSortable;
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(SpTask::class)
            ->alwaysReorderable('sort_order')
            ->rowPartials()
            ->columns([
                TextInputColumn::make('title'),
                TextColumn::make('owner_name'),
            ])
            ->paginated(false);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('sp_tasks', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('owner_name')->nullable();
        $table->integer('sort_order')->default(0);
    });

    SpTask::insert([
        ['title' => 'First', 'owner_name' => 'Ada', 'sort_order' => 1],
        ['title' => 'Second', 'owner_name' => 'Grace', 'sort_order' => 2],
    ]);
});

afterEach(function () {
    Schema::dropIfExists('sp_tasks');
});

it('offers row partials on a reordering table rather than silently disabling them', function () {
    // The cheapest fix for the bug above was for `usesRowPartials()` to return
    // false while reordering, which would give up partial rendering exactly where
    // the table is largest — reorder mode drops pagination. It was not taken, so
    // the anchors have to be here.
    $html = Livewire::test(SpHost::class)->html();

    expect($html)->toContain('wire:partial="row-1"')
        ->and($html)->toContain('wire:partial="row-2"');
});

it('answers an inline save with the row alone while reordering', function () {
    // The response shape the browser bug needs in order to happen at all: no
    // `html` effect, so Livewire's own morph never runs and never fires the hook
    // the handles are rebuilt from.
    $component = Livewire::test(SpHost::class)
        ->call('updateTableCell', 1, 'title', 'Renamed', null);

    $effects = $component->effects;

    expect($effects['wirePartials'] ?? null)->not->toBeNull()
        ->and(array_keys($effects['wirePartials']))->toBe(['row-1'])
        ->and($effects['html'] ?? null)->toBeNull()
        ->and(SpTask::find(1)->title)->toBe('Renamed');
});

it('ships a drag controller that listens for the announcement', function () {
    // Fails if `resources/js` was edited without `npm run build:sortable-assets`.
    $bundle = WireSortableServiceProvider::ASSETS_PATH.'/wire-sortable.js';

    expect(is_file($bundle))->toBeTrue()
        ->and(file_get_contents($bundle))
        ->toContain('wire:partials-applied')
        ->toContain('onPartialsApplied');
});

it('ships a core bundle that makes the announcement', function () {
    // The other half of the contract, and the half that lives in another
    // package: a rename here is a silent no-op for the listener above.
    $bundle = WireCoreServiceProvider::ASSETS_PATH.'/wire-core-dropdown.js';

    expect(is_file($bundle))->toBeTrue()
        ->and(file_get_contents($bundle))->toContain('wire:partials-applied');
});
