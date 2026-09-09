<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\ActionHalt;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;

// ─── Factory ─────────────────────────────���───────────────────────────���──────

it('can be created via make()', function () {
    expect(ActionHalt::make())->toBeInstanceOf(ActionHalt::class);
});

// ─── Fluent API ─────────────────────────────��───────────────────────────────

it('supports fluent configuration', function () {
    $halt = ActionHalt::make()
        ->heading('Smazat?')
        ->description('Opravdu chcete smazat?')
        ->icon('trash', 'danger')
        ->submitLabel('Smazat')
        ->cancelLabel('Ne')
        ->width('lg')
        ->color('danger')
        ->danger();

    expect($halt->getHeading())->toBe('Smazat?')
        ->and($halt->getDescription())->toBe('Opravdu chcete smazat?')
        ->and($halt->getModalIcon())->toBe('trash')
        ->and($halt->getModalIconColor())->toBe('danger')
        ->and($halt->getModalSubmitLabel())->toBe('Smazat')
        ->and($halt->getModalCancelLabel())->toBe('Ne')
        ->and($halt->getWidth())->toBe('lg')
        ->and($halt->getColor())->toBe('danger')
        ->and($halt->isDanger())->toBeTrue();
});

// ─── Defaults ───────────────────────────────────────────────────────────────

it('has correct defaults', function () {
    $halt = ActionHalt::make();

    expect($halt->getHeading())->toBeNull()
        ->and($halt->getDescription())->toBeNull()
        ->and($halt->getModalIcon())->toBeNull()
        ->and($halt->getModalSubmitLabel())->toBe('Confirm')
        ->and($halt->getModalCancelLabel())->toBe('Cancel')
        ->and($halt->getWidth())->toBe('md')
        ->and($halt->isDanger())->toBeFalse()
        ->and($halt->isInformative())->toBeFalse()
        ->and($halt->hasForm())->toBeFalse();
});

// ─���─ Informative ─���────────────────────────────��─────────────────────────────

it('can be set to informative (no submit button)', function () {
    $halt = ActionHalt::make()
        ->heading('Info')
        ->informative();

    expect($halt->isInformative())->toBeTrue()
        ->and($halt->getModalSubmitLabel())->toBeNull()
        ->and($halt->getModalCancelLabel())->toBe('Close');
});

it('noSubmit is alias for informative', function () {
    $halt = ActionHalt::make()->noSubmit();

    expect($halt->isInformative())->toBeTrue();
});

// ─── Form ──────────────��───────────────────────���────────────────────────────

it('can set form with component array', function () {
    $halt = ActionHalt::make()
        ->form([TextInput::make('reason')]);

    expect($halt->hasForm())->toBeTrue()
        ->and($halt->getFormInstance())->toBeInstanceOf(Form::class);
});

it('informative clears form', function () {
    $halt = ActionHalt::make()
        ->form([TextInput::make('reason')])
        ->informative();

    expect($halt->hasForm())->toBeFalse()
        ->and($halt->getFormInstance())->toBeNull();
});

it('can set validation rules', function () {
    $halt = ActionHalt::make()
        ->validation(
            ['reason' => 'required|min:10'],
            ['reason.required' => 'Důvod je povinný'],
            ['reason' => 'Důvod']
        );

    expect($halt->getModalFormValidation())->toBe(['reason' => 'required|min:10'])
        ->and($halt->getModalFormValidationMessages())->toBe(['reason.required' => 'Důvod je povinný'])
        ->and($halt->getModalFormValidationAttributes())->toBe(['reason' => 'Důvod']);
});

it('can fill form with initial data', function () {
    $halt = ActionHalt::make()->fillForm(['reason' => 'default']);

    expect($halt->getModalFormData())->toBe(['reason' => 'default']);
});

// ─── Context ───────────────────────────────────────��────────────────────────

it('can set source context', function () {
    $halt = ActionHalt::make()->source('before', 2);

    expect($halt->getSource())->toBe('before')
        ->and($halt->getHaltIndex())->toBe(2);
});

it('can set skipBeforeOnConfirm', function () {
    $halt = ActionHalt::make()->skipBeforeOnConfirm(false);

    expect($halt->shouldSkipBeforeOnConfirm())->toBeFalse();
});

it('skips before on confirm by default', function () {
    expect(ActionHalt::make()->shouldSkipBeforeOnConfirm())->toBeTrue();
});

it('can set redirect after confirm', function () {
    $halt = ActionHalt::make()->redirectAfterConfirm('/dashboard');

    expect($halt->getRedirectAfterConfirm())->toBe('/dashboard');
});

// ─── Presets ────────────���───────────────────────────────────────────────────

it('has confirmDelete preset', function () {
    $halt = ActionHalt::confirmDelete('Test Record');

    expect($halt->getHeading())->toBe('Delete record')
        ->and($halt->getDescription())->toBe('Are you sure you want to delete "Test Record"? This action is irreversible.')
        ->and($halt->isDanger())->toBeTrue()
        ->and($halt->getModalIcon())->toBe('trash');
});

it('has confirmDelete preset without name', function () {
    $halt = ActionHalt::confirmDelete();

    expect($halt->getDescription())->toBe('Are you sure you want to delete this record? This action is irreversible.');
});

it('has confirmDanger preset', function () {
    $halt = ActionHalt::confirmDanger('Opravdu?', 'Toto nelze vrátit.');

    expect($halt->getHeading())->toBe('Opravdu?')
        ->and($halt->getDescription())->toBe('Toto nelze vrátit.')
        ->and($halt->isDanger())->toBeTrue();
});

it('has confirmWarning preset', function () {
    $halt = ActionHalt::confirmWarning('Pozor', 'Budete přesměrováni.');

    expect($halt->getHeading())->toBe('Pozor')
        ->and($halt->getModalIcon())->toBe('warning');
});

it('has info preset', function () {
    $halt = ActionHalt::info('Hotovo', 'Operace proběhla.');

    expect($halt->isInformative())->toBeTrue()
        ->and($halt->getHeading())->toBe('Hotovo')
        ->and($halt->getModalIcon())->toBe('info');
});

it('has success preset', function () {
    $halt = ActionHalt::success('Úspěch', 'Vše OK.');

    expect($halt->isInformative())->toBeTrue()
        ->and($halt->getModalIcon())->toBe('check-circle');
});

// ─── Serialization ─────────────���────────────────────────────────────────────

it('can serialize to array', function () {
    $halt = ActionHalt::make()
        ->heading('Test')
        ->description('Description')
        ->danger()
        ->source('action', 0);

    $array = $halt->toArray();

    expect($array['halt'])->toBeTrue()
        ->and($array['modal']['heading'])->toBe('Test')
        ->and($array['modal']['description'])->toBe('Description')
        ->and($array['modal']['danger'])->toBeTrue()
        ->and($array['context']['source'])->toBe('action');
});
