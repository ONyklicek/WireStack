<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Infolists\Components;

use NyonCode\WireCore\Foundation\Concerns\CanBeCopyable;
use NyonCode\WireCore\Foundation\Support\CssColor;

/**
 * Color entry — renders the state as a color swatch plus its value, with an
 * optional copy-to-clipboard affordance. The state is expected to be a CSS
 * color string (hex, rgb, hsl, …).
 */
class ColorEntry extends Entry
{
    use CanBeCopyable;

    /**
     * The state as a color safe to interpolate into a `style` attribute, or null
     * when it is not a CSS color the view may vouch for.
     *
     * Blade escaping is not sufficient inside `style`: `e()` leaves `;` intact,
     * so an unguarded value can append further declarations to the rule. The
     * view draws no swatch when this is null.
     */
    public function getSwatch(): ?string
    {
        return CssColor::sanitize($this->getFormattedState());
    }

    protected function viewName(): string
    {
        return 'wire-core::infolists.entries.color';
    }
}
