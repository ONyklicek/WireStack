<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use NyonCode\WireCore\Foundation\Colors\ButtonPalette;

/**
 * The door to the colour palettes, for a component that *has* a colour.
 *
 * This trait used to hold the rules as well as the names: 1 174 lines and
 * twenty-four `match` statements for six unrelated surfaces, which is the shape
 * `plans/god-object-decomposition.md` exists to catch. The rules now live in
 * five palette classes, the names that reach them in
 * {@see ResolvesColorClasses}, and what stays here is the part that needs a
 * host: the five helpers that resolve `$this->getColor()` and hand it on.
 *
 * Nothing moved for a caller. Every static resolver is still reachable through
 * this trait — it uses the other one — so a class that had `use HasColor;`
 * keeps every method it had. A class that has **no** colour of its own uses
 * {@see ResolvesColorClasses} instead, and inherits nothing it cannot answer.
 *
 * Hosts provide `getColor()` themselves — a property and a setter
 * ({@see InteractsWithColor}), a fixed value, or a resolved one. It is not
 * declared abstract here because the two are separable and now separated: this
 * half is used only where the method exists.
 */
trait HasColor
{
    use ResolvesColorClasses;

    /** @var array<string, string> */
    protected static array $colorClassCache = [];

    /**
     * Outlined (bordered) colour classes, for a component that has a colour.
     *
     * The vocabulary itself is {@see self::getOutlinedClasses()}: a Blade file
     * cannot call this one — it is an instance method reading `$this` — and a
     * surface outside a component still needs the same border.
     */
    protected function getOutlinedColorClasses(?string $color = null): string
    {
        return self::getOutlinedClasses($color ?? $this->getColor());
    }

    /** @see ButtonPalette::solid() */
    protected function getSolidColorClasses(?string $color = null): string
    {
        return ButtonPalette::solid($color ?? $this->getColor());
    }

    /** @see ButtonPalette::ghost() */
    protected function getGhostColorClasses(?string $color = null): string
    {
        return ButtonPalette::ghost($color ?? $this->getColor());
    }

    /** @see ButtonPalette::iconButton() */
    protected function getIconButtonColorClasses(?string $color = null): string
    {
        return ButtonPalette::iconButton($color ?? $this->getColor());
    }

    /** @see ButtonPalette::quiet() */
    protected function getQuietButtonColorClasses(?string $color = null): string
    {
        return ButtonPalette::quiet($color ?? $this->getColor());
    }
}
