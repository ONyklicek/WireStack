<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use NyonCode\WireCore\Foundation\Colors\ButtonPalette;
use NyonCode\WireCore\Foundation\Colors\ChartPalette;
use NyonCode\WireCore\Foundation\Colors\NoticePalette;
use NyonCode\WireCore\Foundation\Colors\SemanticPalette;
use NyonCode\WireCore\Foundation\Colors\TextPalette;
use NyonCode\WireCore\Foundation\Colors\TintPalette;

/**
 * The door to the colour palettes — every surface's resolver, under the names
 * every consumer already calls.
 *
 * This trait used to hold the rules as well as the names: 1 174 lines and
 * twenty-four `match` statements for six unrelated surfaces, which is the shape
 * `plans/god-object-decomposition.md` exists to catch. The rules now live in
 * five classes, one per surface, and this delegates:
 *
 *   {@see ButtonPalette}  something you click — solid, outlined, ghost, icon, quiet, modal submit
 *   {@see TintPalette}    a soft block carrying a state — badge, choice card, row tint, diff cell
 *   {@see TextPalette}    text that is only text — a value, a label, a link
 *   {@see NoticePalette}  a surface telling you something — alert banner, modal icon
 *   {@see ChartPalette}   a shape that *is* the value — bar, fill, live dot, progress
 *
 * Nothing moved for a caller. Every method below keeps its name, its signature
 * and its result; what changed is that a rule about one surface is now found and
 * changed in a file about that surface.
 *
 * **This stays the one way to ask.** The palettes are where the rules live, not
 * a second vocabulary to call: two names for one answer is what this repository
 * spends its architecture rules avoiding, and a split that introduced one would
 * have cost more than the file length it saved. Call the palette directly only
 * from inside another palette.
 *
 * What holds across all five:
 *
 * - **The complete Tailwind palette, everywhere.** The semantic roles
 *   (`primary`, `success`, `danger`, `warning`, `info`, `gray`/`secondary`) plus
 *   every raw hue family and the achromatic `white`/`black`. So
 *   `->color('fuchsia')` renders on a solid button, an outlined button, a link,
 *   a badge and a choice card alike.
 * - **A role is not a hue.** `success` renders as whatever `wire-core.colors`
 *   points it at, resolved once by {@see SemanticPalette}
 *   at the top of every resolver. The literal hues stay first-class: `green` is
 *   literal green, distinct from a re-pointable `success`.
 * - **Class strings stay literal**, never interpolated from an owner-supplied
 *   colour, so Tailwind's scanner sees every hue and the arms double as an
 *   allow-list against class injection.
 * - `white`/`black` have no numeric scale, so they resolve adaptively — `black`
 *   is dark ink in light mode and flips to white in dark, `white` the inverse.
 */
trait HasColor
{
    /** @var array<string, string> */
    protected static array $colorClassCache = [];

    /**
     * Get outlined button color classes.
     */
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

    /**
     * Get a solid background-fill class only (no text/hover/focus).
     *
     * Canonical source for surfaces that need just a filled color block — e.g.
     * the "on" track of a toggle switch or a solid count badge. Same hue
     * vocabulary as the rest of the palette (success → emerald, blue → primary,
     * info → cyan), so these surfaces never drift back to raw Tailwind hues.
     */
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

    /** @see ButtonPalette::outlined() */
    public static function getOutlinedClasses(string $color): string
    {
        return ButtonPalette::outlined($color);
    }

    /** @see ButtonPalette::modalSubmit() */
    public static function getModalSubmitButtonClasses(string $color): string
    {
        return ButtonPalette::modalSubmit($color);
    }

    /** @see TextPalette::text() */
    public static function getTextColorClasses(string $color): string
    {
        return TextPalette::text($color);
    }

    /** @see TextPalette::link() */
    public static function getLinkColorClasses(string $color): string
    {
        return TextPalette::link($color);
    }

    /** @see NoticePalette::alert() */
    public static function getAlertColorClasses(string $color): string
    {
        return NoticePalette::alert($color);
    }

    /** @see NoticePalette::iconBg() */
    public static function getModalIconBgClass(string $color): string
    {
        return NoticePalette::iconBg($color);
    }

    /** @see NoticePalette::iconText() */
    public static function getModalIconTextClass(string $color): string
    {
        return NoticePalette::iconText($color);
    }

    /**
     * Get tint classes for a whole painted table row (subtle fill + matching hover).
     *
     * Canonical owner for the "colored data row" surface (`Table::rowColor()`).
     * A full-width row must read far softer than a badge or the toggle-track fill,
     * so this uses the lightest tint (`-50` in light, translucent `-900/20` in dark)
     * and pairs it with a same-hue hover so a hoverable colored row does not flip to
     * the neutral gray hover. The row tint deliberately replaces the default gray
     * hover and zebra striping for that row. Same hue vocabulary as the rest of the
     * palette (success → emerald, blue/primary → primary, info → cyan); unknown
     * names fall back to gray. Class strings are literal so Tailwind's JIT scanner
     * can see them, which also keeps the mapping a safe allow-list.
     */
    /**
     * The resting half of a soft tint — the fill, with no hover on it.
     *
     * Extracted from {@see self::getRowTintClasses()} rather than written beside
     * it: a diff cell and a "before/after" pill want the same wash a clickable
     * row rests at, and want nothing at all to happen on hover. The row resolver
     * now builds on this, so the two cannot drift.
     */
    /** @see ChartPalette::solidBg() */
    public static function getSolidBgClass(string $color): string
    {
        return ChartPalette::solidBg($color);
    }

    /** @see ChartPalette::softBg() */
    public static function getSoftBgClass(string $color): string
    {
        return ChartPalette::softBg($color);
    }

    /** @see ChartPalette::accentBg() */
    public static function getAccentBgClass(string $color): string
    {
        return ChartPalette::accentBg($color);
    }

    /** @see ChartPalette::gradientFill() */
    public static function getGradientFillClasses(string $color): string
    {
        return ChartPalette::gradientFill($color);
    }

    /** @see ChartPalette::fillText() */
    public static function getFillTextClasses(string $color): string
    {
        return ChartPalette::fillText($color);
    }

    /**
     * Get solid color classes for a modal submit button.
     *
     * Canonical source for the primary confirm/submit button at the bottom of an
     * action modal (both the slide-over and centered-dialog layouts), so the two
     * footers stay in sync instead of each re-encoding the hue map. Pairs with a
     * `text-white` base. Semantic names map to fixed hues; an unset/unknown color
     * falls back to the brand primary. Class strings are kept literal so
     * Tailwind's JIT scanner can see them (safe allow-list).
     */
    /** @see TintPalette::badge() */
    public static function getBadgeColorClasses(string $color): string
    {
        return TintPalette::badge($color);
    }

    /** @see TintPalette::choice() */
    public static function getChoiceColorClasses(string $color): array
    {
        return TintPalette::choice($color);
    }

    /** @see TintPalette::softTint() */
    public static function getSoftTintClasses(string $color): string
    {
        return TintPalette::softTint($color);
    }

    /** @see TintPalette::rowTint() */
    public static function getRowTintClasses(string $color): string
    {
        return TintPalette::rowTint($color);
    }

    /** @see TintPalette::rowHover() */
    public static function getRowHoverClasses(string $color): string
    {
        return TintPalette::rowHover($color);
    }
}
