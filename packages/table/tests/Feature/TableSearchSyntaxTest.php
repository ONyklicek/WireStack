<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Core\Query\Search\SearchConfig;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Exceptions\TableConfigurationException;
use NyonCode\WireTable\Table;

/*
 * The search box end to end: what a user types, and which rows come back.
 * Asserted against real rows, because every one of these lives in the SQL.
 */

class SearchSyntaxOrder extends Model
{
    protected $table = 'search_syntax_orders';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'placed_at' => 'datetime',
    ];
}

abstract class SearchSyntaxHost extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $this->configure(
            $table
                ->model(SearchSyntaxOrder::class)
                ->columns([
                    TextColumn::make('reference')->searchable(),
                    TextColumn::make('customer')->searchable(),
                    TextColumn::make('amount')->searchable(),
                    TextColumn::make('placed_at')->searchable(),
                ])
                ->searchable()
                ->paginated(false),
        );
    }

    abstract protected function configure(Table $table): Table;

    public function render()
    {
        return $this->getTableProperty();
    }
}

class LiteralSearchHost extends SearchSyntaxHost
{
    protected function configure(Table $table): Table
    {
        return $table;
    }
}

class TokenizedSearchHost extends SearchSyntaxHost
{
    protected function configure(Table $table): Table
    {
        return $table->search(fn (SearchConfig $s) => $s->tokenize());
    }
}

class RangeSearchHost extends SearchSyntaxHost
{
    protected function configure(Table $table): Table
    {
        return $table->search(fn (SearchConfig $s) => $s->tokenize()->ranges());
    }
}

/** Only the customer is searchable, so a term cannot match some other column. */
class CustomerOnlySearchHost extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(SearchSyntaxOrder::class)
            ->columns([
                TextColumn::make('reference'),
                TextColumn::make('customer')->searchable(),
            ])
            ->searchable()
            ->paginated(false);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

class WildcardSearchHost extends SearchSyntaxHost
{
    protected function configure(Table $table): Table
    {
        return $table->search(fn (SearchConfig $s) => $s->wildcards());
    }
}

beforeEach(function () {
    Schema::create('search_syntax_orders', function (Blueprint $table) {
        $table->id();
        $table->string('reference');
        $table->string('customer');
        $table->decimal('amount', 10, 2);
        $table->dateTime('placed_at');
        $table->timestamps();
    });

    SearchSyntaxOrder::create([
        'reference' => 'INV-001', 'customer' => 'Ada Lovelace',
        'amount' => 50.00, 'placed_at' => '2026-01-30 08:00:00',
    ]);
    SearchSyntaxOrder::create([
        'reference' => 'INV-002', 'customer' => 'Grace Hopper',
        'amount' => 150.00, 'placed_at' => '2026-01-31 23:30:00',
    ]);
    SearchSyntaxOrder::create([
        'reference' => 'INV-003 50%', 'customer' => 'Ada Byron',
        'amount' => 900.00, 'placed_at' => '2026-03-15 12:00:00',
    ]);
});

afterEach(fn () => Schema::dropIfExists('search_syntax_orders'));

// ── The default is unchanged ────────────────────────────────

it('matches an unconfigured term as one whole substring', function () {
    Livewire::test(LiteralSearchHost::class)
        ->set('tableState.search', 'Ada Lovelace')
        ->assertSee('INV-001')
        ->assertDontSee('INV-002');
});

it('does not split an unconfigured term on spaces', function () {
    // "Lovelace Ada" is not a substring of anything, so nothing matches.
    Livewire::test(LiteralSearchHost::class)
        ->set('tableState.search', 'Lovelace Ada')
        ->assertDontSee('INV-001')
        ->assertDontSee('INV-002');
});

it('does not read an operator out of an unconfigured term', function () {
    Livewire::test(LiteralSearchHost::class)
        ->set('tableState.search', '>100')
        ->assertDontSee('INV-001')
        ->assertDontSee('INV-002')
        ->assertDontSee('INV-003');
});

// ── Spaces ──────────────────────────────────────────────────

it('spans two columns when tokenizing', function () {
    // "INV-002" is in one column and "Grace" in another: neither column holds
    // the whole term, so this only matches once the term is split.
    Livewire::test(TokenizedSearchHost::class)
        ->set('tableState.search', 'INV-002 Grace')
        ->assertSee('Grace Hopper')
        ->assertDontSee('INV-001');
});

it('requires every word to match', function () {
    Livewire::test(TokenizedSearchHost::class)
        ->set('tableState.search', 'Ada Hopper')
        ->assertDontSee('INV-001')
        ->assertDontSee('INV-002');
});

it('matches words in either order', function () {
    Livewire::test(TokenizedSearchHost::class)
        ->set('tableState.search', 'Lovelace Ada')
        ->assertSee('INV-001')
        ->assertDontSee('INV-002');
});

it('keeps a quoted phrase together', function () {
    Livewire::test(TokenizedSearchHost::class)
        ->set('tableState.search', '"Ada Lovelace"')
        ->assertSee('INV-001')
        ->assertDontSee('INV-003');
});

// ── Ranges ──────────────────────────────────────────────────

it('compares a numeric column', function (string $typed, array $seen, array $unseen) {
    $test = Livewire::test(RangeSearchHost::class)->set('tableState.search', $typed);

    foreach ($seen as $reference) {
        $test->assertSee($reference);
    }

    foreach ($unseen as $reference) {
        $test->assertDontSee($reference);
    }
})->with([
    ['>100', ['INV-002'], ['INV-001']],
    ['<100', ['INV-001'], ['INV-002']],
    ['100..200', ['INV-002'], ['INV-001']],
    ['>=900', ['INV-003'], ['INV-001']],
]);

it('compares a date column, counting the whole day', function () {
    // Placed at 23:30 on the 31st — a naive comparison against midnight loses it.
    Livewire::test(RangeSearchHost::class)
        ->set('tableState.search', '2026-01-01..2026-01-31')
        ->assertSee('INV-001')
        ->assertSee('INV-002')
        ->assertDontSee('INV-003');
});

it('combines a word and a comparison', function () {
    Livewire::test(RangeSearchHost::class)
        ->set('tableState.search', 'Ada >100')
        ->assertSee('INV-003')
        ->assertDontSee('INV-001')
        ->assertDontSee('INV-002');
});

it('searches a comparison no column can answer as literal text', function () {
    // Nothing numeric matches ">nope", and it must not match everything either.
    Livewire::test(RangeSearchHost::class)
        ->set('tableState.search', '>nope')
        ->assertDontSee('INV-001')
        ->assertDontSee('INV-002')
        ->assertDontSee('INV-003');
});

// ── Wildcards and escaping ──────────────────────────────────

it('treats a typed percent as text, not as a wildcard', function () {
    // Unescaped, "%" matched every row and turned the search into a scan.
    Livewire::test(LiteralSearchHost::class)
        ->set('tableState.search', '50%')
        ->assertSee('INV-003')
        ->assertDontSee('INV-002');
});

it('lets a star stand for anything when wildcards are on', function () {
    Livewire::test(WildcardSearchHost::class)
        ->set('tableState.search', 'Ada*Byron')
        ->assertSee('INV-003')
        ->assertDontSee('INV-001');
});

it('keeps a star literal when wildcards are off', function () {
    Livewire::test(LiteralSearchHost::class)
        ->set('tableState.search', 'Ada*Byron')
        ->assertDontSee('INV-003');
});

// ── The term "0" ────────────────────────────────────────────

it('searches for the term "0" instead of discarding it', function () {
    // `! empty($search)` treated "0" as no search at all, so the table showed
    // every row rather than the ones containing a zero.
    SearchSyntaxOrder::create([
        'reference' => 'ZERO-CUSTOMER', 'customer' => '0 Industries',
        'amount' => 11.11, 'placed_at' => '2026-01-30 08:00:00',
    ]);

    Livewire::test(CustomerOnlySearchHost::class)
        ->set('tableState.search', '0')
        ->assertSee('0 Industries')
        ->assertDontSee('Grace Hopper');
});

it('treats a blank term as no search at all', function () {
    Livewire::test(CustomerOnlySearchHost::class)
        ->set('tableState.search', '   ')
        ->assertSee('Ada Lovelace')
        ->assertSee('Grace Hopper');
});

// ── Column::searchable([...]) on an ordinary column ─────────
//
// The list was stored but never read: only StackedColumn and SplitColumn
// declared HasSearchColumns, so a plain TextColumn searched its own name alone
// while the documentation described the list as working.

class ExplicitColumnsSearchHost extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(SearchSyntaxOrder::class)
            ->columns([
                TextColumn::make('reference')->searchable(['reference', 'customer']),
            ])
            ->searchable()
            ->paginated(false);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

it('searches every column listed on an ordinary column', function () {
    // "Hopper" lives only in `customer`, which is not the column's own name.
    Livewire::test(ExplicitColumnsSearchHost::class)
        ->set('tableState.search', 'Hopper')
        ->assertSee('INV-002')
        ->assertDontSee('INV-001');
});

it('still searches the column its own name', function () {
    Livewire::test(ExplicitColumnsSearchHost::class)
        ->set('tableState.search', 'INV-001')
        ->assertSee('INV-001')
        ->assertDontSee('INV-002');
});

// ── Column::searchAs() ──────────────────────────────────────

class UncastAmountOrder extends Model
{
    protected $table = 'search_syntax_orders';

    protected $guarded = [];
}

class DeclaredTypeSearchHost extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(UncastAmountOrder::class)
            ->columns([
                TextColumn::make('reference'),
                TextColumn::make('amount')->searchable()->searchAs('numeric'),
            ])
            ->searchable()
            ->search(fn (SearchConfig $s) => $s->ranges())
            ->paginated(false);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

it('compares a column whose type only the owner can declare', function () {
    // The model has no cast for `amount`, so nothing can be inferred from it.
    Livewire::test(DeclaredTypeSearchHost::class)
        ->set('tableState.search', '>100')
        ->assertSee('INV-002')
        ->assertSee('INV-003')
        ->assertDontSee('INV-001');
});

it('rejects a search value type it does not know', function () {
    expect(fn () => TextColumn::make('amount')->searchAs('quantity'))
        ->toThrow(TableConfigurationException::class);
});

// ── Structured codes: `8866 01..08` ─────────────────────────
//
// A code with a shared series and a padded numeric tail. The space inside the
// code is also what splits the term, so the range carries the series along and
// the code column puts the two halves back together.

class CodeOrder extends Model
{
    protected $table = 'search_code_orders';

    protected $guarded = [];

    public $timestamps = false;
}

class CodeSearchHost extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(CodeOrder::class)
            ->columns([
                TextColumn::make('reference')->searchable()->searchAs('code'),
                TextColumn::make('customer')->searchable(),
            ])
            ->searchable()
            ->search(fn (SearchConfig $s) => $s->tokenize()->ranges())
            ->paginated(false);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('search_code_orders', function (Blueprint $table) {
        $table->id();
        $table->string('reference');
        $table->string('customer');
    });

    foreach (['01', '02', '05', '08', '09', '12'] as $sequence) {
        CodeOrder::create([
            'reference' => "8866 {$sequence}",
            'customer' => "Zákazník {$sequence}",
        ]);
    }

    CodeOrder::create(['reference' => '9900 05', 'customer' => 'Jiná řada']);
});

afterEach(fn () => Schema::dropIfExists('search_code_orders'));

it('ranges over the sequence within one series', function () {
    $records = Livewire::test(CodeSearchHost::class)
        ->set('tableState.search', '8866 01..08')
        ->viewData('records');

    expect($records->pluck('reference')->all())
        ->toBe(['8866 01', '8866 02', '8866 05', '8866 08']);
});

it('excludes the same sequence in another series', function () {
    $records = Livewire::test(CodeSearchHost::class)
        ->set('tableState.search', '8866 01..08')
        ->viewData('records');

    expect($records->pluck('reference')->all())->not->toContain('9900 05');
});

it('excludes a sequence past the range', function () {
    $records = Livewire::test(CodeSearchHost::class)
        ->set('tableState.search', '8866 01..08')
        ->viewData('records');

    expect($records->pluck('reference')->all())
        ->not->toContain('8866 09')
        ->not->toContain('8866 12');
});

it('reads a one-sided comparison within the series', function () {
    $records = Livewire::test(CodeSearchHost::class)
        ->set('tableState.search', '8866 >=09')
        ->viewData('records');

    expect($records->pluck('reference')->all())->toBe(['8866 09', '8866 12']);
});

it('still finds one exact code', function () {
    $records = Livewire::test(CodeSearchHost::class)
        ->set('tableState.search', '8866 05')
        ->viewData('records');

    expect($records->pluck('reference')->all())->toBe(['8866 05']);
});

it('still searches a code column as plain text', function () {
    $records = Livewire::test(CodeSearchHost::class)
        ->set('tableState.search', '9900')
        ->viewData('records');

    expect($records->pluck('reference')->all())->toBe(['9900 05']);
});

it('narrows a series range by another word', function () {
    $records = Livewire::test(CodeSearchHost::class)
        ->set('tableState.search', '8866 01..08 Zákazník 05')
        ->viewData('records');

    expect($records->pluck('reference')->all())->toBe(['8866 05']);
});

// ── The declaration the search box cannot ask for ───────────
//
// `searchAs()` only says which comparisons a column can answer; without
// `ranges()` none can be typed, so the table would come back empty with nothing
// to explain it. That is refused at render, before anything is typed.

class GuardedSearchHost extends Component
{
    use WithTable;

    public string $type = 'code';

    public bool $columnSearchable = true;

    public bool $tableSearchable = true;

    public bool $ranges = false;

    public function table(Table $table): Table
    {
        $reference = TextColumn::make('reference')->searchAs($this->type);

        $table
            ->model(CodeOrder::class)
            ->columns([$reference->searchable($this->columnSearchable)])
            ->searchable($this->tableSearchable)
            ->paginated(false);

        return $this->ranges
            ? $table->search(fn (SearchConfig $s) => $s->ranges())
            : $table;
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

/**
 * The root cause of a render: the guard fires while the table is being built,
 * so Blade wraps it before it reaches the caller.
 */
function guardFailure(array $params = []): Throwable
{
    try {
        Livewire::test(GuardedSearchHost::class, $params);
    } catch (Throwable $error) {
        return $error->getPrevious() ?? $error;
    }

    throw new RuntimeException('The table rendered without refusing its search declaration.');
}

it('refuses a code column the search box cannot range over', function () {
    $error = guardFailure();

    expect($error)->toBeInstanceOf(TableConfigurationException::class)
        ->and($error->getMessage())->toContain("Column [reference] declares searchAs('code')");
});

it('names tokenize() as well, since a code carries its series as a word', function () {
    expect(guardFailure()->getMessage())->toContain('$s->tokenize()->ranges())');
});

it('asks only for ranges() where no series has to be rejoined', function () {
    expect(guardFailure(['type' => 'numeric']))
        ->getMessage()->toContain('$s->ranges())')
        ->getMessage()->not->toContain('tokenize');
});

it('accepts the declaration once the table reads ranges', function () {
    Livewire::test(GuardedSearchHost::class, ['ranges' => true])
        ->assertOk();
});

it('leaves a text declaration alone, since it asserts no comparison', function () {
    Livewire::test(GuardedSearchHost::class, ['type' => 'text'])
        ->assertOk();
});

it('leaves a column that is not searchable alone', function () {
    Livewire::test(GuardedSearchHost::class, ['columnSearchable' => false])
        ->assertOk();
});

it('leaves a table with no search box alone', function () {
    Livewire::test(GuardedSearchHost::class, ['tableSearchable' => false])
        ->assertOk();
});
