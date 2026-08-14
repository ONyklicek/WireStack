<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Foundation\Support\MobileSheet;
use NyonCode\WireTable\Actions\TableActionClickResolver;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Support\TableGestures;
use NyonCode\WireTable\Support\TableRenderPlan;
use NyonCode\WireTable\Table;

/**
 * What a render resolved, asserted directly instead of through the markup.
 *
 * Every rule below used to live in `tables/index.blade.php`'s opening `@php`
 * block, where the only way to check it was to render a table and go looking for
 * the affordance it drives. The recursive "does this filter hold a value" rule is
 * the one that earns the move on its own: it has a genuinely sharp edge and was
 * verified by a chip appearing in some HTML.
 */
class TrpRow extends Model
{
    protected $table = 'trp_rows';

    protected $guarded = [];

    public $timestamps = false;
}

class TrpComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(TrpRow::class)
            ->perPage(25)
            ->columns([TextColumn::make('name')]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

function trpComponent(): TrpComponent
{
    $component = new TrpComponent;
    $component->mountWithTable();

    return $component;
}

function trpPlan(TrpComponent $component): TableRenderPlan
{
    return TableRenderPlan::build($component->getTable(), $component, collect());
}

it('reads the state a render depends on', function () {
    $component = trpComponent();
    $component->tableState->set('search', 'amelia');
    $component->tableState->set('sort.column', 'name');
    $component->tableState->set('sort.direction', 'desc');

    $plan = trpPlan($component);

    expect($plan->state->search)->toBe('amelia')
        ->and($plan->state->sortColumn)->toBe('name')
        ->and($plan->state->sortDirection)->toBe('desc');
});

it('falls back to the table for a page size the state never set', function () {
    // perPage() on the Table is the default; state only overrides it once a user
    // picks something from the per-page select.
    expect(trpPlan(trpComponent())->state->perPage)->toBe(25);
});

it('sorts ascending until told otherwise', function () {
    expect(trpPlan(trpComponent())->state->sortDirection)->toBe('asc');
});

it('counts a filter as active only when it holds a value', function () {
    $component = trpComponent();
    $component->tableState->set('filters', [
        'status' => 'open',
        'empty' => '',
        'missing' => null,
    ]);

    $plan = trpPlan($component);

    expect(array_keys($plan->state->activeFilters))->toBe(['status'])
        // The unfiltered set is still there — "active" is a reading of it, not a
        // replacement for it.
        ->and($plan->state->filters)->toHaveCount(3);
});

it('does not count a range filter that was typed and then cleared', function () {
    // The sharp edge. A cleared range leaves ['min' => '', 'max' => ''], which is
    // a non-empty array — so plain array_filter() counts it as active and the
    // table offers to clear a filter that is not applied.
    $component = trpComponent();
    $component->tableState->set('filters', [
        'price' => ['min' => '', 'max' => ''],
        'weight' => ['min' => '5', 'max' => ''],
    ]);

    $plan = trpPlan($component);

    expect(array_keys($plan->state->activeFilters))->toBe(['weight'])
        ->and($plan->state->hasActiveFilters)->toBeTrue();
});

it('looks as deep as the filter value nests', function () {
    $component = trpComponent();
    $component->tableState->set('filters', [
        'nested' => ['a' => ['b' => ['c' => '']]],
        'deep' => ['a' => ['b' => ['c' => 'yes']]],
    ]);

    expect(array_keys(trpPlan($component)->state->activeFilters))->toBe(['deep']);
});

it('reads column filters by the same rule', function () {
    $component = trpComponent();
    $component->tableState->set('columnFilters', ['name' => 'a', 'role' => '']);

    $plan = trpPlan($component);

    expect(array_keys($plan->state->activeColumnFilters))->toBe(['name'])
        ->and($plan->state->hasActiveFilters)->toBeTrue();
});

it('has nothing active on a table nobody has touched', function () {
    $plan = trpPlan(trpComponent());

    expect($plan->state->hasActiveFilters)->toBeFalse()
        ->and($plan->state->activeFilters)->toBe([])
        ->and($plan->state->activeColumnFilters)->toBe([]);
});

it('treats a search on its own as an active filter', function () {
    // What drives the "no results match your filters" empty state rather than the
    // plain one — a search with no filter still means the user narrowed something.
    $component = trpComponent();
    $component->tableState->set('search', 'nothing matches this');

    expect(trpPlan($component)->state->hasActiveFilters)->toBeTrue();
});

it('survives a state container holding nulls where arrays belong', function () {
    // `get('filters', [])` still returns null if null was stored, which is why the
    // read coalesces. Cheap to assert, and the alternative is a TypeError inside
    // array_filter() on someone's page.
    $component = trpComponent();
    $component->tableState->set('filters', null);
    $component->tableState->set('columnFilters', null);

    $plan = trpPlan($component);

    expect($plan->state->filters)->toBe([])
        ->and($plan->state->columnFilters)->toBe([])
        ->and($plan->state->hasActiveFilters)->toBeFalse();
});

// ─── Columns ─────────────────────────────────────────────────────────────────

class TrpWideComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(TrpRow::class)
            ->selectable()
            ->fillHandle()
            ->columns([
                TextColumn::make('name')->sortable()->toggleable()->filterAsSelect(['a' => 'A']),
                TextInputColumn::make('role')->toggleable(),
                TextColumn::make('secret')->visible(false),
                TextColumn::make('note')->copyable(),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

function trpWidePlan(): TableRenderPlan
{
    $component = new TrpWideComponent;
    $component->mountWithTable();

    return TableRenderPlan::build($component->getTable(), $component, collect());
}

it('resolves the visible columns and skips the ones nobody may see', function () {
    $plan = trpWidePlan();

    expect(array_map(fn ($c) => $c->getName(), array_values($plan->columns->visible)))
        ->toBe(['name', 'role', 'note'])
        ->and($plan->columns->hasVisible)->toBeTrue();
});

it('resolves column metadata once per column, keyed by name', function () {
    // The arithmetic that earns this: these are per-COLUMN answers a naive view
    // asks per CELL, so a 20x25 page asked each of them 500 times for 25 results.
    $meta = trpWidePlan()->columns->meta;

    expect(array_keys($meta))->toBe(['name', 'role', 'note'])
        ->and($meta['role']['editable'])->toBeTrue()
        ->and($meta['name']['editable'])->toBeFalse();
});

it('compiles each column a body cell with a slot for the record content', function () {
    // The compiled <td>: every attribute on it is column-static, so the row loop
    // splices content into a prepared string rather than re-rendering the tag per
    // cell. A skeleton that lost its slot would silently render empty cells.
    $meta = trpWidePlan()->columns->meta;

    foreach ($meta as $name => $entry) {
        expect($entry)->toHaveKey('cell')
            ->and($entry['cell']->fill(['content' => 'XYZ']))
            ->toContain('XYZ')
            ->toContain('<td');
    }
});

it('lists only the fillable columns, and turns the handle off without any', function () {
    // Fillable means writable: an editable column is, a plain TextColumn is not.
    // So a table can have fillHandle() on and still offer nothing to fill, which
    // is what isFillEnabled has to catch — a handle that writes nowhere.
    expect(trpWidePlan()->columns->fillable)->toBe(['role'])
        ->and(trpWidePlan()->columns->isFillEnabled)->toBeTrue()
        // The plain table has fillHandle() off AND no fillable column.
        ->and(trpPlan(trpComponent())->columns->fillable)->toBe([])
        ->and(trpPlan(trpComponent())->columns->isFillEnabled)->toBeFalse();
});

it('counts the selection and action columns into the colspan', function () {
    // Three visible columns + the selection cell. Off by one here and every
    // full-width row — empty state, group subtotal, summary footer — is wrong.
    expect(trpWidePlan()->columns->colSpan)->toBe(4);
});

it('knows whether any cell will render a copy button', function () {
    expect(trpWidePlan()->columns->hasCopyable)->toBeTrue()
        ->and(trpPlan(trpComponent())->columns->hasCopyable)->toBeFalse();
});

it('keeps a hidden column in the toggle list but out of the visible count', function () {
    // The distinction the two lists exist for, and it is easy to get backwards.
    // `toggleableColumns` filters on canView() ALONE — a column the user has
    // hidden must stay in the menu, or there is no way to switch it back on.
    // `visibleToggleableCount` is the other half: how many of them are on right
    // now, which is what the menu's counter shows.
    $plan = trpWidePlan();

    expect(array_map(fn ($c) => $c->getName(), array_values($plan->columns->toggleable)))
        ->toBe(['name', 'role', 'secret', 'note'])
        ->and($plan->columns->hasToggles)->toBeTrue()
        // 'secret' is hidden, so it is offered but not counted.
        ->and($plan->columns->visibleToggleableCount)->toBe(3);
});

it('lists the filterable columns separately', function () {
    $plan = trpWidePlan();

    expect(array_map(fn ($c) => $c->getName(), array_values($plan->columns->filterable)))
        ->toBe(['name'])
        ->and($plan->columns->hasFilters)->toBeTrue()
        ->and(trpPlan(trpComponent())->columns->hasFilters)->toBeFalse();
});

it('offers no mobile sort control unless the table stacks on mobile', function () {
    // The stacked card view hides the header row that holds the sort buttons, so
    // the control only exists when that view can appear.
    expect(trpWidePlan()->columns->mobileSortable)->toBe([])
        ->and(trpWidePlan()->columns->hasMobileSort)->toBeFalse();
});

it('has no sub-row columns on a table without sub-rows', function () {
    $plan = trpWidePlan();

    expect($plan->columns->subRow)->toBe([])
        ->and($plan->columns->visibleSubRow)->toBe([]);
});

// ─── Actions ─────────────────────────────────────────────────────────────────

class TrpActionsComponent extends Component
{
    use WithTable;

    public bool $collapse = false;

    public function table(Table $table): Table
    {
        $table->model(TrpRow::class)
            ->actionsStyle('quiet')
            ->actionsPosition('start')
            ->actionsColumnWidth('12rem')
            ->columns([TextColumn::make('name')])
            ->actions([Action::make('edit'), Action::make('delete')])
            ->headerActions([Action::make('create')])
            ->bulkActions([Action::make('bulkDelete')]);

        if ($this->collapse) {
            $table->collapseActionsOnMobile(true, 1)->collapseHeaderActionsOnMobile(true, 1);
        }

        return $table;
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

function trpActionsPlan(bool $collapse = false): TableRenderPlan
{
    $component = new TrpActionsComponent;
    $component->collapse = $collapse;
    $component->mountWithTable();

    return TableRenderPlan::build($component->getTable(), $component, collect());
}

it('resolves all four action surfaces', function () {
    // Row, bulk, header and the stacked card's own list — the mobile one is
    // separate on purpose, because a finger has no double-click or right-click.
    $actions = trpActionsPlan()->actions;

    expect($actions->hasAny)->toBeTrue()
        ->and($actions->row)->toHaveCount(2)
        ->and($actions->bulk)->toHaveCount(1)
        ->and($actions->hasBulk)->toBeTrue()
        ->and($actions->header)->toHaveCount(1)
        ->and($actions->hasHeader)->toBeTrue();
});

it('applies the configured row-action style', function () {
    // `quiet` is applied by the getter, to the Table's own Action instances —
    // it does not clone them. Pinned because the plan now calls that getter
    // earlier in the render than the view used to.
    foreach (trpActionsPlan()->actions->row as $action) {
        expect($action->isQuiet())->toBeTrue();
    }
});

it('builds no collapsed group and no header resolver unless asked to collapse', function () {
    // The three nullable members are gated on their own flag. Building a group
    // that is never rendered would be per-render work for nothing.
    $actions = trpActionsPlan()->actions;

    expect($actions->collapseMobile)->toBeFalse()
        ->and($actions->mobileGroup)->toBeNull()
        ->and($actions->collapseHeader)->toBeFalse()
        ->and($actions->mobileHeaderGroup)->toBeNull()
        ->and($actions->headerClick)->toBeNull();
});

it('builds them once collapsing is on', function () {
    $actions = trpActionsPlan(collapse: true)->actions;

    expect($actions->collapseMobile)->toBeTrue()
        ->and($actions->mobileGroup)->not->toBeNull()
        ->and($actions->collapseHeader)->toBeTrue()
        ->and($actions->mobileHeaderGroup)->not->toBeNull()
        ->and($actions->headerClick)->not->toBeNull();
});

it('always carries the click resolver that maps an action to this host', function () {
    // The seam that lets wire-core's action views stay host-agnostic; unlike the
    // header one it is not optional, because the row actions always need it.
    expect(trpActionsPlan()->actions->click)
        ->toBeInstanceOf(TableActionClickResolver::class);
});

it('falls back to the translated label for the actions column', function () {
    $actions = trpActionsPlan()->actions;

    expect($actions->columnLabel)->toBe(__('wire-table::messages.actions_label'))
        ->and($actions->position)->toBe('start')
        ->and($actions->columnWidth)->toBe('12rem');
});

it('has no actions at all on a table that declares none', function () {
    $plan = trpPlan(trpComponent());

    expect($plan->actions->hasAny)->toBeFalse()
        ->and($plan->actions->row)->toBe([])
        ->and($plan->actions->hasBulk)->toBeFalse()
        ->and($plan->actions->hasHeader)->toBeFalse();
});

// ─── Layout ──────────────────────────────────────────────────────────────────

class TrpLayoutComponent extends Component
{
    use WithTable;

    public bool $dense = false;

    public function table(Table $table): Table
    {
        $table->model(TrpRow::class)->columns([TextColumn::make('name')]);

        return $this->dense
            ? $table->compact()->bordered()->stackedOnMobile(true, 'lg')->sheetOnMobile()->mobileBreakpoint('lg')
            : $table->stackedOnMobile(false)->sheetOnMobile(false);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

function trpLayoutPlan(bool $dense): TableRenderPlan
{
    $component = new TrpLayoutComponent;
    $component->dense = $dense;
    $component->mountWithTable();

    return TableRenderPlan::build($component->getTable(), $component, collect());
}

it('resolves the density and border a render uses', function () {
    // The padding maps are the Table's, so a cell rendered outside the main view
    // — the selection cell has its own partial — cannot drift from the rest.
    $dense = trpLayoutPlan(dense: true)->layout;
    $roomy = trpLayoutPlan(dense: false)->layout;

    expect($dense->isBordered)->toBeTrue()
        ->and($roomy->isBordered)->toBeFalse()
        ->and($dense->cellPadding)->not->toBe($roomy->cellPadding)
        ->and($dense->headerPadding)->not->toBe($roomy->headerPadding);
});

it('carries both halves of the stacked-on-mobile swap', function () {
    // Both are always in the document; only CSS decides which is shown, so the
    // two class strings have to be resolved together or they can disagree.
    $layout = trpLayoutPlan(dense: true)->layout;

    expect($layout->isStackedOnMobile)->toBeTrue()
        ->and($layout->tableHiddenClass)->not->toBe('')
        ->and($layout->cardsVisibleClass)->not->toBe('');
});

it('derives every sheet class from the one breakpoint', function () {
    // Five strings, one source. Resolving them together is what stops a partial
    // recomputing them from a breakpoint it was handed separately.
    $layout = trpLayoutPlan(dense: true)->layout;

    expect($layout->sheetOnMobile)->toBeTrue()
        ->and($layout->sheetBreakpoint)->toBe('lg')
        ->and($layout->sheetBreakpointPx)->toBe(MobileSheet::px('lg'))
        ->and($layout->sheetPanel)->toBe(MobileSheet::panel('lg'))
        ->and($layout->sheetMotion)->toBe(MobileSheet::motion('lg'))
        ->and($layout->sheetBackdrop)->toBe(MobileSheet::backdropHide('lg'));
});

it('still resolves the sheet classes when the sheet is switched off', function () {
    // The flag is what the view branches on; the classes stay resolved either
    // way, so nothing has to guard against reading a null.
    $layout = trpLayoutPlan(dense: false)->layout;

    expect($layout->sheetOnMobile)->toBeFalse()
        ->and($layout->sheetPanel)->toBeString()
        ->and($layout->sheetBackdrop)->toBeString();
});

// ─── Paging ──────────────────────────────────────────────────────────────────

class TrpPagedComponent extends Component
{
    use WithTable;

    public string $mode = 'standard';

    public function table(Table $table): Table
    {
        $table->model(TrpRow::class)
            ->searchable()
            ->columns([TextColumn::make('name')->searchable()]);

        return match ($this->mode) {
            'simple' => $table->paginated()->perPage(3)->simplePagination(),
            'cursor' => $table->paginated()->perPage(3)->cursorPagination(),
            'none' => $table->paginated(false),
            default => $table->paginated()->perPage(3),
        };
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

function trpPagedPlan(string $mode): TableRenderPlan
{
    $component = new TrpPagedComponent;
    $component->mode = $mode;
    $component->mountWithTable();

    return $component->tableRenderPlan();
}

function trpSeedRows(int $count): void
{
    Schema::create('trp_rows', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    // NOT range(1, $count): range(1, 0) counts DOWN and yields [1, 0], so a
    // "seed nothing" call would quietly create two rows.
    for ($i = 1; $i <= $count; $i++) {
        TrpRow::create(['name' => 'Row '.$i]);
    }
}

it('counts the whole set when the paginator can answer for it', function () {
    trpSeedRows(11);

    $paging = trpPagedPlan('standard')->paging;

    expect($paging->hasPaginator)->toBeTrue()
        ->and($paging->recordCount)->toBe(11)   // total(), not the 3-row page
        ->and($paging->rangeFrom)->toBe(1)
        ->and($paging->rangeTo)->toBe(3);

    Schema::dropIfExists('trp_rows');
});

it('never asks a simple or cursor paginator for a total it does not have', function () {
    // Both __call-forward to the underlying collection, so asking would surface
    // as a BadMethodCallException from Collection rather than a type error. The
    // numbers below are the CURRENT behaviour, gap included: recordCount is the
    // page, and the range reads 1..count. See the class docblock — fixing that is
    // a separate change, not part of an extraction that must not alter output.
    trpSeedRows(11);

    foreach (['simple', 'cursor'] as $mode) {
        $paging = trpPagedPlan($mode)->paging;

        expect($paging->hasPaginator)->toBeFalse()
            ->and($paging->recordCount)->toBe(3)
            ->and($paging->rangeFrom)->toBe(1)
            ->and($paging->rangeTo)->toBe(3);
    }

    Schema::dropIfExists('trp_rows');
});

it('counts a plain collection as the whole set', function () {
    trpSeedRows(11);

    $paging = trpPagedPlan('none')->paging;

    expect($paging->hasPaginator)->toBeFalse()
        ->and($paging->recordCount)->toBe(11)
        ->and($paging->rangeFrom)->toBe(1)
        ->and($paging->rangeTo)->toBe(11)
        ->and($paging->isPaginated)->toBeFalse();

    Schema::dropIfExists('trp_rows');
});

it('ranges from zero when there is nothing to show', function () {
    trpSeedRows(0);

    $paging = trpPagedPlan('none')->paging;

    expect($paging->recordCount)->toBe(0)
        ->and($paging->rangeFrom)->toBe(0)
        ->and($paging->rangeTo)->toBe(0)
        // Nothing is filtered, so this is "nothing here yet", not "no matches".
        ->and($paging->isEmptyDueToFilter)->toBeFalse();

    Schema::dropIfExists('trp_rows');
});

it('tells an empty result from a filtered-empty one', function () {
    // The two empty states differ: one offers a way back, the other does not.
    trpSeedRows(3);

    $component = new TrpPagedComponent;
    $component->mode = 'none';
    $component->mountWithTable();
    $component->tableState->set('search', 'nothing matches this at all');

    expect($component->tableRenderPlan()->paging->isEmptyDueToFilter)->toBeTrue();

    Schema::dropIfExists('trp_rows');
});

it('counts the column-filter row into the ARIA header offset', function () {
    // Header rows come first in the ARIA numbering. Miss the filter row and
    // every body index is off by one.
    expect(trpPlan(trpComponent())->paging->headerRowCount)->toBe(1)
        ->and(trpWidePlan()->paging->headerRowCount)->toBe(2);
});

// ─── Interaction ─────────────────────────────────────────────────────────────

class TrpGestureComponent extends Component
{
    use WithTable;

    public string $mode = 'full';

    public function table(Table $table): Table
    {
        $table->model(TrpRow::class)->selectable()->columns([TextColumn::make('name')]);

        return match ($this->mode) {
            'full' => $table->gestures()->rowContextMenu([Action::make('ctxOpen')]),
            // all()->shortcutHelp(false), not a closure: the closure form is handed
            // the CURRENT gestures object, which is "none" until something turns
            // them on — so it would leave the layer off rather than trim it.
            'nohelp' => $table->gestures(TableGestures::all()->shortcutHelp(false)),
            default => $table,
        };
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

function trpGesturePlan(string $mode): TableRenderPlan
{
    // Livewire::test rather than a bare instance: the shortcut-help event is
    // derived from the component id, which only a mounted component has. Mounting
    // renders, and rendering reads records, so the table has to exist.
    if (! Schema::hasTable('trp_rows')) {
        Schema::create('trp_rows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
    }

    // The mode goes in as a mount parameter, not assigned afterwards: table() runs
    // during mount and the Table it returns is memoised, so a later write would
    // configure nothing.
    $component = Livewire::test(TrpGestureComponent::class, ['mode' => $mode])->instance();

    return TableRenderPlan::build($component->getTable(), $component, collect());
}

it('scopes the shortcut-help event to one component', function () {
    // A page can hold several tables and a bare window event would open every
    // help modal at once. The hash is lowercase because the listener lives in an
    // ATTRIBUTE NAME (x-on:{event}.window) and the DOM lowercases those — a
    // mixed-case Livewire id would never match what the controller dispatches.
    $interaction = trpGesturePlan('full')->interaction;

    expect($interaction->shortcutHelpEvent)->toStartWith('wire-table-shortcut-help-')
        ->and(substr((string) $interaction->shortcutHelpEvent, -12))->toMatch('/^[0-9a-f]{12}$/')
        ->and($interaction->shortcutHelpEvent)->toBe(strtolower((string) $interaction->shortcutHelpEvent));
});

it('hands the help event to the keyboard controller', function () {
    // The controller learns the event name through its keyboard config. The view
    // used to write this key back onto the array a dozen lines after building it.
    $interaction = trpGesturePlan('full')->interaction;

    expect($interaction->keyboardNav)->toBeTrue()
        ->and($interaction->recordKeyboardConfig)->toHaveKey('help')
        ->and($interaction->recordKeyboardConfig['help'])->toBe($interaction->shortcutHelpEvent);
});

it('emits no help event when the table has no legend to show', function () {
    // No event and no modal at all — not an event pointing at an empty modal.
    $interaction = trpGesturePlan('nohelp')->interaction;

    expect($interaction->shortcutLegend)->toBeNull()
        ->and($interaction->shortcutHelpEvent)->toBeNull()
        // …and the keyboard config still carries the key, holding null.
        ->and($interaction->recordKeyboardConfig)->toHaveKey('help')
        ->and($interaction->recordKeyboardConfig['help'])->toBeNull();
});

it('marks no active row on a table where nothing continues from one', function () {
    // The marker exists only where the keyboard, a range or a sweep carries on
    // from the marked row. A bare click binding runs the action and highlights
    // nothing, so this is null rather than an unused config.
    $bare = trpGesturePlan('bare')->interaction;
    $full = trpGesturePlan('full')->interaction;

    expect($bare->activeRowConfig)->toBeNull()
        ->and($bare->keyboardNav)->toBeFalse()
        ->and($bare->recordKeyboardConfig)->toBeNull()
        ->and($full->activeRowConfig)->not->toBeNull();
});

it('keeps the context menu independent of the actions column', function () {
    expect(trpGesturePlan('full')->interaction->rowContextMenuEnabled)->toBeTrue()
        ->and(trpGesturePlan('bare')->interaction->rowContextMenuEnabled)->toBeFalse();
});

it('resolves after a view has reconfigured the table, not before', function () {
    // The regression this exists for, and only the browser gate caught it:
    // wire-sortable's table view applies the user's persisted COLUMN ORDER by
    // calling $table->columns(...) in its own @php block, ahead of including
    // wire-table's view. A plan built when getTableProperty() hands the view back
    // has already read the declared order, so the reorder was silently undone on
    // every render — verify-column-reorder.mjs went red and nothing else did.
    //
    // So the plan is resolved on FIRST USE, by the view that reads it.
    Schema::create('trp_rows', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    $component = new TrpWideComponent;
    $component->mountWithTable();

    // A render begins: the memo is dropped, and deliberately not refilled.
    $component->getTableProperty();

    // Now a view reconfigures the table, exactly as wire-sortable's does.
    $component->getTable()->columns([
        TextColumn::make('note'),
        TextColumn::make('name'),
    ]);

    expect(array_map(fn ($c) => $c->getName(), array_values($component->tableRenderPlan()->columns->visible)))
        ->toBe(['note', 'name']);

    Schema::dropIfExists('trp_rows');
});

it('gives one render one plan, and the next render its own', function () {
    // The only test here that renders, and rendering reads records.
    Schema::create('trp_rows', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    // The memo is what lets the view and an island body share an answer; dropping
    // it per render is what stops the second render reusing the first one's, since
    // a request may write state before it renders.
    $component = trpComponent();

    $first = $component->tableRenderPlan();
    expect($component->tableRenderPlan())->toBe($first);

    $component->tableState->set('search', 'now set');
    $component->getTableProperty();

    expect($component->tableRenderPlan())->not->toBe($first)
        ->and($component->tableRenderPlan()->state->search)->toBe('now set');

    Schema::dropIfExists('trp_rows');
});
