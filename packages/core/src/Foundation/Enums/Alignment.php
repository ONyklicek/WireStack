<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Enums;

/**
 * Canonical text-alignment enum (`left`/`center`/`right`).
 *
 * Single owner of the horizontal text-alignment vocabulary shared by table
 * columns and table action alignment, so `->alignment('center')` and
 * `->alignment(Alignment::Center)` are interchangeable. Owns the literal
 * `text-*` class (scannable by Tailwind's JIT), replacing the interpolated
 * `text-{$align}` that used to live in the Blade views.
 *
 * Distinct from flex cross-axis alignment (`start`/`end`/`stretch`/`baseline`);
 * that is a separate axis vocabulary and is not modelled here.
 */
enum Alignment: string
{
    case Left = 'left';
    case Center = 'center';
    case Right = 'right';

    /**
     * Get all alignment values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Resolve an alignment token (or enum) to an Alignment, falling back to
     * `$default` for anything unknown. Accepts an already-resolved enum unchanged.
     */
    public static function resolve(string|self $alignment, self $default = self::Left): self
    {
        if ($alignment instanceof self) {
            return $alignment;
        }

        return self::tryFrom($alignment) ?? $default;
    }

    /**
     * The literal Tailwind text-alignment utility for this alignment.
     */
    public function textClass(): string
    {
        return match ($this) {
            self::Left => 'text-left',
            self::Center => 'text-center',
            self::Right => 'text-right',
        };
    }

    /**
     * The literal Tailwind flex main-axis (`justify-*`) utility for this
     * alignment — companion to {@see textClass()} for a flex row of controls.
     */
    public function justifyClass(): string
    {
        return match ($this) {
            self::Left => 'justify-start',
            self::Center => 'justify-center',
            self::Right => 'justify-end',
        };
    }
}
