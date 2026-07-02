<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Modals\View\ModalComponent;

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

// ─── Mobile presentation variants (regression: slideOverOnMobile was a dead flag) ───

class ModalMobileVariantComponent extends Component
{
    public bool $show = true;

    public string $mode = 'default';

    public function mount(string $mode = 'default'): void
    {
        $this->mode = $mode;
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                @if($mode === 'slide-over')
                    <x-wire-modals::modal wire:model="show" heading="Edit" slide-over-on-mobile>Body</x-wire-modals::modal>
                @elseif($mode === 'full-screen')
                    <x-wire-modals::modal wire:model="show" heading="Edit" full-screen-on-mobile>Body</x-wire-modals::modal>
                @else
                    <x-wire-modals::modal wire:model="show" heading="Edit">Body</x-wire-modals::modal>
                @endif
            </div>
        BLADE;
    }
}

it('renders the mobile slide-over variant (edge-pinned container, slide-in transition, scrolling body)', function () {
    Livewire::test(ModalMobileVariantComponent::class, ['mode' => 'slide-over'])
        ->assertSeeHtml('justify-end pl-10')
        ->assertSeeHtml('translate-x-full sm:translate-x-0')
        ->assertSeeHtml('rounded-l-2xl')
        ->assertSeeHtml('flex-1 overflow-y-auto');
});

it('renders the mobile full-screen variant (edge-to-edge panel with scrolling body)', function () {
    Livewire::test(ModalMobileVariantComponent::class, ['mode' => 'full-screen'])
        ->assertSeeHtml('items-stretch justify-center')
        ->assertSeeHtml('translate-y-full sm:translate-y-0')
        ->assertSeeHtml('rounded-none')
        ->assertSeeHtml('flex-1 overflow-y-auto');
});

it('keeps the default dialog without a mobile variant', function () {
    Livewire::test(ModalMobileVariantComponent::class)
        ->assertSeeHtml('items-end justify-center px-4 pt-4 pb-20')
        ->assertSeeHtml('opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95')
        ->assertDontSeeHtml('translate-x-full');
});

it('slide-over wins when both mobile flags are set', function () {
    $component = new ModalComponent(
        slideOverOnMobile: true,
        fullScreenOnMobile: true,
    );

    expect($component->mobileVariant())->toBe('slide-over');
});
