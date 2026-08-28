<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Support\GroupPartitions;
use NyonCode\WireTable\Table;

/*
 * The page split into its groups — the memo, and the page it belongs to.
 *
 * Two rules lived only in a comment. The page is split ONCE per render, because
 * a table with G groups renders G subtotals and filtering the whole page for
 * each of them is G passes for an answer that cannot change between them; and
 * the split only describes the page it was taken from.
 *
 * The second one was broken. Five call sites drop the page memo; three of them
 * remembered to drop the partitions with it, and `setPage()` / `setTableCursor()`
 * did not. Paging inside one request therefore left every group subtotal
 * describing the previous page — the group on screen totalled zero, and a group
 * that was no longer on the page still showed its figure. Nothing failed: the
 * whole package passed with the memo dropped entirely, so neither the caching
 * nor its invalidation was observed at all.
 *
 * GroupPartitions now carries the identity of the record set it split, so the
 * rule is one comparison in one place instead of a line every caller has to
 * remember.
 */

class GpInvoice extends Model
{
    protected $table = 'gp_invoices';

    protected $guarded = [];

    public $timestamps = false;
}

class GpComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(GpInvoice::class)
            ->perPage(2)
            ->defaultSort('number')
            ->columns([
                Column::make('number')->sortable(),
                Column::make('customer'),
                Column::make('total')->summarizeSum('Sum'),
            ])
            ->groupBy('customer');
    }

    public function render()
    {
        return $this->getTableProperty();
    }

    /** Both are protected on the trait; a fixture may look, a consumer may not. */
    public function groupRecordsFor(mixed $value): Collection
    {
        return $this->getGroupRecords($value);
    }

    public function partitions(): GroupPartitions
    {
        return $this->tableGroupPartitions();
    }
}

/** The same table with no groupBy() — there is no way to ungroup one after the fact. */
class GpUngroupedComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(GpInvoice::class)
            ->columns([Column::make('number'), Column::make('total')->summarizeSum('Sum')]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('gp_invoices', function (Blueprint $t) {
        $t->id();
        $t->string('number');
        $t->string('customer');
        $t->integer('total');
    });

    // Two pages of two, one customer per page, so a page change moves every group.
    GpInvoice::create(['number' => 'INV-1', 'customer' => 'Acme', 'total' => 100]);
    GpInvoice::create(['number' => 'INV-2', 'customer' => 'Acme', 'total' => 200]);
    GpInvoice::create(['number' => 'INV-3', 'customer' => 'Zeta', 'total' => 400]);
    GpInvoice::create(['number' => 'INV-4', 'customer' => 'Zeta', 'total' => 800]);
});

afterEach(fn () => Schema::dropIfExists('gp_invoices'));

/** A mounted component whose paginator resolves from its own state, as in a request. */
function gpComponent(): GpComponent
{
    $component = new GpComponent;
    $component->mountWithTable();

    Paginator::currentPageResolver(fn () => $component->paginators['page'] ?? 1);

    return $component;
}

/** @return array<int, int> the summed subtotal for one group, unwrapped */
function gpSubtotal(GpComponent $component, string $group): array
{
    return array_map(
        fn (array $entry): mixed => $entry['value'],
        $component->computeGroupSummaries($group)['total'] ?? [],
    );
}

// ─── The page a split describes ──────────────────────────────────────────────

it('subtotals the groups of the page it is on, after a same-request page change', function () {
    $component = gpComponent();

    // Page 1 is Acme only.
    expect(gpSubtotal($component, 'Acme'))->toBe([300])
        ->and(gpSubtotal($component, 'Zeta'))->toBe([0]);

    $component->setPage(2);

    // Page 2 is Zeta only, and the subtotals have to move with it. Before the
    // partitions knew their page these came back exactly the wrong way round:
    // Acme kept 300 and Zeta, the group actually on screen, totalled 0.
    expect($component->getTableRecords()->pluck('customer')->all())->toBe(['Zeta', 'Zeta'])
        ->and(gpSubtotal($component, 'Zeta'))->toBe([1200])
        ->and(gpSubtotal($component, 'Acme'))->toBe([0]);
});

it('follows a cursor move the same way', function () {
    $component = gpComponent();

    expect(gpSubtotal($component, 'Acme'))->toBe([300]);

    // Any route that drops the page memo has to take the split with it; the
    // partitions ask the memo rather than trusting the caller to say so.
    $component->setTableCursor(null);
    $component->setPage(2);

    expect(gpSubtotal($component, 'Zeta'))->toBe([1200]);
});

it('rebuilds the split after the records are invalidated', function () {
    $component = gpComponent();

    expect(gpSubtotal($component, 'Acme'))->toBe([300]);

    GpInvoice::where('number', 'INV-1')->update(['total' => 1000]);
    $component->invalidateTable();

    expect(gpSubtotal($component, 'Acme'))->toBe([1200]);
});

// ─── Split once, not once per group ──────────────────────────────────────────

it('splits the page once however many groups ask for their rows', function () {
    $component = gpComponent();

    // A group's rows are the very same Collection object every time it is asked
    // for, which is only true if one split answered all of them. Filtering the
    // page per group — the thing the memo exists to avoid — would build a fresh
    // collection on each call.
    $first = $component->partitions();

    $component->computeGroupSummaries('Acme');
    $component->computeGroupSummaries('Zeta');
    $component->groupRecordsFor('Acme');

    // The same split answered all four, and a group's rows are the very same
    // Collection object each time — which a per-group filter could not be.
    expect($component->partitions())->toBe($first)
        ->and($component->groupRecordsFor('Acme'))->toBe($first->get('Acme'));
});

it('has no subtotals to compute when the table is not grouped', function () {
    $component = new GpUngroupedComponent;
    $component->mountWithTable();

    // The view asks per group header, so an ungrouped table never gets here —
    // and the guard is what keeps a stray call from splitting a page for nothing.
    expect($component->computeGroupSummaries('Acme'))->toBe([])
        ->and($component->tableHasGroupSummaries())->toBeFalse();
});

// ─── The split itself ────────────────────────────────────────────────────────

it('keeps page order within a group and between groups', function () {
    $records = collect([
        new GpInvoice(['number' => 'a', 'customer' => 'Zeta']),
        new GpInvoice(['number' => 'b', 'customer' => 'Acme']),
        new GpInvoice(['number' => 'c', 'customer' => 'Zeta']),
    ]);

    $partitions = GroupPartitions::of($records, fn (GpInvoice $r): string => $r->customer);

    // Groups appear in the order they are first met, not sorted.
    expect($partitions->values())->toBe(['Zeta', 'Acme'])
        ->and($partitions->count())->toBe(2)
        ->and($partitions->get('Zeta')->pluck('number')->all())->toBe(['a', 'c']);
});

it('returns nothing for a group the page does not carry', function () {
    $partitions = GroupPartitions::of(
        collect([new GpInvoice(['number' => 'a', 'customer' => 'Acme'])]),
        fn (GpInvoice $r): string => $r->customer,
    );

    expect($partitions->get('Zeta'))->toBeEmpty();
});

it('describes only the exact record set it split', function () {
    $page = collect([new GpInvoice(['number' => 'a', 'customer' => 'Acme'])]);
    $partitions = GroupPartitions::of($page, fn (GpInvoice $r): string => $r->customer, $page);

    // An equal collection is not the same page: the memo hands back one object
    // per fetch, and a new object means a new fetch.
    expect($partitions->describes($page))->toBeTrue()
        ->and($partitions->describes(collect($page->all())))->toBeFalse()
        ->and($partitions->describes(null))->toBeFalse();
});
