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
 * What each pagination mode actually renders.
 *
 * The footer used to branch on `instanceof LengthAwarePaginator` alone, so both
 * other modes fell down the "not paginated" path: **no links at all**, and a
 * "showing 1 – N of N" counting only the page in front of you. Both modes are
 * really constructed by `WithTable::paginateQuery()` and both had tests — of the
 * query, not of the chrome — so it went unseen.
 *
 * Simple pagination is the case worth fixing: it trades the COUNT query away, so
 * it has no `total()`, but `firstItem()`/`lastItem()`/`hasMorePages()` all work.
 *
 * Cursor pagination needed navigation built for it: Livewire's pagination trait
 * is page-based (`previousPage`/`nextPage`/`gotoPage`) with no cursor
 * equivalent, so nothing could drive a `CursorPaginator` — its rows paged
 * correctly but only through a `cursor` query parameter nobody set. The cursor
 * now lives in table state and the controls hand back the encoded cursor the
 * paginator itself produced.
 */
class PmrRow extends Model
{
    protected $table = 'pmr_rows';

    protected $guarded = [];

    public $timestamps = false;
}

class PmrHost extends Component
{
    use WithTable;

    public string $mode = 'standard';

    public function table(Table $table): Table
    {
        $table->model(PmrRow::class)->paginated()->perPage(3)
            ->columns([TextColumn::make('name')]);

        return match ($this->mode) {
            'simple' => $table->simplePagination(),
            'cursor' => $table->cursorPagination(),
            default => $table,
        };
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('pmr_rows', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    for ($i = 1; $i <= 11; $i++) {
        PmrRow::create(['name' => 'Row '.$i]);
    }
});

afterEach(fn () => Schema::dropIfExists('pmr_rows'));

it('offers numbered pages and a total when the paginator knows one', function () {
    $html = Livewire::test(PmrHost::class, ['mode' => 'standard'])->html();

    expect($html)->toContain('data-testid="table-page-next"')
        ->toContain('data-testid="table-page-2"')
        // "showing 1 - 3 of 11"
        ->toContain('>11<');
});

it('offers prev and next for a simple paginator, which had none at all', function () {
    // The fix. Before this, a simple-paginated table rendered no way to leave
    // page one.
    $html = Livewire::test(PmrHost::class, ['mode' => 'simple'])->html();

    expect($html)->toContain('data-testid="table-page-next"')
        ->toContain('data-testid="table-page-current"')
        // ...and no numbered pages, because it cannot know how many there are.
        ->not->toContain('data-testid="table-page-2"');
});

it('does not claim a total a simple paginator cannot know', function () {
    // It used to print "of 3" — the size of the page, presented as the size of
    // the result set.
    $html = Livewire::test(PmrHost::class, ['mode' => 'simple'])->html();

    expect($html)->toContain(__('wire-table::messages.showing'))
        ->not->toContain(__('wire-table::messages.of').' <span');
});

it('says nothing at all about the range under cursor pagination', function () {
    // No total, no offsets. Silence beats a confident wrong number.
    $html = Livewire::test(PmrHost::class, ['mode' => 'cursor'])->html();

    expect($html)->not->toContain(__('wire-table::messages.showing'));
});

it('offers cursor controls, which nothing in Livewire could drive before', function () {
    $html = Livewire::test(PmrHost::class, ['mode' => 'cursor'])->html();

    expect($html)->toContain('data-testid="table-page-next"')
        ->toContain('setTableCursor(')
        // Page one, so there is nowhere back to go.
        ->not->toContain('data-testid="table-page-prev"');
});

it('actually pages a cursor table forwards and back', function () {
    // The controls have to move the rows, not merely appear. The cursor is opaque,
    // so the test drives the button's own payload rather than inventing one.
    $component = Livewire::test(PmrHost::class, ['mode' => 'cursor']);

    expect($component->html())->toContain('Row 1')->not->toContain('Row 4');

    $next = $component->instance()->getTableRecords()->nextCursor()->encode();
    $component->call('setTableCursor', $next);

    expect($component->html())->toContain('Row 4')->not->toContain('Row 1');

    $back = $component->instance()->getTableRecords()->previousCursor()->encode();
    $component->call('setTableCursor', $back);

    expect($component->html())->toContain('Row 1')->not->toContain('Row 4');
});

it('drops the cursor when the set it pointed into changes', function () {
    // A cursor is a position in an ordering. Narrowing or re-sorting the set
    // makes it meaningless, so it cannot survive the way a page number nominally
    // can — the user would land on an empty page.
    $component = Livewire::test(PmrHost::class, ['mode' => 'cursor']);

    $next = $component->instance()->getTableRecords()->nextCursor()->encode();
    $component->call('setTableCursor', $next);
    expect($component->instance()->tableState->get('pagination.cursor'))->not->toBeNull();

    $component->set('tableState.sort.column', 'name');

    expect($component->instance()->tableState->get('pagination.cursor'))->toBeNull();
});

it('still pages a simple paginator through the second page', function () {
    // The links have to reach the host, not merely render.
    $component = Livewire::test(PmrHost::class, ['mode' => 'simple']);

    expect($component->html())->toContain('Row 1')->not->toContain('Row 4');

    $component->call('nextPage');

    expect($component->html())->toContain('Row 4')->not->toContain('Row 1');
});
