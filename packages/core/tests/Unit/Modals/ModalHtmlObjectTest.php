<?php

declare(strict_types=1);

use Illuminate\Contracts\Support\Htmlable;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Modals\Html\Modal;
use NyonCode\WireCore\Modals\Html\SlideOver;

/**
 * The Htmlable Modal + SlideOver objects (Rule 5 framework-wide, Phase 2).
 *
 * The framework renders modals / slide-overs as first-class Htmlable value
 * objects echoed with `{{ $modal }}` — no `<x-wire-modals::*>` component in the
 * framework's own render paths — while implementing Htmlable and owning exactly
 * one Blade view (Modal Best Practices Rule 5).
 */
beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
});

it('both objects are Htmlable', function () {
    expect(new Modal)->toBeInstanceOf(Htmlable::class)
        ->and(new SlideOver)->toBeInstanceOf(Htmlable::class);
});

class ModalHtmlHost extends Component
{
    public bool $showModal = true;

    public bool $showPanel = true;

    public function closeModal(): void {}

    public function closePanel(): void {}

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                {!! new \NyonCode\WireCore\Modals\Html\Modal(
                    heading: 'Edit record',
                    closeAction: 'closeModal',
                    wireModel: 'showModal',
                    stickyFooter: true,
                    body: '<div id="modal-body-marker">form here</div>',
                    footer: '<button id="modal-footer-marker">Save</button>',
                ) !!}
                {!! new \NyonCode\WireCore\Modals\Html\SlideOver(
                    heading: 'Details panel',
                    closeAction: 'closePanel',
                    wireModel: 'showPanel',
                    body: '<div id="slideover-body-marker">panel body</div>',
                ) !!}
            </div>
        BLADE;
    }
}

it('renders both as dialogs with body/footer and wire bindings — no <x-*>', function () {
    Livewire::test(ModalHtmlHost::class)
        // Modal
        ->assertSeeHtml('id="modal-title"')
        ->assertSeeHtml('Edit record')
        ->assertSeeHtml('id="modal-body-marker"')      // pre-rendered $body rendered raw
        ->assertSeeHtml('id="modal-footer-marker"')    // pre-rendered $footer rendered raw
        ->assertSeeHtml('$wire.closeModal()')          // closeAction wired
        // Slide-over
        ->assertSeeHtml('id="slide-over-title"')
        ->assertSeeHtml('Details panel')
        ->assertSeeHtml('id="slideover-body-marker"')
        ->assertSeeHtml('$wire.closePanel()')
        // never leaks the component tag
        ->assertDontSeeHtml('x-wire-modals::modal')
        ->assertDontSeeHtml('x-wire-modals::slide-over');
});
