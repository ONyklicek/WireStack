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

/**
 * Record actions are desktop gestures — a finger has no double-click, no
 * right-click and no Delete key. The mobile stacked cards therefore render the
 * same actions as ordinary buttons, so one table can be an application on a
 * desktop and a plain list on a phone.
 */
class MobileFallbackRow extends Model
{
    protected $table = 'mobile_fallback_rows';

    protected $guarded = [];

    public $timestamps = false;
}

class MobileFallbackComponent extends Component
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
            ->model(MobileFallbackRow::class)
            ->paginated(false)
            ->stackedOnMobile()
            ->columns([TextColumn::make('name')])
            ->recordAction(RecordAction::make(
                Action::make('open')->label('Open record')->action(fn () => null)
            )->onDoubleClick());

        if ($this->mode === 'no-fallback') {
            $table->recordActionButtonsOnMobile(false);
        }

        return $table;
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('mobile_fallback_rows', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
    });
    MobileFallbackRow::insert([['name' => 'Alpha'], ['name' => 'Beta']]);
});

afterEach(fn () => Schema::dropIfExists('mobile_fallback_rows'));

function actionNames(array $actions): array
{
    return array_map(fn (Action $action): string => $action->getName(), $actions);
}

it('offers a behaviour-only record action as a button on mobile only', function () {
    $table = Table::make()->recordAction(
        RecordAction::make(Action::make('open'))->onDoubleClick()
    );

    // Desktop: a gesture and nothing else — no column, no button.
    expect($table->hasActions())->toBeFalse()
        ->and(actionNames($table->getRowActionsForDisplay()))->toBe([])
        // Mobile: the very same action, as a button.
        ->and($table->hasMobileActions())->toBeTrue()
        ->and(actionNames($table->getMobileRowActionsForDisplay()))->toBe(['open']);
});

it('keeps the row actions and adds the record actions after them', function () {
    $table = Table::make()
        ->actions([Action::make('edit')])
        ->recordAction(RecordAction::make(Action::make('open'))->onDoubleClick());

    expect(actionNames($table->getRowActionsForDisplay()))->toBe(['edit'])
        ->and(actionNames($table->getMobileRowActionsForDisplay()))->toBe(['edit', 'open']);
});

it('never doubles an action a record action only references', function () {
    // recordAction('edit') reuses the action declared in actions(): the card must
    // show one button, not the same one twice.
    $table = Table::make()
        ->actions([Action::make('edit')])
        ->recordAction('edit');

    expect(actionNames($table->getMobileRowActionsForDisplay()))->toBe(['edit']);
});

it('leaves an action already promoted into the column alone', function () {
    $table = Table::make()->recordAction(
        RecordAction::make(Action::make('open'))->onDoubleClick()->alsoInRowActions()
    );

    // Promoted to a button, so it is in both lists — once each.
    expect(actionNames($table->getRowActionsForDisplay()))->toBe(['open'])
        ->and(actionNames($table->getMobileRowActionsForDisplay()))->toBe(['open']);
});

it('switches the fallback off', function () {
    $table = Table::make()
        ->recordAction(RecordAction::make(Action::make('open'))->onDoubleClick())
        ->recordActionButtonsOnMobile(false);

    expect($table->showsRecordActionButtonsOnMobile())->toBeFalse()
        ->and($table->getMobileRowActionsForDisplay())->toBe([])
        ->and($table->hasMobileActions())->toBeFalse();
});

it('counts the fallback buttons towards the mobile collapse threshold', function () {
    $table = Table::make()
        ->actions([Action::make('edit'), Action::make('delete')])
        ->recordAction(RecordAction::make(Action::make('open'))->onDoubleClick())
        ->collapseActionsOnMobile(threshold: 3);

    // Two row actions plus the record action make three — the card collapses
    // them into one dropdown, which it would not do counting the column alone.
    expect($table->shouldCollapseActionsOnMobile())->toBeTrue()
        ->and(actionNames($table->getMobileActionGroup()->getActions()))->toBe(['edit', 'delete', 'open']);
});

it('renders the fallback button in the stacked card', function () {
    $html = Livewire::test(MobileFallbackComponent::class)->html();

    expect($html)->toContain('data-testid="table-card-actions"')
        ->toContain('Open record');
});

it('renders no card actions when the fallback is off', function () {
    $html = Livewire::test(MobileFallbackComponent::class, ['mode' => 'no-fallback'])->html();

    // The card's action row is gone entirely — the label may still appear in the
    // action's own modal shell, which is rendered once per table either way.
    expect($html)->not->toContain('data-testid="table-card-actions"')
        ->toContain('data-testid="table-card"');
});
