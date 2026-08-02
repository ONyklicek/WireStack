<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Exceptions\TableConfigurationException;
use NyonCode\WireTable\Table;

/*
 * "All" as a page size.
 *
 * The word never survives past Table::perPageOptions() — everything downstream
 * (the select's wire:model, the query-string parameter, the cache key,
 * normalizePerPage()) compares page sizes strictly as integers, so 'all' is
 * stored as the PER_PAGE_ALL sentinel and the round trip is what these tests
 * are really about.
 */

class PpaPost extends Model
{
    protected $table = 'ppa_posts';

    protected $guarded = [];

    public $timestamps = false;
}

class PpaHost extends Component
{
    use WithTable;

    public bool $offerAll = true;

    public function table(Table $table): Table
    {
        return $table
            ->model(PpaPost::class)
            ->columns([Column::make('title')])
            // Deliberately smaller than the seeded row count, so a page that
            // holds everything is distinguishable from the default one.
            ->perPage(2)
            ->perPageOptions($this->offerAll ? [2, 5, 'all'] : [2, 5]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('ppa_posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
    });

    foreach (range(1, 7) as $i) {
        PpaPost::create(['title' => "Post {$i}"]);
    }
});

afterEach(function () {
    Schema::dropIfExists('ppa_posts');
});

// ─── The vocabulary ──────────────────────────────────────────────────────────

it('stores the word as the sentinel', function () {
    $table = Table::make()->perPageOptions([10, 25, 'all']);

    expect($table->getPerPageOptions())->toBe([10, 25, Table::PER_PAGE_ALL]);
});

it('takes the sentinel constant directly too', function () {
    expect(Table::make()->perPageOptions([10, Table::PER_PAGE_ALL])->getPerPageOptions())
        ->toBe([10, Table::PER_PAGE_ALL]);
});

it('does not care how the word was capitalized or spaced', function (string $word) {
    expect(Table::make()->perPageOptions([10, $word])->getPerPageOptions())
        ->toBe([10, Table::PER_PAGE_ALL]);
})->with(['all', 'All', 'ALL', ' all ']);

it('keeps accepting a page size written as a numeric string', function () {
    expect(Table::make()->perPageOptions([10, '25'])->getPerPageOptions())->toBe([10, 25]);
});

it('refuses a page size that is neither a number nor the word', function () {
    Table::make()->perPageOptions([10, 'lots']);
})->throws(TableConfigurationException::class, 'Page size [lots]');

it('is not offered unless the table asks for it', function () {
    // The shipped default must never put the whole-table read one click away.
    expect(Table::make()->getPerPageOptions())->toBe([10, 25, 50, 100])
        ->and(Table::make()->getPerPageOptions())->not->toContain(Table::PER_PAGE_ALL);
});

it('sorts last however it was declared', function () {
    // Its sentinel is negative, so sort() alone would put it first — and "all"
    // is not a size that belongs in the middle of an ascending list anyway.
    expect(Table::make()->perPageOptions(['all', 50, 10])->getPerPageOptions())
        ->toBe([10, 50, Table::PER_PAGE_ALL]);
});

it('can be the table\'s own default page size', function () {
    $table = Table::make()->perPage('all')->perPageOptions([10, 25, 'all']);

    expect($table->getPerPage())->toBe(Table::PER_PAGE_ALL)
        ->and($table->getPerPageOptions())->toBe([10, 25, Table::PER_PAGE_ALL]);
});

it('joins the options when it is the default but was not listed', function () {
    // Same rule that already keeps perPage(3) selectable: the configured size
    // is always one of the offered ones.
    expect(Table::make()->perPage(Table::PER_PAGE_ALL)->getPerPageOptions())
        ->toBe([10, 25, 50, 100, Table::PER_PAGE_ALL]);
});

// ─── The round trip ──────────────────────────────────────────────────────────

it('puts every record on one page', function () {
    // The table pages by 2, so "Post 7" is proof the sentinel was honoured and
    // not quietly clamped back to the default.
    Livewire::test(PpaHost::class)
        ->assertDontSee('Post 7')
        ->set('tableState.pagination.perPage', Table::PER_PAGE_ALL)
        ->assertSee('Post 1')
        ->assertSee('Post 7');
});

it('leaves the paginator one honest page, not a negative one', function () {
    $host = Livewire::test(PpaHost::class)
        ->set('tableState.pagination.perPage', Table::PER_PAGE_ALL)
        ->instance();

    $records = $host->getTableRecords();

    expect($records->total())->toBe(7)
        ->and($records->lastPage())->toBe(1)
        ->and($records->hasPages())->toBeFalse()
        ->and($records->count())->toBe(7);
});

it('survives the select posting it back as a string', function () {
    // wire:model hands back "-1", and normalizePerPage() is what turns it into
    // an int before it reaches the cache key or the query string.
    $host = Livewire::test(PpaHost::class)
        ->set('tableState.pagination.perPage', '-1')
        ->instance();

    expect($host->tableState->get('pagination.perPage'))->toBe(Table::PER_PAGE_ALL)
        ->and($host->getTableRecords()->count())->toBe(7);
});

it('does not divide by zero on an empty table', function () {
    PpaPost::query()->delete();

    $records = Livewire::test(PpaHost::class)
        ->set('tableState.pagination.perPage', Table::PER_PAGE_ALL)
        ->instance()
        ->getTableRecords();

    expect($records->total())->toBe(0)
        ->and($records->lastPage())->toBe(1)
        ->and($records->count())->toBe(0);
});

it('refuses the sentinel from a table that never offered it', function () {
    // The clamp that stops a forged `perPage: 500000` is the same one gating
    // "all": not offered, so it falls back to the configured default.
    $host = Livewire::test(PpaHost::class)
        ->set('offerAll', false)
        ->set('tableState.pagination.perPage', Table::PER_PAGE_ALL)
        ->instance();

    expect($host->tableState->get('pagination.perPage'))->toBe(2)
        ->and($host->getTableRecords()->count())->toBe(2);
});

it('renders the option with a word instead of the sentinel', function () {
    Livewire::test(PpaHost::class)
        ->assertSeeHtml('<option value="-1"')
        ->assertSee('All')
        ->assertDontSeeHtml('>-1</option>');
});
