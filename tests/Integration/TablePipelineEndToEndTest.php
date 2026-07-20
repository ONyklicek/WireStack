<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Filters\NumberRangeFilter;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Table;

/*
 * Whole-system test: a real Livewire table (table + forms + core booted together
 * by the Integration TestCase) exercised through the full component lifecycle —
 * search, filter, sort, grouping, summaries and a row action all active at once —
 * asserting the RENDERED output rather than any single service in isolation. This
 * is where the render-seam bugs live (a cell view, a group header, a footer),
 * which buildQuery-level tests never reach.
 */

class EteCompany extends Model
{
    protected $table = 'ete_companies';

    protected $guarded = [];

    public $timestamps = false;
}

class EteUser extends Model
{
    protected $table = 'ete_users';

    protected $guarded = [];

    public $timestamps = false;

    public function company(): BelongsTo
    {
        return $this->belongsTo(EteCompany::class, 'company_id');
    }
}

class EteTableComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(EteUser::class)
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('company.name')->label('Company'),
                TextColumn::make('department'),
                TextColumn::make('salary')->summarizeSum('Total'),
            ])
            ->filters([
                SelectFilter::make('company_id')->options(['1' => 'Acme', '2' => 'Evil']),
                NumberRangeFilter::make('age'),
            ])
            ->groupBy('department')
            ->actions([
                Action::make('edit')->label('Edit')->action(fn () => null),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('ete_companies', function (Blueprint $t) {
        $t->id();
        $t->string('name');
    });
    Schema::create('ete_users', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->integer('age');
        $t->integer('salary');
        $t->string('department');
        $t->foreignId('company_id');
    });

    EteCompany::insert([['id' => 1, 'name' => 'Acme'], ['id' => 2, 'name' => 'Evil']]);
    EteUser::insert([
        ['name' => 'Ann', 'age' => 30, 'salary' => 100, 'department' => 'Sales', 'company_id' => 1],
        ['name' => 'Bob', 'age' => 40, 'salary' => 200, 'department' => 'Sales', 'company_id' => 2],
        ['name' => 'Cara', 'age' => 50, 'salary' => 300, 'department' => 'Ops', 'company_id' => 1],
    ]);
});

afterEach(function () {
    Schema::dropIfExists('ete_users');
    Schema::dropIfExists('ete_companies');
});

it('renders the whole table with columns, relation, grouping, summary and a row action', function () {
    Livewire::test(EteTableComponent::class)
        ->assertSee('Ann')
        ->assertSee('Bob')
        ->assertSee('Cara')
        ->assertSee('Acme')            // relation column rendered
        ->assertSee('Sales')           // group header
        ->assertSee('Ops')
        ->assertSee('600')             // footer grand total (100+200+300)
        ->assertSeeHtml('data-testid="action-edit"'); // row action button rendered
});

it('applies a live search through the rendered table', function () {
    Livewire::test(EteTableComponent::class)
        ->set('tableState.search', 'Ann')
        ->assertSee('Ann')
        ->assertDontSee('Bob')
        ->assertDontSee('Cara');
});

it('applies a select filter through the rendered table', function () {
    Livewire::test(EteTableComponent::class)
        ->set('tableState.filters.company_id', ['value' => 1])
        ->assertSee('Ann')     // company 1
        ->assertSee('Cara')    // company 1
        ->assertDontSee('Bob'); // company 2
});

it('applies a number-range filter through the rendered table', function () {
    Livewire::test(EteTableComponent::class)
        ->set('tableState.filters.age', ['min' => 45, 'max' => ''])
        ->assertSee('Cara')     // age 50
        ->assertDontSee('Ann')  // age 30
        ->assertDontSee('Bob'); // age 40
});

it('sorts the rendered rows and combines a filter with a sort', function () {
    // Sort by name desc within the whole set.
    Livewire::test(EteTableComponent::class)
        ->set('tableState.sort.column', 'name')
        ->set('tableState.sort.direction', 'desc')
        ->assertSeeInOrder(['Cara', 'Bob', 'Ann']);

    // Filter + sort together: only company 1, name desc → Cara before Ann, no Bob.
    Livewire::test(EteTableComponent::class)
        ->set('tableState.filters.company_id', ['value' => 1])
        ->set('tableState.sort.column', 'name')
        ->set('tableState.sort.direction', 'desc')
        ->assertSeeInOrder(['Cara', 'Ann'])
        ->assertDontSee('Bob');
});
