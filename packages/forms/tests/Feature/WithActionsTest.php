<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ModalFooterAction;
use NyonCode\WireCore\Actions\ModalStep;
use NyonCode\WireCore\Actions\ViewAction;
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireCore\Infolists\Components\RepeatableEntry;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Concerns\WithActions;

class WithActionsHost extends Component
{
    use WithActions;

    public string $log = '';

    protected function actions(): array
    {
        return [
            Action::make('greet')
                ->action(fn () => $this->log = 'greeted'),

            Action::make('editName')
                ->modalHeading('Edit the name')
                ->form([TextInput::make('name')->required()])
                ->action(fn (array $data) => $this->log = 'saved:'.$data['name']),

            Action::make('confirmDelete')
                ->requiresConfirmation()
                ->action(fn () => $this->log = 'deleted'),

            Action::make('haltIt')
                ->action(fn ($halt) => $halt()),

            ViewAction::make('viewProfile')
                ->infolist([
                    Section::make('Profile')
                        ->headerActions([
                            Action::make('refresh')->action(fn () => $this->log = 'refreshed'),
                            Action::make('noCallback'),
                        ])
                        ->schema([
                            TextEntry::make('name')->actions([
                                Action::make('markVerified')
                                    ->action(fn ($record, $state) => $this->log = 'verified:'.data_get($record, 'name', '').':'.($state ?? '')),
                            ]),
                            RepeatableEntry::make('lines')
                                ->state(fn () => [
                                    ['sku' => 'A1', 'qty' => 3],
                                    ['sku' => 'B2', 'qty' => 7],
                                ])
                                ->schema([TextEntry::make('sku')])
                                ->actions([
                                    Action::make('viewLine')
                                        ->action(fn ($record) => $this->log = 'line:'.data_get($record, 'sku', '').':'.data_get($record, 'qty', '')),
                                ]),
                        ]),
                ]),
        ];
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                <x-wire-actions::button :action="$this->editNameActionForView()" />
                <x-wire-actions::modal-host :component="$this" />
            </div>
        BLADE;
    }

    public function editNameActionForView(): Action
    {
        return Action::make('editName')
            ->label('Edit name')
            ->form([TextInput::make('name')->required()]);
    }
}

it('runs a plain action immediately on mount without opening a modal', function () {
    Livewire::test(WithActionsHost::class)
        ->call('mountAction', 'greet')
        ->assertSet('log', 'greeted')
        ->assertSet('mountedActions', []);
});

it('opens a modal for a form action and runs it on callMountedAction', function () {
    Livewire::test(WithActionsHost::class)
        ->call('mountAction', 'editName')
        ->assertSet('actionModalOpen', true)
        ->assertSet('mountedActions.0.name', 'editName')
        ->set('mountedActions.0.data.name', 'Ada')
        ->call('callMountedAction')
        ->assertSet('log', 'saved:Ada')
        ->assertSet('mountedActions', []);
});

it('validates the modal form before running the action', function () {
    Livewire::test(WithActionsHost::class)
        ->call('mountAction', 'editName')
        ->set('mountedActions.0.data.name', '')
        ->call('callMountedAction')
        ->assertHasErrors('mountedActions.0.data.name')
        ->assertSet('log', '');
});

it('opens a confirmation modal and runs the action only after confirming', function () {
    Livewire::test(WithActionsHost::class)
        ->call('mountAction', 'confirmDelete')
        ->assertSet('actionModalOpen', true)
        ->assertSet('log', '')
        ->call('callMountedAction')
        ->assertSet('log', 'deleted');
});

it('halts the pipeline and shows the halt modal', function () {
    Livewire::test(WithActionsHost::class)
        ->call('mountAction', 'haltIt')
        ->assertSet('mountedHalt.show', true)
        ->assertSet('log', '');
});

it('auto-derives a mountAction wire:click on the action button', function () {
    // Rendered inside a Blade `{{ }}` echo, so the quotes are HTML-escaped;
    // assertSee escapes the needle the same way (Livewire decodes it at runtime).
    Livewire::test(WithActionsHost::class)
        ->assertSee("mountAction('editName')");
});

it('renders the modal-host shell only while an action is mounted', function () {
    Livewire::test(WithActionsHost::class)
        ->assertDontSee('Edit the name')
        ->call('mountAction', 'editName')
        ->assertSee('Edit the name');
});

it('closes the mounted action on unmount', function () {
    Livewire::test(WithActionsHost::class)
        ->call('mountAction', 'editName')
        ->assertSet('actionModalOpen', true)
        ->call('unmountAction')
        ->assertSet('mountedActions', [])
        ->assertSet('mountedActions', []);
});

it('runs an infolist entry action from the open action modal', function () {
    Livewire::test(WithActionsHost::class)
        ->call('mountAction', 'viewProfile')
        ->assertSet('actionModalOpen', true)
        ->call('callInfolistAction', 'markVerified')
        ->assertSet('log', 'verified::');
});

it('runs a section header action from the open action modal', function () {
    Livewire::test(WithActionsHost::class)
        ->call('mountAction', 'viewProfile')
        ->call('callInfolistAction', 'refresh')
        ->assertSet('log', 'refreshed');
});

it('ignores an unknown infolist action name', function () {
    Livewire::test(WithActionsHost::class)
        ->call('mountAction', 'viewProfile')
        ->call('callInfolistAction', 'nope')
        ->assertSet('log', '');
});

it('is a no-op for an infolist action without a callback', function () {
    Livewire::test(WithActionsHost::class)
        ->call('mountAction', 'viewProfile')
        ->call('callInfolistAction', 'noCallback')
        ->assertSet('log', '');
});

it('runs a repeatable per-row action with the row record', function () {
    Livewire::test(WithActionsHost::class)
        ->call('mountAction', 'viewProfile')
        ->call('callInfolistAction', 'viewLine', 1)
        ->assertSet('log', 'line:B2:7');
});

it('runs the first repeatable row action for row index 0', function () {
    Livewire::test(WithActionsHost::class)
        ->call('mountAction', 'viewProfile')
        ->call('callInfolistAction', 'viewLine', 0)
        ->assertSet('log', 'line:A1:3');
});

it('is a no-op for a repeatable row index out of range', function () {
    Livewire::test(WithActionsHost::class)
        ->call('mountAction', 'viewProfile')
        ->call('callInfolistAction', 'viewLine', 9)
        ->assertSet('log', 'line::');
});

// ─── Wizard, record scoping, footer & field actions ─────────────────

class WithActionsRecord extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}

class WithActionsAdvancedHost extends Component
{
    use WithActions;

    public string $log = '';

    protected function actions(): array
    {
        return [
            // Plain, record-scoped action: runs immediately with the record.
            Action::make('touch')
                ->action(fn ($record) => $this->log = 'touched:'.$record->name),

            // Multi-step wizard.
            Action::make('onboard')
                ->steps([
                    ModalStep::make('One')->schema([TextInput::make('first')->required()]),
                    ModalStep::make('Two')->schema([TextInput::make('second')->required()]),
                ])
                ->action(fn (array $data) => $this->log = 'onboard:'.$data['first'].'-'.$data['second']),

            // Record-scoped form action: mounting pushes a frame (so the record
            // is serialized to a {class,key} descriptor) and submitting restores
            // it for the callback across the request.
            Action::make('editRecord')
                ->form([TextInput::make('name')])
                ->action(fn ($record, array $data) => $this->log = 'edited:'.$record->name.'->'.$data['name']),

            // Form action with a custom footer action that fills a field.
            Action::make('editWithFooter')
                ->form([TextInput::make('name')])
                ->modalFooterActions([
                    ModalFooterAction::make('autofill')
                        ->action(fn ($set) => $set('name', 'Auto')),
                ])
                ->action(fn (array $data) => $this->log = 'saved:'.$data['name']),

            // Form action whose field dispatches its own action ($get/$set).
            Action::make('editWithFieldAction')
                ->form([
                    TextInput::make('name')->suffixAction(
                        Action::make('upper')
                            ->action(fn ($get, $set) => $set('name', strtoupper((string) $get('name')))),
                    ),
                ]),

            // Plain action that halts into a modal carrying its own form. The
            // field holds a Closure (visible), so serializing the halt form for
            // the polling fallback throws and is swallowed — exercising that path.
            Action::make('haltWithForm')
                ->action(fn ($halt) => $halt()
                    ->modalHeading('Why?')
                    ->form([TextInput::make('reason')->required()->visible(fn () => true)])),
        ];
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

it('runs a record-scoped action with the bound record', function () {
    $record = new WithActionsRecord(['name' => 'Grace']);
    $record->id = 7;

    Livewire::test(WithActionsAdvancedHost::class)
        ->call('mountAction', 'touch', ['record' => $record])
        ->assertSet('log', 'touched:Grace');
});

it('serializes a records identity into its frame and restores it on submit', function () {
    // The record survives the Livewire round-trip as a {class, key} descriptor:
    // describeFrameRecord() stores it on mount, resolveFrameRecord() re-queries
    // it when the submitted callback asks for $record.
    Schema::create('with_actions_records', function ($t) {
        $t->id();
        $t->string('name');
    });
    $record = WithActionsRecord::create(['id' => 7, 'name' => 'Grace']);

    $test = Livewire::test(WithActionsAdvancedHost::class)
        ->call('mountAction', 'editRecord', ['record' => $record]);

    // The frame carries the descriptor, not the model itself.
    expect($test->get('mountedActions.0.record'))->toBe([
        'class' => WithActionsRecord::class,
        'key' => 7,
    ]);

    $test->set('mountedActions.0.data.name', 'Grace H.')
        ->call('callMountedAction')
        ->assertSet('log', 'edited:Grace->Grace H.');

    Schema::dropIfExists('with_actions_records');
});

it('steps a wizard forward and runs it on the final submit', function () {
    Livewire::test(WithActionsAdvancedHost::class)
        ->call('mountAction', 'onboard')
        ->assertSet('actionModalOpen', true)
        ->set('mountedActions.0.data.first', 'a')
        ->call('nextActionModalStep')
        ->assertSet('mountedActions.0.currentStep', 1)
        ->set('mountedActions.0.data.second', 'b')
        ->call('callMountedAction')
        ->assertSet('log', 'onboard:a-b')
        ->assertSet('mountedActions', []);
});

it('validates the current wizard step before advancing', function () {
    Livewire::test(WithActionsAdvancedHost::class)
        ->call('mountAction', 'onboard')
        ->call('nextActionModalStep')
        ->assertHasErrors('mountedActions.0.data.first')
        ->assertSet('mountedActions.0.currentStep', 0);
});

it('steps a wizard back without validation', function () {
    Livewire::test(WithActionsAdvancedHost::class)
        ->call('mountAction', 'onboard')
        ->set('mountedActions.0.data.first', 'a')
        ->call('nextActionModalStep')
        ->assertSet('mountedActions.0.currentStep', 1)
        ->call('prevActionModalStep')
        ->assertSet('mountedActions.0.currentStep', 0);
});

it('runs a custom modal footer action that mutates the form data', function () {
    Livewire::test(WithActionsAdvancedHost::class)
        ->call('mountAction', 'editWithFooter')
        ->call('callModalFooterAction', 'autofill')
        ->assertSet('mountedActions.0.data.name', 'Auto');
});

it('dispatches a field action on the open action modal form', function () {
    Livewire::test(WithActionsAdvancedHost::class)
        ->call('mountAction', 'editWithFieldAction')
        ->set('mountedActions.0.data.name', 'ada')
        ->call('callFieldAction', 'mountedActions.0.data.name', 'upper')
        ->assertSet('mountedActions.0.data.name', 'ADA');
});

it('attaches a form to the halt modal of a standalone action', function () {
    $component = Livewire::test(WithActionsAdvancedHost::class)
        ->call('mountAction', 'haltWithForm')
        ->assertSet('mountedHalt.show', true);

    expect($component->instance()->getHaltModalFormInstance())->not->toBeNull();
});

// ─── Edge cases: empty registry, missing/hidden actions ─────────────

class WithActionsEmptyHost extends Component
{
    use WithActions;

    public function render(): string
    {
        return '<div></div>';
    }
}

it('no-ops when mounting, submitting or stepping with no actions', function () {
    Livewire::test(WithActionsEmptyHost::class)
        ->call('mountAction', 'missing')
        ->assertSet('mountedActions', [])
        ->call('callMountedAction')
        ->assertSet('mountedActions', [])
        ->call('callModalFooterAction', 'whatever')
        ->assertSet('mountedActions', [])
        ->call('nextActionModalStep')
        ->call('prevActionModalStep')
        ->call('unmountAction')
        ->assertSet('mountedActions', []);
});

class WithActionsToggleHost extends Component
{
    use WithActions;

    public string $log = '';

    public bool $available = true;

    protected function actions(): array
    {
        $actions = [
            // Stays registered but becomes hidden — exercises the submit-time
            // canExecute guard.
            Action::make('hidesLater')
                ->form([TextInput::make('x')])
                ->hidden(fn () => ! $this->available)
                ->action(fn () => $this->log = 'ran'),
        ];

        if ($this->available) {
            // Vanishes from the registry entirely between mount and submit.
            $actions[] = Action::make('vanishes')
                ->form([TextInput::make('x')])
                ->action(fn () => $this->log = 'ran');
        }

        return $actions;
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

it('unmounts when the mounted action vanishes from the registry before submit', function () {
    Livewire::test(WithActionsToggleHost::class)
        ->call('mountAction', 'vanishes')
        ->assertSet('actionModalOpen', true)
        ->set('available', false)
        ->call('callMountedAction')
        ->assertSet('log', '')
        ->assertSet('mountedActions', []);
});

it('does not run an action that became hidden before submit', function () {
    Livewire::test(WithActionsToggleHost::class)
        ->call('mountAction', 'hidesLater')
        ->assertSet('actionModalOpen', true)
        ->set('available', false)
        ->call('callMountedAction')
        ->assertSet('log', '');
});
