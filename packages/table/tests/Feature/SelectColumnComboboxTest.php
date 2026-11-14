<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireTable\Columns\SelectColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * SelectColumn as the shared combobox: a trigger per row bound to the cell's
 * own write, one list per table, the native control on request — and a server
 * that refuses any value that is not one of the options.
 */
class ScxRow extends Model
{
    protected $table = 'scx_rows';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = ['tags' => 'array'];
}

class ScxTag extends Model
{
    protected $table = 'scx_tags';

    protected $guarded = [];

    public $timestamps = false;
}

class ScxTable extends Component
{
    use WithTable;

    public string $mode = 'combobox';

    public bool $clearable = false;

    public bool $lockedRows = false;

    public function table(Table $table): Table
    {
        $status = SelectColumn::make('status')
            ->options(['open' => 'Open', 'paid' => 'Paid', 'void' => 'Void'])
            ->selectablePlaceholder($this->clearable)
            ->disabled(fn (Model $record): bool => $this->lockedRows && $record->getKey() === 2);

        match ($this->mode) {
            'native' => $status->native(),
            'mobile' => $status->nativeOnMobile(),
            'touch' => $status->touchOnMobile(),
            default => $status->searchableOptions(),
        };

        return $table
            ->model(ScxRow::class)
            ->paginated(false)
            ->columns([
                $status,
                SelectColumn::make('tags')
                    ->multiple()
                    ->options(fn () => ScxTag::query()->pluck('name', 'id')->all())
                    ->selectablePlaceholder()
                    ->disabled(fn (Model $record): bool => $this->lockedRows && $record->getKey() === 2)
                    ->createOptionForm([TextInput::make('name')->required()])
                    ->createOptionUsing(fn (array $data) => ScxTag::create($data)),
                SelectColumn::make('status_note')
                    ->label('Note')
                    ->options(['ok' => 'OK'])
                    ->editableRules(fn (): array => ['in:ok'])
                    ->createOptionForm([TextInput::make('name')->required()])
                    ->createOptionUsing(fn (array $data): string => $data['name']),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('scx_rows', function (Blueprint $table) {
        $table->id();
        $table->string('status')->nullable();
        $table->json('tags')->nullable();
        $table->string('status_note')->nullable();
    });
    Schema::create('scx_tags', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    // No explicit ids: Postgres would leave the sequence behind and the created option would collide.
    ScxTag::insert([['name' => 'Urgent'], ['name' => 'Later']]);

    foreach (range(1, 5) as $id) {
        ScxRow::create(['id' => $id, 'status' => 'open', 'tags' => ['1']]);
    }
});

afterEach(function () {
    Schema::dropIfExists('scx_rows');
    Schema::dropIfExists('scx_tags');
});

it('draws a trigger per row and the list once per table', function () {
    $html = Livewire::test(ScxTable::class)->html();

    expect(substr_count($html, 'wireSearchableSelect('))->toBe(15)
        ->and(substr_count($html, 'data-select-panel="select-column:status"'))->toBe(1)
        ->and(substr_count($html, 'data-select-panel="select-column:tags"'))->toBe(1)
        // The labels travel in the panel's data, not in each row.
        ->and(substr_count($html, 'Void'))->toBe(1)
        ->and($html)->toContain('data-testid="select-search"')
        ->and($html)->toContain('data-testid="table-select-tags-create-option"');
});

it('costs no view render per row', function () {
    $count = function (int $rows): int {
        ScxRow::query()->delete();

        foreach (range(1, $rows) as $id) {
            ScxRow::create(['id' => $id, 'status' => 'open', 'tags' => []]);
        }

        $renders = 0;
        View::composer('*', function () use (&$renders) {
            $renders++;
        });

        Livewire::test(ScxTable::class);

        return $renders;
    };

    // Once to warm what is built once per process (the sync-node skeleton).
    $count(2);

    expect($count(12) - $count(2))->toBe(0);
});

it('fills each row into the skeleton, escaped in the encoding of its slot', function () {
    ScxRow::query()->whereKey(3)->update(['status' => 'paid']);

    $html = Livewire::test(ScxTable::class)->html();

    expect($html)->toContain('wire:key="sel-3-status"')
        ->and($html)->toContain('data-record-key="3"')
        ->and($html)->toContain('value: &quot;paid&quot;')
        ->and($html)->toContain('data-server-value="paid"')
        ->and($html)->toContain('value: [&quot;1&quot;]')
        ->and($html)->toContain('data-server-value="[&quot;1&quot;]"')
        ->and($html)->not->toContain('wcSelectSlot');
});

it('locks a disabled row through its own shape', function () {
    $html = Livewire::test(ScxTable::class, ['lockedRows' => true])->html();

    preg_match('/wire:key="sel-2-status".*?<\/button>/s', $html, $two);
    preg_match('/wire:key="sel-1-status".*?<\/button>/s', $html, $one);

    expect($two[0])->toMatch('/\sdisabled\s/')
        ->and($one[0])->not->toMatch('/\sdisabled\s/');
});

it('draws the browser select on request, and both controls split at the breakpoint', function () {
    $native = Livewire::test(ScxTable::class, ['mode' => 'native'])->html();
    $mobile = Livewire::test(ScxTable::class, ['mode' => 'mobile'])->html();

    expect($native)->toContain('x-model="value"')
        ->and($native)->toContain('@change="change($event.target.value, $event)"')
        // Native everywhere: no shared list for this column.
        ->and($native)->not->toContain('data-select-panel="select-column:status"')
        ->and($mobile)->toContain('x-model="value"')
        ->and($mobile)->toContain('data-select-panel="select-column:status"');
});

it('writes a picked option and refuses anything that is not one', function () {
    $table = Livewire::test(ScxTable::class);

    $table->call('updateTableCell', 1, 'status', 'paid');
    expect(ScxRow::find(1)->status)->toBe('paid');

    $table->call('updateTableCell', 1, 'status', 'forged');
    expect(ScxRow::find(1)->status)->toBe('paid');

    $table->call('updateTableCell', 1, 'status', ['paid']);
    expect(ScxRow::find(1)->status)->toBe('paid');
});

it('reads a pasted label as its option', function () {
    Livewire::test(ScxTable::class)->call('updateTableCell', 1, 'status', 'void');
    expect(ScxRow::find(1)->status)->toBe('void');

    Livewire::test(ScxTable::class)->call('updateTableCell', 2, 'status', 'PAID');
    expect(ScxRow::find(2)->status)->toBe('paid');

    Livewire::test(ScxTable::class)->call('updateTableCell', 3, 'tags', 'Later, urgent');
    expect(ScxRow::find(3)->tags)->toBe(['2', '1']);
});

it('empties a cell only where the placeholder may be picked', function () {
    Livewire::test(ScxTable::class)->call('updateTableCell', 1, 'status', null);
    expect(ScxRow::find(1)->status)->toBe('open');

    Livewire::test(ScxTable::class, ['clearable' => true])->call('updateTableCell', 1, 'status', null);
    expect(ScxRow::find(1)->status)->toBeNull();

    Livewire::test(ScxTable::class)->call('updateTableCell', 2, 'tags', []);
    expect(ScxRow::find(2)->tags)->toBe([]);
});

it('keeps a multiple cell to a list of options', function () {
    $table = Livewire::test(ScxTable::class);

    $table->call('updateTableCell', 1, 'tags', ['1', '2']);
    expect(ScxRow::find(1)->tags)->toBe(['1', '2']);

    $table->call('updateTableCell', 1, 'tags', ['1', '99']);
    expect(ScxRow::find(1)->tags)->toBe(['1', '2']);

    $table->call('updateTableCell', 1, 'tags', ['1', ['2']]);
    expect(ScxRow::find(1)->tags)->toBe(['1', '2']);
});

it('creates an option from the list and puts it in the cell it came from', function () {
    Livewire::test(ScxTable::class)
        ->call('openActionModal', '4', 'select-column-create-option:tags')
        ->assertSee('Create option')
        ->assertSeeHtml('tableState.modal.actions.0.data.name')
        ->set('tableState.modal.actions.0.data.name', 'Blocked')
        ->call('submitActionModal')
        ->assertDispatched('select-option-created', statePath: 'select-column:tags', value: '3', label: 'Blocked');

    expect(ScxTag::find(3)?->name)->toBe('Blocked')
        ->and(ScxRow::find(4)->tags)->toBe(['1', '3']);
});

it('creates nothing for a cell the user could not set', function () {
    Livewire::test(ScxTable::class, ['lockedRows' => true])
        ->call('openActionModal', '2', 'select-column-create-option:tags')
        ->set('tableState.modal.actions.0.data.name', 'Sneaky')
        ->call('submitActionModal')
        ->assertNotDispatched('select-option-created');

    expect(ScxTag::where('name', 'Sneaky')->exists())->toBeFalse()
        ->and(ScxRow::find(2)->tags)->toBe(['1']);

    // A column with no create form has no such action at all.
    Livewire::test(ScxTable::class)
        ->call('openActionModal', '1', 'select-column-create-option:status')
        ->assertSet('tableState.modal.actions', []);
});

it('shows the labels in a read-only cell', function () {
    $column = SelectColumn::make('tags')->multiple()->options(['1' => 'Urgent', '2' => 'Later'])->editable(false);
    $record = ScxRow::find(1);
    $record->tags = ['2', '1'];

    expect($column->renderCell($record))->toBe('Later, Urgent');

    $record->tags = [];
    expect($column->renderCell($record))->toBe('Select...');
});

it('lets a search box lift an earlier native()', function () {
    $column = SelectColumn::make('status')->native()->searchableOptions();

    expect($column->isNative())->toBeFalse()
        ->and($column->hasSearchableOptions())->toBeTrue();
});

it('tells the user when the created option cannot be written, and writes nothing', function () {
    Livewire::test(ScxTable::class)
        ->call('openActionModal', '1', 'select-column-create-option:status_note')
        ->set('tableState.modal.actions.0.data.name', 'nope')
        ->call('submitActionModal')
        ->assertNotDispatched('select-option-created');

    expect(ScxRow::find(1)->status_note)->toBeNull();
});

it('finds no create action for a column that is not there', function () {
    Livewire::test(ScxTable::class)
        ->call('openActionModal', '1', 'select-column-create-option:nope')
        ->assertSet('tableState.modal.actions', []);
});

it('labels a created option by its model, by its key, and creates nothing for no key', function () {
    $record = ScxRow::find(1);
    $written = [];
    $write = function (Model $record, mixed $value) use (&$written): void {
        $written[] = $value;
    };

    // A model, named by the column's title attribute, on a list that does not hold it yet.
    $byModel = SelectColumn::make('status')->options(['open' => 'Open'])->relationship('tag', 'name')
        ->createOptionForm([])->createOptionUsing(fn (array $data) => ScxTag::create(['name' => 'Fresh']))
        ->createOptionModalHeading('New status');
    ($byModel->getCreateOptionAction($write)->getActionCallback())([], $record);

    // A bare key the list does not hold: its label is the key.
    $byKey = SelectColumn::make('status')->options(['open' => 'Open'])
        ->createOptionForm([])->createOptionUsing(fn (array $data): string => 'later');
    ($byKey->getCreateOptionAction($write)->getActionCallback())([], $record);

    // Nothing usable came back: nothing is written.
    $none = SelectColumn::make('status')->options(['open' => 'Open'])
        ->createOptionForm([])->createOptionUsing(fn (array $data) => null);
    ($none->getCreateOptionAction($write)->getActionCallback())([], $record);

    expect($written)->toBe(['3', 'later'])
        ->and($byModel->getOptions()['3'])->toBe('Fresh')
        ->and($byKey->getOptions()['later'])->toBe('later')
        ->and($byModel->getCreateOptionAction($write)->getModalHeading())->toBe('New status');
});

it('reads a list stored as JSON text, and refuses a multiple value that is not a list', function () {
    $column = SelectColumn::make('tags')->multiple()->options(['1' => 'Urgent', '2' => 'Later']);
    $record = ScxRow::find(1);

    expect($column->selectedKeys('["2","1"]'))->toBe(['2', '1'])
        ->and($column->selectedKeys('[not json'))->toBe(['[not json'])
        ->and($column->dehydrateState(5, $record))->toBe(5)
        ->and($column->validate('1', $record)['valid'])->toBeFalse();
});

it('draws nothing for a row that may not see the cell', function () {
    $column = SelectColumn::make('status')->options(['open' => 'Open'])->visibleForRecord(fn (): bool => false);

    expect($column->renderCell(ScxRow::find(1)))->toBe('');
});
