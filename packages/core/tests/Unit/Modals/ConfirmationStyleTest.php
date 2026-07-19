<?php

declare(strict_types=1);

use NyonCode\WireCore\Modals\Support\ConfirmationStyle;

/**
 * ConfirmationStyle — presentation resolver extracted from ConfirmationComponent
 * (Rule 5 framework-wide, Phase 0). Reproduces the component's classes verbatim
 * so the confirmation shell renders without the Blade component.
 */
it('resolves the width class from the modal width map', function () {
    expect((new ConfirmationStyle(width: 'md'))->widthClass())->toBe('sm:max-w-md')
        ->and((new ConfirmationStyle(width: 'lg'))->widthClass())->toBe('sm:max-w-lg');
});

it('resolves icon-chip color classes from the icon color', function () {
    $s = new ConfirmationStyle(iconColor: 'danger');

    expect($s->iconBgClass())->not->toBe('')
        ->and($s->iconColorClass())->not->toBe('');
});

it('builds the submit button classes off the action color, defaulting to primary', function () {
    $primary = (new ConfirmationStyle)->submitButtonClasses();
    $danger = (new ConfirmationStyle(color: 'danger'))->submitButtonClasses();

    expect($primary)->toContain('inline-flex w-full justify-center')
        ->and($danger)->toContain('inline-flex w-full justify-center')
        ->and($danger)->not->toBe($primary);
});
