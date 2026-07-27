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
use NyonCode\WireTable\Support\TableGestures;
use NyonCode\WireTable\Table;

/**
 * The gesture layer as a table wears it: which of the desktop pointer/keyboard
 * affordances a given table offers, from `Table::gestures()` and the project
 * default in `config('wire-table.defaults.gestures')`.
 */
class GestureLayerRow extends Model
{
    protected $table = 'gesture_layer_rows';

    protected $guarded = [];

    public $timestamps = false;
}

class GestureLayerComponent extends Component
{
    use WithTable;

    public string $mode = 'default';

    public function mount(string $mode = 'default'): void
    {
        $this->mode = $mode;
    }

    public function table(Table $table): Table
    {
        $table
            ->model(GestureLayerRow::class)
            ->paginated(false)
            ->selectable()
            ->columns([TextColumn::make('name')])
            ->recordAction(RecordAction::make(
                Action::make('open')->label('Open')->action(fn () => null)
            )->onDoubleClick()->onContextMenu());

        if ($this->mode === 'off') {
            $table->gestures(false);
        }

        if ($this->mode === 'plain-off') {
            $table->recordActions([])->gestures(false);
        }

        if ($this->mode === 'mouse-only') {
            $table->gestures(fn (TableGestures $g) => $g->keyboard(false));
        }

        return $table;
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('gesture_layer_rows', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
    });
    GestureLayerRow::insert([['name' => 'Alpha'], ['name' => 'Beta']]);
});

afterEach(fn () => Schema::dropIfExists('gesture_layer_rows'));

// ─── Configuration ───────────────────────────────────────────────

it('offers every gesture by default', function () {
    $table = Table::make()->selectable();

    expect($table->usesGridSemantics())->toBeTrue()
        ->and($table->usesDragSelect())->toBeTrue()
        ->and($table->usesRangeSelection())->toBeTrue()
        ->and($table->usesShortcutHelp())->toBeTrue()
        ->and($table->usesActiveRowMarker())->toBeTrue()
        ->and($table->getGestureConfig())->toBe(['sweep' => true, 'ranges' => true]);
});

it('takes the whole layer away with gestures(false)', function () {
    $table = Table::make()
        ->selectable()
        ->fillHandle()
        ->rowContextMenu([Action::make('archive')])
        ->gestures(false);

    expect($table->usesGridSemantics())->toBeFalse()
        ->and($table->getTableRole())->toBeNull()
        ->and($table->usesDragSelect())->toBeFalse()
        ->and($table->usesRangeSelection())->toBeFalse()
        ->and($table->usesShortcutHelp())->toBeFalse()
        ->and($table->usesActiveRowMarker())->toBeFalse()
        ->and($table->hasRowContextMenu())->toBeFalse()
        ->and($table->isFillHandleEnabled())->toBeFalse()
        ->and($table->mountsRecordActionController())->toBeFalse()
        ->and($table->activeRowMarkerGutter())->toBe('')
        ->and($table->shortcutLegend()->isEmpty())->toBeTrue();
});

it('configures one capability at a time', function () {
    $table = Table::make()
        ->selectable()
        ->gestures(fn (TableGestures $g) => $g->dragSelect(false));

    // Only the sweep goes; the keyboard and the ranges stay, and the controller
    // is still mounted for them.
    expect($table->usesDragSelect())->toBeFalse()
        ->and($table->usesRangeSelection())->toBeTrue()
        ->and($table->usesGridSemantics())->toBeTrue()
        ->and($table->mountsRecordActionController())->toBeTrue()
        ->and($table->getGestureConfig())->toBe(['sweep' => false, 'ranges' => true]);
});

it('keeps the mouse gestures when only the keyboard is dropped', function () {
    $table = Table::make()
        ->selectable()
        ->gestures(fn (TableGestures $g) => $g->keyboard(false));

    expect($table->usesGridSemantics())->toBeFalse()
        ->and($table->usesDragSelect())->toBeTrue()
        ->and($table->usesRangeSelection())->toBeTrue()
        // Both live in the delegated controller, so it still mounts …
        ->and($table->mountsRecordActionController())->toBeTrue()
        // … and the row they grow from is still marked.
        ->and($table->usesActiveRowMarker())->toBeTrue()
        // The help is read by the keyboard layer and cannot outlive it.
        ->and($table->usesShortcutHelp())->toBeFalse();
});

it('needs a selectable table for the selection gestures', function () {
    $table = Table::make()->recordAction('edit');

    expect($table->usesDragSelect())->toBeFalse()
        ->and($table->usesRangeSelection())->toBeFalse();
});

it('accepts a prepared gesture set', function () {
    $table = Table::make()->selectable()->gestures(TableGestures::none()->dragSelect());

    expect($table->usesDragSelect())->toBeTrue()
        ->and($table->usesRangeSelection())->toBeFalse()
        ->and($table->usesGridSemantics())->toBeFalse();
});

it('turns the layer back on explicitly', function () {
    $table = Table::make()->selectable()->gestures(false)->gestures(true);

    expect($table->usesDragSelect())->toBeTrue()
        ->and($table->usesGridSemantics())->toBeTrue();
});

it('takes the project default from config', function () {
    config()->set('wire-table.defaults.gestures', false);

    expect(Table::make()->selectable()->usesGridSemantics())->toBeFalse()
        ->and(Table::make()->selectable()->usesDragSelect())->toBeFalse();

    // A table still overrides it in either direction.
    expect(Table::make()->selectable()->gestures(true)->usesGridSemantics())->toBeTrue();
});

it('takes a mixed project default from config', function () {
    config()->set('wire-table.defaults.gestures', ['drag_select' => false]);

    $table = Table::make()->selectable();

    expect($table->usesDragSelect())->toBeFalse()
        ->and($table->usesRangeSelection())->toBeTrue()
        ->and($table->usesGridSemantics())->toBeTrue();
});

it('drops the range rows from the legend when ranges are off', function () {
    $table = Table::make()
        ->selectable()
        ->gestures(fn (TableGestures $g) => $g->rangeSelection(false));

    $keys = [];
    foreach ($table->shortcutLegend()->sections() as $section) {
        foreach ($section['hints'] as $hint) {
            $keys = array_merge($keys, $hint->keys);
        }
    }

    expect($keys)->toContain('Space')
        ->toContain('mod+A')
        ->not->toContain('shift+ArrowDown');
});

it('closes the fill endpoint together with the gestures', function () {
    // The server reads the same flag the view does, so a table with the
    // gestures off cannot be driven by a forged fill request either.
    $table = Table::make()->fillHandle()->gestures(false);

    expect($table->isFillHandleEnabled())->toBeFalse();
});

// ─── Rendering ───────────────────────────────────────────────────

/**
 * The x-data configs go through `@js()`, which escapes every quote as \u0022.
 * Decoding them back keeps the expectations readable as the JSON they are.
 */
function gestureHtml(string $mode = 'default'): string
{
    return str_replace('\u0022', '"', Livewire::test(GestureLayerComponent::class, ['mode' => $mode])->html());
}

it('renders the grid, the gesture config and the menu by default', function () {
    $html = gestureHtml();

    expect($html)->toContain('role="grid"')
        ->toContain('x-data="wireRecordActions(')
        ->toContain('"sweep":true')
        ->toContain('"ranges":true')
        ->toContain('data-record-menu')
        ->toContain('data-testid="shortcut-help"');
});

it('renders an ordinary table with the gestures off', function () {
    $html = gestureHtml('off');

    expect($html)->not->toContain('role="grid"')
        ->not->toContain('data-record-menu')
        ->not->toContain('data-testid="shortcut-help"')
        // The declared double-click binding is not part of the implicit layer:
        // it was asked for by name and keeps its controller — with every
        // gesture in it switched off.
        ->toContain('x-data="wireRecordActions(')
        ->toContain('"sweep":false')
        ->toContain('"ranges":false')
        // The selection itself is untouched: the checkboxes still work …
        ->toContain('data-testid="table-row-select"')
        // … and the cell now takes a modified click too, since with the ranges
        // gone nobody else would answer it.
        ->toContain('x-on:click="toggle(');
});

it('renders no controller at all for a table with nothing but the gestures off', function () {
    // Same table minus the declared record action: with the implicit layer gone
    // there is nothing left to mount.
    $html = gestureHtml('plain-off');

    expect($html)->not->toContain('wireRecordActions(')
        ->not->toContain('role="grid"')
        ->toContain('data-testid="table-row-select"');
});

it('renders the mouse gestures without the keyboard', function () {
    $html = gestureHtml('mouse-only');

    expect($html)->toContain('x-data="wireRecordActions(')
        ->toContain('"sweep":true')
        ->not->toContain('role="grid"')
        ->not->toContain('data-testid="shortcut-help"');
});
