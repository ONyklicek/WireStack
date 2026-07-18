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
