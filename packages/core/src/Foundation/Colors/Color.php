<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Colors;

/**
 * Color enum representing the standard Wire color palette.
 */
enum Color: string
{
    // Semantic roles (each maps to a fixed Tailwind hue in HasColor).
    case Primary = 'primary';
    case Success = 'success';
    case Danger = 'danger';
    case Warning = 'warning';
    case Info = 'info';
    case Gray = 'gray';

    // Raw Tailwind hue families — the full palette, so a type-safe Color::Teal
    // renders the same everywhere its string equivalent 'teal' does.
    //
    // NOTE: `blue`, `green`, `red`, `yellow` and `cyan` are first-class literal
    // hues, NOT aliases of the semantic roles. `blue` is a literal Tailwind blue
    // (distinct from the re-themeable brand `primary`), `green` is literal green
    // (distinct from `success`/`emerald`), and `yellow` is literal yellow
    // (distinct from `warning`/`amber`). `red`/`cyan` share the same hue as
    // `danger`/`info` but remain their own members for a complete vocabulary.
    case Blue = 'blue';
    case Green = 'green';
    case Red = 'red';
    case Yellow = 'yellow';
    case Cyan = 'cyan';
    case Slate = 'slate';
    case Zinc = 'zinc';
    case Neutral = 'neutral';
    case Stone = 'stone';
    case Orange = 'orange';
    case Lime = 'lime';
    case Teal = 'teal';
    case Sky = 'sky';
    case Indigo = 'indigo';
    case Violet = 'violet';
    case Purple = 'purple';
    case Fuchsia = 'fuchsia';
    case Pink = 'pink';
    case Rose = 'rose';

    // Achromatic endpoints. Tailwind has no numeric `white`/`black` scale, so the
    // HasColor resolvers render these adaptively: `black` is a dark fill/ink in
    // light mode and flips to white in dark mode, `white` is the inverse.
    case White = 'white';
    case Black = 'black';

    /**
     * Get all color values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Resolve a color string to a Color enum, with alias support.
     *
     * Semantic roles keep only their true aliases (`emerald`/`amber` share the
     * role hue; `secondary` is gray). The literal hues `blue`/`green`/`red`/
     * `yellow`/`cyan` resolve to their own members rather than the semantic role,
     * so a caller asking for `blue` gets literal blue, not the brand primary.
     *
     * Unknown colors fall back to gray rather than throwing, which keeps a typo
     * from breaking a page — but also makes `->color('bleu')` render silently.
     * Use {@see self::tryResolve()} when a caller needs to tell a real color
     * from a misspelt one.
     */
    public static function resolve(string $color): self
    {
        return self::tryResolve($color) ?? self::Gray;
    }

    /**
     * Resolve a color string, or null when it names no known color or alias.
     *
     * This is the canonical "is this a real color?" test. It exists so tooling
     * can distinguish a deliberate gray from a typo that {@see self::resolve()}
     * silently greys out.
     */
    public static function tryResolve(string $color): ?self
    {
        return match ($color) {
            'primary' => self::Primary,
            'success', 'emerald' => self::Success,
            'danger' => self::Danger,
            'warning', 'amber' => self::Warning,
            'info' => self::Info,
            'gray', 'secondary' => self::Gray,
            'blue' => self::Blue,
            'green' => self::Green,
            'red' => self::Red,
            'yellow' => self::Yellow,
            'cyan' => self::Cyan,
            'white' => self::White,
            'black' => self::Black,
            'slate' => self::Slate,
            'zinc' => self::Zinc,
            'neutral' => self::Neutral,
            'stone' => self::Stone,
            'orange' => self::Orange,
            'lime' => self::Lime,
            'teal' => self::Teal,
            'sky' => self::Sky,
            'indigo' => self::Indigo,
            'violet' => self::Violet,
            'purple' => self::Purple,
            'fuchsia' => self::Fuchsia,
            'pink' => self::Pink,
            'rose' => self::Rose,
            default => null,
        };
    }
}
