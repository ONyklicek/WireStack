<?php

declare(strict_types=1);

use Illuminate\Contracts\Support\Htmlable;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Modals\Html\Confirmation;

/**
 * The Htmlable Confirmation object (Rule 5 framework-wide, Phase 1).
 *
 * The framework renders confirmations as a first-class Htmlable value object
 * echoed with `{{ $confirmation }}` — no `<x-wire-modals::confirmation>`
 * component in the framework's own render paths — while implementing Htmlable
 * and owning exactly one Blade view (Modal Best Practices Rule 5).
 */
beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
});

it('is Htmlable and applies the label + danger defaults', function () {
    $c = new Confirmation(isDanger: true);

    expect($c)->toBeInstanceOf(Htmlable::class)
        ->and($c->color)->toBe('danger')          // isDanger promotes the color
        ->and($c->submitLabel)->not->toBeNull()    // default translated label
        ->and($c->cancelLabel)->not->toBeNull();
});

class ConfirmationObjectHost extends Component
{
    public bool $show = true;

    public function submit(): void {}

    public function close(): void {}

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                {!! new \NyonCode\WireCore\Modals\Html\Confirmation(
                    heading: 'Delete record?',
                    description: 'This cannot be undone.',
                    icon: 'trash',
                    iconColor: 'danger',
                    submitLabel: 'Delete',
                    cancelLabel: 'Cancel',
                    color: 'danger',
                    closeAction: 'close',
                    wireModel: 'show',
                    wireClick: 'submit',
                    body: '<div id="halt-body">extra</div>',
                    footerActions: [['name' => 'preview', 'label' => 'Preview', 'color' => 'gray', 'position' => 'before']],
                ) !!}
            </div>
        BLADE;
    }
}

it('renders as a dialog with forwarded wire bindings, body and buttons — no <x-*>', function () {
    Livewire::test(ConfirmationObjectHost::class)
        ->assertSeeHtml('role="dialog"')
        ->assertSeeHtml('id="confirmation-modal-title"')
        ->assertSeeHtml('Delete record?')
        ->assertSeeHtml('wire:click="submit"')             // $wireClick forwarded onto the submit button
        ->assertSeeHtml('$wire.close()')                    // closeAction on cancel/escape
        ->assertSeeHtml('id="halt-body"')                   // body rendered
        ->assertSeeHtml('data-testid="confirmation-confirm"')
        ->assertSeeHtml('data-testid="confirmation-cancel"')
        // additional footer action rendered via the Action API (Modal Rule 4)
        ->assertSeeHtml('data-testid="modal-footer-action-preview"')
        ->assertSeeHtml("callModalFooterAction('preview')")
        ->assertDontSeeHtml('x-wire-modals::confirmation'); // never falls back to the component
});

class ConfirmationOpenOnHost extends Component
{
    public function render(): string
    {
        return <<<'BLADE'
            <div>
                {!! new \NyonCode\WireCore\Modals\Html\Confirmation(
                    heading: 'Event-opened?',
                    openOn: 'demo-open-confirm',
                    isInformative: true,
                ) !!}
            </div>
        BLADE;
    }
}

it('opens on a window event instead of a wire:model binding when openOn is set', function () {
    Livewire::test(ConfirmationOpenOnHost::class)
        ->assertSeeHtml('x-on:demo-open-confirm.window="show = true"')
        ->assertSeeHtml('x-data="{ show: false }"')
        ->assertDontSeeHtml('entangle')
        // the cancel button still closes the client-side state
        ->assertSeeHtml('data-testid="confirmation-cancel"');
});

class ConfirmationVariantHost extends Component
{
    public bool $show = true;

    public string $variant = 'default';

    public function mount(string $variant = 'default'): void
    {
        $this->variant = $variant;
    }

    public function render(): string
    {
        // @entangle needs a live Livewire context, so the Confirmation object is
        // rendered inside a host component rather than via a bare toHtml() call.
        return <<<'BLADE'
            <div>
                {!! new \NyonCode\WireCore\Modals\Html\Confirmation(
                    heading: 'Delete?',
                    wireModel: 'show',
                    slideOverOnMobile: $variant === 'sheet',
                    fullScreenOnMobile: $variant === 'full',
                ) !!}
            </div>
        BLADE;
    }
}

it('renders a centered dialog by default (no mobile sheet)', function () {
    Livewire::test(ConfirmationVariantHost::class, ['variant' => 'default'])
        ->assertDontSeeHtml('translate-y-full')       // no bottom-sheet slide-up
        ->assertDontSeeHtml('rounded-t-2xl');
});

it('renders a bottom-sheet on mobile when slideOverOnMobile is set (regression: the confirmation shell ignored the flag)', function () {
    Livewire::test(ConfirmationVariantHost::class, ['variant' => 'sheet'])
        ->assertSeeHtml('translate-y-full sm:translate-y-0')  // slides up from the bottom edge
        ->assertSeeHtml('rounded-t-2xl')                       // sheet rounding
        ->assertSeeHtml('items-end');                         // pinned to the bottom
});

it('renders full-screen on mobile when fullScreenOnMobile is set', function () {
    Livewire::test(ConfirmationVariantHost::class, ['variant' => 'full'])
        ->assertSeeHtml('translate-y-full sm:translate-y-0')
        ->assertSeeHtml('items-stretch');
});
