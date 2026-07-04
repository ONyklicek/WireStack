<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Support\MobileSheet;

afterEach(fn () => config(['wire-core.mobile.breakpoint' => 'sm']));

it('defaults to the sm breakpoint', function () {
    config(['wire-core.mobile.breakpoint' => null]);

    expect(MobileSheet::breakpoint())->toBe('sm')
        ->and(MobileSheet::px())->toBe(639.98)
        ->and(MobileSheet::panel())->toContain('max-sm:fixed')
        ->and(MobileSheet::backdropHide())->toBe('sm:hidden')
        ->and(MobileSheet::grabberShow())->toBe('hidden max-sm:flex');
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
    expect(MobileSheet::px())->toBe(1023.98)->and(MobileSheet::panel())->toContain('max-lg:fixed');

    config(['wire-core.mobile.breakpoint' => 'nonsense']);
    expect(MobileSheet::breakpoint())->toBe('sm')->and(MobileSheet::px())->toBe(639.98);
});
