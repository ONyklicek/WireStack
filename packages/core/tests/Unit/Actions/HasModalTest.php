<?php

declare(strict_types=1);

use Livewire\Component;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Core\State\StateContainer;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;

// ─── Basic Modal ───────────────────────────────────────────────────────────

it('has no modal by default', function () {
    expect(Action::make('test')->hasModal())->toBeFalse();
});

it('can require confirmation', function () {
    $action = Action::make('delete')->requiresConfirmation();

    expect($action->hasModal())->toBeTrue()
        ->and($action->doesRequireConfirmation())->toBeTrue();
});

// ─── Modal Heading & Description ───────────────────────────────────────────

it('can set modal heading', function () {
    $action = Action::make('delete')
        ->requiresConfirmation()
        ->modalHeading('Opravdu smazat?');

    expect($action->getModalHeading())->toBe('Opravdu smazat?');
});

it('supports dynamic modal heading via closure', function () {
    $action = Action::make('delete')
        ->requiresConfirmation()
        ->modalHeading(fn ($record) => "Smazat {$record->name}?");

    $record = (object) ['name' => 'Jan'];
    expect($action->getModalHeading($record))->toBe('Smazat Jan?');
});

it('can set modal description', function () {
    $action = Action::make('delete')
        ->requiresConfirmation()
        ->modalDescription('Tato akce je nevratná.');

    expect($action->getModalDescription())->toBe('Tato akce je nevratná.');
});

// ─── Modal Icon ────────────────────────────────────────────────────────────

it('can set modal icon', function () {
    $action = Action::make('delete')
        ->requiresConfirmation()
        ->modalIcon('trash', 'danger');

    expect($action->getModalIcon())->toBe('trash')
        ->and($action->getModalIconColor())->toBe('danger');
});

// ─── Modal Labels ──────────────────────────────────────────────────────────

it('can set modal submit label', function () {
    $action = Action::make('archive')
        ->requiresConfirmation()
        ->modalSubmitActionLabel('Archivovat');

    expect($action->getModalSubmitActionLabel())->toBe('Archivovat');
});

it('can set modal cancel label', function () {
    $action = Action::make('test')
        ->requiresConfirmation()
        ->modalCancelActionLabel('Zpět');

    expect($action->getModalCancelActionLabel())->toBe('Zpět');
});

// ─── Modal Width ───────────────────────────────────────────────────────────

it('has default modal width', function () {
    $action = Action::make('test')->requiresConfirmation();

    expect($action->getModalWidth())->toBe('md');
});

it('can set custom modal width', function () {
    $action = Action::make('test')
        ->requiresConfirmation()
        ->modalWidth('xl');

    expect($action->getModalWidth())->toBe('xl');
});

// ─── Modal Behavior ────────────────────────────────────────────────────────

it('closes on click away by default', function () {
    $action = Action::make('test')->requiresConfirmation();

    expect($action->shouldCloseModalOnClickAway())->toBeTrue();
});

it('can disable close on click away', function () {
    $action = Action::make('test')
        ->requiresConfirmation()
        ->closeModalOnClickAway(false);

    expect($action->shouldCloseModalOnClickAway())->toBeFalse();
});

it('closes on escape by default', function () {
    $action = Action::make('test')->requiresConfirmation();

    expect($action->shouldCloseModalOnEscape())->toBeTrue();
});

it('can disable close on escape', function () {
    $action = Action::make('test')
        ->requiresConfirmation()
        ->closeModalOnEscape(false);

    expect($action->shouldCloseModalOnEscape())->toBeFalse();
});

// ─── Slide Over ────────────────────────────────────────────────────────────

it('is not slide over by default', function () {
    $action = Action::make('test')->requiresConfirmation();

    expect($action->isSlideOver())->toBeFalse();
});

it('can be slide over', function () {
    $action = Action::make('test')
        ->requiresConfirmation()
        ->slideOver();

    expect($action->isSlideOver())->toBeTrue();
});

it('can be slide over on mobile only', function () {
    $action = Action::make('test')
        ->requiresConfirmation()
        ->slideOverOnMobile();

    expect($action->isSlideOverOnMobile())->toBeTrue();
});

// ─── Form Modal ────────────────────────────────────────────────────────────

it('has no form modal by default', function () {
    expect(Action::make('test')->hasFormModal())->toBeFalse();
});

it('can set form via component array', function () {
    $action = Action::make('edit')
        ->requiresConfirmation()
        ->form([
            TextInput::make('reason'),
        ]);

    expect($action->hasFormModal())->toBeTrue()
        ->and($action->hasFormInstance())->toBeTrue();
});

it('supports dynamic form fields via closure', function () {
    $action = Action::make('edit')
        ->requiresConfirmation()
        ->form(fn ($record) => [
            TextInput::make('name'),
        ]);

    expect($action->hasFormModal())->toBeTrue()
        ->and($action->hasFormInstance())->toBeTrue();

    $record = (object) ['name' => 'Jan'];
    $form = $action->getFormInstance(context: $record);
    expect($form)->toBeInstanceOf(Form::class);
});

it('uses tableState for modal form state when bound to a table livewire component', function () {
    $component = new class extends Component
    {
        public StateContainer $tableState;

        public function render()
        {
            return <<<'BLADE'
<div></div>
BLADE;
        }
    };

    $component->tableState = new StateContainer([
        'modal' => [
            'action' => [
                'formData' => [],
            ],
        ],
    ]);

    $action = Action::make('edit')
        ->requiresConfirmation()
        ->form([
            TextInput::make('reason'),
        ]);

    $form = $action->getFormInstance($component);

    expect($form)->toBeInstanceOf(Form::class);

    $fields = $form->getFlatComponents();

    expect($fields)->toHaveCount(1)
        ->and($fields[0]->getStatePath())->toBe('tableState.modal.action.formData.reason');
});

// ─── Form Validation ──────────────────────────────────────────────────────

it('can set form validation rules', function () {
    $action = Action::make('submit')
        ->requiresConfirmation()
        ->form([
            TextInput::make('reason'),
        ])
        ->formValidation(['reason' => 'required|min:10']);

    // getFormValidation() prefixes keys with 'actionModalFormData.'
    expect($action->getFormValidation())->toHaveKey('actionModalFormData.reason');

    // getRawFormValidation() returns unprefixed keys
    expect($action->getRawFormValidation())->toHaveKey('reason');
});

// ─── Multiple Steps ────────────────────────────────────────────────────────

it('has no steps by default', function () {
    expect(Action::make('test')->hasMultipleSteps())->toBeFalse();
});

// ─── Modal Config ──────────────────────────────────────────────────────────

it('generates modal config array', function () {
    $action = Action::make('delete')
        ->requiresConfirmation()
        ->modalHeading('Smazat?')
        ->modalDescription('Nevratná akce.')
        ->modalIcon('trash', 'danger');

    $config = $action->getModalConfig();

    expect($config)->toBeArray()
        ->and($config)->toHaveKey('heading')
        ->and($config['heading'])->toBe('Smazat?');
});

// ─── Sticky Footer/Header ─────────────────────────────────────────────────

it('can set sticky footer', function () {
    $action = Action::make('test')
        ->requiresConfirmation()
        ->stickyFooter();

    $config = $action->getModalConfig();
    expect($config['stickyFooter'])->toBeTrue();
});

it('can set sticky header', function () {
    $action = Action::make('test')
        ->requiresConfirmation()
        ->stickyHeader();

    $config = $action->getModalConfig();
    expect($config['stickyHeader'])->toBeTrue();
});

it('can set max height', function () {
    $action = Action::make('test')
        ->requiresConfirmation()
        ->modalMaxHeight('80vh');

    $config = $action->getModalConfig();
    expect($config['maxHeight'])->toBe('80vh');
});
