<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Modals\View\ModalComponent;
use NyonCode\WireCore\Modals\View\SlideOverComponent;

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

// ─── Modal stacking z-index ───────────────────────────────────────

class ModalZIndexComponent extends Component
{
    public bool $show = true;

    public ?int $z = null;

    public function mount(?int $z = null): void
    {
        $this->z = $z;
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                <x-wire-modals::modal wire:model="show" heading="Edit" :z-index="$z">Body</x-wire-modals::modal>
                <x-wire-modals::confirmation wire:model="show" heading="Delete?" :z-index="$z" />
                <x-wire-modals::slide-over wire:model="show" heading="Details" :z-index="$z">Body</x-wire-modals::slide-over>
            </div>
        BLADE;
    }
}

it('plugs a stacking z-index into each modal surface when given', function () {
    Livewire::test(ModalZIndexComponent::class, ['z' => 70])
        ->assertSeeHtml('z-index: 70');
});

it('omits an inline z-index when no stacking level is set', function () {
    Livewire::test(ModalZIndexComponent::class)
        ->assertDontSeeHtml('z-index:');
});

// ─── Suspended (parent) modal shell ───────────────────────────────

class SuspendedShellComponent extends Component
{
    public bool $slideOver = false;

    public function mount(bool $slideOver = false): void
    {
        $this->slideOver = $slideOver;
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                @include('wire-core::modals.suspended', [
                    'modalData' => [
                        'heading' => 'Parent heading',
                        'description' => 'Parent description',
                        'width' => 'lg',
                        'slideOver' => $slideOver,
                    ],
                    'zIndex' => 50,
                ])
            </div>
        BLADE;
    }
}

it('renders a dimmed, inert suspended modal shell with its heading and z-index', function () {
    Livewire::test(SuspendedShellComponent::class)
        ->assertSeeHtml('Parent heading')
        ->assertSeeHtml('Parent description')
        ->assertSeeHtml('z-index: 50')
        ->assertSeeHtml('pointer-events-none')
        ->assertSeeHtml('opacity-60');
});

it('renders the suspended shell in its slide-over form', function () {
    Livewire::test(SuspendedShellComponent::class, ['slideOver' => true])
        ->assertSeeHtml('Parent heading')
        ->assertSeeHtml('fixed inset-y-0 right-0');
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
                @if($mode === 'bottom-sheet')
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

it('renders the mobile bottom-sheet variant (bottom-pinned container, slide-up transition, scrolling body)', function () {
    Livewire::test(ModalMobileVariantComponent::class, ['mode' => 'bottom-sheet'])
        ->assertSeeHtml('items-end justify-center')
        ->assertSeeHtml('translate-y-full sm:translate-y-0')
        ->assertSeeHtml('rounded-t-2xl')
        ->assertSeeHtml('flex-1 overflow-y-auto')
        ->assertDontSeeHtml('translate-x-full');
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

class SlideOverBottomSheetComponent extends Component
{
    public bool $show = true;

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                <x-wire-modals::slide-over wire:model="show" heading="Details" bottom-sheet-on-mobile>Body</x-wire-modals::slide-over>
            </div>
        BLADE;
    }
}

it('renders the slide-over as a mobile bottom-sheet (full-width tray, slide-up) that stays a slide-over ≥sm', function () {
    Livewire::test(SlideOverBottomSheetComponent::class)
        ->assertSeeHtml('inset-x-0 bottom-0 sm:inset-x-auto sm:top-0 sm:bottom-0 sm:right-0 sm:pl-10')
        ->assertSeeHtml('w-full sm:w-screen')
        ->assertSeeHtml('translate-y-full sm:translate-y-0 sm:translate-x-full')
        ->assertSeeHtml('max-h-[85vh] rounded-t-2xl sm:h-full sm:max-h-none sm:rounded-none')
        // Width caps at ≥sm so the mobile tray is full-width.
        ->assertSeeHtml('sm:max-w-md');
});

it('keeps the plain slide-over edge-pinned at every breakpoint without the bottom-sheet flag', function () {
    Livewire::test(ModalComponentRenderComponent::class)
        ->assertSeeHtml('inset-y-0 right-0 pl-10')
        ->assertDontSeeHtml('translate-y-full');
});

it('bottom-sheet wins when both mobile flags are set', function () {
    $component = new ModalComponent(
        slideOverOnMobile: true,
        fullScreenOnMobile: true,
    );

    expect($component->mobileVariant())->toBe('bottom-sheet');
});

it('gates the modal sheet switch on the configured breakpoint', function () {
    $sheet = new ModalComponent(slideOverOnMobile: true);

    // Default sm: the desktop reset + width cap gate at sm.
    config(['wire-core.mobile.breakpoint' => 'sm']);
    expect($sheet->containerClasses())->toContain('sm:block')
        ->and($sheet->widthClass())->toBe('sm:max-w-md')
        ->and($sheet->panelVariantClasses())->toContain('sm:inline-block');

    // md (tablets): everything shifts to md so the sheet spans up to 768px.
    config(['wire-core.mobile.breakpoint' => 'md']);
    expect($sheet->containerClasses())->toContain('md:block')->not->toContain('sm:block')
        ->and($sheet->widthClass())->toBe('md:max-w-md')
        ->and($sheet->panelVariantClasses())->toContain('md:inline-block')
        ->and($sheet->transitionClasses()['enterStart'])->toContain('md:translate-y-0');

    config(['wire-core.mobile.breakpoint' => 'sm']);
});

it('gates the compose slide-over sheet switch on the configured breakpoint', function () {
    $so = new SlideOverComponent(bottomSheetOnMobile: true);

    config(['wire-core.mobile.breakpoint' => 'sm']);
    expect($so->positionClasses())->toContain('sm:right-0')
        ->and($so->panelClasses())->toContain('sm:h-full')
        ->and($so->widthClass())->toBe('sm:max-w-md');

    config(['wire-core.mobile.breakpoint' => 'md']);
    expect($so->positionClasses())->toContain('md:right-0')->not->toContain('sm:right-0')
        ->and($so->panelClasses())->toContain('md:h-full')
        ->and($so->widthWrapperClasses())->toBe('w-full md:w-screen')
        ->and($so->widthClass())->toBe('md:max-w-md');

    config(['wire-core.mobile.breakpoint' => 'sm']);
});
