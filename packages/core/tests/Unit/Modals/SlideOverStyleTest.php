<?php

declare(strict_types=1);

use NyonCode\WireCore\Modals\Support\SlideOverStyle;

/**
 * SlideOverStyle — presentation/layout resolver extracted from SlideOverComponent
 * (Rule 5 framework-wide, Phase 0). Reproduces the component's classes verbatim
 * so the slide-over shell renders without the Blade component.
 */
it('resolves a plain right-edge slide-over', function () {
    $s = new SlideOverStyle(position: 'right');

    expect($s->positionClasses())->toBe('inset-y-0 right-0 pl-10')
        ->and($s->widthWrapperClasses())->toBe('w-screen')
        ->and($s->panelClasses())->toBe('h-full')
        ->and($s->translateEnterStart())->toBe('translate-x-full')
        ->and($s->translateEnterEnd())->toBe('translate-x-0')
        ->and($s->translateLeaveStart())->toBe('translate-x-0')
        ->and($s->translateLeaveEnd())->toBe('translate-x-full');
});

it('resolves a plain left-edge slide-over', function () {
    $s = new SlideOverStyle(position: 'left');

    expect($s->positionClasses())->toBe('inset-y-0 left-0 pr-10')
        ->and($s->translateEnterStart())->toBe('-translate-x-full');
});

it('resolves the bottom-sheet-on-mobile variant (default sm breakpoint)', function () {
    $s = new SlideOverStyle(bottomSheetOnMobile: true);

    expect($s->positionClasses())->toContain('inset-x-0 bottom-0')->toContain('sm:right-0')
        ->and($s->widthWrapperClasses())->toBe('w-full sm:w-screen')
        ->and($s->panelClasses())->toContain('max-h-[85vh]')->toContain('sm:h-full')
        ->and($s->translateEnterStart())->toContain('translate-y-full')->toContain('sm:translate-x-full')
        ->and($s->translateEnterEnd())->toBe('translate-y-0 sm:translate-x-0');
});

it('honours the md and lg breakpoints for the bottom-sheet variant', function () {
    $md = new SlideOverStyle(bottomSheetOnMobile: true, breakpoint: 'md');
    $lg = new SlideOverStyle(bottomSheetOnMobile: true, breakpoint: 'lg');

    expect($md->positionClasses())->toContain('md:right-0')->not->toContain('sm:right-0')
        ->and($md->widthWrapperClasses())->toBe('w-full md:w-screen')
        ->and($md->panelClasses())->toContain('md:h-full')
        ->and($lg->positionClasses())->toContain('lg:right-0')
        ->and($lg->widthWrapperClasses())->toBe('w-full lg:w-screen')
        ->and($lg->panelClasses())->toContain('lg:h-full')
        ->and($lg->translateEnterEnd())->toBe('translate-y-0 lg:translate-x-0');
});

it('resolves the LEFT-edge bottom-sheet variant per breakpoint', function () {
    $sm = new SlideOverStyle(position: 'left', bottomSheetOnMobile: true);
    $md = new SlideOverStyle(position: 'left', bottomSheetOnMobile: true, breakpoint: 'md');
    $lg = new SlideOverStyle(position: 'left', bottomSheetOnMobile: true, breakpoint: 'lg');

    expect($sm->translateEnterStart())->toContain('sm:-translate-x-full')
        ->and($sm->positionClasses())->toContain('sm:left-0')->toContain('sm:pr-10')
        ->and($md->translateEnterStart())->toContain('md:-translate-x-full')
        ->and($md->translateEnterEnd())->toBe('translate-y-0 md:translate-x-0')
        ->and($md->positionClasses())->toContain('md:left-0')->toContain('md:pr-10')
        ->and($lg->translateEnterStart())->toContain('lg:-translate-x-full')
        ->and($lg->positionClasses())->toContain('lg:left-0');
});

it('resolves the width class responsively only for the bottom-sheet variant', function () {
    // Plain slide-over keeps a fixed width at every breakpoint (non-responsive);
    // the bottom-sheet is full-width on mobile and caps above the breakpoint.
    expect((new SlideOverStyle(width: 'md'))->widthClass())->toBe('max-w-md')
        ->and((new SlideOverStyle(width: 'md', bottomSheetOnMobile: true))->widthClass())->toBe('sm:max-w-md');
});
