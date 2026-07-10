<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ModalFooterAction;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Concerns\WithActions;

class ModalStackHost extends Component
{
    use WithActions;

    public string $log = '';

    protected function actions(): array
    {
        return [
            Action::make('parent')
                ->modalHeading('Parent modal')
                ->form([TextInput::make('a')])
                ->action(fn () => $this->log .= 'parent;'),

            Action::make('child')
                ->modalHeading('Child modal')
                ->form([TextInput::make('b')])
                ->action(fn () => $this->log .= 'child;'),

            // A footer action that opens a nested modal from within the parent.
            Action::make('withFooter')
                ->modalHeading('With footer')
                ->modalFooterActions([
                    ModalFooterAction::make('openChild')
                        ->action(fn ($component) => $component->mountAction('child')),
                ])
                ->action(fn () => null),
        ];
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                <x-wire-actions::modal-host :component="$this" />
            </div>
        BLADE;
    }
}

it('stacks a second action on top instead of replacing the first', function () {
    Livewire::test(ModalStackHost::class)
        ->call('mountAction', 'parent')
        ->assertSet('mountedAction.name', 'parent')
        ->assertSet('suspendedActions', [])
        ->call('mountAction', 'child')
        ->assertSet('mountedAction.name', 'child')
        ->assertSet('mountedAction.show', true)
        ->tap(fn ($c) => expect($c->get('suspendedActions'))->toHaveCount(1))
        ->tap(fn ($c) => expect($c->get('suspendedActions')[0]['meta']['name'])->toBe('parent'));
});

it('resumes the parent modal when the stacked modal closes', function () {
    Livewire::test(ModalStackHost::class)
        ->call('mountAction', 'parent')
        ->call('mountAction', 'child')
        ->call('unmountAction')
        ->assertSet('mountedAction.name', 'parent')
        ->assertSet('mountedAction.show', true)
        ->tap(fn ($c) => expect($c->get('suspendedActions'))->toBeEmpty())
        // Closing the last (parent) modal clears the stack entirely.
        ->call('unmountAction')
        ->assertSet('mountedAction.show', null);
});

it('preserves the parent form data while a stacked modal is open', function () {
    Livewire::test(ModalStackHost::class)
        ->call('mountAction', 'parent')
        ->set('actionModalFormData.a', 'Ada')
        ->call('mountAction', 'child')
        ->set('actionModalFormData.b', 'Grace')
        ->call('unmountAction')
        // Back on the parent, its field value survived the round-trip.
        ->assertSet('mountedAction.name', 'parent')
        ->assertSet('actionModalFormData.a', 'Ada');
});

it('exposes suspended parent modals for rendering', function () {
    $component = Livewire::test(ModalStackHost::class)
        ->call('mountAction', 'parent')
        ->call('mountAction', 'child');

    $suspended = $component->instance()->getSuspendedActionModals();

    expect($suspended)->toHaveCount(1)
        ->and($suspended[0]['heading'])->toBe('Parent modal');
});

it('renders the suspended parent shell behind the active modal with a higher z-index', function () {
    Livewire::test(ModalStackHost::class)
        ->call('mountAction', 'parent')
        ->call('mountAction', 'child')
        // Both the dimmed parent shell and the active child modal are present.
        ->assertSeeHtml('Parent modal')
        ->assertSeeHtml('Child modal')
        // The active modal is layered above the base z-50.
        ->assertSeeHtml('z-index: 60');
});

it('keeps the nested modal open when a footer action opened it', function () {
    Livewire::test(ModalStackHost::class)
        ->call('mountAction', 'withFooter')
        ->call('callModalFooterAction', 'openChild')
        // The footer callback opened 'child'; the modal must not auto-close.
        ->assertSet('mountedAction.name', 'child')
        ->assertSet('mountedAction.show', true)
        ->tap(fn ($c) => expect($c->get('suspendedActions'))->toHaveCount(1));
});
