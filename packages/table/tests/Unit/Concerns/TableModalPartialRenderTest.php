<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Core\State\StateContainer;

beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
});

class ActionModalPartialRenderComponent extends Component
{
    public StateContainer $tableState;

    public array $modalData = [];

    public function mount(array $modalData): void
    {
        $this->tableState = new StateContainer([
            'modal' => [
                'actions' => [['show' => true]],
                'open' => true,
            ],
        ]);
        $this->modalData = $modalData;
    }

    public function isActionModalVisible(): bool
    {
        return (bool) $this->tableState->get('modal.open');
    }

    public function getActionModalData(): array
    {
        return $this->modalData;
    }

    public function getActionModalFormInstance(): null
    {
        return null;
    }

    public function getActionModalInfolistInstance(): null
    {
        return null;
    }

    public function getActionModalFormInstanceForDepth(int $depth): null
    {
        return null;
    }

    public function getMountedActionStepIndex(): int
    {
        return 0;
    }

    public function getMountedActionModals(): array
    {
        return $this->isActionModalVisible() ? [$this->modalData] : [];
    }

    public function closeActionModal(): void {}

    public function submitActionModal(): void {}

    public function render()
    {
        return <<<'BLADE'
<div>
    @include('wire-table::tables.partials.action-modal', ['component' => $this])
</div>
BLADE;
    }
}

class HaltModalPartialRenderComponent extends Component
{
    public StateContainer $tableState;

    public function mount(): void
    {
        $this->tableState = new StateContainer([
            'modal' => [
                'halt' => ['show' => true],
            ],
        ]);
    }

    public function getHaltModalData(): array
    {
        return [
            'heading' => 'Halted',
            'description' => 'Confirm halt.',
            'submitLabel' => 'Continue',
            'cancelLabel' => 'Cancel',
        ];
    }

    public function getHaltModalFormInstance(): null
    {
        return null;
    }

    public function closeHaltModal(): void {}

    public function submitHaltModal(): void {}

    public function render()
    {
        return <<<'BLADE'
<div>
    @include('wire-table::tables.partials.halt-modal', ['component' => $this])
</div>
BLADE;
    }
}

it('passes close action through action modal variants', function (array $modalData) {
    Livewire::test(ActionModalPartialRenderComponent::class, [
        'modalData' => array_merge([
            'heading' => 'Action',
            'description' => 'Confirm action.',
            'submitLabel' => 'Submit',
            'cancelLabel' => 'Cancel',
            'actionColor' => 'primary',
        ], $modalData),
    ])
        ->assertSeeHtml("entangle('tableState.modal.open')")
        ->assertSeeHtml('$wire.closeActionModal()')
        ->assertSeeHtml('wire:click="submitActionModal"')
        ->set('tableState.modal.open', false)
        ->assertDontSeeHtml('wire:click="submitActionModal"');
})->with([
    'confirmation' => [['isConfirmation' => true]],
    'slide over' => [['slideOver' => true]],
    'form modal' => [['isConfirmation' => false]],
]);

it('passes close action through halt modal partial', function () {
    Livewire::test(HaltModalPartialRenderComponent::class)
        ->assertSeeHtml("entangle('tableState.modal.halt.show')")
        ->assertSeeHtml('$wire.closeHaltModal()')
        ->assertSeeHtml('wire:click="submitHaltModal"')
        ->set('tableState.modal.halt.show', false)
        ->assertDontSeeHtml('wire:click="submitHaltModal"');
});
