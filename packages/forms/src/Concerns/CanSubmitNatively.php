<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Concerns;

use NyonCode\WireForms\Contracts\SupportsNativeSubmit;

/**
 * Implements {@see SupportsNativeSubmit} for a field whose value lives on one
 * element.
 *
 * Only the native branch lives here. The Livewire binding stays in each view,
 * which is not a symmetry oversight: a text input appends its debounce modifier
 * to `wire:model` and a checkbox does not, so one shared owner for that branch
 * would silently change what the checkbox renders. This trait adds a mode; it
 * does not rewrite the one that already works.
 *
 * The native `name` is the field's own name, never its state path: Fortify's
 * request expects `email`, not `data.email`, and the dotted path is a Livewire
 * addressing detail that has no meaning to a browser form.
 */
trait CanSubmitNatively
{
    protected bool $nativeSubmit = false;

    /** Render this field for a native submit rather than for Livewire. */
    public function nativeSubmit(bool $native = true): static
    {
        $this->nativeSubmit = $native;

        return $this;
    }

    /** Whether this field is currently rendering for a native submit. */
    public function submitsNatively(): bool
    {
        return $this->nativeSubmit;
    }

    /**
     * `name`, and the value the browser should show.
     *
     * `old()` first so a rejected submission comes back filled in — that is the
     * half a hand-written login form usually forgets, and the reason a user
     * retypes an address because the password was wrong.
     *
     * The fallback is the field's declared default, not "its current value":
     * a field does not hold one. State belongs to the form, and on a native
     * submit there is no form state to hold — the browser posted the last one
     * and `old()` is what came back of it.
     */
    public function getNativeBindingHtml(): string
    {
        $name = $this->getName();
        $value = old($name, $this->getDefault());

        $out = 'name="'.e($name).'"';

        // No value attribute when there is nothing to put in one — a field with
        // no default and no rejected submission behind it renders just its name.
        if (is_scalar($value)) {
            $out .= ' value="'.e((string) $value).'"';
        }

        return $out;
    }
}
