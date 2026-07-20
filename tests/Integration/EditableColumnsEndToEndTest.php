<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Foundation\Support\RecordVersion;
use NyonCode\WireTable\Columns\SelectColumn;
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Columns\ToggleColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * Whole-system test: inline cell editing driven through the live Livewire
 * component (call('updateTableCell', ...)), crossing the request/response
 * boundary — skipRender, validation, the optimistic-lock version — that a direct
 * method call never exercises. Covers text / toggle / select columns, a
 * validation failure, and a stale-version conflict.
 */

class EclUser extends Model
{
    protected $table = 'ecl_users';

    protected $guarded = [];

    protected $casts = ['active' => 'boolean'];
    // Timestamped: updated_at drives the optimistic-lock version.
}

class EclTableComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(EclUser::class)
            ->paginated(false)
            ->columns([
                TextInputColumn::make('name')->required(),
                ToggleColumn::make('active'),
                SelectColumn::make('status')->options(['open' => 'Open', 'closed' => 'Closed']),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('ecl_users', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->boolean('active')->default(false);
        $t->string('status')->default('open');
        $t->timestamps();
    });

    EclUser::create(['name' => 'Alice', 'active' => false, 'status' => 'open']);
});

afterEach(fn () => Schema::dropIfExists('ecl_users'));

it('commits a text cell edit through the live component', function () {
    Livewire::test(EclTableComponent::class)
        ->call('updateTableCell', '1', 'name', 'Alicia')
        ->assertReturned(fn ($r) => $r['success'] === true && $r['version'] !== null);

    expect(EclUser::find(1)->name)->toBe('Alicia');
});

it('commits toggle and select cell edits through the live component', function () {
    Livewire::test(EclTableComponent::class)
        ->call('updateTableCell', '1', 'active', true)
        ->assertReturned(fn ($r) => $r['success'] === true)
        ->call('updateTableCell', '1', 'status', 'closed')
        ->assertReturned(fn ($r) => $r['success'] === true);

    $fresh = EclUser::find(1);
    expect($fresh->active)->toBeTrue()
        ->and($fresh->status)->toBe('closed');
});

it('rejects an edit that fails validation and leaves the record unchanged', function () {
    Livewire::test(EclTableComponent::class)
        ->call('updateTableCell', '1', 'name', '')   // required
        ->assertReturned(fn ($r) => $r['success'] === false);

    expect(EclUser::find(1)->name)->toBe('Alice');
});

it('rejects a stale-version write (optimistic lock conflict)', function () {
    $record = EclUser::find(1);
    $staleVersion = app(RecordVersion::class)->stamp($record);

    // A concurrent edit moves the record's version forward.
    $record->forceFill(['updated_at' => Carbon::now()->addMinutes(5)])->saveQuietly();

    Livewire::test(EclTableComponent::class)
        ->call('updateTableCell', '1', 'name', 'Hacked', $staleVersion)
        ->assertReturned(fn ($r) => $r['success'] === false);

    expect(EclUser::find(1)->name)->toBe('Alice'); // write rejected
});
