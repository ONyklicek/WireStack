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
            // The gesture layer is opt-in: keyboard navigation and the sweep
            // are off until a table asks. Everything asserted here is that
            // layer, so the fixture asks.
            ->gestures()
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

class SelectableOnlyComponent extends RecordActionRenderComponent
{
    public bool $withRecordActions = false;

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

// ─── Grid semantics without record actions ───────────────────────

it('grids a selectable table even without record actions', function () {
    // usesGridSemantics() owns the decision: a selectable table is keyboard
    // territory too — Space and Shift+arrow work the selection there.
    Livewire::test(SelectableOnlyComponent::class)
        ->assertSee('role="grid"', false)
        ->assertSee('role="row"', false)
        ->assertSee('tabindex="0"', false);
});

it('mounts the record-action controller on a selectable-only grid', function () {
    // The keyboard selection (Space, Shift+arrow, mod+A), the roving tabindex
    // and the active-row marker all live in the delegated controller, so a
    // grid without record actions needs it just the same.
    Livewire::test(SelectableOnlyComponent::class)
        ->assertSee('wireRecordActions', false)
        ->assertSee('rowTabindex(', false)
        ->assertSee('onKeydown($event)', false);
});

// ─── Active-row marker ───────────────────────────────────────────

it('binds the active-row marker on every row instead of toggling classes', function () {
    // The marker is an Alpine binding so it survives the Livewire roundtrip the
    // click itself triggers; the controller gets both the marker class and the
    // hover it has to switch off while a row is active.
    $html = Livewire::test(RecordActionRenderComponent::class)->html();

    expect($html)
        ->toContain('\u0022class\u0022:\u0022bg-primary-100 dark:bg-primary-900')
        ->toContain('\u0022hover\u0022:\u0022hover:bg-gray-50')
        ->and(substr_count($html, "...rowClass('"))->toBe(3);
});

it('merges the selection tint and the active marker into one class binding', function () {
    // Two `:class` attributes on one <tr> would silently drop the second, so a
    // selectable table with record actions must emit a single merged object.
    $html = Livewire::test(SelectableRecordActionComponent::class)->html();

    expect(substr_count($html, ':class="{'))->toBe(3)
        ->and($html)->toContain('isSelected(')
        ->and($html)->toContain('...rowClass(');
});

it('leaves rows unbound when there is no record-action controller', function () {
    Livewire::test(NoRecordActionComponent::class)
        ->assertDontSee('rowClass(', false);
});

it('binds the roving tabindex so a morph cannot drop the grid out of the tab order', function () {
    // Livewire rewrites every row from this markup on each update, so an
    // assigned tabstop would be wiped by the first sort/filter/page change.
    $html = Livewire::test(RecordActionRenderComponent::class)->html();

    expect(substr_count($html, ':tabindex="rowTabindex('))->toBe(3)
        // The first row is the tabstop before Alpine boots (and before any row
        // is chosen), the rest stay out of the tab order.
        ->and(substr_count($html, 'tabindex="0"'))->toBe(1);
});
