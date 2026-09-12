<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireTable\Columns\SelectColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Columns\ToggleColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Exceptions\TableConfigurationException;
use NyonCode\WireTable\Support\InactiveRow;
use NyonCode\WireTable\Table;

// ─── Model, host components ─────────────────────────────────────────────────

class InvOrder extends Model
{
    protected $table = 'inv_orders';

    protected $guarded = [];

    public $timestamps = false;
}

/** Row 2 is cancelled; everything the state governs is left at its default. */
class InvComponent extends Component
{
    use WithTable;

    public bool $lockSelection = false;

    public bool $lockActions = false;

    public bool $allowEditing = false;

    public function table(Table $table): Table
    {
        return $table
            ->model(InvOrder::class)
            ->paginated(false)
            ->selectable()
            ->columns([
                TextInputColumn::make('name'),
                ToggleColumn::make('paid'),
                SelectColumn::make('status')->options(['open' => 'Open', 'cancelled' => 'Cancelled']),
                TextColumn::make('total'),
            ])
            ->actions([
                Action::make('archive')->label('Archive')->action(
                    fn (InvOrder $record) => $record->update(['name' => 'Archived']),
                ),
            ])
            ->rowInactive(
                fn (InvOrder $order) => $order->status === 'cancelled',
                fn (InactiveRow $row) => $row
                    ->strikethrough()
                    ->color('danger')
                    ->editing($this->allowEditing)
                    ->selectable(! $this->lockSelection)
                    ->actions(! $this->lockActions),
            );
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

/** The same table without the state, to show what an ordinary row still emits. */
class InvPlainComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(InvOrder::class)
            ->paginated(false)
            ->selectable()
            ->columns([TextInputColumn::make('name'), TextColumn::make('total')]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

function invComponent(bool $lockSelection = false, bool $lockActions = false, bool $allowEditing = false): InvComponent
{
    $component = new InvComponent;
    $component->lockSelection = $lockSelection;
    $component->lockActions = $lockActions;
    $component->allowEditing = $allowEditing;
    $component->mountWithTable();

    return $component;
}

function invRecord(array $attributes = []): Model
{
    return (new class extends Model
    {
        protected $guarded = [];
    })->forceFill($attributes);
}

beforeEach(function () {
    Schema::create('inv_orders', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('status');
        $table->boolean('paid')->default(false);
        $table->integer('total')->default(0);
    });

    InvOrder::create(['id' => 1, 'name' => 'Live', 'status' => 'open', 'total' => 10]);
    InvOrder::create(['id' => 2, 'name' => 'Void', 'status' => 'cancelled', 'total' => 20]);
});

afterEach(function () {
    Schema::dropIfExists('inv_orders');
});

// ─── The state itself ───────────────────────────────────────────────────────

it('declares nothing until a table asks', function () {
    $table = Table::make();

    expect($table->hasInactiveRecords())->toBeFalse()
        ->and($table->isRecordInactive(invRecord(['status' => 'cancelled'])))->toBeFalse()
        ->and($table->getInactiveRowClasses(invRecord()))->toBe('')
        ->and($table->getRowStateAttributes(false))->toBe('');
});

it('resolves the predicate per record', function () {
    $table = Table::make()->rowInactive(fn (Model $record) => $record->status === 'cancelled');

    expect($table->hasInactiveRecords())->toBeTrue()
        ->and($table->isRecordInactive(invRecord(['status' => 'cancelled'])))->toBeTrue()
        ->and($table->isRecordInactive(invRecord(['status' => 'open'])))->toBeFalse()
        ->and($table->isRecordInactive(null))->toBeFalse();
});

it('takes a plain bool for a table that is inactive as a whole', function () {
    expect(Table::make()->rowInactive()->isRecordInactive(invRecord()))->toBeTrue()
        ->and(Table::make()->rowInactive(false)->hasInactiveRecords())->toBeFalse()
        ->and(Table::make()->rowInactive(false)->isRecordInactive(invRecord()))->toBeFalse();
});

it('ships dimmed, unstruck, untinted, and locked for editing', function () {
    $row = InactiveRow::make();

    expect($row->isDimmed())->toBeTrue()
        ->and($row->isStrikethrough())->toBeFalse()
        ->and($row->getColor())->toBeNull()
        ->and($row->allowsEditing())->toBeFalse()
        ->and($row->allowsSelection())->toBeTrue()
        ->and($row->allowsActions())->toBeTrue();
});

it('accepts a prebuilt InactiveRow as well as a configurator', function () {
    $table = Table::make()->rowInactive(true, InactiveRow::make()->strikethrough()->dim(false));

    expect($table->getInactiveRow()->isStrikethrough())->toBeTrue()
        ->and($table->getInactiveRow()->isDimmed())->toBeFalse();
});

// ─── The look ───────────────────────────────────────────────────────────────

it('dims the row and strikes its text, inputs included', function () {
    $table = Table::make()->rowInactive(true, fn (InactiveRow $row) => $row->strikethrough());

    $classes = $table->getRowClasses(invRecord(), 0);

    expect($classes)->toContain('[&>td]:line-through')
        // A form control does not inherit text-decoration, so it is named.
        ->toContain('[&_input]:line-through')
        ->toContain('[&_select]:line-through')
        ->toContain('[&>td]:text-gray-400')
        ->toContain('dark:[&>td]:text-gray-500');
});

it('leaves an active row of the same table untouched', function () {
    $table = Table::make()->rowInactive(
        fn (Model $record) => $record->status === 'cancelled',
        fn (InactiveRow $row) => $row->strikethrough(),
    );

    expect($table->getRowClasses(invRecord(['status' => 'open']), 0))
        ->not->toContain('line-through')
        ->not->toContain('text-gray-400');
});

it('tints an inactive row through the canonical row-tint owner', function () {
    $table = Table::make()->rowInactive(
        fn (Model $record) => $record->status === 'cancelled',
        fn (InactiveRow $row) => $row->color('danger'),
    );

    expect($table->getRowColor(invRecord(['status' => 'cancelled'])))->toBe('danger')
        ->and($table->getRowClasses(invRecord(['status' => 'cancelled']), 0))->toContain('bg-red-50')
        ->and($table->getRowColor(invRecord(['status' => 'open'])))->toBeNull();
});

it('lets an explicit rowColor win over the inactive tint', function () {
    $table = Table::make()
        ->rowColor('info')
        ->rowInactive(true, fn (InactiveRow $row) => $row->color('danger'));

    expect($table->getRowColor(invRecord()))->toBe('info');
});

it('carries the same look onto the stacked card', function () {
    $table = Table::make()->rowInactive(true, fn (InactiveRow $row) => $row->strikethrough()->color('danger'));

    $card = $table->getRowCardClasses(invRecord());

    expect($card)->toContain('line-through')
        ->toContain('text-gray-400')
        ->toContain('bg-red-50')
        ->toContain('border-b');
});

it('marks the row for assistive technology and for CSS', function () {
    expect(Table::make()->rowInactive()->getRowStateAttributes(true))
        ->toBe(' aria-disabled="true" data-inactive="true"');
});

// ─── The lock the columns inherit ───────────────────────────────────────────

it('locks every editable column of an inactive record', function () {
    $table = Table::make()
        ->columns([
            $text = TextInputColumn::make('name'),
            $toggle = ToggleColumn::make('paid'),
            $select = SelectColumn::make('status')->options([]),
        ])
        ->rowInactive(fn (Model $record) => $record->status === 'cancelled');

    // The push happens on first use of the column set, not on declaration.
    $table->getColumns();

    $cancelled = invRecord(['status' => 'cancelled']);
    $open = invRecord(['status' => 'open']);

    expect($text->isDisabled($cancelled))->toBeTrue()
        ->and($text->canEdit($cancelled))->toBeFalse()
        ->and($toggle->isDisabled($cancelled))->toBeTrue()
        ->and($select->canEdit($cancelled))->toBeFalse()
        ->and($text->isDisabled($open))->toBeFalse()
        ->and($toggle->canEdit($open))->toBeTrue();
});

it('locks the columns whichever order the two were declared in', function () {
    $table = Table::make()
        ->rowInactive(fn (Model $record) => $record->status === 'cancelled')
        ->columns([$text = TextInputColumn::make('name')]);

    $table->getColumns();

    expect($text->isDisabled(invRecord(['status' => 'cancelled'])))->toBeTrue();
});

it('keeps the column own disabled() rule alongside the table one', function () {
    $table = Table::make()
        ->columns([$text = TextInputColumn::make('name')->disabled(fn (Model $r) => $r->locked === true)])
        ->rowInactive(fn (Model $record) => $record->status === 'cancelled');

    $table->getColumns();

    expect($text->isDisabled(invRecord(['status' => 'open', 'locked' => true])))->toBeTrue()
        ->and($text->isDisabled(invRecord(['status' => 'cancelled', 'locked' => false])))->toBeTrue()
        ->and($text->isDisabled(invRecord(['status' => 'open', 'locked' => false])))->toBeFalse();
});

it('leaves the editors open when the state says editing is allowed', function () {
    $table = Table::make()
        ->columns([$text = TextInputColumn::make('name')])
        ->rowInactive(true, fn (InactiveRow $row) => $row->editing());

    $table->getColumns();

    expect($text->isDisabled(invRecord()))->toBeFalse();
});

// ─── The server refuses the write ───────────────────────────────────────────

it('refuses an inline write to an inactive record', function () {
    $result = invComponent()->updateTableCell(2, 'name', 'Forged');

    expect($result['success'])->toBeFalse()
        ->and(InvOrder::find(2)->name)->toBe('Void');
});

it('still writes to an active record of the same table', function () {
    $result = invComponent()->updateTableCell(1, 'name', 'Renamed');

    expect($result['success'])->toBeTrue()
        ->and(InvOrder::find(1)->name)->toBe('Renamed');
});

it('refuses a boolean cell on an inactive record too', function () {
    $result = invComponent()->updateTableCell(2, 'paid', true);

    expect($result['success'])->toBeFalse()
        ->and((bool) InvOrder::find(2)->paid)->toBeFalse();
});

// ─── The optional locks ─────────────────────────────────────────────────────

it('keeps an inactive record selectable by default', function () {
    $component = invComponent();
    $component->toggleRecordSelection('2');

    expect($component->isRecordSelected('2'))->toBeTrue();
});

it('refuses to tick a record whose state withholds the selection', function () {
    $component = invComponent(lockSelection: true);
    $component->toggleRecordSelection('2');

    expect($component->isRecordSelected('2'))->toBeFalse();

    // The active row of the same table still ticks.
    $component->toggleRecordSelection('1');
    expect($component->isRecordSelected('1'))->toBeTrue();
});

it('leaves a locked record out of "select page" and out of the header box', function () {
    $component = invComponent(lockSelection: true);
    $component->selectAllRecords();

    expect($component->getSelectablePageRecordKeys())->toBe(['1'])
        ->and($component->isRecordSelected('1'))->toBeTrue()
        ->and($component->isRecordSelected('2'))->toBeFalse()
        // Every row a tick can reach is selected, so the header box completes.
        ->and($component->areAllVisibleSelected())->toBeTrue();
});

it('still lets a locked record be unticked once it is in the selection', function () {
    $component = invComponent();
    $component->toggleRecordSelection('2');

    $component->lockSelection = true;
    $component->invalidateTable();

    $component->toggleRecordSelection('2');

    expect($component->isRecordSelected('2'))->toBeFalse();
});

it('runs a row action on an inactive record by default', function () {
    invComponent()->executeTableAction('2', 'archive');

    expect(InvOrder::find(2)->name)->toBe('Archived');
});

it('refuses a row action on a record whose state withholds them', function () {
    invComponent(lockActions: true)->executeTableAction('2', 'archive');

    expect(InvOrder::find(2)->name)->toBe('Void');

    // The active row of the same table still runs it.
    invComponent(lockActions: true)->executeTableAction('1', 'archive');

    expect(InvOrder::find(1)->name)->toBe('Archived');
});

// ─── The render ─────────────────────────────────────────────────────────────

it('marks only the inactive row in the rendered table', function () {
    $html = Livewire::test(InvComponent::class)->html();

    expect(substr_count($html, 'data-inactive="true"'))->toBe(1)
        ->and($html)->toContain('aria-disabled="true"')
        // The class attribute is escaped on the way out, as every row class is;
        // the parser hands the browser back the `[&>td]:` variant it was written as.
        ->toContain('[&amp;&gt;td]:line-through');
});

it('emits no state attribute at all for a table that never declared one', function () {
    $html = Livewire::test(InvPlainComponent::class)->html();

    expect($html)->not->toContain('data-inactive')
        ->not->toContain('aria-disabled');
});

it('hands the client only the page keys a tick can reach', function () {
    // The client's select-all, its ranges and its sweep all work from this list,
    // so a locked row left in it is ticked by "select page" however firmly its
    // own checkbox refuses.
    $locked = Livewire::test(InvComponent::class, ['lockSelection' => true])->html();
    $open = Livewire::test(InvComponent::class)->html();

    expect($locked)->toContain('data-page-keys="[&quot;1&quot;]"')
        ->and($open)->toContain('data-page-keys="[&quot;1&quot;,&quot;2&quot;]"');
});

it('renders the checkbox and the action cell inert when the state withholds them', function () {
    $html = Livewire::test(InvComponent::class, ['lockSelection' => true, 'lockActions' => true])->html();

    expect($html)->toContain('inert');
});

// ─── Config defaults ────────────────────────────────────────────────────────

it('seeds the state from the project default', function () {
    config()->set('wire-table.defaults.inactive_rows', [
        'strikethrough' => true,
        'dim' => false,
        'color' => 'warning',
        'selectable' => false,
    ]);

    $row = Table::make()->rowInactive()->getInactiveRow();

    expect($row->isStrikethrough())->toBeTrue()
        ->and($row->isDimmed())->toBeFalse()
        ->and($row->getColor())->toBe('warning')
        ->and($row->allowsSelection())->toBeFalse()
        ->and($row->allowsEditing())->toBeFalse();
});

it('lets a table override the project default', function () {
    config()->set('wire-table.defaults.inactive_rows', ['strikethrough' => true]);

    $table = Table::make()->rowInactive(true, fn (InactiveRow $row) => $row->strikethrough(false));

    expect($table->getInactiveRow()->isStrikethrough())->toBeFalse();
});

it('refuses an unknown option in the project default', function () {
    config()->set('wire-table.defaults.inactive_rows', ['strikthrough' => true]);

    Table::make()->rowInactive()->getInactiveRow();
})->throws(TableConfigurationException::class, 'Unknown inactive-row option [strikthrough]');

it('reads a snake_cased config key the way the gesture config is read', function () {
    config()->set('wire-table.defaults.inactive_rows', ['strike-through' => true]);

    expect(Table::make()->rowInactive()->getInactiveRow()->isStrikethrough())->toBeTrue();
});
