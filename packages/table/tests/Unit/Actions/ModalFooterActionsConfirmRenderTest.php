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
        ->toContain("callModalFooterAction('reset')");
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
        ->toContain("callModalFooterAction('preview')")
        ->not->toContain('wire:confirm');
});
