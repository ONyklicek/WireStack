<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\ActionHalt;
use NyonCode\WireForms\Components\TextInput;

it('can be created via make()', function () {
    expect(ActionHalt::make())->toBeInstanceOf(ActionHalt::class);
});

it('supports fluent configuration', function () {
    $halt = ActionHalt::make()
        ->heading('Test')
        ->body('Description')
        ->icon('check', 'success')
        ->submitLabel('OK')
        ->cancelLabel('Cancel')
        ->width('lg')
        ->danger();

    expect($halt->getModalHeading())->toBe('Test')
        ->and($halt->getModalDescription())->toBe('Description')
        ->and($halt->getModalIcon())->toBe('check')
        ->and($halt->getModalIconColor())->toBe('success')
        ->and($halt->getModalSubmitLabel())->toBe('OK')
        ->and($halt->getModalCancelLabel())->toBe('Cancel')
        ->and($halt->getModalWidth())->toBe('lg')
        ->and($halt->isDanger())->toBeTrue();
});

it('has correct defaults', function () {
    $halt = ActionHalt::make();

    expect($halt->getModalHeading())->toBeNull()
        ->and($halt->getModalSubmitLabel())->toBe('Confirm')
        ->and($halt->getModalCancelLabel())->toBe('Cancel')
        ->and($halt->getModalWidth())->toBe('md')
        ->and($halt->isDanger())->toBeFalse()
        ->and($halt->isInformative())->toBeFalse();
});

it('informative mode clears form and submit', function () {
    $halt = ActionHalt::make()
        ->form([TextInput::make('reason')])
        ->informative();

    expect($halt->isInformative())->toBeTrue()
        ->and($halt->getModalSubmitLabel())->toBeNull()
        ->and($halt->getFormInstance())->toBeNull()
        ->and($halt->getModalCancelLabel())->toBe('Close');
});

it('has confirmDelete preset', function () {
    $halt = ActionHalt::confirmDelete('Test Record');

    expect($halt->getModalHeading())->toBe('Delete record')
        ->and($halt->getModalDescription())->toBe('Are you sure you want to delete "Test Record"? This action is irreversible.')
        ->and($halt->isDanger())->toBeTrue();
});

it('can set source context', function () {
    $halt = ActionHalt::make()->source('before', 2);

    expect($halt->getSource())->toBe('before')
        ->and($halt->getHaltIndex())->toBe(2);
});

it('serializes to array', function () {
    $array = ActionHalt::make()
        ->heading('Test')
        ->danger()
        ->source('action')
        ->toArray();

    expect($array['halt'])->toBeTrue()
        ->and($array['modal']['heading'])->toBe('Test')
        ->and($array['modal']['danger'])->toBeTrue()
        ->and($array['context']['source'])->toBe('action');
});

it('can set form with validation', function () {
    $halt = ActionHalt::make()
        ->form([TextInput::make('reason')])
        ->validation(['reason' => 'required'], ['reason.required' => 'Povinné']);

    expect($halt->hasForm())->toBeTrue()
        ->and($halt->getModalFormValidation())->toBe(['reason' => 'required'])
        ->and($halt->getModalFormValidationMessages())->toBe(['reason.required' => 'Povinné']);
});
