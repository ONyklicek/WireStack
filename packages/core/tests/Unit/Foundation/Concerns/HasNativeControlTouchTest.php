<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Concerns\HasNativeControl;
use NyonCode\WireCore\Foundation\Enums\NativeControlMode;

function makeTouchSurface(): object
{
    return new class
    {
        use HasNativeControl;
    };
}

it('is off by default', function () {
    expect(makeTouchSurface()->usesTouchOnMobile())->toBeFalse();
});

it('turns on per instance and off again', function () {
    expect(makeTouchSurface()->touchOnMobile()->usesTouchOnMobile())->toBeTrue()
        ->and(makeTouchSurface()->touchOnMobile()->touchOnMobile(false)->usesTouchOnMobile())->toBeFalse();
});

it('follows the global wire-core.mobile.touch default, which an instance can refuse', function () {
    config(['wire-core.mobile.touch' => true]);

    expect(makeTouchSurface()->usesTouchOnMobile())->toBeTrue()
        ->and(makeTouchSurface()->touchOnMobile(false)->usesTouchOnMobile())->toBeFalse();
});

// One precedence for every surface: native() > touchOnMobile() > nativeOnMobile().
it('gives way to native() and outranks nativeOnMobile()', function () {
    expect(makeTouchSurface()->touchOnMobile()->native()->usesTouchOnMobile())->toBeFalse();

    $surface = makeTouchSurface()->nativeOnMobile()->touchOnMobile();

    expect($surface->usesTouchOnMobile())->toBeTrue()
        ->and($surface->isNativeOnMobile())->toBeFalse()
        ->and($surface->getNativeControlMode())->toBe(NativeControlMode::Never);
});

it('outranks the global native default as well', function () {
    config(['wire-core.mobile.native' => true, 'wire-core.mobile.touch' => true]);

    expect(makeTouchSurface()->isNativeOnMobile())->toBeFalse()
        ->and(makeTouchSurface()->usesTouchOnMobile())->toBeTrue();
});

it('lets a surface without a touch control decline', function () {
    $surface = new class
    {
        use HasNativeControl;

        protected function supportsTouchOnMobile(): bool
        {
            return false;
        }
    };

    expect($surface->touchOnMobile()->usesTouchOnMobile())->toBeFalse();
});
