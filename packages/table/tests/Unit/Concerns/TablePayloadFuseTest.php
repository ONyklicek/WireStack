<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
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

    public function kids()
    {
        return $this->hasMany(PfKid::class, 'row_id');
    }
}

class PfKid extends Model
{
    protected $table = 'pf_kids';

    protected $guarded = [];
}

class PfComponent extends Component
{
    use WithTable;

    public int $cols = 3;

    public bool $copyable = false;

    public bool $selectable = false;

    public bool $contextMenu = false;

    public bool $subRows = false;

    public bool $actions = false;

    public string $siblings = '';

    public function mount(
        int $cols = 3,
        bool $copyable = false,
        bool $selectable = false,
        bool $contextMenu = false,
        bool $subRows = false,
        bool $actions = false,
        string $siblings = '',
    ): void {
        $this->cols = $cols;
        $this->copyable = $copyable;
        $this->selectable = $selectable;
        $this->contextMenu = $contextMenu;
        $this->subRows = $subRows;
        $this->actions = $actions;
        $this->siblings = $siblings;
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

        if ($this->contextMenu) {
            $table->gestures()->rowContextMenu([Action::make('edit')->label('Edit')]);
        }

        if ($this->subRows) {
            $table->subRows('kids')->subRowColumns([TextColumn::make('label')]);
        }

        if ($this->actions) {
            $table->actions([Action::make('edit')->label('Edit')]);
        }

        // The sibling-row variants all carry a real DB column (`name`) so grouping has
        // something to group by. 'none' is their baseline: same columns, no siblings,
        // so a delta against it measures the sibling row and nothing else.
        if ($this->siblings !== '') {
            array_unshift($columns, TextColumn::make('name'));
        }

        // Worst case on purpose: `name` is unique, so there is one group per row and
        // the slope measures a group header (and subtotal) per row rather than per
        // group. A table grouped by a timestamp behaves like this.
        if ($this->siblings === 'grouped') {
            $table->groupBy('name');
        }

        if ($this->siblings === 'grouped-summaries') {
            $table->groupBy('name')->groupSummaries();
            $columns[0]->summarize('count');
        }

        if ($this->siblings === 'stacked') {
            $table->stackedOnMobile();
        }

        if ($this->siblings === 'subrows-expanded') {
            $table->subRows('kids')
                ->subRowColumns([TextColumn::make('label')])
                ->subRowsDefaultExpanded();
        }

        return $table
            ->model(PfRow::class)
            ->paginated(false)
            ->selectable($this->selectable)
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
function pfMeasure(
    int $cols = 3,
    bool $copyable = false,
    bool $selectable = false,
    bool $contextMenu = false,
    bool $subRows = false,
    bool $actions = false,
    string $siblings = '',
): array {
    $html = Livewire::test(PfComponent::class, [
        'cols' => $cols,
        'copyable' => $copyable,
        'selectable' => $selectable,
        'contextMenu' => $contextMenu,
        'subRows' => $subRows,
        'actions' => $actions,
        'siblings' => $siblings,
    ])->html();

    return [
        'bytes' => strlen($html),
        'whitespaceRuns' => preg_match_all('/>\s+</', $html),
        'comments' => substr_count($html, '<!--'),
        'openMarkers' => substr_count($html, '<!--[if BLOCK]>'),
        'closeMarkers' => substr_count($html, '<!--[if ENDBLOCK]>'),
    ];
}

/**
 * Per-row cost, as the slope between two row counts.
 *
 * @return array{bytes: float, whitespaceRuns: float, comments: float}
 */
function pfPerRow(
    int $cols = 3,
    bool $copyable = false,
    bool $selectable = false,
    bool $contextMenu = false,
    bool $subRows = false,
    bool $actions = false,
    string $siblings = '',
): array {
    pfSeed(4);
    $small = pfMeasure($cols, $copyable, $selectable, $contextMenu, $subRows, $actions, $siblings);

    pfSeed(8); // 4 → 12 rows
    $large = pfMeasure($cols, $copyable, $selectable, $contextMenu, $subRows, $actions, $siblings);

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
    $first = (int) (PfRow::max('id') ?? 0);

    PfRow::insert(array_map(fn (int $i) => [
        // Unique across seed calls: the grouping variants group by this column, and a
        // repeated name would merge two rows into one group and understate the slope.
        'name' => 'row-'.($first + $i),
        'created_at' => $now,
        'updated_at' => $now,
    ], range(1, $rows)));

    // One child per row, so the expander cell renders its toggle (a row with no
    // sub-rows renders the cell empty and would measure the wrong thing).
    PfKid::insert(array_map(fn (int $i) => [
        'row_id' => $first + $i,
        'label' => 'k',
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

    Schema::create('pf_kids', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('row_id');
        $table->string('label');
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('pf_rows');
    Schema::dropIfExists('pf_kids');
});

// ─── The fuse ────────────────────────────────────────────────────────────────

it('emits balanced morph markers in every table shape', function () {
    // Livewire wraps each `@if`/`@foreach` in a `[if BLOCK]` / `[if ENDBLOCK]` comment
    // pair so morphdom can pair a block's children when their number changes. If an
    // opening marker goes missing, the block boundaries no longer nest and the morph
    // is working from a lie — the failure §8f caught by hand (the header reordered and
    // the body did not), which nothing in the suite could see.
    //
    // Markers CAN go missing from valid-looking Blade: Livewire's injector matches
    // directives with a regex and skips the ones it cannot classify. Gluing a directive
    // straight onto a Blade comment (`--}}@if(...)`) is one way to lose one, and it
    // costs nothing but a newline to avoid. This asserts the invariant instead of the
    // authoring rule, so it catches the next way as well.
    $shapes = [
        'plain' => fn () => pfMeasure(),
        'selectable' => fn () => pfMeasure(selectable: true),
        'copyable' => fn () => pfMeasure(copyable: true),
        'context menu' => fn () => pfMeasure(contextMenu: true),
        'sub-rows' => fn () => pfMeasure(subRows: true),
        'actions' => fn () => pfMeasure(actions: true),
        'grouped' => fn () => pfMeasure(siblings: 'grouped'),
        'grouped + summaries' => fn () => pfMeasure(siblings: 'grouped-summaries'),
        'expanded sub-rows' => fn () => pfMeasure(siblings: 'subrows-expanded'),
    ];

    pfSeed(4);

    foreach ($shapes as $name => $measure) {
        $m = $measure();

        expect($m['openMarkers'])
            ->toBe($m['closeMarkers'], "unbalanced morph markers in the '{$name}' table");
    }
});

it('keeps a row under its byte budget', function () {
    // Measured 2026-08-08 on Livewire 3: 1823 B/row for three plain text cells, down
    // from 4214. Two changes got it there — the <td> chrome assembled once per column
    // instead of interpolated by Blade per cell (4214 → 2347), and the <tr> opening tag
    // compiled once for the table instead of re-deciding four table-level `@if`s per row
    // (2347 → 1823). The headroom is for a class-list edit, not for a new wrapper:
    // crossing this means every commit got bigger for every user.
    //
    // Re-measured 2026-08-14 on Livewire 4.4.0: 1826.75 B/row. `smart_wire_keys`
    // defaults to true on v4 and compiles wire:keys into loops, so this was expected to
    // move — it did not, because the cells are concatenated by a raw PHP `foreach`
    // inside an `@php` block (`$cellsHtml` in `tables/index.blade.php`), not by a Blade
    // `@foreach`, so the v4 key compiler never sees a loop here. The +3.75 B is drift.
    expect(pfPerRow()['bytes'])->toBeLessThan(1900);
});

it('keeps a row under its whitespace-node budget', function () {
    // Measured 2026-08-08 on Livewire 3: 11 runs/row, down from 21 — the cells no longer
    // contribute any, because they are concatenated in PHP rather than laid out by a
    // @foreach. Deterministic, so the budget is the measurement: one more nested tag
    // pair written into the row loop shows up here immediately.
    //
    // Re-measured 2026-08-14 on Livewire 4.4.0: 11 runs/row, unchanged. Note the budget
    // sits EXACTLY on the measurement — there is no headroom, so the next change to trip
    // this has not necessarily regressed anything, it has simply used the first byte of
    // slack that never existed. Re-measure before deciding.
    expect(pfPerRow()['whitespaceRuns'])->toBeLessThanOrEqual(11);
});

it('keeps a row under its morph-marker budget', function () {
    // Measured 2026-08-08 on Livewire 3: 16 comments/row = 8 conditionals, down from 24.
    // Dropping the per-cell @foreach and its nested @if took four pairs with it.
    //
    // Re-measured 2026-08-14 on Livewire 4.4.0: 16 comments/row, unchanged. Like the
    // whitespace budget above, this one sits exactly on the measurement.
    //
    // Do NOT try to win the last few by deleting conditionals from the row loop. The
    // context-menu `@if` was removed on exactly that reasoning — the panel string is
    // already empty when there is no menu — and it broke the column reorder: morphdom
    // needs that block boundary to pair a row's children when the cell list changes.
    // Both fuses stayed green; only verify-gesture-lab.mjs caught it.
    expect(pfPerRow()['comments'])->toBeLessThanOrEqual(16);
});

it('keeps a selection cell to a splice', function () {
    // A selectable table adds one <td> per row, and it used to be the most expensive
    // thing left in the row: 2251 B and 10 whitespace nodes — MORE than the whole rest
    // of a three-column row (1823 B / 11 nodes). Ten nodes because the cell was four
    // nested tags laid out over eleven lines, and every run between two tags is a text
    // node the morph walks on every commit.
    //
    // It is now one skeleton compiled per table from `tables.partials.selection-cell`,
    // spliced with the record key per row. Measured 2026-08-08: 1172 B (−48 %) and
    // ZERO whitespace nodes.
    //
    // The zero is the load-bearing half of this budget. It only holds while the tags in
    // the partial keep touching; re-indenting it "for readability" puts every one of
    // those nodes straight back, which is exactly the change this catches.
    $plain = pfPerRow();
    $selectable = pfPerRow(selectable: true);

    $cell = [
        'bytes' => $selectable['bytes'] - $plain['bytes'],
        'whitespaceRuns' => $selectable['whitespaceRuns'] - $plain['whitespaceRuns'],
    ];

    expect($cell['bytes'])->toBeLessThan(1250)
        ->and($cell['whitespaceRuns'])->toEqual(0);
});

it('keeps the context-menu panel to its items', function () {
    // The teleported panel is pure scaffolding — position, colours, role="menu" — and
    // it is identical on every row, because one wireRecordActions controller on the
    // <tbody> drives them all by record key. It used to be laid out by Blade per row:
    // 1659 B and 14 whitespace nodes for a ONE-item menu.
    //
    // It is now one skeleton compiled per table, filled with the key and the items.
    // Measured 2026-08-08: 982 B (−41 %) and 7 whitespace nodes. What is left is
    // almost entirely the item markup itself, which is genuinely per-record (an action
    // may be hidden for this row) and is core's dropdown-item, not this partial.
    $plain = pfPerRow();
    $menu = pfPerRow(contextMenu: true);

    $panel = [
        'bytes' => $menu['bytes'] - $plain['bytes'],
        'whitespaceRuns' => $menu['whitespaceRuns'] - $plain['whitespaceRuns'],
    ];

    expect($panel['bytes'])->toBeLessThan(1050)
        ->and($panel['whitespaceRuns'])->toBeLessThanOrEqual(7);
});

it('keeps the sub-row expander cell to its toggle', function () {
    // The expander cell was an @include laid out by Blade on every row: 1044 B, 8
    // whitespace text nodes and a view render per row, for a button whose only
    // per-record part is the key in one Alpine expression.
    //
    // It is now three compiled shapes (no toggle / collapsed / expanded) spliced per
    // row. Measured 2026-08-08: 760 B (−27 %) and 1 whitespace node. The `@if` inside
    // the partial is kept, so the morph markers per row are unchanged at 2 — see §8f.
    $plain = pfPerRow();
    $subRows = pfPerRow(subRows: true);

    $cell = [
        'bytes' => $subRows['bytes'] - $plain['bytes'],
        'whitespaceRuns' => $subRows['whitespaceRuns'] - $plain['whitespaceRuns'],
        'comments' => $subRows['comments'] - $plain['comments'],
    ];

    expect($cell['bytes'])->toBeLessThan(820)
        ->and($cell['whitespaceRuns'])->toBeLessThanOrEqual(1)
        ->and($cell['comments'])->toEqual(2);
});

it('keeps the actions cell to its buttons', function () {
    // One action costs 1155 B and 10 whitespace nodes per row, down from 1470 B and 16
    // — the difference is the four text nodes the cell's own <td>/<div>/@foreach layout
    // used to lay out around markup that never varies.
    //
    // What is left is the button itself (core's actions/button.blade.php), which is
    // genuinely per-record and belongs to the action-render work, not to this loop.
    //
    // The comment budget went 10 → 8 when the row body moved into PHP
    // (Support\RowRenderer). It used to say the `@foreach` and its markers had to
    // stay, because an action can be non-executable for one row and not the next
    // and the button list really does change. That was true of the markers and is
    // now true of something cheaper: each record-scoped button carries
    // `wire:key="act-{key}-{name}"`, so the morph pairs them by identity instead —
    // see RowMorphKeysTest, which is what holds that in place.
    $plain = pfPerRow();
    $actions = pfPerRow(actions: true);

    $cell = [
        'bytes' => $actions['bytes'] - $plain['bytes'],
        'whitespaceRuns' => $actions['whitespaceRuns'] - $plain['whitespaceRuns'],
        'comments' => $actions['comments'] - $plain['comments'],
    ];

    expect($cell['bytes'])->toBeLessThan(1220)
        ->and($cell['whitespaceRuns'])->toBeLessThanOrEqual(10)
        ->and($cell['comments'])->toEqual(8);
});

it('keeps the sibling rows off the row budget', function () {
    // Three rows can sit BESIDE a body row: the group header, the group subtotal and
    // the expanded sub-rows panel. None is bounded by anything — group by a date and
    // there is one group per row; expand by default and every row carries a panel — so
    // all three were measured as a per-row slope, against a baseline with the same
    // columns and no siblings.
    //
    // Measured 2026-08-08, before → after:
    //   group header        318 B,  6 nodes  →  222 B, 0 nodes  (skeleton, one render
    //                                           for the table instead of one per group)
    //   + group subtotal  1 674 B, 28 nodes  → 1 222 B, 4 nodes
    //   expanded panel    3 019 B, 37 nodes  → 2 113 B, 8 nodes
    //
    // The panels' remaining bytes are the nested table's real content. What went was
    // indentation: every run of it between two tags is a DOM text node, and these
    // partials are emitted once per group / per expanded row.
    $baseline = pfPerRow(siblings: 'none');

    $cost = function (string $variant) use ($baseline) {
        $m = pfPerRow(siblings: $variant);

        return [
            'bytes' => $m['bytes'] - $baseline['bytes'],
            'whitespaceRuns' => $m['whitespaceRuns'] - $baseline['whitespaceRuns'],
        ];
    };

    expect($cost('grouped')['bytes'])->toBeLessThan(260)
        ->and($cost('grouped')['whitespaceRuns'])->toEqual(0)
        ->and($cost('grouped-summaries')['bytes'])->toBeLessThan(1300)
        ->and($cost('grouped-summaries')['whitespaceRuns'])->toBeLessThanOrEqual(4)
        ->and($cost('subrows-expanded')['bytes'])->toBeLessThan(2200)
        ->and($cost('subrows-expanded')['whitespaceRuns'])->toBeLessThanOrEqual(8);
});

it('keeps the stacked mobile card off the row budget', function () {
    // `stackedOnMobile()` renders every record a SECOND time: the desktop rows and the
    // cards are both in the document, and CSS decides which one is seen. So the card's
    // layout is emitted per row on every commit, whichever width the reader is at, and
    // its indentation was the single biggest per-row cost in the table — 4391 B and 36
    // whitespace nodes, 225 % on top of the row itself.
    //
    // Closed up 2026-08-09: 2830 B and 10 nodes, markers unchanged at 22. What is left
    // is the card's real content plus the conditionals that shape it.
    //
    // The budget is deliberately tight on the NODE count, because that is the part a
    // re-indent silently undoes. Whether this second rendering should happen at all is
    // a separate, larger question — see the plan.
    $base = pfPerRow(siblings: 'none');
    $stacked = pfPerRow(siblings: 'stacked');

    $card = [
        'bytes' => $stacked['bytes'] - $base['bytes'],
        'whitespaceRuns' => $stacked['whitespaceRuns'] - $base['whitespaceRuns'],
        'comments' => $stacked['comments'] - $base['comments'],
    ];

    expect($card['bytes'])->toBeLessThan(2950)
        ->and($card['whitespaceRuns'])->toBeLessThanOrEqual(10)
        ->and($card['comments'])->toEqual(22);
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
