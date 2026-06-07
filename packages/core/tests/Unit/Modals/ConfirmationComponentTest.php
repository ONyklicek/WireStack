<?php

declare(strict_types=1);

use NyonCode\WireCore\Modals\View\ConfirmationComponent;

it('delegates primary modal icon classes to the canonical modal icon palette', function () {
    $component = new ConfirmationComponent(iconColor: 'primary');

    expect($component->iconBgClass())->toBe(ConfirmationComponent::getModalIconBgClass('primary'))
        ->and($component->iconColorClass())->toBe(ConfirmationComponent::getModalIconTextClass('primary'));
});

it('delegates gray modal icon classes to the canonical modal icon palette', function () {
    $component = new ConfirmationComponent(iconColor: 'gray');

    expect($component->iconBgClass())->toBe(ConfirmationComponent::getModalIconBgClass('gray'))
        ->and($component->iconColorClass())->toBe(ConfirmationComponent::getModalIconTextClass('gray'));
});

it('uses the neutral gray modal icon palette by default when no icon color is provided', function () {
    $component = new ConfirmationComponent(iconColor: 'unknown-color');

    expect($component->iconBgClass())->toBe(ConfirmationComponent::getModalIconBgClass('unknown-color'))
        ->and($component->iconBgClass())->toBe('bg-gray-100 dark:bg-gray-700')
        ->and($component->iconColorClass())->toBe(ConfirmationComponent::getModalIconTextClass('unknown-color'))
        ->and($component->iconColorClass())->toBe('text-gray-600 dark:text-gray-400');
});
