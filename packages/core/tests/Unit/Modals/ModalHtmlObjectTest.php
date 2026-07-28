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

class ModalOpenOnHost extends Component
{
    public function render(): string
    {
        return <<<'BLADE'
            <div>
                {!! new \NyonCode\WireCore\Modals\Html\Modal(
                    heading: 'Keyboard shortcuts',
                    openOn: 'demo-open-help',
                    body: '<div id="open-on-modal-body">help</div>',
                ) !!}
                {!! new \NyonCode\WireCore\Modals\Html\SlideOver(
                    heading: 'Event panel',
                    openOn: 'demo-open-panel',
                    body: '<div id="open-on-panel-body">panel</div>',
                ) !!}
            </div>
        BLADE;
    }
}

it('opens on a window event instead of a wire:model binding when openOn is set', function () {
    Livewire::test(ModalOpenOnHost::class)
        // `show` is plain Alpine state, opened by the window event…
        ->assertSeeHtml('x-on:demo-open-help.window="show = true"')
        ->assertSeeHtml('x-on:demo-open-panel.window="show = true"')
        ->assertSeeHtml('x-data="{ show: false }"')
        // …and never a Livewire-entangled binding.
        ->assertDontSeeHtml('entangle');
});

class ModalOpenOnUnsafeHost extends Component
{
    public function render(): string
    {
        return <<<'BLADE'
            <div>
                {!! new \NyonCode\WireCore\Modals\Html\Modal(
                    heading: 'Unsafe',
                    openOn: 'evil onmouseover=alert(1) x',
                ) !!}
            </div>
        BLADE;
    }
}

it('drops an openOn event name that is not a safe attribute token', function () {
    // x-on:{event} sits in attribute-name position, where Blade escaping does
    // not stop a space from starting a brand-new attribute.
    Livewire::test(ModalOpenOnUnsafeHost::class)
        ->assertDontSeeHtml('onmouseover')
        ->assertDontSeeHtml('x-on:evil')
        ->assertSeeHtml('x-data="{ show: false }"');
});

class ModalOpenOnBoundHost extends Component
{
    public bool $show = false;

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                {!! new \NyonCode\WireCore\Modals\Html\Modal(
                    heading: 'Bound',
                    wireModel: 'show',
                    openOn: 'demo-open-bound',
                ) !!}
            </div>
        BLADE;
    }
}

it('ignores openOn when a wire:model binding owns the show state', function () {
    Livewire::test(ModalOpenOnBoundHost::class)
        ->assertSeeHtml('entangle')
        ->assertDontSeeHtml('x-on:demo-open-bound.window')
        ->assertDontSeeHtml('x-data="{ show: false }"');
});

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
