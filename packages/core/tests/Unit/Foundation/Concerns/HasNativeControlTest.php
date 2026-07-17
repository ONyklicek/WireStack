<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Concerns\HasNativeControl;

// A surface that takes the trait's default (the shared combobox).
function makeComboboxSurface(): object
{
    return new class
    {
        use HasNativeControl;
    };
}

// A surface whose own default is the browser-native control — it overrides
// defaultNative() rather than redeclaring $native, which PHP would reject.
function makeNativeDefaultSurface(): object
{
    return new class
    {
        use HasNativeControl;

        protected function defaultNative(): bool
        {
            return true;
        }
    };
}

it('defaults to the shared combobox', function () {
    expect(makeComboboxSurface()->isNative())->toBeFalse();
});

it('opts into the native control via native()', function () {
    expect(makeComboboxSurface()->native()->isNative())->toBeTrue();
});

it('accepts an explicit false', function () {
    expect(makeComboboxSurface()->native(false)->isNative())->toBeFalse();
});

it('is fluent', function () {
    $surface = makeComboboxSurface();

    expect($surface->native())->toBe($surface);
});

it('honours a surface that overrides the default', function () {
    expect(makeNativeDefaultSurface()->isNative())->toBeTrue();
});

it('lets an explicit native(false) win over a native default', function () {
    expect(makeNativeDefaultSurface()->native(false)->isNative())->toBeFalse();
});

// The second extension point: a surface that must force the native control in
// some mode aliases the trait's isNative() so the explicit ->native() choice
// still counts (this is how DateTimePicker's month mode works).
it('supports a surface that forces native while still honouring native()', function () {
    $surface = new class
    {
        use HasNativeControl {
            HasNativeControl::isNative as protected nativeChoice;
        }

        public bool $forced = false;

        public function isNative(): bool
        {
            return $this->nativeChoice() || $this->forced;
        }
    };

    expect($surface->isNative())->toBeFalse();

    $surface->forced = true;
    expect($surface->isNative())->toBeTrue()
        ->and($surface->native(false)->isNative())->toBeTrue();

    $surface->forced = false;
    expect($surface->isNative())->toBeFalse()
        ->and($surface->native()->isNative())->toBeTrue();
});
