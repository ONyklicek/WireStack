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
     */
    public static function resolve(string $color): self
    {
        return match ($color) {
            'primary', 'blue' => self::Primary,
            'success', 'green', 'emerald' => self::Success,
            'danger', 'red' => self::Danger,
            'warning', 'yellow', 'amber' => self::Warning,
            'info', 'cyan' => self::Info,
            'gray', 'secondary' => self::Gray,
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
            default => self::Gray,
        };
    }
}
