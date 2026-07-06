<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Enums;

/**
 * Canonical floating-panel placement enum for dropdown menus (Floating UI).
 *
 * Owns the `{bottom,top}-{start,end}` placement vocabulary plus the static
 * transform-origin / position utilities the panel still needs (Floating UI does
 * the actual positioning; only the scale-transition origin is a static class).
 * Class strings are literal so Tailwind's JIT scanner sees them; the exhaustive
 * `match` doubles as a safe allow-list.
 */
enum Placement: string
{
    case BottomStart = 'bottom-start';
    case BottomEnd = 'bottom-end';
    case TopStart = 'top-start';
    case TopEnd = 'top-end';

    /**
     * Get all placement values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Resolve a placement token (or enum) to a Placement, falling back to
     * `$default` for anything unknown. Accepts an already-resolved enum unchanged.
     */
    public static function resolve(string|self $placement, self $default = self::BottomEnd): self
    {
        if ($placement instanceof self) {
            return $placement;
        }

        return self::tryFrom($placement) ?? $default;
    }

    /**
     * Position + transform-origin utilities for a statically-positioned panel
     * (the fallback when Floating UI is not driving placement).
     */
    public function panelClasses(): string
    {
        return match ($this) {
            self::BottomStart => 'left-0 origin-top-left',
            self::BottomEnd => 'right-0 origin-top-right',
            self::TopStart => 'left-0 bottom-full origin-bottom-left',
            self::TopEnd => 'right-0 bottom-full origin-bottom-right',
        };
    }

    /**
     * Transform-origin class only — for the Floating-UI-driven panel, whose
     * position is handled by JS but whose scale transition still needs an origin.
     */
    public function originClass(): string
    {
        return match ($this) {
            self::BottomStart => 'origin-top-left',
            self::BottomEnd => 'origin-top-right',
            self::TopStart => 'origin-bottom-left',
            self::TopEnd => 'origin-bottom-right',
        };
    }
}
