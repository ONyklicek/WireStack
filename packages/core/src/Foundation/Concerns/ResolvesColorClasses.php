<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use NyonCode\WireCore\Foundation\Colors\ButtonPalette;
use NyonCode\WireCore\Foundation\Colors\ChartPalette;
use NyonCode\WireCore\Foundation\Colors\NoticePalette;
use NyonCode\WireCore\Foundation\Colors\SemanticPalette;
use NyonCode\WireCore\Foundation\Colors\TextPalette;
use NyonCode\WireCore\Foundation\Colors\TintPalette;
use NyonCode\WireCore\Foundation\View\Palette;

/**
 * Every colour resolver in the system, under the names every consumer calls.
 *
 * The static half of {@see HasColor}, and the half that asks nothing of its
 * host: give it a colour name and it answers with class strings. A class that
 * *has* a colour uses `HasColor` and gets this with it; a class that only needs
 * the vocabulary — {@see Palette}, the door a Blade file calls through, and the
 * audit trail's event style, which answers for an *event* rather than for
 * itself — uses this one alone.
 *
 * That split is not tidiness. `HasColor`'s instance helpers read
 * `$this->getColor()`, which a host with no colour of its own cannot answer, so
 * a static-only host inherited five methods that would fatal if anything ever
 * called them. Nothing did, which is exactly the kind of thing that stays true
 * until it does not.
 *
 * The rules themselves live one file per surface, and this delegates:
 *
 *   {@see ButtonPalette}  something you click — solid, outlined, ghost, icon, quiet, modal submit
 *   {@see TintPalette}    a soft block carrying a state — badge, choice card, row tint, diff cell
 *   {@see TextPalette}    text that is only text — a value, a label, a link
 *   {@see NoticePalette}  a surface telling you something — alert banner, modal icon
 *   {@see ChartPalette}   a shape that *is* the value — bar, fill, live dot, progress
 *
 * **This stays the one way to ask.** The palettes are where the rules live, not
 * a second vocabulary to call: two names for one answer is what this repository
 * spends its architecture rules avoiding. Call a palette directly only from
 * inside another palette.
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
trait ResolvesColorClasses
{
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

    /** @see TintPalette::badge() */
    public static function getBadgeColorClasses(string $color): string
    {
        return TintPalette::badge($color);
    }

    /**
     * @see TintPalette::choice()
     *
     * @return array{input: string, solid: string, text: string, card: string, indicator: string}
     */
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
