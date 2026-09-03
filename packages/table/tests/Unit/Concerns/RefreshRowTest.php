<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/**
 * `refreshRow()` is what a polling cell calls, and nothing had ever called it.
 *
 * `PollColumn` compiles `wire:poll` to `refreshRow('<key>')`, and the only test
 * over that pair asserted the *string* appears in the markup — so the method it
 * names could have had an empty body and every table test would still have
 * passed. The coverage diff gate is what noticed: zero executions on the one
 * statement it has.
 */
class RfrRecord extends Model
{
    protected $table = 'rfr_records';

    protected $guarded = [];

    public $timestamps = false;
}

class RfrComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(RfrRecord::class)
            ->paginated(false)
            ->columns([TextColumn::make('status')]);
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

beforeEach(function () {
    Schema::create('rfr_records', function (Blueprint $table) {
        $table->id();
        $table->string('status');
    });

    RfrRecord::create(['status' => 'pending']);

    $this->component = new RfrComponent;
    $this->component->mountWithTable();
});

afterEach(function () {
    Schema::dropIfExists('rfr_records');
});

it('makes the next render read the row again', function () {
    // The point of the whole call: a cell polls because its value is expected to
    // change on the server, so the records held from the last render are exactly
    // what must not answer the next one.
    expect($this->component->getTableRecords()->first()->status)->toBe('pending');

    RfrRecord::query()->update(['status' => 'done']);

    // Without the refresh the cache still answers, which is the bug the poll
    // exists to avoid — the spinner stops and the old value is still there.
    expect($this->component->getTableRecords()->first()->status)->toBe('pending');

    $this->component->refreshRow('1');

    expect($this->component->getTableRecords()->first()->status)->toBe('done');
});

it('keeps the query it already planned', function () {
    $this->component->getTableRecords();

    // Asserted white-box because the distinction has no other outward sign: the
    // row data is stale after a poll, the *plan* that fetches it is not, and
    // re-running the planner for every polling cell would be work for nothing.
    // The docblock on refreshRow() states it; this is what holds it to that.
    $cachedQuery = new ReflectionProperty($this->component, 'cachedQuery');
    $cachedRecords = new ReflectionProperty($this->component, 'cachedRecords');

    expect($cachedQuery->getValue($this->component))->not->toBeNull();

    $this->component->refreshRow('1');

    expect($cachedRecords->getValue($this->component))->toBeNull()
        ->and($cachedQuery->getValue($this->component))->not->toBeNull();
});
