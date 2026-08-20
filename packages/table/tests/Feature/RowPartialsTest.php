<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/**
 * A write answering with the row it changed, instead of the table.
 *
 * The last link in the chain islands could not reach: an island's name is
 * re-evaluated inside its own compiled view file, which never sees a loop
 * variable, so there is no island per record. A partial is an HTML attribute the
 * server picks at write time.
 *
 * Measured on a 25-column, 20-row page: 49.3 ms and 556 kB as a targeted island
 * render of the whole data region, 3.2 ms and 26 kB as one row.
 *
 * Most of what follows is about **when it refuses**, because that is what makes
 * it safe to switch on. Three shapes keep numbers outside a row, and a row-only
 * answer would leave every one of them showing the old figure.
 */
class RpRow extends Model
{
    protected $table = 'rp_rows';

    protected $guarded = [];

    public $timestamps = false;
}

class RpHost extends Component
{
    use WithTable;

    public bool $partials = true;

    public bool $summaries = false;

    public bool $grouped = false;

    public bool $groupedByEditable = false;

    public bool $stacked = false;

    public function mount(
        bool $partials = true,
        bool $summaries = false,
        bool $grouped = false,
        bool $groupedByEditable = false,
        bool $stacked = false,
    ): void {
        $this->partials = $partials;
        $this->summaries = $summaries;
        $this->grouped = $grouped;
        $this->groupedByEditable = $groupedByEditable;
        $this->stacked = $stacked;
    }

    public function table(Table $table): Table
    {
        $amount = TextColumn::make('amount');

        if ($this->summaries) {
            $amount->summarizeSum();
        }

        $table->model(RpRow::class)
            ->columns([TextInputColumn::make('name'), $amount])
            ->rowPartials($this->partials);

        if ($this->grouped) {
            $table->groupBy('amount')->groupSummaries();
        }

        // Grouping by the column the table lets you EDIT, which is the only way a
        // write to the group column gets past the editability guard and reaches
        // the shape check.
        if ($this->groupedByEditable) {
            $table->groupBy('name');
        }

        return $this->stacked ? $table->stackedOnMobile() : $table;
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('rp_rows', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->integer('amount')->default(1);
    });

    RpRow::create(['name' => 'First', 'amount' => 1]);
    RpRow::create(['name' => 'Second', 'amount' => 2]);
});

afterEach(fn () => Schema::dropIfExists('rp_rows'));

it('anchors every row when the table asked for it', function () {
    $html = Livewire::test(RpHost::class)->html();

    expect($html)->toContain('wire:partial="row-1"')
        ->toContain('wire:partial="row-2"');
});

it('anchors nothing by default', function () {
    // Opt-in, and it stays opt-in: a row re-rendered on its own keeps its
    // position, so an edit that would move the record under the current sort
    // leaves it where it is until the next full render.
    expect(Livewire::test(RpHost::class, ['partials' => false])->html())
        ->not->toContain('wire:partial');
});

it('answers a cell save with that row alone', function () {
    $test = Livewire::test(RpHost::class);

    $test->call('updateTableCell', '1', 'name', 'Edited', null);

    $partials = $test->effects['wirePartials'] ?? [];

    expect(array_keys($partials))->toBe(['row-1'])
        ->and($partials['row-1'])->toContain('Edited')
        ->and($partials['row-1'])->toStartWith('<tr')
        // The row, and not the page around it.
        ->and($partials['row-1'])->not->toContain('<tbody')
        ->and($test->effects['html'] ?? null)->toBeNull();
});

it('writes the value it sends back', function () {
    // The partial has to be the truth, not a picture of it.
    Livewire::test(RpHost::class)->call('updateTableCell', '1', 'name', 'Edited', null);

    expect(RpRow::find(1)->name)->toBe('Edited');
});

it('sends nothing partial when the write fails', function () {
    // A refused write changes nothing, so there is nothing to re-render — and
    // the response must not claim to have covered the request.
    $test = Livewire::test(RpHost::class);

    $test->call('updateTableCell', '999', 'name', 'Edited', null);

    expect($test->effects['wirePartials'] ?? null)->toBeNull();
});

it('answers with the totals too, where the table shows them', function () {
    // A total is computed over the whole filtered set, so any write moves it, and
    // it sits outside every row — which is why a summarised table refused row
    // partials outright until the footer had an anchor of its own.
    $test = Livewire::test(RpHost::class, ['summaries' => true]);

    expect($test->instance()->getTable()->usesRowPartials())->toBeTrue()
        ->and($test->html())->toContain('wire:partial="summary"');

    $test->call('updateTableCell', '1', 'name', 'Edited', null);

    expect(array_keys($test->effects['wirePartials'] ?? []))->toBe(['row-1', 'summary'])
        ->and($test->effects['html'] ?? null)->toBeNull();
});

it('anchors no totals on a table that shows none', function () {
    expect(Livewire::test(RpHost::class)->html())->not->toContain('wire:partial="summary"');
});

it('answers with the group subtotal a write moved', function () {
    // A subtotal is moved by a write to any member of its group, and it is a
    // sibling row rather than part of one. Each subtotal ROW carries its own
    // anchor: a subtotal is as many rows as the widest column has summaries, and
    // wrapping them would need a <tbody> per group — which would break the
    // invariant that the delegated record-actions controller has one root.
    $test = Livewire::test(RpHost::class, ['grouped' => true, 'summaries' => true]);

    expect($test->instance()->getTable()->usesRowPartials())->toBeTrue()
        ->and($test->html())->toContain('wire:partial="group-');

    $test->call('updateTableCell', '1', 'name', 'Edited', null);

    $partials = array_keys($test->effects['wirePartials'] ?? []);

    expect($partials)->toContain('row-1')
        ->and(collect($partials)->filter(fn ($n) => str_starts_with($n, 'group-'))->values()->all())
        ->not->toBeEmpty()
        ->and($test->effects['html'] ?? null)->toBeNull();
});

it('takes the full render when the write moves the record to another group', function () {
    // Editing the column the table groups BY is a change to the page's shape, not
    // to a row's contents — the record leaves one group for another, and no set
    // of regions can describe that.
    //
    // The table groups by `name` here, which is the editable column, so the write
    // actually lands and the shape check is what refuses the partial. Grouping by
    // a read-only column would have the write refused for being uneditable, and
    // this would pass without the guard existing at all.
    $test = Livewire::test(RpHost::class, ['groupedByEditable' => true]);

    $test->call('updateTableCell', '1', 'name', 'Moved', null);

    expect(RpRow::find(1)->name)->toBe('Moved')
        ->and($test->effects['wirePartials'] ?? null)->toBeNull()
        ->and($test->effects['html'] ?? null)->not->toBeNull();
});

it('finds the edited record wherever it sits on the page', function () {
    // The row is located by walking the page, so a record that is not the first
    // one has rows skipped ahead of it — and the position it is rendered at is
    // the position it was FOUND at, not zero. A row rendered at the wrong index
    // would strip the wrong background and read as a striping bug.
    $test = Livewire::test(RpHost::class);

    $test->call('updateTableCell', '2', 'name', 'Second edited', null);

    expect(array_keys($test->effects['wirePartials'] ?? []))->toBe(['row-2'])
        ->and($test->effects['wirePartials']['row-2'])->toContain('Second edited')
        ->and($test->effects['wirePartials']['row-2'])->toContain('wire:partial="row-2"');
});

it('answers with the card too, where the table renders one', function () {
    // The stacked layout renders every record twice — the rows are in the same
    // document, hidden by CSS at this width. A write that refreshed only one of
    // them would leave a phone showing the old value and a desktop the new one,
    // which is why this shape refused outright until the card had an anchor.
    $test = Livewire::test(RpHost::class, ['stacked' => true]);

    expect($test->instance()->getTable()->usesRowPartials())->toBeTrue()
        ->and($test->html())->toContain('wire:partial="row-1"')
        ->toContain('wire:partial="card-1"');

    $test->call('updateTableCell', '1', 'name', 'Edited', null);

    expect(array_keys($test->effects['wirePartials'] ?? []))->toBe(['row-1', 'card-1'])
        ->and($test->effects['wirePartials']['card-1'])->toContain('Edited');
});

it('anchors no card on a table that does not render one', function () {
    // …and the anchor costs nothing where there is no card to anchor.
    expect(Livewire::test(RpHost::class)->html())->not->toContain('wire:partial="card-');
});

it('falls back to a full render when the flag is off', function () {
    // Without the flag nothing is anchored, so the write must still repaint what
    // it changed the ordinary way.
    $test = Livewire::test(RpHost::class, ['partials' => false]);

    $test->call('updateTableCell', '1', 'name', 'Edited', null);

    expect($test->effects['wirePartials'] ?? null)->toBeNull()
        ->and($test->effects['html'] ?? null)->not->toBeNull();
});

it('answers with both footers where the table renders both', function () {
    // A stacked table renders its totals twice into one document — the desktop
    // <tfoot> and a card footer for the width that hides the table. Refreshing
    // one and not the other leaves a phone showing the old total and a desktop
    // the new one, which is the same trap the card itself was anchored for.
    $test = Livewire::test(RpHost::class, ['stacked' => true, 'summaries' => true]);

    expect($test->html())
        ->toContain('wire:partial="summary"')
        ->toContain('wire:partial="summary-mobile"');

    $test->call('updateTableCell', '1', 'name', 'Edited', null);

    expect(array_keys($test->effects['wirePartials'] ?? []))
        ->toBe(['row-1', 'card-1', 'summary', 'summary-mobile']);
});

it('queues nothing at all when no record moved', function () {
    // The satellite pass is what a row partial drags along with it — the card,
    // the totals, the group subtotal. Handed nothing, it must queue nothing:
    // a response carrying a fresh total but no row would be a partial truth, and
    // the coverage rule exists to keep those off the wire.
    //
    // Called directly because neither in-repo caller can produce an empty set —
    // refreshTable() returns earlier when nothing changed, and the write path
    // always passes the one record it wrote. The guard is for a host that
    // computes its own moved set, which the protected seam invites.
    $test = Livewire::test(RpHost::class, ['summaries' => true, 'stacked' => true]);
    $instance = $test->instance();

    (function () {
        $this->queueSatellitePartials($this->getTable(), $this->tableRenderPlan(), []);
    })->call($instance);

    expect($test->effects['wirePartials'] ?? null)->toBeNull();
});
