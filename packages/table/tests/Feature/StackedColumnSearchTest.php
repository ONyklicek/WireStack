<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\StackedColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * ->searchable(['name', 'email']) set a column list the planner never read: it
 * planned one clause from the column's own name, so searching a stacked cell
 * only ever matched its first attribute. Asserted against real rows, because the
 * whole change lives in the SQL.
 */

class StackedSearchUser extends Model
{
    protected $table = 'stacked_search_users';

    protected $guarded = [];
}

class StackedSearchHost extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(StackedSearchUser::class)
            ->columns([
                StackedColumn::make('name')
                    ->primary('name')
                    ->secondary('email')
                    ->searchable(['name', 'email']),
            ])
            ->searchable()
            ->paginated(false);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('stacked_search_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->timestamps();
    });

    StackedSearchUser::create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
    StackedSearchUser::create(['name' => 'Grace Hopper', 'email' => 'grace@navy.mil']);
});

afterEach(fn () => Schema::dropIfExists('stacked_search_users'));

it('still matches the column its own name', function () {
    Livewire::test(StackedSearchHost::class)
        ->set('tableState.search', 'Ada')
        ->assertSee('Ada Lovelace')
        ->assertDontSee('Grace Hopper');
});

// The regression: 'navy' lives only in the second listed column.
it('matches a column listed in searchable() that is not its own', function () {
    Livewire::test(StackedSearchHost::class)
        ->set('tableState.search', 'navy')
        ->assertSee('Grace Hopper')
        ->assertDontSee('Ada Lovelace');
});

it('still excludes rows matching neither column', function () {
    Livewire::test(StackedSearchHost::class)
        ->set('tableState.search', 'nobody')
        ->assertDontSee('Ada Lovelace')
        ->assertDontSee('Grace Hopper');
});
