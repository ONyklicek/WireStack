<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ModalFooterAction;
use NyonCode\WireCore\Modals\ModalStack;
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

            // A nested modal whose action returns a value into the parent form.
            Action::make('childReturns')
                ->modalHeading('Child returns')
                ->form([TextInput::make('b')])
                ->action(fn ($setParent, $parentData) => $setParent('a', 'from-child:'.($parentData['a'] ?? ''))),

            // A footer action that opens a nested modal from within the parent.
            Action::make('withFooter')
                ->modalHeading('With footer')
                ->modalFooterActions([
                    ModalFooterAction::make('openChild')
                        ->action(fn ($component) => $component->mountAction('child')),
                ])
                ->action(fn () => null),

            // Writes into an arbitrary ancestor frame by depth.
            Action::make('childFramed')
                ->modalHeading('Child framed')
                ->form([TextInput::make('b')])
                ->action(fn ($setFrame) => $setFrame(0, 'a', 'framed')),

            // A nested action whose callback closes itself via $close().
            Action::make('childCloses')
                ->modalHeading('Child closes')
                ->form([TextInput::make('b')])
                ->action(fn ($close) => $close()),

            // A modal whose footer swaps itself for another action in place.
            Action::make('withReplace')
                ->modalHeading('With replace')
                ->modalFooterActions([
                    ModalFooterAction::make('goChild')
                        ->action(fn ($replace) => $replace('child')),
                ])
                ->action(fn () => null),

            // Exposes the arguments passed to mountAction().
            Action::make('logsArgs')
                ->modalHeading('Logs args')
                ->form([TextInput::make('b')])
                ->action(fn ($arguments) => $this->log .= 'args:'.($arguments['tag'] ?? 'none')),

            // A parent that declares its nested action inline (registerActions),
            // opened from a footer via mountAction by name.
            Action::make('withRegistered')
                ->modalHeading('With registered')
                ->form([TextInput::make('a')])
                ->registerActions([
                    Action::make('inlineChild')
                        ->modalHeading('Inline child')
                        ->form([TextInput::make('b')])
                        ->action(fn ($setParent) => $setParent('a', 'from-inline')),
                ])
                ->modalFooterActions([
                    ModalFooterAction::make('openInline')
                        ->action(fn ($component) => $component->mountAction('inlineChild')),
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
        ->assertSet('mountedActions.0.name', 'parent')
        ->tap(fn ($c) => expect($c->get('mountedActions'))->toHaveCount(1))
        ->call('mountAction', 'child')
        ->assertSet('mountedActions.1.name', 'child')
        ->assertSet('actionModalOpen', true)
        // The parent frame stays on the stack, live, below the active child.
        ->tap(fn ($c) => expect($c->get('mountedActions'))->toHaveCount(2))
        ->assertSet('mountedActions.0.name', 'parent');
});

it('resumes the parent modal when the stacked modal closes', function () {
    Livewire::test(ModalStackHost::class)
        ->call('mountAction', 'parent')
        ->call('mountAction', 'child')
        ->call('unmountAction')
        ->assertSet('mountedActions.0.name', 'parent')
        ->assertSet('actionModalOpen', true)
        ->tap(fn ($c) => expect($c->get('mountedActions'))->toHaveCount(1))
        // Closing the last (parent) modal clears the stack entirely.
        ->call('unmountAction')
        ->assertSet('mountedActions', []);
});

it('preserves the parent form data while a stacked modal is open', function () {
    Livewire::test(ModalStackHost::class)
        ->call('mountAction', 'parent')
        ->set('mountedActions.0.data.a', 'Ada')
        ->call('mountAction', 'child')
        ->set('mountedActions.1.data.b', 'Grace')
        ->call('unmountAction')
        // Back on the parent, its field value survived the round-trip.
        ->assertSet('mountedActions.0.name', 'parent')
        ->assertSet('mountedActions.0.data.a', 'Ada');
});

it('lets a nested modal return data into the parent form', function () {
    Livewire::test(ModalStackHost::class)
        ->call('mountAction', 'parent')
        ->set('mountedActions.0.data.a', 'seed')
        ->call('mountAction', 'childReturns')
        // Submitting the child runs its action ($setParent) and auto-closes it.
        ->call('callMountedAction')
        ->assertSet('mountedActions.0.name', 'parent')
        ->assertSet('mountedActions.0.data.a', 'from-child:seed')
        ->tap(fn ($c) => expect($c->get('mountedActions'))->toHaveCount(1));
});

it('exposes every mounted modal for rendering', function () {
    $component = Livewire::test(ModalStackHost::class)
        ->call('mountAction', 'parent')
        ->call('mountAction', 'child');

    $modals = $component->instance()->getMountedActionModals();

    // Bottom-first: parent (behind) then child (active on top).
    expect($modals)->toHaveCount(2)
        ->and($modals[0]['heading'])->toBe('Parent modal')
        ->and($modals[1]['heading'])->toBe('Child modal');
});

it('renders the live parent form behind the active modal with a higher z-index', function () {
    Livewire::test(ModalStackHost::class)
        ->call('mountAction', 'parent')
        ->call('mountAction', 'child')
        // Both the dimmed (but live) parent form and the active child modal render.
        ->assertSeeHtml('Parent modal')
        ->assertSeeHtml('Child modal')
        // The parent stays a live form: its field is still bound to its frame.
        ->assertSeeHtml('mountedActions.0.data.a')
        // The active modal is layered above the base z-50.
        ->assertSeeHtml('z-index: 60');
});

it('keeps the nested modal open when a footer action opened it', function () {
    Livewire::test(ModalStackHost::class)
        ->call('mountAction', 'withFooter')
        ->call('callModalFooterAction', 'openChild')
        // The footer callback opened 'child'; the modal must not auto-close.
        ->assertSet('mountedActions.1.name', 'child')
        ->assertSet('actionModalOpen', true)
        ->tap(fn ($c) => expect($c->get('mountedActions'))->toHaveCount(2));
});

it('stacks three levels deep and pops back one at a time', function () {
    $c = Livewire::test(ModalStackHost::class)
        ->call('mountAction', 'parent')   // depth 0
        ->set('mountedActions.0.data.a', 'A')
        ->call('mountAction', 'child')    // depth 1
        ->set('mountedActions.1.data.b', 'B')
        ->call('mountAction', 'withFooter'); // depth 2 (active)

    // All three frames live at once.
    expect($c->get('mountedActions'))->toHaveCount(3)
        ->and($c->get('mountedActions')[2]['name'])->toBe('withFooter');

    // Render shows all three modal levels at once, z-indexed by depth.
    $c->assertSeeHtml('Parent modal')   // parent form, z 50
        ->assertSeeHtml('Child modal')  // child form, z 60
        ->assertSeeHtml('With footer')  // active, z 70
        ->assertSeeHtml('z-index: 50')
        ->assertSeeHtml('z-index: 60')
        ->assertSeeHtml('z-index: 70');

    // Close pops one level at a time, restoring each parent's data in order.
    $c->call('unmountAction')
        ->assertSet('mountedActions.1.name', 'child')
        ->assertSet('mountedActions.1.data.b', 'B')
        ->tap(fn ($x) => expect($x->get('mountedActions'))->toHaveCount(2));

    $c->call('unmountAction')
        ->assertSet('mountedActions.0.name', 'parent')
        ->assertSet('mountedActions.0.data.a', 'A')
        ->tap(fn ($x) => expect($x->get('mountedActions'))->toHaveCount(1));

    $c->call('unmountAction')
        ->assertSet('mountedActions', []);
});

it('recedes each deeper parent level with a scale/translate depth cue', function () {
    Livewire::test(ModalStackHost::class)
        ->call('mountAction', 'parent')
        ->call('mountAction', 'child')
        ->call('mountAction', 'withFooter')
        // Stacked-card cue: the parent shells carry an inline scale/translate.
        ->assertSeeHtml('scale(')
        ->assertSeeHtml('translateY(');
});

it('caps stacking at the safety depth to guard against runaway re-entrancy', function () {
    $c = Livewire::test(ModalStackHost::class)->call('mountAction', 'parent');

    // Try to stack far more than the cap; each opens 'child' on top of the last.
    for ($i = 0; $i < 20; $i++) {
        $c->call('mountAction', 'child');
    }

    // The whole live stack never exceeds ModalStack::MAX_DEPTH.
    expect($c->get('mountedActions'))
        ->toHaveCount(ModalStack::MAX_DEPTH);
});

it('writes into an arbitrary ancestor frame via $setFrame(depth, …)', function () {
    Livewire::test(ModalStackHost::class)
        ->call('mountAction', 'parent')       // depth 0
        ->set('mountedActions.0.data.a', 'seed')
        ->call('mountAction', 'child')        // depth 1
        ->call('mountAction', 'childFramed')  // depth 2 (writes to depth 0)
        ->call('callMountedAction')
        ->assertSet('mountedActions.0.data.a', 'framed')
        ->tap(fn ($c) => expect($c->get('mountedActions'))->toHaveCount(2));
});

it('exposes the arguments passed to mountAction as $arguments', function () {
    Livewire::test(ModalStackHost::class)
        ->call('mountAction', 'logsArgs', ['tag' => 'hello'])
        ->call('callMountedAction')
        ->assertSet('log', 'args:hello');
});

it('resolves an inline nested action declared via registerActions', function () {
    Livewire::test(ModalStackHost::class)
        ->call('mountAction', 'withRegistered')
        ->set('mountedActions.0.data.a', 'seed')
        // 'inlineChild' is not in actions() — only registered under withRegistered.
        ->call('callModalFooterAction', 'openInline')
        ->assertSet('mountedActions.1.name', 'inlineChild')
        ->tap(fn ($c) => expect($c->get('mountedActions'))->toHaveCount(2))
        // Its action returns data into the parent, then it closes.
        ->call('callMountedAction')
        ->assertSet('mountedActions.0.data.a', 'from-inline')
        ->tap(fn ($c) => expect($c->get('mountedActions'))->toHaveCount(1));
});

it('does not double-pop the parent when a nested action closes itself via $close()', function () {
    // Regression: the auto-close guard was `count <= depthBefore`, so a callback
    // that itself popped the frame (count < before) triggered a second close that
    // tore down the parent. It must be `=== depthBefore`.
    Livewire::test(ModalStackHost::class)
        ->call('mountAction', 'parent')
        ->set('mountedActions.0.data.a', 'keep')
        ->call('mountAction', 'childCloses')
        ->call('callMountedAction')            // child's action calls $close()
        ->assertSet('mountedActions.0.name', 'parent')
        ->assertSet('mountedActions.0.data.a', 'keep')
        ->tap(fn ($c) => expect($c->get('mountedActions'))->toHaveCount(1));
});

it('replaceMountedAction swaps the active modal in place, leaving parents untouched', function () {
    Livewire::test(ModalStackHost::class)
        ->call('mountAction', 'parent')
        ->set('mountedActions.0.data.a', 'keep')
        ->call('mountAction', 'child')                   // depth 1 (active)
        ->call('replaceMountedAction', 'childReturns')   // replaces depth 1 in place
        ->assertSet('mountedActions.1.name', 'childReturns')
        ->assertSet('mountedActions.0.name', 'parent')
        ->assertSet('mountedActions.0.data.a', 'keep')
        ->tap(fn ($c) => expect($c->get('mountedActions'))->toHaveCount(2));
});

it('lets a footer callback replace the active modal via $replace without auto-closing it', function () {
    // The auto-close guard counts stack mutations, not frame count: a footer
    // callback that pops+pushes (net-zero count) must still be recognised as
    // having handled the modal, so the swapped-in modal is not torn down.
    Livewire::test(ModalStackHost::class)
        ->call('mountAction', 'withReplace')
        ->call('callModalFooterAction', 'goChild')
        ->assertSet('mountedActions.0.name', 'child')
        ->assertSet('actionModalOpen', true)
        ->tap(fn ($c) => expect($c->get('mountedActions'))->toHaveCount(1));
});

it('cancelParentActions() tears down the entire stack', function () {
    Livewire::test(ModalStackHost::class)
        ->call('mountAction', 'parent')
        ->call('mountAction', 'child')
        ->call('mountAction', 'withFooter')
        ->call('cancelParentActions')
        ->assertSet('mountedActions', [])
        ->assertSet('actionModalOpen', false);
});

it('cancelParentActions($name) unwinds up to and including the named ancestor', function () {
    Livewire::test(ModalStackHost::class)
        ->call('mountAction', 'parent')      // depth 0
        ->call('mountAction', 'child')       // depth 1
        ->call('mountAction', 'withFooter')  // depth 2
        // Closes withFooter + child, stops after child; parent survives.
        ->call('cancelParentActions', 'child')
        ->assertSet('mountedActions.0.name', 'parent')
        ->assertSet('actionModalOpen', true)
        ->tap(fn ($c) => expect($c->get('mountedActions'))->toHaveCount(1));
});
