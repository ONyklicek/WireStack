<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Enums;

/**
 * How much room the interface gives itself.
 *
 * Two states, not three, and deliberately unlike {@see Theme}: a theme needs
 * `system` because there is an outside answer to follow and a person has to be
 * able to go back to it. Density has no outside answer — `Normal` *is* the
 * return state, and a `Comfortable` step would be a value nobody has measured.
 * It can be added the day something needs it.
 *
 * **Compact is three changes, not one.** Every one of them came out of measuring
 * the real pages rather than reasoning about the scale, and the second and third
 * exist because the first over- and under-reaches:
 *
 *   1. `--spacing` drops. Tailwind 4 compiles every padding, gap and size
 *      utility to `calc(var(--spacing) * n)`, so this is what actually tightens
 *      a table row (65px → 52px) and the gaps between form fields.
 *   2. **Icons and the top bar are pinned.** The same token drives `h-16` and
 *      `w-4`, so an unpinned compact takes the shell's top bar from 64px to 44px
 *      and an icon from 16px to 10px — 8px in the media manager. That is not
 *      density, it is a defect. Pinning the icon also recovers the icon-only
 *      buttons whose height follows it (media: 11px → 19px).
 *   3. **Form controls are reached explicitly.** `@tailwindcss/forms` sets
 *      `padding: .5rem .75rem` on every text input, select and textarea as a
 *      literal in its base layer, so `--spacing` cannot touch the one surface
 *      where density matters most. Addressing it doubles the gain on a form:
 *      five fields span 258px by default, 240px on the token alone, 228px with
 *      the control padding.
 *
 * What does *not* change is the type: font size reads `--text-*`, a separate
 * family, so compact tightens the chrome and leaves the words alone.
 *
 * The CSS keys on `[data-density]` rather than being written into a stylesheet
 * so that a fixed choice and a per-person switch are the same rules — one sets
 * the attribute from config, the other will flip it in the browser.
 */
enum Density: string
{
    /** The shipped spacing. Nothing is emitted for it. */
    case Normal = 'normal';

    case Compact = 'compact';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Anything unknown — a typo, an old value — is the shipped spacing. */
    public static function resolve(string|self|null $density): self
    {
        if ($density instanceof self) {
            return $density;
        }

        return self::tryFrom((string) $density) ?? self::Normal;
    }

    /** What `config('wire-core.density')` asks for. */
    public static function configured(): self
    {
        return self::resolve(config('wire-core.density'));
    }

    public function label(): string
    {
        return __('wire-core::messages.density_'.$this->value);
    }

    /**
     * The glyph for the switch.
     *
     * Two bars against four: the difference the setting makes, drawn as the
     * thing it does to a list. `arrows-pointing-in` was the other candidate and
     * says "collapse", which is a different idea — nothing folds away here, the
     * rows just sit closer.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Normal => 'outline:bars-2',
            self::Compact => 'outline:bars-4',
        };
    }
}
