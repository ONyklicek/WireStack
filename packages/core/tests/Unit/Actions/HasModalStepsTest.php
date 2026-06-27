<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ModalStep;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;

function wizardAction(): Action
{
    return Action::make('wizard')->steps([
        ModalStep::make('One')->schema([TextInput::make('name')]),
        ModalStep::make('Two')->schema([TextInput::make('email')]),
    ]);
}

it('treats an action with steps as a form modal, not a confirmation', function () {
    $action = wizardAction();

    expect($action->hasMultipleSteps())->toBeTrue()
        ->and($action->getStepCount())->toBe(2)
        ->and($action->hasFormModal())->toBeTrue()
        ->and($action->hasFormInstance())->toBeTrue()
        ->and($action->doesRequireConfirmation())->toBeFalse();
});

it('resolves a Form instance for each step', function () {
    $action = wizardAction();

    expect($action->getStepFormInstance(null, null, 0))->toBeInstanceOf(Form::class)
        ->and($action->getStepFormInstance(null, null, 1))->toBeInstanceOf(Form::class);
});

it('clamps the step index to the valid range', function () {
    $action = wizardAction();

    expect($action->getModalStep(99))->toBe($action->getModalStep(1))
        ->and($action->getModalStep(-5))->toBe($action->getModalStep(0));
});

it('returns no step form or step when the action is not a wizard', function () {
    $action = Action::make('plain')->form([TextInput::make('name')]);

    expect($action->hasMultipleSteps())->toBeFalse()
        ->and($action->getStepCount())->toBe(0)
        ->and($action->getStepFormInstance(null, null, 0))->toBeNull()
        ->and($action->getModalStep(0))->toBeNull();
});

it('still treats a plain modal action as a confirmation', function () {
    $action = Action::make('delete')->requiresConfirmation();

    expect($action->doesRequireConfirmation())->toBeTrue();
});
