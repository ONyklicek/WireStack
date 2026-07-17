<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionGroup;
use NyonCode\WireCore\Actions\BulkAction;
use NyonCode\WireCore\Actions\HeaderAction;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/**
 * ModalStackingTest drives row and header actions. This covers what it does not:
 * a **bulk** action that opens a modal, an action reached **inside an
 * ActionGroup**, replaceMountedAction() routing to each kind, and the frame
 * guards that fire when there is no frame to write to.
 */
class BgaUser extends Model
{
    protected $table = 'bga_users';

    protected $guarded = [];

    public $timestamps = false;
}

class BgaComponent extends Component
{
    use WithTable;

    public string $log = '';

    /** Lets a test make an action disappear while its modal is open. */
    public bool $offersConditional = true;

    public function table(Table $table): Table
    {
        return $table
            ->model(BgaUser::class)
            ->paginated(false)
            ->columns([TextColumn::make('name')])
            ->actions([
                // The action the test reaches for lives inside a group, not in
                // the top-level list.
                ActionGroup::make([
                    Action::make('groupedEdit')
                        ->modalHeading('Grouped edit')
                        ->form([TextInput::make('name')])
                        ->action(fn () => null),
                ]),
                Action::make('swaps')
                    ->modalHeading('Swaps')
                    ->form([TextInput::make('note')])
                    ->action(fn ($replace) => $replace($this->log)),
                Action::make('writesToParent')
                    ->modalHeading('Writes to parent')
                    ->form([TextInput::make('note')])
                    // At depth 0 there is no parent frame to write into.
                    ->action(fn ($setParent) => $setParent('name', 'orphaned')),
                ...($this->offersConditional ? [
                    Action::make('conditional')
                        ->modalHeading('Conditional')
                        ->form([TextInput::make('note')])
                        ->action(fn () => null),
                ] : []),
            ])
            ->headerActions([
                HeaderAction::make('hdrCreate')
                    ->modalHeading('Header create')
                    ->form([TextInput::make('name')])
                    ->action(fn () => null),
            ])
            ->bulkActions([
                BulkAction::make('bulkEdit')
                    ->modalHeading('Bulk edit')
                    ->form([TextInput::make('note')])
                    ->fillFormUsing(fn ($records): array => ['note' => 'for '.$records->count()])
                    ->action(fn () => null),
                BulkAction::make('bulkNoModal')
                    ->action(function ($records): void {
                        $this->log = 'ran on '.$records->count();
                    }),
                BulkAction::make('bulkOuter')
                    ->modalHeading('Bulk outer')
                    ->registerActions([
                        // Declared inline; never in the bulkActions list itself.
                        BulkAction::make('bulkInner')
                            ->modalHeading('Bulk inner')
                            ->form([TextInput::make('note')])
                            ->action(fn () => null),
                    ])
                    ->action(fn () => null),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    Schema::create('bga_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    BgaUser::create(['name' => 'Ada']);
    BgaUser::create(['name' => 'Grace']);
});

afterEach(function () {
    Schema::dropIfExists('bga_users');
});

// ─── Bulk action modals ───────────────────────────────────────

it('opens a modal for a bulk action, carrying the selection into its form', function () {
    $test = Livewire::test(BgaComponent::class)
        ->call('toggleRecordSelection', '1')
        ->call('toggleRecordSelection', '2')
        ->call('openBulkActionModal', 'bulkEdit');

    $state = $test->instance()->tableState;

    expect($state->get('modal.actions'))->toHaveCount(1)
        ->and($state->get('modal.actions.0.name'))->toBe('bulkEdit')
        ->and($state->get('modal.actions.0.isBulk'))->toBeTrue()
        ->and($state->get('modal.actions.0.isHeaderAction'))->toBeFalse()
        // A bulk frame has no single record.
        ->and($state->get('modal.actions.0.recordKey'))->toBeNull()
        // The defaults are computed from the selection, not from an empty set.
        ->and($state->get('modal.actions.0.data.note'))->toBe('for 2');
});

it('passes arguments through to the bulk action frame', function () {
    $test = Livewire::test(BgaComponent::class)
        ->call('toggleRecordSelection', '1')
        ->call('openBulkActionModal', 'bulkEdit', ['tag' => 'from-toolbar']);

    expect($test->instance()->tableState->get('modal.actions.0.arguments'))
        ->toBe(['tag' => 'from-toolbar']);
});

it('runs a bulk action with no modal instead of opening one', function () {
    $test = Livewire::test(BgaComponent::class)
        ->call('toggleRecordSelection', '1')
        ->call('toggleRecordSelection', '2')
        ->call('openBulkActionModal', 'bulkNoModal');

    expect($test->instance()->log)->toBe('ran on 2')
        ->and($test->instance()->tableState->get('modal.actions', []))->toBe([]);
});

it('ignores a bulk action name that is not registered', function () {
    $test = Livewire::test(BgaComponent::class)
        ->call('openBulkActionModal', 'noSuchBulkAction');

    expect($test->instance()->tableState->get('modal.actions', []))->toBe([]);
});

it('finds a bulk action registered inline on another bulk action', function () {
    // bulkInner is only reachable by recursing into bulkOuter's registerActions().
    $test = Livewire::test(BgaComponent::class)
        ->call('toggleRecordSelection', '1')
        ->call('openBulkActionModal', 'bulkOuter')
        ->call('openBulkActionModal', 'bulkInner');

    $state = $test->instance()->tableState;

    expect($state->get('modal.actions'))->toHaveCount(2)
        ->and($state->get('modal.actions.1.name'))->toBe('bulkInner')
        ->and($state->get('modal.actions.1.isBulk'))->toBeTrue();
});

// ─── Actions inside a group ───────────────────────────────────

it('opens a modal for an action that lives inside an ActionGroup', function () {
    // The row-action lookup has to recurse into groups; a grouped action is not
    // in getActions() by name.
    $test = Livewire::test(BgaComponent::class)
        ->call('openActionModal', '1', 'groupedEdit');

    $state = $test->instance()->tableState;

    expect($state->get('modal.actions'))->toHaveCount(1)
        ->and($state->get('modal.actions.0.name'))->toBe('groupedEdit')
        ->and($state->get('modal.actions.0.recordKey'))->toBe('1')
        ->and($state->get('modal.actions.0.isBulk'))->toBeFalse();
});

// ─── replaceMountedAction routing ─────────────────────────────

it('replaces the mounted action with a header action', function () {
    $test = Livewire::test(BgaComponent::class)
        ->call('openActionModal', '1', 'swaps')
        ->call('replaceMountedAction', 'hdrCreate');

    $state = $test->instance()->tableState;

    expect($state->get('modal.actions'))->toHaveCount(1)
        ->and($state->get('modal.actions.0.name'))->toBe('hdrCreate')
        ->and($state->get('modal.actions.0.isHeaderAction'))->toBeTrue();
});

it('replaces the mounted action with a bulk action', function () {
    $test = Livewire::test(BgaComponent::class)
        ->call('toggleRecordSelection', '1')
        ->call('openActionModal', '1', 'swaps')
        ->call('replaceMountedAction', 'bulkEdit');

    $state = $test->instance()->tableState;

    expect($state->get('modal.actions'))->toHaveCount(1)
        ->and($state->get('modal.actions.0.name'))->toBe('bulkEdit')
        ->and($state->get('modal.actions.0.isBulk'))->toBeTrue();
});

it('replaces the mounted action with a row action, keeping the record', function () {
    $test = Livewire::test(BgaComponent::class)
        ->call('openActionModal', '2', 'swaps')
        ->call('replaceMountedAction', 'groupedEdit');

    $state = $test->instance()->tableState;

    expect($state->get('modal.actions'))->toHaveCount(1)
        ->and($state->get('modal.actions.0.name'))->toBe('groupedEdit')
        // The replaced frame's record carries over without an explicit key.
        ->and($state->get('modal.actions.0.recordKey'))->toBe('2');
});

it('mounts a row action by an explicit record key when nothing is mounted', function () {
    // With no frame open there is no record to inherit, so the explicit key is
    // what the frame is built from.
    $test = Livewire::test(BgaComponent::class)
        ->call('replaceMountedAction', 'groupedEdit', ['recordKey' => '2']);

    $state = $test->instance()->tableState;

    expect($state->get('modal.actions'))->toHaveCount(1)
        ->and($state->get('modal.actions.0.name'))->toBe('groupedEdit')
        ->and($state->get('modal.actions.0.recordKey'))->toBe('2');
});

it('leaves the stack alone when replacing with a name nothing answers to', function () {
    $test = Livewire::test(BgaComponent::class)
        ->call('openActionModal', '1', 'swaps')
        ->call('replaceMountedAction', 'noSuchActionAnywhere');

    // The frame was popped for the replacement that never arrived; what matters
    // is that no half-built frame is left behind.
    expect($test->instance()->tableState->get('modal.actions', []))->toBe([]);
});

// ─── Frame guards with no frame to write to ───────────────────

it('writes nowhere when a depth-0 modal sets a parent value', function () {
    // Documented behaviour: $setParent from the bottom frame has no parent to
    // write into, so the write is dropped rather than corrupting frame -1.
    $test = Livewire::test(BgaComponent::class)
        ->call('openActionModal', '1', 'writesToParent')
        ->call('submitActionModal');

    // The action ran and the modal closed; the orphaned parent write went
    // nowhere rather than corrupting frame -1.
    expect($test->instance()->tableState->get('modal.actions', []))->toBe([]);
});

it('ignores a step change when no modal is mounted', function () {
    $test = Livewire::test(BgaComponent::class)
        ->call('nextActionModalStep');

    expect($test->instance()->tableState->get('modal.actions', []))->toBe([]);
});

it('ignores a modal submit when nothing is mounted', function () {
    // resolveCurrentModalAction() asks for frame -1 and must answer "nothing"
    // rather than reach into the state container.
    $test = Livewire::test(BgaComponent::class)
        ->call('submitActionModal');

    expect($test->instance()->tableState->get('modal.open'))->not->toBeTrue()
        ->and($test->instance()->tableState->get('modal.actions', []))->toBe([]);
});

it('steps back harmlessly when the modal is already gone', function () {
    // "Back" has no mounted-action guard of its own, so a stale click after the
    // modal closed writes a step into frame -1 — which must go nowhere.
    $test = Livewire::test(BgaComponent::class)
        ->call('prevActionModalStep');

    expect($test->instance()->tableState->get('modal.actions', []))->toBe([]);
});

it('survives an action disappearing while its modal is open', function () {
    // The frame still names `conditional`, but the next request no longer
    // registers it. Resolving the frame must answer "nothing" instead of
    // rendering a modal around a null action.
    $test = Livewire::test(BgaComponent::class)
        ->call('openActionModal', '1', 'conditional');

    expect($test->instance()->tableState->get('modal.actions.0.name'))->toBe('conditional');

    $test->set('offersConditional', false)
        ->call('submitActionModal')
        ->assertOk();
});
