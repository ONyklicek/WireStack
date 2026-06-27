<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ModalStep;
use NyonCode\WireCore\Modals\ConfirmationDialog;
use NyonCode\WireCore\Modals\Modal;
use NyonCode\WireCore\Modals\SlideOver;
use NyonCode\WireCore\Modals\Wizard;
use NyonCode\WireForms\Components\TextInput;

it('configures an action from a Modal config object', function () {
    $action = Action::make('edit')->modal(
        Modal::make()
            ->heading('Edit record')
            ->description('Change the details')
            ->width('lg')
            ->closeOnClickAway(false)
            ->fullScreenOnMobile()
            ->mobileWidth('full')
            ->stickyFooter()
    );

    $config = $action->getModalConfig();

    expect($action->hasModal())->toBeTrue()
        ->and($config['heading'])->toBe('Edit record')
        ->and($config['description'])->toBe('Change the details')
        ->and($config['width'])->toBe('lg')
        ->and($config['closeOnClickAway'])->toBeFalse()
        ->and($config['fullScreenOnMobile'])->toBeTrue()
        ->and($config['mobileWidth'])->toBe('full')
        ->and($config['stickyFooter'])->toBeTrue();
});

it('configures a slide-over action from a SlideOver config object', function () {
    $action = Action::make('view')->modal(
        SlideOver::make()->heading('Details')
    );

    expect($action->isSlideOver())->toBeTrue()
        ->and($action->getModalConfig()['heading'])->toBe('Details');
});

it('maps SlideOver mobileOnly onto slide-over-on-mobile only', function () {
    $action = Action::make('view')->modal(
        SlideOver::make()->heading('Details')->mobileOnly()
    );

    expect($action->isSlideOverOnMobile())->toBeTrue()
        ->and($action->isSlideOver())->toBeFalse();
});

it('configures a confirmation action from a ConfirmationDialog config object', function () {
    $action = Action::make('delete')->modal(
        ConfirmationDialog::delete('User')
    );

    $config = $action->getModalConfig();

    expect($action->hasModal())->toBeTrue()
        ->and($action->doesRequireConfirmation())->toBeTrue()
        ->and($config['isConfirmation'])->toBeTrue()
        ->and($config['icon'])->not->toBeNull();
});

it('configures a wizard action from a Wizard config object', function () {
    $action = Action::make('create')->modal(
        Wizard::make()
            ->heading('Create user')
            ->steps([
                ModalStep::make('Account')->schema([TextInput::make('name')]),
                ModalStep::make('Contact')->schema([TextInput::make('email')]),
            ])
    );

    expect($action->hasMultipleSteps())->toBeTrue()
        ->and($action->getStepCount())->toBe(2)
        ->and($action->hasFormModal())->toBeTrue()
        ->and($action->doesRequireConfirmation())->toBeFalse()
        ->and($action->getModalConfig()['heading'])->toBe('Create user');
});

it('mirrors the modal accent color onto the action color', function () {
    $action = Action::make('save')->modal(
        Modal::make()->heading('Save')->color('success')
    );

    expect($action->getColor())->toBe('success');
});
