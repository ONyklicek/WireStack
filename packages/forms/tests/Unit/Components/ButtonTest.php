<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireForms\Components\Button;

test('button delegates presentation to its internal action', function () {
    $button = Button::make('verify')
        ->label('Verify now')
        ->icon('heroicon-o-check', 'after')
        ->color('success')
        ->size('lg')
        ->outlined();

    $action = $button->getButtonAction();

    expect($action)->toBeInstanceOf(Action::class)
        ->and($action->getLabel())->toBe('Verify now')
        ->and($action->getIcon())->toBe('heroicon-o-check')
        ->and($action->getIconPosition())->toBe('after')
        ->and($action->getColor())->toBe('success')
        ->and($action->getSize())->toBe('lg')
        ->and($action->isOutlined())->toBeTrue();
});

test('button resolves its own field action by name', function () {
    $button = Button::make('verify')->action(fn () => null);

    expect($button->getFieldAction('verify'))->toBe($button->getButtonAction())
        ->and($button->getFieldAction('missing'))->toBeNull();
});

test('button registers a callback on its action', function () {
    $button = Button::make('verify')->action(fn () => 'done');

    expect($button->getButtonAction()->getActionCallback())->toBeInstanceOf(Closure::class);
});

test('button renders the button view', function () {
    expect(Button::make('verify')->render()->name())->toBe('wire-forms::components.button');
});
