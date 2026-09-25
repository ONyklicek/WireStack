<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\BulkAction;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Filters\TrashedFilter;
use NyonCode\WireTable\Table;

/*
 * A row a TrashedFilter brought into the list has to be found again when its
 * action is clicked.
 *
 * The listing narrows its query through the filter; an action looks its record
 * up by key in the base query, whose soft-delete scope hides trashed rows. So a
 * Restore button on a trashed row found nothing and did nothing — and did so
 * silently. A table carrying a TrashedFilter now looks keys up among the
 * trashed too; one without keeps the scope, because it never lists them.
 */
class TraNote extends Model
{
    use SoftDeletes;

    protected $table = 'tra_notes';

    protected $guarded = [];

    public $timestamps = false;
}

class TraNotesTable extends Component
{
    use WithTable;

    public static bool $withFilter = true;

    /** @var array<int, mixed> */
    public array $touched = [];

    public function table(Table $table): Table
    {
        return $table
            ->model(TraNote::class)
            ->paginated(false)
            ->columns([TextColumn::make('body')])
            ->filters(static::$withFilter ? [TrashedFilter::make('trashed')] : [])
            ->actions([
                Action::make('restore')->action(fn (Model $record) => $record->restore()),
                Action::make('confirmRestore')->requiresConfirmation()->action(fn (Model $record) => $record->restore()),
            ])
            ->bulkActions([
                BulkAction::make('collect')->action(fn ($records) => $this->touched = $records->pluck('id')->sort()->values()->all()),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('tra_notes', function (Blueprint $table) {
        $table->id();
        $table->string('body');
        $table->softDeletes();
    });

    TraNote::query()->create(['body' => 'Live']);
    TraNote::query()->create(['body' => 'Binned'])->delete();

    TraNotesTable::$withFilter = true;
});

it('runs a row action on a trashed row the filter listed', function () {
    Livewire::test(TraNotesTable::class)->call('executeTableAction', '2', 'restore');

    expect(TraNote::query()->find(2))->not->toBeNull();
});

it('opens and runs a confirmed row action on a trashed row', function () {
    Livewire::test(TraNotesTable::class)
        ->call('openActionModal', '2', 'confirmRestore')
        ->call('submitActionModal');

    expect(TraNote::query()->find(2))->not->toBeNull();
});

it('hands a bulk action the trashed rows that were ticked', function () {
    $test = Livewire::test(TraNotesTable::class)
        ->call('toggleRecordSelection', '1')
        ->call('toggleRecordSelection', '2')
        ->call('executeBulkActionWithData', 'collect', []);

    expect($test->get('touched'))->toBe([1, 2]);
});

it('keeps the soft-delete scope on a table that never lists trashed rows', function () {
    TraNotesTable::$withFilter = false;

    Livewire::test(TraNotesTable::class)->call('executeTableAction', '2', 'restore');

    expect(TraNote::query()->find(2))->toBeNull();
});
