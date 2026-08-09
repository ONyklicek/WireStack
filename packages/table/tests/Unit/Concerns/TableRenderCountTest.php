<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/**
 * Render-count fuse (see architecture/plans/render-engine-htmlable-first.md §1).
 *
 * The whole render engine's cost is `view()->render()` calls, and the anti-pattern
 * it guards against is per-row markup: a cell/action/`@include` that runs once per
 * row turns into N×View. Nothing else in the suite counts view renders, so a PR
 * that drops an `@include` back into the row loop stays green everywhere else and
 * only shows up on a customer's Debugbar. This test is that missing tripwire.
 *
 * How it counts: a wildcard view composer (`*`) fires once for every view instance
 * rendered in a request — including every `@include` and every `<x-…>` component,
 * because `@include` compiles to `make()->render()` and `View::renderContents()`
 * calls `callComposer()` on each render. So the counter sees the true per-row cost,
 * not just the column-cell path.
 */

// ─── Test Model + Component ──────────────────────────────────────────────────

class RcRow extends Model
{
    protected $table = 'rc_rows';

    protected $guarded = [];

    public function kids()
    {
        return $this->hasMany(RcKid::class, 'row_id');
    }
}

class RcKid extends Model
{
    protected $table = 'rc_kids';

    protected $guarded = [];
}

class RcComponent extends Component
{
    use WithTable;

    public int $cols = 2;

    public bool $copyable = false;

    public bool $editable = false;

    public bool $selectable = false;

    public bool $contextMenu = false;

    public bool $subRows = false;

    public bool $grouped = false;

    public int $actions = 0;

    public function mount(
        int $cols = 2,
        bool $copyable = false,
        bool $editable = false,
        bool $selectable = false,
        bool $contextMenu = false,
        bool $subRows = false,
        bool $grouped = false,
        int $actions = 0,
    ): void {
        $this->cols = $cols;
        $this->copyable = $copyable;
        $this->editable = $editable;
        $this->selectable = $selectable;
        $this->contextMenu = $contextMenu;
        $this->subRows = $subRows;
        $this->grouped = $grouped;
        $this->actions = $actions;
    }

    public function table(Table $table): Table
    {
        $columns = [];

        for ($i = 0; $i < $this->cols; $i++) {
            // An inline-edit column is the Rule 2 boundary: its per-record variation
            // is structural (input value, wire:key, per-record Alpine commit config),
            // so it renders a view per cell and is the fuse's negative control.
            if ($this->editable) {
                $columns[] = TextInputColumn::make('name');

                continue;
            }

            // displayUsing keeps the cell off any DB attribute, so a plain cell is
            // exactly one view render (tables.columns.text) with no icon/url/copy.
            $column = TextColumn::make('c'.$i)->displayUsing(fn () => 'v');

            if ($this->copyable) {
                $column->copyable();
            }

            $columns[] = $column;
        }

        if ($this->contextMenu) {
            $table->gestures()->rowContextMenu([Action::make('edit')->label('Edit')]);
        }

        if ($this->subRows) {
            $table->subRows('kids')->subRowColumns([TextColumn::make('label')]);
        }

        // `name` is unique per row, so this is one group per row — the worst case, and
        // the shape a table grouped by a timestamp actually has.
        if ($this->grouped) {
            $table->groupBy('name');
        }

        if ($this->actions > 0) {
            $table->actions(array_map(
                fn (int $i) => Action::make('a'.$i)->label('A'.$i),
                range(1, $this->actions),
            ));
        }

        return $table
            ->model(RcRow::class)
            ->paginated(false)
            ->selectable($this->selectable)
            ->columns($columns);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

/** Identical tables either side of ->fillHandle(), so the delta is the fill markup. */
class RcFillComponent extends Component
{
    use WithTable;

    public bool $fill = false;

    public function mount(bool $fill = false): void
    {
        $this->fill = $fill;
    }

    public function table(Table $table): Table
    {
        return $table
            ->model(RcRow::class)
            ->paginated(false)
            ->fillHandle($this->fill)
            ->columns([TextInputColumn::make('name')]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Count every view render that happens while $render runs.
 *
 * The composer is registered fresh each call and writes to its own counter, so
 * calling this twice in one test does not cross-contaminate the returned values.
 */
function rcRenderCount(Closure $render): int
{
    $count = 0;

    View::composer('*', function () use (&$count): void {
        $count++;
    });

    $render();

    return $count;
}

function rcSeed(int $rows): void
{
    $now = now();
    $first = (int) (RcRow::max('id') ?? 0);

    RcRow::insert(array_map(fn (int $i) => [
        'name' => 'row-'.$i,
        'created_at' => $now,
        'updated_at' => $now,
    ], range(1, $rows)));

    // One child each, so every row renders the expander's toggle shape rather than
    // the empty one — the shape that used to cost an @include per row.
    RcKid::insert(array_map(fn (int $i) => [
        'row_id' => $first + $i,
        'label' => 'k',
        'created_at' => $now,
        'updated_at' => $now,
    ], range(1, $rows)));
}

function rcRender(
    int $cols = 2,
    bool $copyable = false,
    bool $editable = false,
    bool $selectable = false,
    bool $contextMenu = false,
    bool $subRows = false,
    bool $grouped = false,
    int $actions = 0,
): Closure {
    return fn () => Livewire::test(RcComponent::class, [
        'cols' => $cols,
        'copyable' => $copyable,
        'editable' => $editable,
        'selectable' => $selectable,
        'contextMenu' => $contextMenu,
        'subRows' => $subRows,
        'grouped' => $grouped,
        'actions' => $actions,
    ])->html();
}

function rcFillRender(bool $fill): Closure
{
    return fn () => Livewire::test(RcFillComponent::class, ['fill' => $fill])->html();
}

// ─── Setup ───────────────────────────────────────────────────────────────────

beforeEach(function () {
    Schema::create('rc_rows', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('rc_kids', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('row_id');
        $table->string('label');
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('rc_rows');
    Schema::dropIfExists('rc_kids');
});

// ─── The fuse ────────────────────────────────────────────────────────────────

it('adds zero view renders per row for skeletonable text cells (§7)', function () {
    // The row loop calls renderCellFast: a plain TextColumn resolves its view ONCE
    // into a per-column skeleton and splices state per row. So growing the row count
    // adds NO cell view renders — the whole point of §7.
    rcSeed(4);
    $small = rcRenderCount(rcRender(3));

    rcSeed(8); // 4 → 12 rows
    $large = rcRenderCount(rcRender(3));

    // Zero per-row growth: the fixed chrome + 3 column skeletons are built regardless
    // of row count, and the 8 extra rows splice strings (no view render).
    expect($large - $small)->toBe(0);
});

it('makes column count a one-time skeleton cost, not O(rows × cols) (§7)', function () {
    // Two extra skeletonable columns add a fixed number of skeleton renders that is
    // the SAME at any row count — proving the cost is O(columns), not O(rows×cols).
    rcSeed(5);
    $deltaFew = rcRenderCount(rcRender(4)) - rcRenderCount(rcRender(2));

    rcSeed(5); // 5 → 10 rows
    $deltaMany = rcRenderCount(rcRender(4)) - rcRenderCount(rcRender(2));

    expect($deltaMany)->toBe($deltaFew);
});

it('still catches per-row renders in non-skeletonable cells (the fuse lives)', function () {
    // The fuse is only worth anything if it can still trip, so it needs a cell that
    // genuinely renders per row. That is the inline-edit column, and deliberately so:
    // its per-record variation is STRUCTURAL (input value, wire:key, the per-record
    // Alpine commit config), which a value-splice cannot express — the Rule 2
    // boundary documented in render-engine-htmlable-first.md §7.
    //
    // It used to be the copyable column. That stopped working as a control when the
    // copy value became a slot, which is the point of the assertion below it.
    rcSeed(4);
    $plainSmall = rcRenderCount(rcRender(2, editable: false));
    $editSmall = rcRenderCount(rcRender(2, editable: true));

    rcSeed(8); // 4 → 12 rows
    $plainLarge = rcRenderCount(rcRender(2, editable: false));
    $editLarge = rcRenderCount(rcRender(2, editable: true));

    expect(($plainLarge - $plainSmall) / 8)->toBe(0)              // skeletonised → 0/row
        ->and(($editLarge - $editSmall) / 8)->toBeGreaterThan(0); // per-cell render
});

it('adds zero view renders per row for a copyable cell too (§7 multi-slot)', function () {
    // A copyable cell carried a per-record copy value, so it fell back to the full
    // per-cell render and cost a measured 33× a splice. The copy value is a slot now,
    // so its per-row slope is the plain cell's: zero.
    rcSeed(4);
    $small = rcRenderCount(rcRender(2, copyable: true));

    rcSeed(8); // 4 → 12 rows
    $large = rcRenderCount(rcRender(2, copyable: true));

    expect($large - $small)->toBe(0);
});

it('renders the selection cell partial once per table, never once per row', function () {
    // The selection cell moved from inline markup in the row loop to its own partial,
    // `tables.partials.selection-cell` — which means it now costs a view render where
    // it cost none. That trade is only sound if the render is O(1): the Table compiles
    // the partial into a Skeleton once and the rows splice the record key into it.
    //
    // Measured as a delta between selectable and plain at two row counts, so the fixed
    // chrome and the one-off partial render both drop out and what is left is the
    // per-row slope. Rendering the partial per row instead would make this grow.
    rcSeed(4);
    $selectableSmall = rcRenderCount(rcRender(2, selectable: true));
    $plainSmall = rcRenderCount(rcRender(2));

    rcSeed(8); // 4 → 12 rows
    $selectableLarge = rcRenderCount(rcRender(2, selectable: true));
    $plainLarge = rcRenderCount(rcRender(2));

    expect($selectableLarge - $selectableSmall)->toBe($plainLarge - $plainSmall)
        ->and($selectableLarge - $selectableSmall)->toBe(0);
});

it('renders the context-menu panel partial once per table, never once per row', function () {
    // Same trade as the selection cell: the panel moved into its own partial, so it
    // now costs a view render where inline markup cost none. That is only sound if the
    // render is O(1) — the Table compiles the partial into a Skeleton once and each row
    // splices its key and its (genuinely per-record) items into it.
    //
    // The items themselves still render per row, and must: an action can be hidden for
    // one record and visible for the next. So this compares the SLOPE against a table
    // whose menu is switched off, which nets out everything but the panel scaffolding.
    rcSeed(4);
    $menuSmall = rcRenderCount(rcRender(2, contextMenu: true));
    $plainSmall = rcRenderCount(rcRender(2));

    rcSeed(8); // 4 → 12 rows
    $menuLarge = rcRenderCount(rcRender(2, contextMenu: true));
    $plainLarge = rcRenderCount(rcRender(2));

    $panelSlope = ($menuLarge - $menuSmall) - ($plainLarge - $plainSmall);

    // The items are one dropdown-item render per row; the scaffolding around them is
    // zero. Putting the panel back in the row loop would make this 2 per row.
    expect($panelSlope / 8)->toEqual(1)
        ->and($plainLarge - $plainSmall)->toBe(0);
});

it('renders the sub-row expander cell once per shape, never once per row', function () {
    // The expander cell was an @include in the row loop — the N×View shape this fuse
    // exists to catch. It is now three compiled shapes (no toggle, collapsed, expanded)
    // and every row splices its key into one of them, so growing the row count adds no
    // renders at all.
    rcSeed(4);
    $subSmall = rcRenderCount(rcRender(2, subRows: true));
    $plainSmall = rcRenderCount(rcRender(2));

    rcSeed(8); // 4 → 12 rows
    $subLarge = rcRenderCount(rcRender(2, subRows: true));
    $plainLarge = rcRenderCount(rcRender(2));

    expect($subLarge - $subSmall)->toBe($plainLarge - $plainSmall)
        ->and($subLarge - $subSmall)->toBe(0);
});

it('renders the group header once for the table, never once per group', function () {
    // Group count is unbounded: grouped by a status there are three headers, grouped by
    // a date there is one per row. The header is a one-slot skeleton now, so its render
    // cost is the same either way — measured here at its worst, one group per row.
    rcSeed(4);
    $groupedSmall = rcRenderCount(rcRender(2, grouped: true));
    $plainSmall = rcRenderCount(rcRender(2));

    rcSeed(8); // 4 → 12 rows, and 4 → 12 groups
    $groupedLarge = rcRenderCount(rcRender(2, grouped: true));
    $plainLarge = rcRenderCount(rcRender(2));

    expect($groupedLarge - $groupedSmall)->toBe($plainLarge - $plainSmall)
        ->and($groupedLarge - $groupedSmall)->toBe(0);
});

it('adds zero view renders per row for an action button', function () {
    // The action column was the last N×View in the engine: a button was two renders
    // (the button view and the content partial it includes) for every action on every
    // row. Action::render() compiles one skeleton per SHAPE now and splices the click
    // expression, so a table with actions has the same per-row slope as one without.
    rcSeed(4);

    // Warm-up: the canonical spinner partial resolves once per request, and whichever
    // measurement ran first would otherwise carry it and read as a negative slope.
    rcRender(2, actions: 3)();

    $withSmall = rcRenderCount(rcRender(2, actions: 3));
    $plainSmall = rcRenderCount(rcRender(2));

    rcSeed(8); // 4 → 12 rows
    $withLarge = rcRenderCount(rcRender(2, actions: 3));
    $plainLarge = rcRenderCount(rcRender(2));

    expect($withLarge - $withSmall)->toBe($plainLarge - $plainSmall)
        ->and($withLarge - $withSmall)->toBe(0);
});

// The fill handle is one element per table, positioned over the active cell by
// JS. Rendering it per cell would be the §7 anti-pattern AND would have to live
// inside the editable cell partial, which is skeleton-spliced — so this pins the
// cost at O(1). Measured as the delta between two otherwise identical tables,
// because the editable cells themselves are not skeletonable and carry their own
// per-row slope.
it('adds the fill handle once per table, never once per row', function () {
    rcSeed(4);

    // Warm-up: @assets and @once emit once per process, so the first render of
    // each component is inflated by scaffolding that will never render again.
    // Comparing slopes rather than absolute counts is what makes this robust.
    rcFillRender(true)();
    rcFillRender(false)();

    $fillSmall = rcRenderCount(rcFillRender(true));
    $plainSmall = rcRenderCount(rcFillRender(false));

    rcSeed(8); // 4 → 12 rows

    $fillLarge = rcRenderCount(rcFillRender(true));
    $plainLarge = rcRenderCount(rcFillRender(false));

    // Eight more rows cost the same with the handle as without it → the fill
    // markup is O(1). Moving the include inside the row loop breaks this.
    expect($fillLarge - $fillSmall)->toBe($plainLarge - $plainSmall);
});
