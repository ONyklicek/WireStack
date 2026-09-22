<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use NyonCode\WireCore\Foundation\Enums\NativeControlMode;

/**
 * Canonical owner of the "browser's built-in control, or ours?" choice for every
 * surface that has both — a select (native <select> vs the shared combobox) and
 * a date/time field (native <input type="date"> vs the Alpine picker) alike.
 *
 * Ours is the default, so a control looks the same whether it sits in a form, a
 * table filter panel, or a column header. `->native()` opts a single surface back
 * out to the browser's element everywhere; `->nativeOnMobile()` does so only
 * below the surface's mobile breakpoint, where a phone's own wheel or list beats
 * any panel drawn into a 360px viewport. The global default for the second is
 * `wire-core.mobile.native`.
 *
 * A third answer is ours again, but built for a thumb: `->touchOnMobile()`
 * (global `wire-core.mobile.touch`) gives a picker a wheel and a select a
 * bottom-sheet list below the breakpoint, keeping every feature the field has.
 * One precedence for every surface: `native()` > `touchOnMobile()` >
 * `nativeOnMobile()`.
 *
 * Views read the resolved answer through {@see getNativeControlMode()} and
 * {@see usesTouchOnMobile()}.
 *
 * Four extension points:
 *  - a surface whose *default* differs overrides {@see defaultNative()} rather
 *    than redeclaring `$native` — PHP rejects a trait property redeclared with a
 *    different initial value;
 *  - a surface that must *force* native in some mode overrides isNative(),
 *    aliasing this trait's copy so the explicit ->native() choice still counts
 *    (see DateTimePicker's month mode);
 *  - a surface whose custom control carries something the browser's element
 *    cannot (a remote search, a "create option" button) overrides
 *    {@see supportsNativeOnMobile()} so a phone keeps the feature;
 *  - a surface with no touch control in some configuration overrides
 *    {@see supportsTouchOnMobile()} (DateTimePicker's month mode).
 */
trait HasNativeControl
{
    /** null = follow {@see defaultNative()}; an explicit native() call pins it. */
    protected ?bool $native = null;

    /** null = follow {@see defaultNativeOnMobile()}; nativeOnMobile() pins it. */
    protected ?bool $nativeOnMobile = null;

    /** null = follow {@see defaultTouchOnMobile()}; touchOnMobile() pins it. */
    protected ?bool $touchOnMobile = null;

    /** Use the browser's native control instead of the custom combobox/picker. */
    public function native(bool $native = true): static
    {
        $this->native = $native;

        return $this;
    }

    /** Use the browser's native control below the mobile breakpoint only, the custom one above it. */
    public function nativeOnMobile(bool $condition = true): static
    {
        $this->nativeOnMobile = $condition;

        return $this;
    }

    /** Use the touch-built control below the mobile breakpoint: a wheel for dates and times, a full-height list for selects. */
    public function touchOnMobile(bool $condition = true): static
    {
        $this->touchOnMobile = $condition;

        return $this;
    }

    public function isNative(): bool
    {
        return $this->native ?? $this->defaultNative();
    }

    /**
     * Whether the browser's control takes over below the breakpoint.
     *
     * False whenever the control is native everywhere already, and whenever the
     * surface says the custom control carries something a phone would lose. An
     * explicit `->native(false)` also opts out of the global config default —
     * the per-instance choice outranks the app-wide one — while an explicit
     * `->nativeOnMobile()` still wins over it.
     */
    public function isNativeOnMobile(): bool
    {
        if ($this->isNative() || ! $this->supportsNativeOnMobile()) {
            return false;
        }

        // A touch-built control owns the phone when it is on — it keeps the
        // features the browser's element drops.
        if ($this->usesTouchOnMobile()) {
            return false;
        }

        return $this->nativeOnMobile ?? ($this->native === null && $this->defaultNativeOnMobile());
    }

    /**
     * Whether a phone gets the touch-built control. Never over native(), which
     * means the browser's element on every screen, the phone included.
     */
    public function usesTouchOnMobile(): bool
    {
        if ($this->isNative() || ! $this->supportsTouchOnMobile()) {
            return false;
        }

        return $this->touchOnMobile ?? $this->defaultTouchOnMobile();
    }

    /** The one answer a view branches on. */
    public function getNativeControlMode(): NativeControlMode
    {
        return match (true) {
            $this->isNative() => NativeControlMode::Always,
            $this->isNativeOnMobile() => NativeControlMode::Mobile,
            default => NativeControlMode::Never,
        };
    }

    /**
     * The surface's default when native() was never called.
     */
    protected function defaultNative(): bool
    {
        return false;
    }

    /**
     * The surface's default when nativeOnMobile() was never called — the global
     * `wire-core.mobile.native` flag, next to the sheet flag it replaces on a phone.
     */
    protected function defaultNativeOnMobile(): bool
    {
        return (bool) config('wire-core.mobile.native', false);
    }

    /**
     * Whether the browser's element can stand in for the custom control on a
     * phone without losing a feature the owner configured.
     */
    protected function supportsNativeOnMobile(): bool
    {
        return true;
    }

    /** The global `wire-core.mobile.touch` flag, next to the native and sheet flags. */
    protected function defaultTouchOnMobile(): bool
    {
        return (bool) config('wire-core.mobile.touch', false);
    }

    /** Whether this surface, as configured, has a touch control at all. */
    protected function supportsTouchOnMobile(): bool
    {
        return true;
    }
}
