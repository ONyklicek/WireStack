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

it('can set form with Form instance', function () {
    $halt = ActionHalt::make()
        ->form(Form::make()->schema([
            TextInput::make('reason'),
        ]));

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
it('speaks the canonical modal vocabulary, and serializes what the view can honour', function () {
    // A halt IS a modal, so it composes Foundation's HasModalProperties rather
    // than keeping a private copy of heading/description/width — and with it come
    // three settings the halt modal could never express before.
    $halt = ActionHalt::make()
        ->heading('Archive it?')
        ->description('This cannot be undone.')
        ->width('lg')
        ->closeOnEscape(false)
        ->closeOnClickAway(false)
        ->id('archive-halt')
        ->danger();

    $modal = $halt->toArray()['modal'];

    expect($modal['heading'])->toBe('Archive it?')
        ->and($modal['description'])->toBe('This cannot be undone.')
        ->and($modal['width'])->toBe('lg')
        ->and($modal['closeOnEscape'])->toBeFalse()
        ->and($modal['closeOnClickAway'])->toBeFalse()
        ->and($modal['id'])->toBe('archive-halt')
        ->and($modal['danger'])->toBeTrue();
});

it('resolves a closure heading while the caller is still there', function () {
    // The trait keeps closures for a render that can pass a context. A halt has
    // no such moment: it is serialized into component state the instant it is
    // raised, so the closure is called now or never.
    $halt = ActionHalt::make()
        ->heading(fn (): string => 'Computed')
        ->description(fn (): string => 'Also computed');

    expect($halt->getHeading())->toBe('Computed')
        ->and($halt->getDescription())->toBe('Also computed')
        ->and($halt->toArray()['modal']['heading'])->toBe('Computed');
});

it('lets a form and informative() overrule each other, whichever came last', function () {
    // Before 2.0 this was order-dependent and silent: informative() cleared the
    // form, and a form() after it set the instance while hasForm() stayed false —
    // so the fields never rendered and the instance was still serialized for a
    // modal that had nowhere to put them.
    $formLast = ActionHalt::make()
        ->informative()
        ->form([TextInput::make('reason')]);

    expect($formLast->hasForm())->toBeTrue()
        ->and($formLast->isInformative())->toBeFalse()
        ->and($formLast->getModalSubmitLabel())->not->toBeNull();

    $informativeLast = ActionHalt::make()
        ->form([TextInput::make('reason')])
        ->validation(['reason' => 'required'])
        ->informative();

    expect($informativeLast->hasForm())->toBeFalse()
        ->and($informativeLast->getFormInstance())->toBeNull()
        ->and($informativeLast->getModalFormValidation())->toBeNull()
        ->and($informativeLast->getModalSubmitLabel())->toBeNull();
});

it('lets danger fill the colour slot, and lets an explicit colour keep it', function () {
    // Same rule as the confirmation surfaces — see ConfirmationDialogTest. The
    // icon colour is a separate slot and still derives from danger on its own.
    expect(ActionHalt::make()->danger()->getColor())->toBe('danger')
        ->and(ActionHalt::make()->color('primary')->danger()->getColor())->toBe('primary')
        ->and(ActionHalt::make()->color('primary')->danger()->getModalIconColor())->toBe('danger');
});
