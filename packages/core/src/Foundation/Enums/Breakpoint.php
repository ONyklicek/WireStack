<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Enums;

use NyonCode\WireCore\Foundation\Support\ResponsiveGrid;

/**
 * Canonical responsive breakpoint enum.
 *
 * Single owner of the Tailwind breakpoint vocabulary (`sm`…`2xl`) so every
 * responsive surface — table column visibility, mobile-stacked layout, the
 * responsive grid — resolves the same token to the same literal utility instead
 * of re-encoding its own `match` map. Owner-facing setters accept `string|Breakpoint`
 * so `->visibleFrom('md')` and `->visibleFrom(Breakpoint::Md)` are interchangeable.
 *
 * The per-surface helpers return **literal** class strings (never interpolated
 * from a token) so Tailwind's JIT scanner sees every utility and the `match`
 * arms double as a safe allow-list. Compatible with Tailwind 3 and 4 (plain
 * `md:` prefix syntax only).
 */
enum Breakpoint: string
{
    case Sm = 'sm';
    case Md = 'md';
    case Lg = 'lg';
    case Xl = 'xl';
    case TwoXl = '2xl';

    /**
     * Get all breakpoint values, in min-width order.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Resolve a breakpoint token (or enum) to a Breakpoint, falling back to
     * `$default` for anything unknown. Accepts an already-resolved enum unchanged.
     */
    public static function resolve(string|self $breakpoint, self $default = self::Md): self
    {
        if ($breakpoint instanceof self) {
            return $breakpoint;
        }

        return self::tryFrom($breakpoint) ?? $default;
    }

    /**
     * Try to resolve a raw token to a Breakpoint, or null when it is not a known
     * breakpoint. Used by surfaces (e.g. the responsive grid) that must skip
     * unknown tokens rather than fall back to a default.
     */
    public static function tryFromToken(string $token): ?self
    {
        return self::tryFrom($token);
    }

    /**
     * The Tailwind variant prefix for this breakpoint (e.g. `md:`).
     *
     * Interpolation-safe only where the consuming surface separately guarantees
     * the resulting utility is scannable (see {@see ResponsiveGrid}).
     */
    public function prefix(): string
    {
        return $this->value.':';
    }

    /**
     * `{bp}:table-cell` — reveal a `hidden` element as a table cell from this
     * breakpoint up (column `visibleFrom`).
     */
    public function tableCellClass(): string
    {
        return match ($this) {
            self::Sm => 'sm:table-cell',
            self::Md => 'md:table-cell',
            self::Lg => 'lg:table-cell',
            self::Xl => 'xl:table-cell',
            self::TwoXl => '2xl:table-cell',
        };
    }

    /**
     * `{bp}:hidden` — hide an element from this breakpoint up (column `hiddenFrom`,
     * stacked mobile cards).
     */
    public function hiddenAtClass(): string
    {
        return match ($this) {
            self::Sm => 'sm:hidden',
            self::Md => 'md:hidden',
            self::Lg => 'lg:hidden',
            self::Xl => 'xl:hidden',
            self::TwoXl => '2xl:hidden',
        };
    }

    /**
     * `hidden {bp}:block` — hidden below this breakpoint, block from it up
     * (stacked mobile table wrapper).
     */
    public function blockFromClass(): string
    {
        return match ($this) {
            self::Sm => 'hidden sm:block',
            self::Md => 'hidden md:block',
            self::Lg => 'hidden lg:block',
            self::Xl => 'hidden xl:block',
            self::TwoXl => 'hidden 2xl:block',
        };
    }

    /**
     * `hidden {bp}:inline` — hidden below this breakpoint, inline from it up
     * (desktop half of a responsive cell wrapper).
     */
    public function inlineFromClass(): string
    {
        return match ($this) {
            self::Sm => 'hidden sm:inline',
            self::Md => 'hidden md:inline',
            self::Lg => 'hidden lg:inline',
            self::Xl => 'hidden xl:inline',
            self::TwoXl => 'hidden 2xl:inline',
        };
    }
}
