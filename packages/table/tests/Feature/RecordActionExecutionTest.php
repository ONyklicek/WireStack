<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Support\RecordAction;
use NyonCode\WireTable\Table;

class RecActRow extends Model
{
    protected $table = 'rec_act_rows';

    protected $guarded = [];

    public $timestamps = false;
}

class RecordActionComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(RecActRow::class)
            ->paginated(false)
            ->columns([TextColumn::make('name')])
            ->recordActions([
                // Behaviour-only: its own callback, no toolbar button, not in actions().
                RecordAction::make(
                    Action::make('touch')->action(fn (RecActRow $record) => $record->update(['name' => 'touched']))
                )->onDoubleClick(),

                // Guarded: hidden, so canExecute() is false — the endpoint must refuse it.
                RecordAction::make(
                    Action::make('guarded')
                        ->visible(false)
                        ->action(fn (RecActRow $record) => $record->update(['name' => 'should-not-happen']))
                )->onClick(),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('rec_act_rows', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
    });
    RecActRow::insert([['id' => 1, 'name' => 'Alpha']]);
});

afterEach(fn () => Schema::dropIfExists('rec_act_rows'));

it('executes a behaviour-only record action through the existing endpoint', function () {
    Livewire::test(RecordActionComponent::class)
        ->call('executeTableAction', '1', 'touch');

    expect(RecActRow::find(1)->name)->toBe('touched');
});

it('refuses a record action that cannot execute on the record', function () {
    Livewire::test(RecordActionComponent::class)
        ->call('executeTableAction', '1', 'guarded');

    expect(RecActRow::find(1)->name)->toBe('Alpha');
});
