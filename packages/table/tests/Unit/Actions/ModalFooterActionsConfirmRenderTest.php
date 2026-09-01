<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\ModalFooterAction;

function renderModalFooterActions(array $actions): string
{
    return view('wire-table::tables.partials.modal-footer-actions', [
        'footerActions' => array_map(fn (ModalFooterAction $action) => $action->toArray(), $actions),
        'position' => 'before',
    ])->render();
}

it('renders wire:confirm on a footer action that requires confirmation', function () {
    $html = renderModalFooterActions([
        ModalFooterAction::make('reset')->confirm('Opravdu resetovat?'),
    ]);

    expect($html)
        ->toContain('wire:confirm="Opravdu resetovat?"')
        ->toContain('callModalFooterAction(&#039;reset&#039;)');
});

it('renders the translated default message for requiresConfirmation()', function () {
    $html = renderModalFooterActions([
        ModalFooterAction::make('reset')->requiresConfirmation(),
    ]);

    expect($html)->toContain('wire:confirm="'.trans('wire-core::actions.confirm_description').'"');
});

it('renders no wire:confirm without confirmation', function () {
    $html = renderModalFooterActions([
        ModalFooterAction::make('preview'),
    ]);

    expect($html)
        ->toContain('callModalFooterAction(&#039;preview&#039;)')
        ->not->toContain('wire:confirm');
});

it('gates each footer action\'s loading state on that action alone', function () {
    // modalFooterActions() is a list, and `wire:target` used to carry the bare
    // method name — which Livewire matches against every call to it, so
    // clicking one footer button disabled and span all of them. The click, the
    // disabled gate and the spinner all have to name the same action.
    $html = renderModalFooterActions([
        ModalFooterAction::make('preview'),
        ModalFooterAction::make('reset'),
    ]);

    expect($html)->toContain('wire:target="callModalFooterAction(&#039;preview&#039;)"')
        ->and($html)->toContain('wire:target="callModalFooterAction(&#039;reset&#039;)"')
        ->and($html)->not->toContain('wire:target="callModalFooterAction"');
});
