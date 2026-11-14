<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\BulkAction;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Table;
use PHPUnit\Framework\AssertionFailedError;

/*
 * The test helpers on Livewire::test(): each one goes through the endpoint the
 * browser uses, and the assertions read the page the table would render.
 */
class TtmInvoice extends Model
{
    protected $table = 'ttm_invoices';

    protected $guarded = [];

    public $timestamps = false;
}

class TtmTable extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(TtmInvoice::class)
            ->perPage(2)
            ->searchable()
            ->columns([TextColumn::make('number')->searchable()->sortable(), TextColumn::make('status')])
            ->filters([SelectFilter::make('status')->options(['paid' => 'Paid', 'draft' => 'Draft'])])
            ->actions([
                Action::make('pay')->action(fn (TtmInvoice $record) => $record->update(['status' => 'paid'])),
                Action::make('note')
                    ->form([TextInput::make('note')])
                    ->action(fn (TtmInvoice $record, array $data) => $record->update(['note' => $data['note']])),
            ])
            ->bulkActions([
                BulkAction::make('void')
                    ->form([TextInput::make('reason')])
                    ->action(fn ($records, array $data) => $records->each->update(['status' => 'void', 'note' => $data['reason']])),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    Schema::create('ttm_invoices', function (Blueprint $table) {
        $table->id();
        $table->string('number');
        $table->string('status');
        $table->string('note')->nullable();
    });

    TtmInvoice::insert([
        ['id' => 1, 'number' => 'INV-1', 'status' => 'draft'],
        ['id' => 2, 'number' => 'INV-2', 'status' => 'paid'],
        ['id' => 3, 'number' => 'INV-3', 'status' => 'draft'],
    ]);
});

afterEach(function () {
    Schema::dropIfExists('ttm_invoices');
});

it('sees the records on the current page, by model or key, and in order', function () {
    Livewire::test(TtmTable::class)
        ->assertCanSeeTableRecords([TtmInvoice::find(1), 2])
        ->assertCanNotSeeTableRecords([3])
        ->assertCountTableRecords(3)
        ->sortTable('number', 'desc')
        ->assertCanSeeTableRecords([3, 2], inOrder: true)
        ->sortTable('number', 'desc')
        ->assertCanSeeTableRecords([3, 2], inOrder: true);
});

it('searches, filters and resets', function () {
    Livewire::test(TtmTable::class)
        ->searchTable('INV-3')
        ->assertCanSeeTableRecords([3])
        ->assertCountTableRecords(1)
        ->searchTable(null)
        ->filterTable('status', ['value' => 'draft'])
        ->assertCanNotSeeTableRecords([2])
        ->resetTableFilters()
        ->assertCountTableRecords(3);
});

it('runs a row action, with and without a form, and a bulk action', function () {
    Livewire::test(TtmTable::class)
        ->callTableAction('pay', TtmInvoice::find(1))
        ->callTableAction('note', 3, ['note' => 'late'])
        ->callTableBulkAction('void', [2], ['reason' => 'duplicate']);

    expect(TtmInvoice::query()->orderBy('id')->get(['status', 'note'])->toArray())->toBe([
        ['status' => 'paid', 'note' => null],
        ['status' => 'void', 'note' => 'duplicate'],
        ['status' => 'draft', 'note' => 'late'],
    ]);
});

it('fails when a record is missing, or out of order', function () {
    expect(fn () => Livewire::test(TtmTable::class)->assertCanSeeTableRecords([3]))
        ->toThrow(AssertionFailedError::class, 'Record [3] is not on the table')
        ->and(fn () => Livewire::test(TtmTable::class)->assertCanNotSeeTableRecords([1]))
        ->toThrow(AssertionFailedError::class, 'Record [1] is on the table')
        ->and(fn () => Livewire::test(TtmTable::class)->assertCanSeeTableRecords([2, 1], inOrder: true))
        ->toThrow(AssertionFailedError::class, 'not in the expected order');
});
