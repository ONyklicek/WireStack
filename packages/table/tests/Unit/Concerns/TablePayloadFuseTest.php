<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/**
 * Payload fuse — the client-side half of the render-cost model.
 *
 * `TableRenderCountTest` counts `view()->render()`, which is the SERVER cost. It is
 * blind to the cost the browser pays, and that one is not small: every commit ships
 * the whole table back and Livewire morphs it, walking each node it finds. Measured
 * on a 40-row preview, the morph took ~100 ms — longer than the round-trip that
 * carried it — and 79 % of the nodes it walked held no content at all.
 *
 * Two things make a node the morph must walk, and neither shows up in a render count:
 *
 * 1. **A run of whitespace between two tags is one text node**, however long it is.
 *    So indentation is not free at the DOM level, and — the part that matters when
 *    fixing it — SHORTENING the indentation saves bytes but not nodes. Only removing
 *    the run removes the node.
 * 2. **Every `@if` / `@foreach` costs two comment nodes**, injected by Livewire so its
 *    morph can tell a change from an insertion (`inject_morph_markers`).
 *
 * So this counts the three things a payload is made of — bytes, whitespace runs and
 * comments — as a SLOPE per row, the same trick the render fuse uses: the fixed
 * chrome (toolbar, filters, modals) drops out of a difference, so the budgets pin
 * the per-row cost and stay honest when the chrome legitimately grows.
 *
 * The budgets are deliberately near the measured values (see each `expect`). A change
 * that trips one is not necessarily wrong — but it is a change to what every user's
 * browser does on every keystroke, and it should be a decision, not a side effect.
 */

// ─── Test Model + Component ──────────────────────────────────────────────────

class PfRow extends Model
{
    protected $table = 'pf_rows';

    protected $guarded = [];
}

class PfComponent extends Component
{
    use WithTable;

    public int $cols = 3;

    public bool $copyable = false;

    public function mount(int $cols = 3, bool $copyable = false): void
    {
        $this->cols = $cols;
        $this->copyable = $copyable;
    }

    public function table(Table $table): Table
    {
        $columns = [];

        for ($i = 0; $i < $this->cols; $i++) {
            // displayUsing keeps the value off any DB attribute and constant across
            // rows, so the slope measures the SCAFFOLDING around the cell, never the
            // content in it.
            $column = TextColumn::make('c'.$i)->displayUsing(fn () => 'v');

            if ($this->copyable) {
                $column->copyable();
            }

            $columns[] = $column;
        }

        return $table
            ->model(PfRow::class)
            ->paginated(false)
            ->columns($columns);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * The three payload metrics for one rendered table.
 *
 * `whitespaceRuns` counts `>   <` — each one is exactly one whitespace-only text
 * node in the browser. `comments` counts every `<!--`, which at this size is almost
 * entirely Livewire's morph markers.
 *
 * @return array{bytes: int, whitespaceRuns: int, comments: int}
 */
function pfMeasure(int $cols = 3, bool $copyable = false): array
{
    $html = Livewire::test(PfComponent::class, ['cols' => $cols, 'copyable' => $copyable])->html();

    return [
        'bytes' => strlen($html),
        'whitespaceRuns' => preg_match_all('/>\s+</', $html),
        'comments' => substr_count($html, '<!--'),
    ];
}

/**
 * Per-row cost, as the slope between two row counts.
 *
 * @return array{bytes: float, whitespaceRuns: float, comments: float}
 */
function pfPerRow(int $cols = 3, bool $copyable = false): array
{
    pfSeed(4);
    $small = pfMeasure($cols, $copyable);

    pfSeed(8); // 4 → 12 rows
    $large = pfMeasure($cols, $copyable);

    return [
        'bytes' => ($large['bytes'] - $small['bytes']) / 8,
        'whitespaceRuns' => ($large['whitespaceRuns'] - $small['whitespaceRuns']) / 8,
        'comments' => ($large['comments'] - $small['comments']) / 8,
    ];
}

/** What one `->copyable()` adds to a cell, in bytes and in nodes. */
function pfCopyCost(): array
{
    $plain = pfPerRow(3, copyable: false);
    $copy = pfPerRow(3, copyable: true);

    return [
        'bytes' => ($copy['bytes'] - $plain['bytes']) / 3,
        'whitespaceRuns' => ($copy['whitespaceRuns'] - $plain['whitespaceRuns']) / 3,
        'comments' => ($copy['comments'] - $plain['comments']) / 3,
    ];
}

function pfSeed(int $rows): void
{
    $now = now();

    PfRow::insert(array_map(fn (int $i) => [
        'name' => 'row-'.$i,
        'created_at' => $now,
        'updated_at' => $now,
    ], range(1, $rows)));
}

// ─── Setup ───────────────────────────────────────────────────────────────────

beforeEach(function () {
    Schema::create('pf_rows', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('pf_rows');
});

// ─── The fuse ────────────────────────────────────────────────────────────────

it('keeps a row under its byte budget', function () {
    // Measured 2026-08-08: 1823 B/row for three plain text cells, down from 4214.
    // Two changes got it there — the <td> chrome assembled once per column instead of
    // interpolated by Blade per cell (4214 → 2347), and the <tr> opening tag compiled
    // once for the table instead of re-deciding four table-level `@if`s per row
    // (2347 → 1823). The headroom is for a class-list edit, not for a new wrapper:
    // crossing this means every commit got bigger for every user.
    expect(pfPerRow()['bytes'])->toBeLessThan(1900);
});

it('keeps a row under its whitespace-node budget', function () {
    // Measured 2026-08-08: 11 runs/row, down from 21 — the cells no longer contribute
    // any, because they are concatenated in PHP rather than laid out by a @foreach.
    // Deterministic, so the budget is the measurement: one more nested tag pair
    // written into the row loop shows up here immediately.
    expect(pfPerRow()['whitespaceRuns'])->toBeLessThanOrEqual(11);
});

it('keeps a row under its morph-marker budget', function () {
    // Measured 2026-08-08: 16 comments/row = 8 conditionals, down from 24. Dropping
    // the per-cell @foreach and its nested @if took four pairs with it. This is the
    // cheapest signal that a new `@if`/`@foreach` landed inside the row loop.
    expect(pfPerRow()['comments'])->toBeLessThanOrEqual(16);
});

it('keeps a copyable cell to one button', function () {
    // A copy affordance is one `<button data-copy>`; the click, the clipboard and
    // the feedback pill are bound once for the document by the record-copy bundle.
    //
    // Measured 2026-08-07, before → after delegating it: 2042 B → 943 B and 11 → 2
    // whitespace nodes per cell. What is left is mostly the inline clipboard SVG
    // (~690 B of the 943); the wrapper and button are ~250. Crossing this budget
    // means the per-cell markup grew back — an Alpine component, a second icon or a
    // feedback element per cell are exactly the shapes it is here to catch.
    $cost = pfCopyCost();

    expect($cost['bytes'])->toBeLessThan(1000)
        ->and($cost['whitespaceRuns'])->toBeLessThanOrEqual(2);
});
