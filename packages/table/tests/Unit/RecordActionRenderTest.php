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

class RecRenderRow extends Model
{
    protected $table = 'rec_render_rows';

    protected $guarded = [];

    public $timestamps = false;
}

class RecordActionRenderComponent extends Component
{
    use WithTable;

    public bool $withRecordActions = true;

    public bool $selectable = false;

    public function table(Table $table): Table
    {
        $table = $table
            ->model(RecRenderRow::class)
            ->paginated(false)
            ->columns([TextColumn::make('name')]);

        if ($this->selectable) {
            $table->selectable();
        }

        return $this->withRecordActions
            ? $table->recordActions([
                RecordAction::make(Action::make('view')->action(fn () => null))->onClick(),
                RecordAction::make(Action::make('edit')->action(fn () => null))->onDoubleClick(),
            ])
            : $table;
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

class NoRecordActionComponent extends RecordActionRenderComponent
{
    public bool $withRecordActions = false;
}

class SelectableRecordActionComponent extends RecordActionRenderComponent
{
    public bool $selectable = true;
}

beforeEach(function () {
    Schema::create('rec_render_rows', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
    });
    RecRenderRow::insert([['name' => 'Alpha'], ['name' => 'Beta'], ['name' => 'Gamma']]);
});

afterEach(fn () => Schema::dropIfExists('rec_render_rows'));

it('mounts the delegated controller with click and double-click listeners', function () {
    Livewire::test(RecordActionRenderComponent::class)
        ->assertSee('wireRecordActions', false)
        ->assertSee("onPointer('click', \$event)", false)
        ->assertSee("onPointer('dblclick', \$event)", false)
        ->assertSee('cursor-pointer', false);
});

it('mounts the controller exactly once, not per row', function () {
    $html = Livewire::test(RecordActionRenderComponent::class)->html();

    // Three rows render; a delegated controller must appear once, never per row.
    expect(substr_count($html, 'wireRecordActions('))->toBe(1)
        ->and(substr_count($html, 'x-data="wireRecordActions'))->toBe(1);
});

it('adds no record-action wiring when none are configured', function () {
    Livewire::test(NoRecordActionComponent::class)
        ->assertDontSee('wireRecordActions', false);
});

// ─── Keyboard navigation markup (F5) ─────────────────────────────

it('marks the table as a grid with focusable, keydown-wired rows', function () {
    Livewire::test(RecordActionRenderComponent::class)
        ->assertSee('role="grid"', false)
        ->assertSee('role="row"', false)
        ->assertSee('onKeydown($event)', false)
        ->assertSee('tabindex="-1"', false)
        ->assertSee('focus-visible:ring-2', false);
});

it('leaves a plain table ungridded when there are no record actions', function () {
    Livewire::test(NoRecordActionComponent::class)
        ->assertDontSee('role="grid"', false)
        ->assertDontSee('onKeydown', false);
});

it('exposes the selection-root hook keyboard range-select reaches for', function () {
    // The delegated controller finds the one selection component (checkboxes +
    // bulk bar) via data-selection-root to drive Space / Shift+arrow / mod+A.
    Livewire::test(SelectableRecordActionComponent::class)
        ->assertSee('data-selection-root', false);
});

it('omits the selection-root hook when the table is not selectable', function () {
    Livewire::test(RecordActionRenderComponent::class)
        ->assertDontSee('data-selection-root', false);
});
