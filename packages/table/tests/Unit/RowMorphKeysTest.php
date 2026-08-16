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
use NyonCode\WireTable\Table;

/**
 * Every conditional child of a `<tr>` carries a `wire:key`.
 *
 * The row's cells are spliced from compiled skeletons and carry no keys, which
 * is fine: there is one per visible column, always, so the morph pairs them by
 * position and only their contents change. The row's *leading* children are
 * different — the teleported context menu is absent for a record with no visible
 * action, and the selection and expander cells come and go with the table's
 * configuration. When their number changes, positional pairing slides the whole
 * row by one.
 *
 * Today Livewire's `@if` block markers bracket those regions and keep the morph
 * honest — 26 markers and 702 B per row, measured on `/previews/sortable-columns`.
 * That is the safety net the row loop is currently built on, and it is the reason
 * removing a single seemingly-redundant `@if` once broke the column reorder (the
 * header moved, the body did not) while staying green on both render fuses and on
 * a byte-identical check; only `verify-gesture-lab.mjs` saw it.
 *
 * These keys replace that net with something explicit and ~35 B per row. They are
 * what makes it safe to take the markers out — so if one goes missing, this fails
 * here rather than as a body that will not reorder.
 */
class RmkRow extends Model
{
    protected $table = 'rmk_rows';

    protected $guarded = [];

    public $timestamps = false;
}

class RmkHost extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(RmkRow::class)
            ->selectable()
            ->subRows('children')
            ->actions([Action::make('open')->label('Open')])
            ->rowContextMenu([Action::make('open')->label('Open')])
            ->columns([TextColumn::make('name')]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('rmk_rows', function (Blueprint $t) {
        $t->id();
        $t->string('name');
    });

    RmkRow::create(['name' => 'First']);
});

afterEach(fn () => Schema::dropIfExists('rmk_rows'));

it('keys every child of a row that may or may not be there', function () {
    $html = Livewire::test(RmkHost::class)->html();

    expect($html)
        // The teleported right-click menu: absent for a record with no action.
        ->toContain('wire:key="ctx-1"')
        // The selection cell.
        ->toContain('wire:key="sel-1"')
        // The sub-row expander cell.
        ->toContain('wire:key="exp-1"')
        // …and the row itself, which is what pairs one row against its own self.
        ->toContain('wire:key="row-1"');
});

it('keys them per record, so two rows never pair against each other', function () {
    RmkRow::create(['name' => 'Second']);

    $html = Livewire::test(RmkHost::class)->html();

    expect($html)->toContain('wire:key="ctx-1"')
        ->toContain('wire:key="ctx-2"')
        ->toContain('wire:key="sel-2"')
        ->toContain('wire:key="exp-2"');
});
