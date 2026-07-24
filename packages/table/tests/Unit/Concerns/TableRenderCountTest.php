<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Livewire\Component;
use Livewire\Livewire;
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
}

class RcComponent extends Component
{
    use WithTable;

    public int $cols = 2;

    public bool $copyable = false;

    public function mount(int $cols = 2, bool $copyable = false): void
    {
        $this->cols = $cols;
        $this->copyable = $copyable;
    }

    public function table(Table $table): Table
    {
        $columns = [];

        for ($i = 0; $i < $this->cols; $i++) {
            // displayUsing keeps the cell off any DB attribute, so a plain cell is
            // exactly one view render (tables.columns.text) with no icon/url/copy.
            $column = TextColumn::make('c'.$i)->displayUsing(fn () => 'v');

            if ($this->copyable) {
                $column->copyable();
            }

            $columns[] = $column;
        }

        return $table
            ->model(RcRow::class)
            ->paginated(false)
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

    RcRow::insert(array_map(fn (int $i) => [
        'name' => 'row-'.$i,
        'created_at' => $now,
        'updated_at' => $now,
    ], range(1, $rows)));
}

function rcRender(int $cols = 2, bool $copyable = false): Closure
{
    return fn () => Livewire::test(RcComponent::class, [
        'cols' => $cols,
        'copyable' => $copyable,
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
});

afterEach(function () {
    Schema::dropIfExists('rc_rows');
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
    // A copyable cell is NOT skeletonable (per-record copy value) → it falls back to
    // the full per-cell render. So its per-row slope is > 0 while the plain cell's is
    // 0 — the fuse still trips on any per-row view render, which is its whole job.
    rcSeed(4);
    $plainSmall = rcRenderCount(rcRender(2, copyable: false));
    $copySmall = rcRenderCount(rcRender(2, copyable: true));

    rcSeed(8); // 4 → 12 rows
    $plainLarge = rcRenderCount(rcRender(2, copyable: false));
    $copyLarge = rcRenderCount(rcRender(2, copyable: true));

    expect(($plainLarge - $plainSmall) / 8)->toBe(0)              // skeletonised → 0/row
        ->and(($copyLarge - $copySmall) / 8)->toBeGreaterThan(0); // fallback → per-row
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
