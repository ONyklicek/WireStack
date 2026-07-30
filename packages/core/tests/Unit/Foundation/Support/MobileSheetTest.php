<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Enums\Breakpoint;
use NyonCode\WireCore\Foundation\Support\MobileSheet;

afterEach(fn () => config(['wire-core.mobile.breakpoint' => 'sm']));

it('defaults to the sm breakpoint', function () {
    config(['wire-core.mobile.breakpoint' => null]);

    expect(MobileSheet::breakpoint())->toBe('sm')
        ->and(MobileSheet::px())->toBe(639.98)
        ->and(MobileSheet::panel())->toContain('max-sm:fixed')
        ->and(MobileSheet::backdropHide())->toBe('sm:hidden')
        ->and(MobileSheet::grabberShow())->toBe('hidden max-sm:flex')
        ->and(MobileSheet::scrollArea())->toBe('max-sm:w-full max-sm:max-h-[60vh]');
});

it('sizes an inner scroll region for a sheet at every breakpoint', function () {
    expect(MobileSheet::scrollArea())->toBe('max-sm:w-full max-sm:max-h-[60vh]')
        ->and(MobileSheet::scrollArea('md'))->toBe('max-md:w-full max-md:max-h-[60vh]')
        ->and(MobileSheet::scrollArea('lg'))->toBe('max-lg:w-full max-lg:max-h-[60vh]')
        ->and(MobileSheet::scrollArea('nonsense'))->toBe('max-sm:w-full max-sm:max-h-[60vh]');
});

it('keeps a cap on the inner scroll region rather than removing it', function () {
    // Not a style preference: max-h-none would hand scrolling to the panel, and a
    // list that reveals its current value by setting its own scrollTop would
    // silently stop doing so.
    expect(MobileSheet::scrollArea())->not->toContain('max-h-none');
});

it('switches every class to the configured breakpoint (md for tablets)', function () {
    config(['wire-core.mobile.breakpoint' => 'md']);

    expect(MobileSheet::px())->toBe(767.98)
        ->and(MobileSheet::panel())->toContain('max-md:fixed')->not->toContain('max-sm:')
        ->and(MobileSheet::panelPadded())->toContain('max-md:pb-[calc(1rem_+_env(safe-area-inset-bottom))]')
        ->and(MobileSheet::motion())->toBe('max-md:scale-100 max-md:translate-y-full')
        ->and(MobileSheet::backdropHide())->toBe('md:hidden')
        ->and(MobileSheet::grabberShow())->toBe('hidden max-md:flex');
});

it('supports lg and falls back to sm for unknown values', function () {
    config(['wire-core.mobile.breakpoint' => 'lg']);
    expect(MobileSheet::px())->toBe(1023.98)
        ->and(MobileSheet::panel())->toContain('max-lg:fixed')
        ->and(MobileSheet::panelPadded())->toContain('max-lg:pb-[calc(1rem_+_env(safe-area-inset-bottom))]')
        ->and(MobileSheet::motion())->toBe('max-lg:scale-100 max-lg:translate-y-full')
        ->and(MobileSheet::grabberShow())->toBe('hidden max-lg:flex')
        ->and(MobileSheet::backdropHide())->toBe('lg:hidden');

    config(['wire-core.mobile.breakpoint' => 'nonsense']);
    expect(MobileSheet::breakpoint())->toBe('sm')->and(MobileSheet::px())->toBe(639.98);
});

it('accepts a Breakpoint enum override, resolving non-sheet breakpoints to sm', function () {
    expect(MobileSheet::breakpoint(Breakpoint::Lg))->toBe('lg')
        ->and(MobileSheet::breakpoint(Breakpoint::Md))->toBe('md')
        // xl/2xl are not sheet-supported, so they fall back to sm like the string.
        ->and(MobileSheet::breakpoint(Breakpoint::Xl))->toBe('sm')
        ->and(MobileSheet::breakpoint(Breakpoint::Xl))->toBe(MobileSheet::breakpoint('xl'));
});
