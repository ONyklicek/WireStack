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

// ─── Mobile-sheet variant (delegated to the canonical ModalStyle owner) ──────

it('is a centered dialog by default (no mobile variant)', function () {
    $s = new ConfirmationStyle;

    expect($s->mobileVariant())->toBeNull()
        // The default panel keeps its historical shrink-to-content width — the
        // stretch is gated at the breakpoint (sm:w-full), never edge-to-edge.
        ->and($s->panelWidthClass())->toBe('sm:w-full sm:max-w-md')
        // No sheet rounding: default panel variant classes carry the plain radius.
        ->and($s->panelVariantClasses())->toContain('rounded-2xl')
        ->and($s->panelVariantClasses())->not->toContain('rounded-t-2xl');
});

it('becomes a bottom-sheet below the breakpoint when slideOverOnMobile is set', function () {
    $s = new ConfirmationStyle(slideOverOnMobile: true);

    expect($s->mobileVariant())->toBe('bottom-sheet')
        // Edge-to-edge below the breakpoint, capped above it.
        ->and($s->panelWidthClass())->toContain('w-full')
        ->and($s->widthClass())->toBe('sm:max-w-md')
        // Sheet rounding + flex column, dialog rounding restored from sm up.
        ->and($s->panelVariantClasses())->toContain('rounded-t-2xl')
        ->and($s->panelVariantClasses())->toContain('sm:rounded-2xl')
        // Slides up from the bottom instead of the fade + scale dialog.
        ->and($s->transitionClasses()['enterStart'])->toContain('translate-y-full')
        // Container pins the sheet to the bottom edge.
        ->and($s->containerClasses())->toContain('items-end');
});

it('becomes full-screen below the breakpoint when fullScreenOnMobile is set', function () {
    $s = new ConfirmationStyle(fullScreenOnMobile: true);

    expect($s->mobileVariant())->toBe('full-screen')
        ->and($s->panelVariantClasses())->toContain('rounded-none')
        ->and($s->containerClasses())->toContain('items-stretch');
});

it('honours a custom mobile breakpoint for the sheet switch', function () {
    $s = new ConfirmationStyle(slideOverOnMobile: true, breakpoint: 'lg');

    expect($s->widthClass())->toBe('lg:max-w-md')
        ->and($s->transitionClasses()['enterStart'])->toContain('lg:translate-y-0');
});
