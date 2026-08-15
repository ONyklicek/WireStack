<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/**
 * One set of totals, one aggregate batch — however many places show them.
 *
 * A stacked table renders its totals twice into the same document: the desktop
 * `<tfoot>` inside the `<table>`, and a card footer for the width where that
 * table is hidden. Both halves are always in the document; only CSS decides
 * which is seen. Each include asked the host to compute the totals, so the whole
 * `SummaryBatch` ran twice per render and issued byte-identical SQL — invisible
 * in the markup, which is why no render test could see it, and paid on every
 * render of every stacked table with a summary.
 *
 * On the tables this is for, that aggregate is over the entire filtered set.
 */
class ScoRow extends Model
{
    protected $table = 'sco_rows';

    protected $guarded = [];

    public $timestamps = false;
}

class ScoHost extends Component
{
    use WithTable;

    public bool $stacked = true;

    public function table(Table $table): Table
    {
        $table->model(ScoRow::class)->paginated()->perPage(10)->columns([
            TextColumn::make('name'),
            TextColumn::make('amount')->summarizeSum()->summarizeAvg(),
        ]);

        return $this->stacked ? $table->stackedOnMobile() : $table;
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('sco_rows', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->integer('amount');
    });

    $rows = [];
    for ($i = 1; $i <= 30; $i++) {
        $rows[] = ['name' => 'Row '.$i, 'amount' => $i];
    }
    ScoRow::insert($rows);
});

afterEach(fn () => Schema::dropIfExists('sco_rows'));

/** @return array{0: string, 1: int} */
function scoRender(bool $stacked): array
{
    $aggregates = 0;

    DB::listen(function ($query) use (&$aggregates) {
        if (str_contains(strtoupper($query->sql), 'SUM(')) {
            $aggregates++;
        }
    });

    $html = Livewire::test(ScoHost::class, ['stacked' => $stacked])->html();

    return [$html, $aggregates];
}

it('runs the aggregate batch once, not once per place the totals appear', function () {
    [$html, $aggregates] = scoRender(stacked: true);

    // Both footers are in the document…
    expect($html)->toContain('data-testid="summary-scope-query"')
        // …and between them they cost one aggregate query, not two.
        ->and($aggregates)->toBe(1);
});

it('costs the same on a table that renders the totals once', function () {
    // The control: an unstacked table only ever had one footer, so the memo must
    // not have changed what it does — only how often it is asked.
    [, $aggregates] = scoRender(stacked: false);

    expect($aggregates)->toBe(1);
});

it('still answers each scope separately', function () {
    // Memoised per scope, not once per render: switching the toggle has to
    // recompute, or the footer would show the totals for the scope before it.
    $component = Livewire::test(ScoHost::class, ['stacked' => false]);

    $query = $component->instance()->computeTableSummaries('query');
    $page = $component->instance()->computeTableSummaries('page');

    // 30 rows summed against a 10-row page, so the two cannot agree.
    expect($query)->not->toBe($page)
        ->and($query['amount'][0]['value'])->not->toBe($page['amount'][0]['value']);
});

it('forgets the totals between renders', function () {
    // A memo living longer than one render would hand the second render the
    // first one's numbers — after a write, that is a wrong total on screen.
    $component = Livewire::test(ScoHost::class, ['stacked' => false]);

    $before = $component->instance()->computeTableSummaries('query')['amount'][0]['value'];

    ScoRow::create(['name' => 'Row 31', 'amount' => 1000]);

    // A new render begins, which is what drops the memo.
    $component->instance()->getTableProperty();

    expect($component->instance()->computeTableSummaries('query')['amount'][0]['value'])
        ->not->toBe($before);
});
