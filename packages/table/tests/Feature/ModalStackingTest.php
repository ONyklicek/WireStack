<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\HeaderAction;
use NyonCode\WireCore\Actions\ModalFooterAction;
use NyonCode\WireCore\Modals\ModalStack;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

class MsUser extends Model
{
    protected $table = 'ms_users';

    protected $guarded = [];

    public $timestamps = false;
}

class MsStackComponent extends Component
{
    use WithTable;

    public string $log = '';

    public function table(Table $table): Table
    {
        return $table
            ->model(MsUser::class)
            ->paginated(false)
            ->columns([TextColumn::make('name')])
            ->actions([
                Action::make('edit')
                    ->modalHeading('Edit user')
                    ->form([TextInput::make('name')])
                    ->registerActions([
                        // Declared inline; not in the top-level actions list.
                        Action::make('inlineNote')
                            ->modalHeading('Inline note')
                            ->form([TextInput::make('note')])
                            ->action(fn ($setParent) => $setParent('name', 'from-inline')),
                    ])
                    ->action(fn () => null),
                Action::make('note')
                    ->modalHeading('Add note')
                    ->form([TextInput::make('note')])
                    ->action(fn () => null),
                // A nested modal whose action returns a value into the parent.
                Action::make('noteReturns')
                    ->modalHeading('Note returns')
                    ->form([TextInput::make('note')])
                    ->action(fn ($setParent) => $setParent('name', 'from-note')),
                // Reads the arguments passed to openActionModal().
                Action::make('argAction')
                    ->modalHeading('Arg action')
                    ->form([TextInput::make('note')])
                    ->action(fn ($arguments) => $this->log = 'tag:'.($arguments['tag'] ?? 'none')),
                // A row action whose callback swaps itself for another in place.
                Action::make('replacesSelf')
                    ->modalHeading('Replaces self')
                    ->form([TextInput::make('note')])
                    ->action(fn ($replace) => $replace('note')),
            ])
            ->headerActions([
                // A header action with an inline-registered nested header action.
                HeaderAction::make('hdrEdit')
                    ->modalHeading('Header edit')
                    ->form([TextInput::make('name')])
                    ->registerActions([
                        HeaderAction::make('hdrNested')
                            ->modalHeading('Header nested')
                            ->form([TextInput::make('x')])
                            ->action(fn ($setParent) => $setParent('name', 'from-hdr-nested')),
                    ])
                    ->modalFooterActions([
                        ModalFooterAction::make('openHdrNested')
                            ->action(fn ($component) => $component->openHeaderActionModal('hdrNested')),
                    ])
                    ->action(fn () => null),
                // A header action that stacks itself, to exercise the depth cap.
                HeaderAction::make('deepHdr')
                    ->modalHeading('Deep header')
                    ->modalFooterActions([
                        ModalFooterAction::make('more')
                            ->action(fn ($component) => $component->openHeaderActionModal('deepHdr')),
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

    Schema::create('ms_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    MsUser::create(['name' => 'Ada']);
});

afterEach(function () {
    Schema::dropIfExists('ms_users');
});

it('stacks a second table action modal on top of the first', function () {
    $test = Livewire::test(MsStackComponent::class)
        ->call('openActionModal', '1', 'edit')
        ->call('openActionModal', '1', 'note');

    $state = $test->instance()->tableState;

    // Both frames live at once; note is the active top, edit the parent below it.
    expect($state->get('modal.actions'))->toHaveCount(2)
        ->and($state->get('modal.actions.1.name'))->toBe('note')
        ->and($state->get('modal.open'))->toBeTrue()
        ->and($state->get('modal.actions.0.name'))->toBe('edit');
});

it('resumes the parent table modal when the stacked one closes', function () {
    $test = Livewire::test(MsStackComponent::class)
        ->call('openActionModal', '1', 'edit')
        ->call('openActionModal', '1', 'note')
        ->call('closeActionModal');

    $state = $test->instance()->tableState;

    expect($state->get('modal.actions'))->toHaveCount(1)
        ->and($state->get('modal.actions.0.name'))->toBe('edit')
        ->and($state->get('modal.open'))->toBeTrue();

    // Closing the last (parent) modal clears it fully.
    $test->call('closeActionModal');
    expect($test->instance()->isActionModalVisible())->toBeFalse();
});

it('preserves the parent table modal form data across a stacked modal', function () {
    $test = Livewire::test(MsStackComponent::class)
        ->call('openActionModal', '1', 'edit')
        ->set('tableState.modal.actions.0.data.name', 'Grace')
        ->call('openActionModal', '1', 'note')
        ->call('closeActionModal');

    expect($test->instance()->tableState->get('modal.actions.0.data.name'))->toBe('Grace');
});

it('lets a nested table modal return data into the parent form', function () {
    $test = Livewire::test(MsStackComponent::class)
        ->call('openActionModal', '1', 'edit')
        ->set('tableState.modal.actions.0.data.name', 'seed')
        ->call('openActionModal', '1', 'noteReturns')
        ->call('submitActionModal');

    $state = $test->instance()->tableState;

    expect($state->get('modal.actions'))->toHaveCount(1)
        ->and($state->get('modal.actions.0.name'))->toBe('edit')
        ->and($state->get('modal.actions.0.data.name'))->toBe('from-note');
});

it('exposes every mounted table modal for rendering', function () {
    $test = Livewire::test(MsStackComponent::class)
        ->call('openActionModal', '1', 'edit')
        ->call('openActionModal', '1', 'note');

    $modals = $test->instance()->getMountedActionModals();

    expect($modals)->toHaveCount(2)
        ->and($modals[0]['heading'])->toBe('Edit user')
        ->and($modals[1]['heading'])->toBe('Add note');
});

it('renders the live parent form and elevates the active modal z-index', function () {
    Livewire::test(MsStackComponent::class)
        ->call('openActionModal', '1', 'edit')
        ->call('openActionModal', '1', 'note')
        ->assertSeeHtml('Edit user')
        ->assertSeeHtml('Add note')
        ->assertSeeHtml('z-index: 60');
});

it('exposes arguments passed to openActionModal as $arguments', function () {
    Livewire::test(MsStackComponent::class)
        ->call('openActionModal', '1', 'argAction', ['tag' => 'hi'])
        ->call('submitActionModal')
        ->assertSet('log', 'tag:hi');
});

it('resolves an inline nested table action declared via registerActions', function () {
    $test = Livewire::test(MsStackComponent::class)
        ->call('openActionModal', '1', 'edit')
        ->set('tableState.modal.actions.0.data.name', 'seed')
        // 'inlineNote' is only registered under 'edit', not a top-level action.
        ->call('openActionModal', '1', 'inlineNote')
        ->call('submitActionModal');

    $state = $test->instance()->tableState;

    expect($state->get('modal.actions'))->toHaveCount(1)
        ->and($state->get('modal.actions.0.name'))->toBe('edit')
        ->and($state->get('modal.actions.0.data.name'))->toBe('from-inline');
});

it('resolves an inline-registered nested HEADER action (registerActions parity)', function () {
    // Regression: findHeaderAction/findBulkAction did not recurse into
    // registerActions(), so a nested header action opened via
    // openHeaderActionModal() silently no-oped.
    $test = Livewire::test(MsStackComponent::class)
        ->call('openHeaderActionModal', 'hdrEdit')
        ->set('tableState.modal.actions.0.data.name', 'seed')
        // 'hdrNested' is only registered under 'hdrEdit', not a top-level header action.
        ->call('callModalFooterAction', 'openHdrNested')
        ->assertSet('tableState.modal.actions.1.name', 'hdrNested')
        ->call('submitActionModal');

    $state = $test->instance()->tableState;

    expect($state->get('modal.actions'))->toHaveCount(1)
        ->and($state->get('modal.actions.0.name'))->toBe('hdrEdit')
        ->and($state->get('modal.actions.0.data.name'))->toBe('from-hdr-nested');
});

it('caps stacking at the safety depth on the TABLE host', function () {
    $test = Livewire::test(MsStackComponent::class)->call('openHeaderActionModal', 'deepHdr');

    for ($i = 0; $i < 20; $i++) {
        $test->call('callModalFooterAction', 'more');
    }

    expect($test->instance()->tableState->get('modal.actions'))
        ->toHaveCount(ModalStack::MAX_DEPTH);
});

it('replaceMountedAction swaps a table row action in place, inheriting the record', function () {
    $test = Livewire::test(MsStackComponent::class)
        ->call('openActionModal', '1', 'edit')          // depth 0, record 1
        ->call('openActionModal', '1', 'note')          // depth 1 (active)
        ->call('replaceMountedAction', 'noteReturns');  // replaces depth 1 in place

    $state = $test->instance()->tableState;

    expect($state->get('modal.actions'))->toHaveCount(2)
        ->and($state->get('modal.actions.1.name'))->toBe('noteReturns')
        ->and($state->get('modal.actions.0.name'))->toBe('edit')
        // The replacement inherited the replaced frame's record.
        ->and((string) $state->get('modal.actions.1.recordKey'))->toBe('1');
});

it('lets a row action callback replace itself via $replace without auto-closing (table submit guard)', function () {
    // Regression: submitActionModal's auto-close guard counts stack mutations,
    // so a callback that pops+pushes (net-zero count) does not tear down the swap.
    $test = Livewire::test(MsStackComponent::class)
        ->call('openActionModal', '1', 'replacesSelf')
        ->call('submitActionModal');

    $state = $test->instance()->tableState;

    expect($state->get('modal.actions'))->toHaveCount(1)
        ->and($state->get('modal.actions.0.name'))->toBe('note');
});

it('cancelParentActions() clears the entire table modal stack', function () {
    $test = Livewire::test(MsStackComponent::class)
        ->call('openActionModal', '1', 'edit')
        ->call('openActionModal', '1', 'note')
        ->call('cancelParentActions');

    expect($test->instance()->isActionModalVisible())->toBeFalse()
        ->and($test->instance()->tableState->get('modal.actions'))->toHaveCount(0);
});
