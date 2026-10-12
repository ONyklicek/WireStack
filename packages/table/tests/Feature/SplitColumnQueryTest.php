<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\SplitColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * A split whose children were all ->searchable() was not searched at all: the
 * query planner asks a column for the Searchable capability, and the split only
 * overrode isSearchable(). These run the whole table, so they see the query the
 * search box actually produces.
 */

class SplitQueryRow extends Model
{
    protected $table = 'split_query_rows';

    protected $guarded = [];
}

class SplitQueryHost extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(SplitQueryRow::class)
            ->columns([
                TextColumn::make('name'),
                SplitColumn::make('pay_status')
                    ->vertical()
                    ->columns([
                        TextColumn::make('paid_amount')->numeric()->money('Kč')->searchable()->sortable(),
                        TextColumn::make('total_amount')->numeric()->money('Kč')->prefix('z ')->searchable(),
                    ]),
            ])
            ->paginated(false);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::dropIfExists('split_query_rows');
    Schema::create('split_query_rows', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->decimal('total_amount', 10, 2);
        $table->decimal('paid_amount', 10, 2);
        $table->timestamps();
    });

    SplitQueryRow::create(['name' => 'Alpha', 'total_amount' => 1000, 'paid_amount' => 500]);
    SplitQueryRow::create(['name' => 'Bravo', 'total_amount' => 200, 'paid_amount' => 900]);
});

it('searches across the children of a split that is not itself searchable', function () {
    $html = Livewire::test(SplitQueryHost::class)
        ->set('tableState.search', '900')
        ->html();

    expect($html)->toContain('Bravo')
        ->and($html)->not->toContain('Alpha');
});

it('reaches the second child as well as the first', function () {
    $html = Livewire::test(SplitQueryHost::class)
        ->set('tableState.search', '1000')
        ->html();

    expect($html)->toContain('Alpha')
        ->and($html)->not->toContain('Bravo');
});

it('stacks the two amounts as two lines', function () {
    $html = Livewire::test(SplitQueryHost::class)->html();

    expect($html)->toContain('<div class="">500 Kč</div><div class="">z 1 000 Kč</div>');
});

it('sorts by the first sortable child', function () {
    $html = Livewire::test(SplitQueryHost::class)
        ->call('sortTable', 'pay_status')
        ->html();

    expect(strpos($html, 'Alpha'))->toBeLessThan(strpos($html, 'Bravo'));
});
