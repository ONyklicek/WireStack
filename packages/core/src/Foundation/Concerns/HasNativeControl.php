<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

/**
 * Canonical owner of the "browser's built-in control, or ours?" choice for every
 * surface that has both — a select (native <select> vs the shared combobox) and
 * a date/time field (native <input type="date"> vs the Alpine picker) alike.
 *
 * Ours is the default, so a control looks the same whether it sits in a form, a
 * table filter panel, or a column header. `->native()` opts a single surface back
 * out to the browser's element (cheaper to render, but it no longer matches the
 * rest of the stack).
 *
 * Two extension points:
 *  - a surface whose *default* differs overrides {@see defaultNative()} rather
 *    than redeclaring `$native` — PHP rejects a trait property redeclared with a
 *    different initial value;
 *  - a surface that must *force* native in some mode overrides isNative(),
 *    aliasing this trait's copy so the explicit ->native() choice still counts
 *    (see DateTimePicker's month mode).
 */
trait HasNativeControl
{
    /** null = follow {@see defaultNative()}; an explicit native() call pins it. */
    protected ?bool $native = null;

    /** Use the browser's native control instead of the custom combobox/picker. */
    public function native(bool $native = true): static
    {
        $this->native = $native;

        return $this;
    }

    public function isNative(): bool
    {
        return $this->native ?? $this->defaultNative();
    }

    /**
     * The surface's default when native() was never called.
     */
    protected function defaultNative(): bool
    {
        return false;
    }
}
