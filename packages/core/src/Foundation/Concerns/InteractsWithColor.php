<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use NyonCode\WireCore\Foundation\Colors\Color;

/**
 * Colour STATE for a UI surface — the `$color` slot, its enum-normalising fluent
 * setter, and a nullable getter.
 *
 * The state counterpart to {@see HasColor}, which owns only the class-map
 * resolvers (`getBadgeColorClasses`, `getTextColorClasses`, …). Surfaces that
 * need a non-null fallback override {@see getColor()} — a covariant `: string`
 * return is allowed — while the setter and the slot stay canonical.
 *
 * Not for surfaces whose colour is closure-evaluated (those compose the
 * Actions `HasDynamicProperties` / infolist Entry variants instead).
 */
trait InteractsWithColor
{
    protected ?string $color = null;

    /** Set the colour (a hue string or a {@see Color} enum). */
    public function color(string|Color|null $color): static
    {
        $this->color = $color instanceof Color ? $color->value : $color;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }
}
