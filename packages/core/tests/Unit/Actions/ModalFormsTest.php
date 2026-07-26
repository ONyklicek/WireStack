<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\Contracts\ModalForm;
use NyonCode\WireCore\Actions\Contracts\ModalFormFactory;
use NyonCode\WireCore\Actions\ModalStep;
use NyonCode\WireCore\Actions\Support\ModalForms;

/**
 * The ModalForms seam is how wire-core builds an action-modal form without
 * naming wire-forms' concrete Form. When wire-forms is installed a factory is
 * bound (the core TestCase binds it, mirroring WireFormsServiceProvider); a
 * standalone wire-core has no binding and must degrade gracefully rather than
 * fatally referencing a class it does not ship.
 */
it('resolves the bound factory and builds a form when wire-forms is installed', function () {
    expect(ModalForms::factory())->toBeInstanceOf(ModalFormFactory::class)
        ->and(ModalForms::make([]))->toBeInstanceOf(ModalForm::class);
});

it('degrades to null when no form factory is bound (standalone wire-core)', function () {
    app()->offsetUnset(ModalFormFactory::class);

    expect(ModalForms::factory())->toBeNull()
        ->and(ModalForms::make([]))->toBeNull();
});

it('degrades an action form modal to a form-less modal when the factory is unbound', function () {
    app()->offsetUnset(ModalFormFactory::class);

    $action = Action::make('edit')->form([]);

    // The modal still opens (hasModal), it simply carries no form body — a
    // standalone wire-core renders it as a confirmation/heading dialog.
    expect($action->hasModal())->toBeTrue()
        ->and($action->hasFormInstance())->toBeFalse()
        ->and($action->getFormInstance())->toBeNull()
        ->and($action->getStepFormInstance())->toBeNull();
});

it('degrades a wizard step form to null when the factory is unbound', function () {
    app()->offsetUnset(ModalFormFactory::class);

    $action = Action::make('wizard')->steps([
        ModalStep::make('One')->schema([]),
    ]);

    // The step itself resolves; only its form body cannot be built without a
    // form runtime, so the wizard degrades instead of fataling.
    expect($action->getModalStep(0))->not->toBeNull()
        ->and($action->getStepFormInstance(null, null, 0))->toBeNull();
});
