<?php

declare(strict_types=1);

use NyonCode\WireCore\Modals\Support\ModalStyle;

/**
 * ModalStyle — the presentation/layout resolver extracted from ModalComponent
 * (Rule 5 framework-wide, Phase 0). It must reproduce the component's classes
 * verbatim so the modal shell can be rendered without the Blade component.
 */
it('resolves the centered-dialog layout when the modal opts out of the mobile variants', function () {
    $s = new ModalStyle(width: 'md', fullScreenOnMobile: false, slideOverOnMobile: false);

    expect($s->mobileVariant())->toBeNull()
        ->and($s->hasGrabber())->toBeFalse()
        ->and($s->footerSafeAreaClass())->toBe('')
        ->and($s->containerClasses())->toContain('flex min-h-[100dvh] items-end')->toContain('sm:block sm:p-0')
        ->and($s->panelVariantClasses())->toContain('rounded-2xl')->toContain('sm:my-8')
        ->and($s->bodyVariantClasses())->toBe('')
        ->and($s->widthClass())->not->toBe('');

    $t = $s->transitionClasses();
    expect($t)->toHaveKeys(['enterStart', 'enterEnd', 'leaveStart', 'leaveEnd'])
        ->and($t['enterStart'])->toContain('opacity-0');
});

it('resolves the bottom-sheet variant (slideOverOnMobile)', function () {
    $s = new ModalStyle(slideOverOnMobile: true);

    expect($s->mobileVariant())->toBe('bottom-sheet')
        ->and($s->containerClasses())->toContain('items-end')
        ->and($s->panelVariantClasses())->toContain('rounded-t-2xl')
        ->and($s->bodyVariantClasses())->toContain('flex-1 overflow-y-auto')
        ->and($s->transitionClasses()['enterStart'])->toContain('translate-y-full');
});

it('resolves the full-screen variant', function () {
    $s = new ModalStyle(fullScreenOnMobile: true);

    expect($s->mobileVariant())->toBe('full-screen')
        ->and($s->panelVariantClasses())->toContain('rounded-none')
        ->and($s->containerClasses())->toContain('items-stretch');
});

it('lets bottom-sheet win over full-screen when both are set', function () {
    $s = new ModalStyle(fullScreenOnMobile: true, slideOverOnMobile: true);

    expect($s->mobileVariant())->toBe('bottom-sheet');
});

it('honours a per-modal breakpoint (lg)', function () {
    $s = new ModalStyle(slideOverOnMobile: true, breakpoint: 'lg');

    expect($s->containerClasses())->toContain('lg:block lg:p-0')
        ->and($s->panelVariantClasses())->toContain('lg:inline-block')
        ->and($s->transitionClasses()['enterStart'])->toContain('lg:')
        ->and($s->bodyVariantClasses())->toContain('lg:');
});

it('honours the md breakpoint', function () {
    $s = new ModalStyle(slideOverOnMobile: true, breakpoint: 'md');

    expect($s->containerClasses())->toContain('md:block md:p-0')
        ->and($s->transitionClasses()['enterStart'])->toContain('md:')
        ->and($s->bodyVariantClasses())->toContain('md:');
});

it('drops the overflow-visible reset when a maxHeight is set', function () {
    $withMax = new ModalStyle(slideOverOnMobile: true, maxHeight: '80vh');
    $without = new ModalStyle(slideOverOnMobile: true);

    expect($withMax->bodyVariantClasses())->not->toContain('overflow-visible')
        ->and($without->bodyVariantClasses())->toContain('overflow-visible');
});

it('resolves the icon-chip color classes via HasColor', function () {
    $s = new ModalStyle(iconColor: 'danger');

    expect($s->iconBgClass())->not->toBe('')
        ->and($s->iconColorClass())->not->toBe('');
});

it('resolves the plain centered-dialog transitions per breakpoint', function () {
    // No mobile variant → the plain fade+scale dialog transition, gated at the
    // configured breakpoint.
    expect((new ModalStyle(breakpoint: 'md'))->transitionClasses()['enterStart'])->toContain('md:scale-95')
        ->and((new ModalStyle(breakpoint: 'lg'))->transitionClasses()['leaveEnd'])->toContain('lg:scale-95');
});

it('follows wire-core.modals.mobile when the modal says nothing', function (string $configured, ?string $variant) {
    config(['wire-core.modals.mobile' => $configured]);

    expect((new ModalStyle)->mobileVariant())->toBe($variant);
})->with([
    'sheet (the default)' => ['sheet', 'bottom-sheet'],
    'full-screen' => ['full-screen', 'full-screen'],
    'dialog' => ['dialog', null],
    'anything else falls back to the sheet' => ['drawer', 'bottom-sheet'],
]);

it('lets either explicit flag outrank the config default', function () {
    config(['wire-core.modals.mobile' => 'sheet']);

    expect((new ModalStyle(fullScreenOnMobile: true))->mobileVariant())->toBe('full-screen')
        ->and((new ModalStyle(slideOverOnMobile: false))->mobileVariant())->toBeNull();
});

it('gives a bottom sheet a grabber, a safe-area footer and a dynamic-viewport cap', function () {
    $s = new ModalStyle(slideOverOnMobile: true, breakpoint: 'md');

    expect($s->hasGrabber())->toBeTrue()
        ->and($s->breakpoint())->toBe('md')
        ->and($s->footerSafeAreaClass())->toBe('max-md:pb-[calc(1rem_+_max(0.5rem,env(safe-area-inset-bottom)))]')
        ->and($s->panelVariantClasses())->toContain('max-h-[var(--wire-sheet-h,92dvh)]')->not->toContain('85vh');
});

it('keeps a full-screen panel clear of the status bar', function () {
    $s = new ModalStyle(fullScreenOnMobile: true);

    expect($s->panelVariantClasses())->toContain('max-sm:pt-[env(safe-area-inset-top)]')->toContain('min-h-[100dvh]')
        ->and($s->hasGrabber())->toBeFalse()
        ->and($s->footerSafeAreaClass())->toBe('max-sm:pb-[calc(1rem_+_max(0.5rem,env(safe-area-inset-bottom)))]');
});
