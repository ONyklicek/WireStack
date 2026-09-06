<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ModalStep;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Concerns\WithActions;

/*
 * An action modal's submitted bag goes through the same write-path seam as
 * Form::save() (ADR 0021).
 *
 * Before this, the seam had two hosts and the action modal was not one of them:
 * the callback got raw Livewire state. A cleared number input submits '', which
 * no numeric column can hold — MySQL in strict mode refuses the insert outright
 * ("Incorrect decimal value: ''") — and a cleared Select submitted '' even
 * though Select::dehydrateState() has turned that into null on the save path
 * all along. The same schema wrote different values depending on which host
 * persisted it.
 */

class ActionDehydrationHost extends Component
{
    use WithActions;

    /** @var array<string, mixed>|null What the action callback actually received. */
    public ?array $submitted = null;

    protected function actions(): array
    {
        return [$this->editAction(), $this->wizardAction(), $this->repeaterAction()];
    }

    public function editAction(): Action
    {
        return Action::make('edit')
            ->form([
                TextInput::make('discount')->numeric(),
                TextInput::make('note'),
                Select::make('status')->options(['draft' => 'Draft', 'published' => 'Published']),
            ])
            ->action(fn (array $data) => $this->submitted = $data);
    }

    public function wizardAction(): Action
    {
        return Action::make('wizard')
            ->steps([
                ModalStep::make('amounts')
                    ->schema([TextInput::make('discount')->numeric()]),
                ModalStep::make('details')
                    ->schema([TextInput::make('note')]),
            ])
            ->action(fn (array $data) => $this->submitted = $data);
    }

    public function repeaterAction(): Action
    {
        return Action::make('repeater')
            ->form([
                Repeater::make('rows')->schema([
                    TextInput::make('quantity')->numeric(),
                    TextInput::make('label'),
                ]),
            ])
            ->action(fn (array $data) => $this->submitted = $data);
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                <x-wire-actions::modal-host :component="$this" />
            </div>
        BLADE;
    }
}

/**
 * The submitted bag as the action callback received it.
 *
 * Read back off the instance rather than asserted with assertSet(): Livewire
 * compares loosely, so `'' == null` passes and a test written that way stays
 * green with the whole seam removed — which is how it was first written here.
 *
 * @return array<string, mixed>
 */
function submittedBag(Testable $component): array
{
    return $component->instance()->submitted ?? [];
}

it('stores a cleared number input as null, leaving text alone', function () {
    $component = Livewire::test(ActionDehydrationHost::class)
        ->call('mountAction', 'edit')
        ->set('mountedActions.0.data.discount', '')
        ->set('mountedActions.0.data.note', '')
        ->call('callMountedAction');

    $bag = submittedBag($component);

    expect($bag['discount'])->toBeNull()
        // '' is a legitimate string: a non-nullable text column holds one.
        ->and($bag['note'])->toBe('');
});

it('keeps a number that was actually entered', function () {
    $component = Livewire::test(ActionDehydrationHost::class)
        ->call('mountAction', 'edit')
        ->set('mountedActions.0.data.discount', '12.5')
        ->call('callMountedAction');

    expect(submittedBag($component)['discount'])->toBe('12.5');
});

it('applies a fields own dehydration to the action bag, not just to Form::save', function () {
    // Select has implemented the seam since ADR 0021; only the save path ran it.
    $component = Livewire::test(ActionDehydrationHost::class)
        ->call('mountAction', 'edit')
        ->set('mountedActions.0.data.status', '')
        ->call('callMountedAction');

    expect(submittedBag($component)['status'])->toBeNull();
});

it('dehydrates every wizard step, not only the one on screen at submit', function () {
    $component = Livewire::test(ActionDehydrationHost::class)
        ->call('mountAction', 'wizard')
        ->set('mountedActions.0.data.discount', '')
        ->call('nextActionModalStep')
        ->set('mountedActions.0.data.note', 'ok')
        ->call('callMountedAction');

    $bag = submittedBag($component);

    expect($bag['discount'])->toBeNull()
        ->and($bag['note'])->toBe('ok');
});

it('dehydrates repeater children per item', function () {
    $component = Livewire::test(ActionDehydrationHost::class)
        ->call('mountAction', 'repeater')
        ->set('mountedActions.0.data.rows', [
            ['quantity' => '', 'label' => 'first'],
            ['quantity' => '3', 'label' => ''],
        ])
        ->call('callMountedAction');

    expect(submittedBag($component)['rows'])->toBe([
        ['quantity' => null, 'label' => 'first'],
        ['quantity' => '3', 'label' => ''],
    ]);
});
