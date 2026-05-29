<?php

declare(strict_types=1);

use NyonCode\WireCore\Modals\ConfirmationDialog;

// ─── Factory ───────────────────────────────────────────────────────────

it('can be created with make', function () {
    expect(ConfirmationDialog::make())->toBeInstanceOf(ConfirmationDialog::class);
});

// ─── Basic Properties ──────────────────────────────────────────────────

it('can set heading and description', function () {
    $dialog = ConfirmationDialog::make()
        ->heading('Delete?')
        ->description('This cannot be undone.');

    expect($dialog->getHeading())->toBe('Delete?')
        ->and($dialog->getDescription())->toBe('This cannot be undone.');
});

// ─── Icon ──────────────────────────────────────────────────────────────

it('has warning as default icon color', function () {
    expect(ConfirmationDialog::make()->getIconColor())->toBe('warning');
});

it('can set icon with color', function () {
    $dialog = ConfirmationDialog::make()->icon('trash', 'danger');

    expect($dialog->getIcon())->toBe('trash')
        ->and($dialog->getIconColor())->toBe('danger');
});

// ─── Danger ────────────────────────────────────────────────────────────

it('is not danger by default', function () {
    expect(ConfirmationDialog::make()->isDanger())->toBeFalse();
});

it('can be set to danger', function () {
    $dialog = ConfirmationDialog::make()->danger();

    expect($dialog->isDanger())->toBeTrue()
        ->and($dialog->getColor())->toBe('danger');
});

// ─── Informative ───────────────────────────────────────────────────────

it('is not informative by default', function () {
    expect(ConfirmationDialog::make()->isInformative())->toBeFalse();
});

it('can be set to informative', function () {
    $dialog = ConfirmationDialog::make()->informative();

    expect($dialog->isInformative())->toBeTrue();
});

it('has no submit label when informative', function () {
    expect(ConfirmationDialog::make()->informative()->getSubmitLabel())->toBeNull();
});

it('uses close label when informative', function () {
    expect(ConfirmationDialog::make()->informative()->getCancelLabel())->toBe('Close');
});

// ─── Labels ────────────────────────────────────────────────────────────

it('has default submit label from translation', function () {
    expect(ConfirmationDialog::make()->getSubmitLabel())->toBe('Confirm');
});

it('has default cancel label from translation', function () {
    expect(ConfirmationDialog::make()->getCancelLabel())->toBe('Cancel');
});

it('can set custom labels', function () {
    $dialog = ConfirmationDialog::make()
        ->submitLabel('Delete')
        ->cancelLabel('Keep');

    expect($dialog->getSubmitLabel())->toBe('Delete')
        ->and($dialog->getCancelLabel())->toBe('Keep');
});

// ─── Presets ───────────────────────────────────────────────────────────

it('creates delete preset', function () {
    $dialog = ConfirmationDialog::delete('User');

    expect($dialog->getHeading())->toBe('Delete record')
        ->and($dialog->getDescription())->toBe('Are you sure you want to delete "User"? This action is irreversible.')
        ->and($dialog->getIcon())->toBe('trash')
        ->and($dialog->getIconColor())->toBe('danger')
        ->and($dialog->getSubmitLabel())->toBe('Delete')
        ->and($dialog->isDanger())->toBeTrue();
});

it('creates delete preset without record name', function () {
    $dialog = ConfirmationDialog::delete();

    expect($dialog->getDescription())->toBe('Are you sure you want to delete this record? This action is irreversible.');
});

it('creates danger preset', function () {
    $dialog = ConfirmationDialog::makeDanger('Careful!', 'This is dangerous.');

    expect($dialog->getHeading())->toBe('Careful!')
        ->and($dialog->getDescription())->toBe('This is dangerous.')
        ->and($dialog->getIcon())->toBe('warning')
        ->and($dialog->isDanger())->toBeTrue();
});

it('creates warning preset', function () {
    $dialog = ConfirmationDialog::makeWarning('Watch out', 'Proceed with care.');

    expect($dialog->getHeading())->toBe('Watch out')
        ->and($dialog->getDescription())->toBe('Proceed with care.')
        ->and($dialog->getIcon())->toBe('warning')
        ->and($dialog->getIconColor())->toBe('warning');
});

it('creates info preset', function () {
    $dialog = ConfirmationDialog::makeInfo('Note', 'Something to know.');

    expect($dialog->getHeading())->toBe('Note')
        ->and($dialog->getIcon())->toBe('info')
        ->and($dialog->getIconColor())->toBe('info')
        ->and($dialog->isInformative())->toBeTrue()
        ->and($dialog->getSubmitLabel())->toBeNull();
});

it('creates success preset', function () {
    $dialog = ConfirmationDialog::makeSuccess('Done', 'Operation complete.');

    expect($dialog->getHeading())->toBe('Done')
        ->and($dialog->getIcon())->toBe('check-circle')
        ->and($dialog->getIconColor())->toBe('success')
        ->and($dialog->isInformative())->toBeTrue();
});

// ─── Serialization ─────────────────────────────────────────────────────

it('serializes to array', function () {
    $dialog = ConfirmationDialog::make()
        ->heading('Confirm')
        ->description('Are you sure?')
        ->icon('warning', 'warning')
        ->submitLabel('Yes')
        ->cancelLabel('No')
        ->danger()
        ->id('confirm-dialog');

    $array = $dialog->toArray();

    expect($array['heading'])->toBe('Confirm')
        ->and($array['description'])->toBe('Are you sure?')
        ->and($array['icon'])->toBe('warning')
        ->and($array['iconColor'])->toBe('warning')
        ->and($array['submitLabel'])->toBe('Yes')
        ->and($array['cancelLabel'])->toBe('No')
        ->and($array['isDanger'])->toBeTrue()
        ->and($array['isInformative'])->toBeFalse()
        ->and($array['id'])->toBe('confirm-dialog');
});

it('serializes informative dialog correctly', function () {
    $array = ConfirmationDialog::makeInfo('Info', 'Details.')->toArray();

    expect($array['submitLabel'])->toBeNull()
        ->and($array['isInformative'])->toBeTrue()
        ->and($array['cancelLabel'])->toBe('Close');
});
