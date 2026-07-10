<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

class MsfUser extends Model
{
    protected $table = 'msf_users';

    protected $guarded = [];

    public $timestamps = false;
}

class MsfComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(MsfUser::class)
            ->paginated(false)
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('role')->filterAsMultiSelect([
                    'admin' => 'Admin',
                    'editor' => 'Editor',
                    'viewer' => 'Viewer',
                ]),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('msf_users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('role')->nullable();
    });
    MsfUser::insert([
        ['name' => 'Ann', 'role' => 'admin'],
        ['name' => 'Bob', 'role' => 'editor'],
        ['name' => 'Cara', 'role' => 'viewer'],
    ]);
});

afterEach(fn () => Schema::dropIfExists('msf_users'));

it('seeds a multi-select column filter as an empty array on mount', function () {
    $component = new MsfComponent;
    $component->mountWithTable();

    // Must be an array (not null) so Livewire binds the header checkboxes as a
    // group and toggles membership instead of replacing a scalar.
    expect($component->tableState->get('columnFilters.role'))->toBe([]);
});

it('filters rows to the selected values with whereIn', function () {
    $component = new MsfComponent;
    $component->mountWithTable();

    $component->tableState->set('columnFilters.role', ['admin', 'viewer']);

    $names = $component->getTableRecords()->pluck('name')->all();

    expect($names)->toContain('Ann')->toContain('Cara')->not->toContain('Bob');
});

it('shows every row when the selection is empty', function () {
    $component = new MsfComponent;
    $component->mountWithTable();

    $component->tableState->set('columnFilters.role', []);

    expect($component->getTableRecords())->toHaveCount(3);
});
