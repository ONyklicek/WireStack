<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Core\State\StateContainer;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Preferences\TableViewPayload;
use NyonCode\WireTable\Support\ColumnLayout;
use NyonCode\WireTable\Table;

/*
 * Column widths the user drags and columns pinned to the start: kept in the
 * table state, read against the table as declared, drawn first and sticky.
 */
class ClRow extends Model
{
    protected $table = 'cl_rows';

    protected $guarded = [];

    public $timestamps = false;
}

class ClTable extends Component
{
    use WithTable;

    public bool $layout = true;

    public function table(Table $table): Table
    {
        return $table
            ->model(ClRow::class)
            ->paginated(false)
            ->columns([
                TextColumn::make('number')->pinned(),
                TextColumn::make('customer'),
                TextColumn::make('total'),
            ])
            ->resizableColumns($this->layout)
            ->pinnableColumns($this->layout);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('cl_rows', function (Blueprint $table) {
        $table->id();
        $table->string('number');
        $table->string('customer');
        $table->integer('total');
    });

    ClRow::insert([['id' => 1, 'number' => 'INV-1', 'customer' => 'Acme', 'total' => 10]]);
});

afterEach(function () {
    Schema::dropIfExists('cl_rows');
});

it('keeps a dragged width, clamped, and puts it back on null', function () {
    $table = Livewire::test(ClTable::class)
        ->call('resizeTableColumn', 'customer', 320)
        ->call('resizeTableColumn', 'total', 5)
        ->call('resizeTableColumn', 'nope', 200);

    expect($table->get('tableState.columns.widths'))->toBe(['customer' => 320, 'total' => ColumnLayout::MIN_WIDTH]);
    $table->assertSeeHtml('style="width: 320px; min-width: 320px"');

    $table->call('resizeTableColumn', 'customer', null);
    expect($table->get('tableState.columns.widths'))->toBe(['total' => ColumnLayout::MIN_WIDTH]);
});

it('draws the resize grip only on a resizable table', function () {
    Livewire::test(ClTable::class)->assertSeeHtml('data-testid="table-column-resize-customer"');
    Livewire::test(ClTable::class, ['layout' => false])
        ->assertDontSeeHtml('data-column-resize')
        ->call('resizeTableColumn', 'customer', 300)
        ->call('toggleColumnPin', 'customer')
        ->assertSet('tableState.columns.widths', [])
        ->assertSet('tableState.columns.pinned', []);
});

it('pins and unpins a column, draws pinned ones first and sticky, and keeps a declared pin', function () {
    $table = Livewire::test(ClTable::class)->call('toggleColumnPin', 'total');

    expect($table->get('tableState.columns.pinned'))->toBe(['total']);

    $html = $table->html();
    // The declared pin first, then the user's, then the rest.
    expect(strpos($html, 'data-pinned="number"'))->toBeLessThan(strpos($html, 'data-pinned="total"'))
        ->and(strpos($html, 'data-pinned="total"'))->toBeLessThan(strpos($html, 'data-column="customer"'))
        ->and($html)->toContain('data-wire-pinned')
        ->and($html)->toContain('data-testid="table-column-pin-total"');

    // A declared pin cannot be taken off.
    $table->call('toggleColumnPin', 'number')->call('toggleColumnPin', 'total');
    expect($table->get('tableState.columns.pinned'))->toBe([])
        ->and($table->html())->toContain('data-pinned="number"');
});

it('reads a stored layout against the table as it is now', function () {
    $table = Table::make()->columns([TextColumn::make('a'), TextColumn::make('b')])->resizableColumns()->pinnableColumns();

    expect(ColumnLayout::widths(['a' => '90', 'gone' => 100, 'b' => 99999, 3 => 50], $table))->toBe(['a' => 90, 'b' => ColumnLayout::MAX_WIDTH])
        ->and(ColumnLayout::pins(['b', 'gone', 'b', 7], $table))->toBe(['b'])
        ->and(ColumnLayout::widths('junk', $table))->toBe([])
        ->and(ColumnLayout::pins(['a'], Table::make()->columns([TextColumn::make('a')])))->toBe([]);

    $state = new StateContainer;
    TableViewPayload::applyTo(['columns.widths' => ['a' => 120, 'gone' => 1], 'columns.pinned' => ['gone', 'a']], $state, $table);

    expect($state->get('columns.widths'))->toBe(['a' => 120])
        ->and($state->get('columns.pinned'))->toBe(['a']);
});

it('keeps the unpinned cell exactly as it was', function () {
    // Every body cell is a skeleton emitted once per cell; a pinned column adds
    // to its own cells and leaves every other cell byte-for-byte alone.
    $html = Livewire::test(ClTable::class, ['layout' => false])->html();

    expect($html)->toContain('data-column="customer">')
        ->and($html)->not->toContain('data-pinned="customer"');
});
