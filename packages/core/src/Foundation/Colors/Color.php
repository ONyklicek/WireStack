<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Colors;

/**
 * Color enum representing the standard Wire color palette.
 */
enum Color: string
{
    case Primary = 'primary';
    case Success = 'success';
    case Danger = 'danger';
    case Warning = 'warning';
    case Info = 'info';
    case Gray = 'gray';
    case Purple = 'purple';
    case Pink = 'pink';

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
            'success', 'green' => self::Success,
            'danger', 'red' => self::Danger,
            'warning', 'yellow' => self::Warning,
            'info', 'cyan' => self::Info,
            'gray', 'secondary' => self::Gray,
            'purple' => self::Purple,
            'pink' => self::Pink,
            default => self::Gray,
        };
    }
}
