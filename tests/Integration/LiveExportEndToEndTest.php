<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Table;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
 * Whole-system test: exporting from a live component honours the component's
 * active state. exportTable() builds from getFilteredTableQuery(), so the file
 * must reflect the filter / search the user set through the lifecycle — not the
 * whole table. Exercised end to end: set state via Livewire, then export.
 */

class LexCompany extends Model
{
    protected $table = 'lex_companies';

    protected $guarded = [];

    public $timestamps = false;
}

class LexUser extends Model
{
    protected $table = 'lex_users';

    protected $guarded = [];

    public $timestamps = false;

    public function company(): BelongsTo
    {
        return $this->belongsTo(LexCompany::class, 'company_id');
    }
}

class LexTableComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(LexUser::class)
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('company.name')->label('Company'),
            ])
            ->filters([
                SelectFilter::make('company_id')->options(['1' => 'Acme', '2' => 'Evil']),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('lex_companies', function (Blueprint $t) {
        $t->id();
        $t->string('name');
    });
    Schema::create('lex_users', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->foreignId('company_id');
    });

    LexCompany::insert([['id' => 1, 'name' => 'Acme'], ['id' => 2, 'name' => 'Evil']]);
    LexUser::insert([
        ['name' => 'Ann', 'company_id' => 1],
        ['name' => 'Bob', 'company_id' => 2],
        ['name' => 'Cara', 'company_id' => 1],
    ]);
});

afterEach(function () {
    Schema::dropIfExists('lex_users');
    Schema::dropIfExists('lex_companies');
});

function lexCapture(StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return ob_get_clean() ?: '';
}

it('exports the whole table when no filter is active', function () {
    $response = Livewire::test(LexTableComponent::class)->instance()->exportTable('csv');
    $csv = lexCapture($response);

    expect($csv)->toContain('Ann')->toContain('Bob')->toContain('Cara');
});

it('exports only the rows matching the active filter', function () {
    $response = Livewire::test(LexTableComponent::class)
        ->set('tableState.filters.company_id', ['value' => 1])
        ->instance()
        ->exportTable('csv');

    $csv = lexCapture($response);

    expect($csv)->toContain('Ann')      // company 1
        ->toContain('Cara')             // company 1
        ->not->toContain('Bob');        // company 2 filtered out
});

it('exports only the rows matching the active search', function () {
    $response = Livewire::test(LexTableComponent::class)
        ->set('tableState.search', 'Ann')
        ->instance()
        ->exportTable('csv');

    $csv = lexCapture($response);

    expect($csv)->toContain('Ann')
        ->not->toContain('Bob')
        ->not->toContain('Cara');
});
