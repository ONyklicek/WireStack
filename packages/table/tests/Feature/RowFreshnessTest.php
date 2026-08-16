<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Foundation\Support\RecordVersion;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/**
 * A poll answering with the rows that moved, not the page they sit in.
 *
 * The ERP half of the island plan: several people on one table, and a
 * colleague's write should repaint their row while leaving everything else —
 * including whatever the reader has half-typed in a cell of their own —
 * untouched. A full re-render morphs the whole table to deliver one changed
 * value.
 *
 * Which rows moved is worked out **server-side from this component's own page**,
 * and deliberately not carried on the broadcast: the channel is scoped to a
 * model class rather than to a viewer, so record keys on it would tell every
 * listener which records exist and change, including the ones their own query
 * would never return. The event stays a bare signal.
 */
class RfRow extends Model
{
    protected $table = 'rf_rows';

    protected $guarded = [];
}

class RfHost extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(RfRow::class)
            ->poll('5s')
            ->rowPartials()
            ->columns([TextInputColumn::make('name'), TextColumn::make('amount')]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('rf_rows', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->integer('amount')->default(1);
        $t->timestamps();
    });

    RfRow::create(['name' => 'First', 'amount' => 1]);
    RfRow::create(['name' => 'Second', 'amount' => 2]);
});

afterEach(fn () => Schema::dropIfExists('rf_rows'));

it('sends only the row somebody else changed', function () {
    $test = Livewire::test(RfHost::class);

    // The first poll has nothing to compare against, so it renders and records
    // where every row stood.
    $test->call('refreshTable');

    // Somebody else writes — out of band, exactly as another session would.
    RfRow::find(2)->update(['name' => 'Changed elsewhere']);

    $test->call('refreshTable');

    $partials = $test->effects['wirePartials'] ?? [];

    expect(array_keys($partials))->toBe(['row-2'])
        ->and($partials['row-2'])->toContain('Changed elsewhere')
        ->and($test->effects['html'] ?? null)->toBeNull();
});

it('sends nothing at all when nothing moved', function () {
    // The cheapest possible answer, and the common one on a table nobody is
    // editing: no partials, no html.
    $test = Livewire::test(RfHost::class);

    $test->call('refreshTable');
    $test->call('refreshTable');

    expect($test->effects['wirePartials'] ?? null)->toBeNull()
        ->and($test->effects['html'] ?? null)->toBeNull();
});

it('renders the whole table when the page itself changed', function () {
    // A row that arrived, left or moved under the sort is a change no per-row
    // partial can express: the page's shape moved, not a row's contents.
    $test = Livewire::test(RfHost::class);

    $test->call('refreshTable');

    RfRow::create(['name' => 'Third', 'amount' => 3]);

    $test->call('refreshTable');

    expect($test->effects['wirePartials'] ?? null)->toBeNull()
        ->and($test->effects['html'] ?? null)->not->toBeNull();
});

it('carries the fresh version stamp with the row it refreshes', function () {
    // The trap this whole mechanism could have walked into. A row refreshed
    // without a current stamp hands the next editor a lock that cannot detect
    // the write it just missed — every subsequent edit on that row would be
    // refused as a conflict, or worse, accepted over the top of one.
    $test = Livewire::test(RfHost::class);

    $test->call('refreshTable');

    $record = RfRow::find(2);
    $record->update(['name' => 'Changed elsewhere']);

    $test->call('refreshTable');

    $stamp = app(RecordVersion::class)->stamp($record->fresh());

    expect($test->effects['wirePartials']['row-2'])->toContain($stamp);
});

it('leaves the rest of the page out of it', function () {
    // What the reader keeps: the row they are editing is not in the response, so
    // nothing morphs over it.
    $test = Livewire::test(RfHost::class);

    $test->call('refreshTable');

    RfRow::find(2)->update(['name' => 'Changed elsewhere']);

    $test->call('refreshTable');

    expect($test->effects['wirePartials'])->not->toHaveKey('row-1');
});
