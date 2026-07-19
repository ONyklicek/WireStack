<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Concerns\HasColor;
use NyonCode\WireCore\Modals\View\ConfirmationComponent;

// The presentation logic now lives in the extracted ConfirmationStyle (Rule 5
// Phase 0); the component exposes it via style(). These assert the delegation to
// the canonical modal icon palette is preserved verbatim.

it('delegates primary modal icon classes to the canonical modal icon palette', function () {
    $style = (new ConfirmationComponent(iconColor: 'primary'))->style();

    expect($style->iconBgClass())->toBe(HasColor::getModalIconBgClass('primary'))
        ->and($style->iconColorClass())->toBe(HasColor::getModalIconTextClass('primary'));
});

it('delegates gray modal icon classes to the canonical modal icon palette', function () {
    $style = (new ConfirmationComponent(iconColor: 'gray'))->style();

    expect($style->iconBgClass())->toBe(HasColor::getModalIconBgClass('gray'))
        ->and($style->iconColorClass())->toBe(HasColor::getModalIconTextClass('gray'));
});

it('uses the neutral gray modal icon palette by default when no icon color is provided', function () {
    $style = (new ConfirmationComponent(iconColor: 'unknown-color'))->style();

    expect($style->iconBgClass())->toBe(HasColor::getModalIconBgClass('unknown-color'))
        ->and($style->iconBgClass())->toBe('bg-gray-100 dark:bg-gray-700')
        ->and($style->iconColorClass())->toBe(HasColor::getModalIconTextClass('unknown-color'))
        ->and($style->iconColorClass())->toBe('text-gray-600 dark:text-gray-400');
});
