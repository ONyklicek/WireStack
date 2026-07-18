<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;
use NyonCode\WireCore\Foundation\Enums\Size;

/**
 * Size property for components (xs, sm, md, lg, xl).
 *
 * Accepts either a raw size token or the canonical {@see Size} enum, so
 * `->size('lg')` and `->size(Size::Lg)` are interchangeable. The size is stored
 * as its string value so downstream getters/resolvers keep returning strings.
 */
trait HasSize
{
    protected string|Closure $size = 'md';

    /** Set the component size on the xs–xl scale (a `Size` enum, a keyword string, or a Closure). */
    public function size(string|Size|Closure $size): static
    {
        $this->size = $size instanceof Size ? $size->value : $size;

        return $this;
    }

    /** Size preset: extra-small. */
    public function xs(): static
    {
        return $this->size('xs');
    }

    /** Size preset: small. */
    public function sm(): static
    {
        return $this->size('sm');
    }

    /** Size preset: medium (the default). */
    public function md(): static
    {
        return $this->size('md');
    }

    /** Size preset: large. */
    public function lg(): static
    {
        return $this->size('lg');
    }

    /** Size preset: extra-large. */
    public function xl(): static
    {
        return $this->size('xl');
    }

    public function getSize(): string
    {
        return $this->evaluate($this->size) ?? 'md';
    }

    /**
     * Canonical soft "pill"/badge sizing (padding + font size).
     *
     * Single source for badge-like surfaces (BadgeColumn, PollColumn, …) so the
     * xs/sm/md/lg scale stays identical everywhere. Literal class strings are
     * kept verbatim for Tailwind's JIT scanner.
     */
    public static function getBadgeSizeClasses(string|Size $size): string
    {
        $size = $size instanceof Size ? $size->value : $size;

        return match ($size) {
            'xs' => 'px-1.5 py-0.5 text-[10px]',
            'sm' => 'px-2 py-0.5 text-xs',
            'lg' => 'px-3 py-1 text-sm',
            'md' => 'px-2.5 py-1 text-xs',
            default => 'px-2.5 py-1 text-xs',
        };
    }

    /**
     * Canonical button sizing (padding + font size + icon gap).
     *
     * Single source for clickable button surfaces (action buttons, action group
     * triggers, ButtonColumn) so the xs/sm/md/lg scale stays identical across
     * every surface. `$iconOnly` returns square padding without text/gap for
     * icon-only buttons — enlarged one step on mobile so the tap target clears
     * ~40px on a touch screen, then back to the compact desktop size from `sm`
     * up. Literal class strings are kept verbatim for Tailwind's JIT scanner
     * (safe allow-list).
     */
    public static function getButtonSizeClasses(string|Size $size, bool $iconOnly = false): string
    {
        $size = $size instanceof Size ? $size->value : $size;

        if ($iconOnly) {
            return match ($size) {
                'xs' => 'p-2 sm:p-1',
                'md' => 'p-2.5 sm:p-2',
                'lg' => 'p-3 sm:p-2.5',
                default => 'p-2.5 sm:p-1.5',
            };
        }

        return match ($size) {
            'xs' => 'px-2 py-1 text-xs gap-1',
            'md' => 'px-3 py-2 text-sm gap-2',
            'lg' => 'px-4 py-2.5 text-base gap-2',
            default => 'px-2.5 py-1.5 text-sm gap-1.5',
        };
    }

    /**
     * Canonical icon-dimension classes for an icon rendered inside/next to a button
     * surface (Radio segmented/buttons, ButtonColumn triggers).
     *
     * The scale tracks {@see getButtonSizeClasses()} so the glyph stays proportional to
     * the button text across every clickable surface. Literal class strings are kept
     * verbatim for Tailwind's JIT scanner.
     */
    public static function getButtonIconSizeClasses(string|Size $size): string
    {
        $size = $size instanceof Size ? $size->value : $size;

        return match ($size) {
            'xs' => 'w-3.5 h-3.5',
            'md' => 'w-5 h-5',
            'lg' => 'w-5 h-5',
            default => 'w-4 h-4',
        };
    }

    /**
     * Canonical icon-dimension classes for a standalone display icon (IconColumn).
     *
     * Larger than {@see getButtonIconSizeClasses()} because the icon is the content
     * itself rather than an accent beside a label. Literal class strings are kept
     * verbatim for Tailwind's JIT scanner.
     */
    public static function getIconSizeClasses(string|Size $size): string
    {
        $size = $size instanceof Size ? $size->value : $size;

        return match ($size) {
            'xs' => 'w-4 h-4',
            'sm' => 'w-5 h-5',
            'lg' => 'w-7 h-7',
            'xl' => 'w-8 h-8',
            default => 'w-6 h-6',
        };
    }
}
