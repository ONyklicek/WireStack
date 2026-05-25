<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;

beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
});

class ActionModalPartialRenderComponent extends Component
{
    public bool $showActionModal = true;

    public array $modalData = [];

    public function mount(array $modalData): void
    {
        $this->modalData = $modalData;
    }

    public function getActionModalData(): array
    {
        return $this->modalData;
    }

    public function getActionModalFormInstance(): null
    {
        return null;
    }

    public function closeActionModal(): void {}

    public function submitActionModal(): void {}

    public function render()
    {
        return view('wire-table::tables.partials.action-modal', ['component' => $this]);
    }
}

class HaltModalPartialRenderComponent extends Component
{
    public bool $showHaltModal = true;

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
        return view('wire-table::tables.partials.halt-modal', ['component' => $this]);
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
        ->assertSeeHtml('$wire.closeActionModal()')
        ->assertSeeHtml('wire:click="submitActionModal"');
})->with([
    'confirmation' => [['isConfirmation' => true]],
    'slide over' => [['slideOver' => true]],
    'form modal' => [['isConfirmation' => false]],
]);

it('passes close action through halt modal partial', function () {
    Livewire::test(HaltModalPartialRenderComponent::class)
        ->assertSeeHtml('$wire.closeHaltModal()')
        ->assertSeeHtml('wire:click="submitHaltModal"');
});
