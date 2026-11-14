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

it('names each footer action for browser tests, as the action modal host does', function () {
    $html = renderModalFooterActions([
        ModalFooterAction::make('preview')->label('Preview'),
    ]);

    expect($html)
        ->toContain('data-testid="modal-footer-action-preview"')
        ->toContain('aria-label="Preview"');
});

dataset('footer action views', [
    'table' => [fn (ModalFooterAction $action) => renderModalFooterActions([$action])],
    'core' => [fn (ModalFooterAction $action) => view('wire-core::actions.partials.modal-host-footer-action', [
        'footerAction' => $action->toArray(),
    ])->render()],
]);

it('draws an outlined footer action in its own colour', function (Closure $render) {
    // outlined() used to ignore the colour and draw the neutral grey button
    // whatever the action asked for.
    $html = $render(ModalFooterAction::make('skip')->color('yellow')->outlined());

    expect($html)->toContain('border-yellow-700')
        ->toContain('text-yellow-700')
        ->not->toContain('text-gray-700');
})->with('footer action views');

it('keeps the neutral outlined footer action exactly as it was', function (Closure $render) {
    $html = $render(ModalFooterAction::make('skip')->outlined());

    expect($html)->toContain('border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600');
})->with('footer action views');

it('fills a footer action that is not outlined', function (Closure $render) {
    $html = $render(ModalFooterAction::make('skip')->color('yellow'));

    expect($html)->toContain('bg-yellow-400');
})->with('footer action views');
