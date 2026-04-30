<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\ModalFooterAction;

it('can be created via make()', function () {
    $action = ModalFooterAction::make('save');

    expect($action->getName())->toBe('save');
});

it('can set label', function () {
    $action = ModalFooterAction::make('save')->label('Uložit');

    expect($action->getLabel())->toBe('Uložit');
});

it('can set icon', function () {
    $action = ModalFooterAction::make('save')->icon('check');

    expect($action->getIcon())->toBe('check');
});

it('can set color', function () {
    $action = ModalFooterAction::make('cancel')->color('danger');

    expect($action->getColor())->toBe('danger');
});

it('has default gray color', function () {
    expect(ModalFooterAction::make('test')->getColor())->toBe('gray');
});

it('can be outlined', function () {
    $action = ModalFooterAction::make('cancel')->outlined();

    expect($action->isOutlined())->toBeTrue();
});

it('can set action callback', function () {
    $callback = fn () => null;
    $action = ModalFooterAction::make('custom')->action($callback);

    expect($action->getActionCallback())->toBe($callback);
});

it('can set position', function () {
    $action = ModalFooterAction::make('help')->position('after');

    expect($action->getPosition())->toBe('after');
});

it('has before position by default', function () {
    expect(ModalFooterAction::make('test')->getPosition())->toBe('before');
});

it('can close modal', function () {
    $action = ModalFooterAction::make('cancel')->closesModal();

    expect($action->shouldCloseModal())->toBeTrue();
});

it('can submit form', function () {
    $action = ModalFooterAction::make('save')->submitsForm();

    expect($action->shouldSubmitForm())->toBeTrue();
});

it('serializes to array', function () {
    $action = ModalFooterAction::make('save')
        ->label('Uložit')
        ->icon('check')
        ->color('primary');

    $array = $action->toArray();

    expect($array)->toBeArray()
        ->and($array['name'])->toBe('save')
        ->and($array['label'])->toBe('Uložit')
        ->and($array['icon'])->toBe('check')
        ->and($array['color'])->toBe('primary');
});
