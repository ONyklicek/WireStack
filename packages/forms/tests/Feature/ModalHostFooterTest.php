<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ModalStep;
use NyonCode\WireCore\Actions\ViewAction;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Concerns\WithActions;

/**
 * Which buttons the action modal's footer actually renders, per modal shape.
 *
 * The footer partial decides between Back / Next / Submit from `$isWizard`,
 * `$currentStep`, `$isLastStep` and `$hasInfolist`. Nothing asserted on that
 * output before, so a 1.17.2 typo (`@elseunless`, which Blade passes through as
 * text while its `@endunless` closes the preceding `@if`) moved Submit into the
 * wizard-Next branch: single-step forms and the last wizard step lost their
 * submit button entirely, and the whole suite stayed green.
 *
 * `wire-core::actions.partials.modal-host-footer` backs both the Modal and the
 * SlideOver shell, so both are exercised here.
 *
 * The partial is owned by wire-core but lives in the forms suite alongside
 * WithActionsTest: rendering a form modal needs the wire-forms views, which the
 * core suite deliberately does not boot (it binds only the ModalFormFactory).
 */
class ModalHostFooterHost extends Component
{
    use WithActions;

    public string $log = '';

    protected function actions(): array
    {
        return [
            Action::make('editName')
                ->modalHeading('Edit the name')
                ->form([TextInput::make('name')])
                ->action(fn (array $data) => $this->log = 'saved:'.($data['name'] ?? '')),

            Action::make('editInSlideOver')
                ->modalHeading('Edit in a slide-over')
                ->slideOver()
                ->form([TextInput::make('name')])
                ->action(fn () => $this->log = 'saved'),

            // Three steps so the middle one — Back *and* Next, no Submit — is a
            // state the matrix can actually reach.
            Action::make('onboard')
                ->modalHeading('Onboard')
                ->steps([
                    ModalStep::make('One')->schema([TextInput::make('first')]),
                    ModalStep::make('Two')->schema([TextInput::make('second')]),
                    ModalStep::make('Three')->schema([TextInput::make('third')]),
                ])
                ->action(fn () => $this->log = 'onboarded'),

            ViewAction::make('viewProfile')
                ->modalHeading('Profile')
                ->infolist([TextEntry::make('name')]),
        ];
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div><x-wire-actions::modal-host :component="$this" /></div>
            BLADE;
    }
}

/** @return Testable */
function openFooterModal(string $action)
{
    return Livewire::test(ModalHostFooterHost::class)->call('mountAction', $action);
}

/**
 * Assert exactly which of the footer's four buttons are present.
 *
 * @param  list<string>  $expected  testid suffixes that must render; every other
 *                                  footer button must be absent
 */
function assertFooterButtons(mixed $test, array $expected): void
{
    foreach (['modal-cancel', 'modal-back', 'modal-next', 'modal-submit'] as $testId) {
        $needle = 'data-testid="'.$testId.'"';

        in_array($testId, $expected, true)
            ? $test->assertSeeHtml($needle)
            : $test->assertDontSeeHtml($needle);
    }
}

// ─── Single-step form ────────────────────────────────────────────

it('renders cancel and submit for a single-step form modal', function () {
    // The exact shape the 1.17.2 typo broke: no wizard, so Submit is the only
    // way out — losing it made the modal a dead end.
    assertFooterButtons(openFooterModal('editName'), ['modal-cancel', 'modal-submit']);
});

it('wires the submit button to the host submit method', function () {
    openFooterModal('editName')
        ->assertSeeHtml('wire:click="callMountedAction"')
        ->assertSeeHtml('wire:target="callMountedAction"');
});

it('renders the same footer inside a slide-over shell', function () {
    assertFooterButtons(openFooterModal('editInSlideOver'), ['modal-cancel', 'modal-submit']);
});

// ─── Wizard ──────────────────────────────────────────────────────

it('renders cancel and next on the first wizard step', function () {
    assertFooterButtons(openFooterModal('onboard'), ['modal-cancel', 'modal-next']);
});

it('renders back and next on a middle wizard step', function () {
    $test = openFooterModal('onboard')
        ->call('nextActionModalStep')
        ->assertSet('mountedActions.0.currentStep', 1);

    assertFooterButtons($test, ['modal-cancel', 'modal-back', 'modal-next']);
});

it('renders back and submit on the last wizard step, never next', function () {
    // The second shape the typo broke: the final step rendered Next-or-nothing,
    // so a wizard could be walked to the end and never submitted.
    $test = openFooterModal('onboard')
        ->call('nextActionModalStep')
        ->call('nextActionModalStep')
        ->assertSet('mountedActions.0.currentStep', 2);

    assertFooterButtons($test, ['modal-cancel', 'modal-back', 'modal-submit']);
});

it('wires the wizard navigation buttons to the step methods', function () {
    openFooterModal('onboard')
        ->call('nextActionModalStep')
        ->assertSeeHtml('wire:click="prevActionModalStep"')
        ->assertSeeHtml('wire:click="nextActionModalStep"');
});

// ─── Infolist (read-only) ────────────────────────────────────────

it('renders cancel only for an infolist modal, with nothing to submit', function () {
    assertFooterButtons(openFooterModal('viewProfile'), ['modal-cancel']);
});
