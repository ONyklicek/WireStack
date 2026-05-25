<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;

beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
});

class ModalComponentRenderComponent extends Component
{
    public bool $showModal = true;

    public bool $showConfirmation = true;

    public bool $showPanel = true;

    public function closeModal(): void {}

    public function closeConfirmation(): void {}

    public function closePanel(): void {}

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                <x-wire-modals::modal wire:model="showModal" close-action="closeModal" heading="Edit">Body</x-wire-modals::modal>
                <x-wire-modals::confirmation wire:model="showConfirmation" close-action="closeConfirmation" heading="Delete?" />
                <x-wire-modals::slide-over wire:model="showPanel" close-action="closePanel" heading="Details">Body</x-wire-modals::slide-over>
            </div>
        BLADE;
    }
}

it('renders close action handlers for modal components', function () {
    Livewire::test(ModalComponentRenderComponent::class)
        ->assertSeeHtml('$wire.closeModal()')
        ->assertSeeHtml('$wire.closeConfirmation()')
        ->assertSeeHtml('$wire.closePanel()');
});
