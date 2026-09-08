<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Concerns\WithActions;

/*
 * A halt outside a table.
 *
 * The pipeline that raises one is core's, so every host that runs actions can be
 * halted — but until 2.0 only the table could *show* it: the standalone host
 * wrote `mountedHalt` state that no view read and no method could confirm, so
 * the action stopped and the screen said nothing at all.
 */

class ShComponent extends Component
{
    use WithActions;

    public string $log = '';

    public bool $informative = false;

    protected function actions(): array
    {
        return [
            // Asks a question, then does the work on the confirmed pass.
            Action::make('archive')
                ->action(function (bool $confirmed, array $data, callable $halt) {
                    if (! $confirmed) {
                        return $halt()
                            ->heading('Why are you archiving this?')
                            ->form([TextInput::make('reason')->required()])
                            ->maxHeight('24rem')
                            ->validation(['reason' => 'required|min:3']);
                    }

                    $this->log = 'archived:'.($data['reason'] ?? '');
                }),

            // A dead end: something to read, nothing to confirm.
            Action::make('locked')
                ->action(fn (callable $halt) => $halt()
                    ->informative()
                    ->heading('Nothing to export')
                    ->description('The filter you applied matches no rows.')),
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

it('shows the halt modal on a host that is not a table', function () {
    Livewire::test(ShComponent::class)
        ->call('mountAction', 'archive')
        ->assertSet('mountedHalt.show', true)
        ->assertSet('log', '')
        ->assertSee('Why are you archiving this?');
});

it('keeps the modal open when the halt form fails its rules', function () {
    Livewire::test(ShComponent::class)
        ->call('mountAction', 'archive')
        ->set('actionModalHaltData.reason', 'no')
        ->call('submitHaltModal')
        ->assertHasErrors()
        ->assertSet('mountedHalt.show', true)
        ->assertSet('log', '');
});

it('still renders the halt form after a failed validation', function () {
    // The instance that drew the fields belongs to the request that raised the
    // halt; every later render restores it from the session. Without that the
    // modal comes back as a heading and two buttons — the config still says it
    // has a form, and there is nothing left to render one from.
    $test = Livewire::test(ShComponent::class)
        ->call('mountAction', 'archive')
        ->set('actionModalHaltData.reason', '')
        ->call('submitHaltModal')
        ->assertHasErrors();

    expect($test->instance()->getHaltModalFormInstance())->not->toBeNull();

    $test->assertSee('Why are you archiving this?')
        ->assertSeeHtml('actionModalHaltData.reason')
        // The message is rendered server-side onto the field's own state path.
        // Getting it onto the screen inside a teleported confirmation is a
        // separate, older problem — see verify-standalone-halt.mjs.
        ->assertSee('field is required');

    dump(['html-has-message' => str_contains($test->html(), 'field is required'), 'errors' => array_keys($test->errors()->toArray())]);
});

it('re-runs the action with what the halt collected', function () {
    Livewire::test(ShComponent::class)
        ->call('mountAction', 'archive')
        ->set('actionModalHaltData.reason', 'superseded')
        ->call('submitHaltModal')
        ->assertSet('log', 'archived:superseded')
        ->assertSet('mountedHalt', []);
});

it('dismisses without running the action', function () {
    Livewire::test(ShComponent::class)
        ->call('mountAction', 'archive')
        ->call('closeHaltModal')
        ->assertSet('mountedHalt', [])
        ->assertSet('log', '');
});

it('renders an informative halt with no submit', function () {
    $test = Livewire::test(ShComponent::class)
        ->call('mountAction', 'locked')
        ->assertSet('mountedHalt.show', true)
        ->assertSee('Nothing to export');

    expect($test->instance()->getHaltModalData()['informative'])->toBeTrue()
        ->and($test->instance()->getHaltModalData()['submitLabel'])->toBeNull();
});

it('caps the halt body at the height the halt asked for', function () {
    // A halt speaks the modal vocabulary, so maxHeight() has to mean something:
    // it was serialized into the halt config and dropped by every render path.
    Livewire::test(ShComponent::class)
        ->call('mountAction', 'archive')
        ->assertSeeHtml('style="max-height: 24rem"')
        ->assertSeeHtml('overflow-y-auto overscroll-contain');
});
