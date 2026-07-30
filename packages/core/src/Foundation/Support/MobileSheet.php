<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Support;

use NyonCode\WireCore\Foundation\Enums\Breakpoint;

/**
 * Canonical Tailwind class vocabulary for the mobile bottom-sheet presentation,
 * parameterised by a breakpoint (sm / md / lg). Below the breakpoint a floating
 * panel becomes a sheet; from the breakpoint up it stays a floating panel.
 *
 * Every method takes an optional breakpoint override; when omitted it falls back
 * to the global `wire-core.mobile.breakpoint` config. Every returned string is a
 * literal per breakpoint (match arms) so Tailwind's scanner can see them —
 * interpolating `max-{$bp}:` would never generate the CSS.
 */
final class MobileSheet
{
    /** @var list<string> */
    public const BREAKPOINTS = ['sm', 'md', 'lg'];

    /**
     * Resolve a breakpoint token: an explicit override wins, else the global
     * config default; anything outside the sheet-supported subset (sm/md/lg)
     * falls back to `sm`. Accepts the canonical {@see Breakpoint} enum too — its
     * value is used, so a non-sheet breakpoint (xl/2xl) resolves to `sm` just like
     * the equivalent string.
     */
    public static function breakpoint(string|Breakpoint|null $override = null): string
    {
        if ($override instanceof Breakpoint) {
            $override = $override->value;
        }

        $bp = $override ?? config('wire-core.mobile.breakpoint', 'sm');

        return in_array($bp, self::BREAKPOINTS, true) ? (string) $bp : 'sm';
    }

    /**
     * Max-width (px) used by the JS side (Floating UI skip + swipe + focus trap)
     * to match the CSS breakpoint. Just under the next breakpoint's min-width.
     */
    public static function px(?string $breakpoint = null): float
    {
        return match (self::breakpoint($breakpoint)) {
            'md' => 767.98,
            'lg' => 1023.98,
            default => 639.98,
        };
    }

    /**
     * Sheet panel overrides (position, size, rounding, safe-area padding).
     */
    public static function panel(?string $breakpoint = null): string
    {
        return match (self::breakpoint($breakpoint)) {
            'md' => 'max-md:fixed max-md:inset-x-0 max-md:bottom-0 max-md:top-auto max-md:w-auto max-md:max-h-[85vh] max-md:overflow-y-auto max-md:rounded-b-none max-md:rounded-t-2xl max-md:border-x-0 max-md:border-b-0 max-md:pb-[env(safe-area-inset-bottom)]',
            'lg' => 'max-lg:fixed max-lg:inset-x-0 max-lg:bottom-0 max-lg:top-auto max-lg:w-auto max-lg:max-h-[85vh] max-lg:overflow-y-auto max-lg:rounded-b-none max-lg:rounded-t-2xl max-lg:border-x-0 max-lg:border-b-0 max-lg:pb-[env(safe-area-inset-bottom)]',
            default => 'max-sm:fixed max-sm:inset-x-0 max-sm:bottom-0 max-sm:top-auto max-sm:w-auto max-sm:max-h-[85vh] max-sm:overflow-y-auto max-sm:rounded-b-none max-sm:rounded-t-2xl max-sm:border-x-0 max-sm:border-b-0 max-sm:pb-[env(safe-area-inset-bottom)]',
        };
    }

    /**
     * Same as {@see panel()} but the safe-area padding keeps a 1rem base — for
     * panels that already carry their own inner padding (the date picker's p-4).
     */
    public static function panelPadded(?string $breakpoint = null): string
    {
        return match (self::breakpoint($breakpoint)) {
            'md' => 'max-md:fixed max-md:inset-x-0 max-md:bottom-0 max-md:top-auto max-md:w-auto max-md:max-h-[85vh] max-md:overflow-y-auto max-md:rounded-b-none max-md:rounded-t-2xl max-md:border-x-0 max-md:border-b-0 max-md:pb-[calc(1rem_+_env(safe-area-inset-bottom))]',
            'lg' => 'max-lg:fixed max-lg:inset-x-0 max-lg:bottom-0 max-lg:top-auto max-lg:w-auto max-lg:max-h-[85vh] max-lg:overflow-y-auto max-lg:rounded-b-none max-lg:rounded-t-2xl max-lg:border-x-0 max-lg:border-b-0 max-lg:pb-[calc(1rem_+_env(safe-area-inset-bottom))]',
            default => 'max-sm:fixed max-sm:inset-x-0 max-sm:bottom-0 max-sm:top-auto max-sm:w-auto max-sm:max-h-[85vh] max-sm:overflow-y-auto max-sm:rounded-b-none max-sm:rounded-t-2xl max-sm:border-x-0 max-sm:border-b-0 max-sm:pb-[calc(1rem_+_env(safe-area-inset-bottom))]',
        };
    }

    /**
     * Enter-start / leave-end transform for the slide-up (added to the base
     * desktop scale transition).
     */
    public static function motion(?string $breakpoint = null): string
    {
        return match (self::breakpoint($breakpoint)) {
            'md' => 'max-md:scale-100 max-md:translate-y-full',
            'lg' => 'max-lg:scale-100 max-lg:translate-y-full',
            default => 'max-sm:scale-100 max-sm:translate-y-full',
        };
    }

    /**
     * Show-below-breakpoint utility for the grabber handle.
     */
    public static function grabberShow(?string $breakpoint = null): string
    {
        return match (self::breakpoint($breakpoint)) {
            'md' => 'hidden max-md:flex',
            'lg' => 'hidden max-lg:flex',
            default => 'hidden max-sm:flex',
        };
    }

    /**
     * A scrolling region *inside* a sheet: full width, and a cap sized for a
     * phone rather than for a dropdown.
     *
     * An inner list that keeps its desktop cap strands a short, narrow column
     * inside a full-width sheet. The fix is a **bigger** cap, not `max-h-none`:
     * removing it entirely hands scrolling to the panel (which carries its own
     * `max-h-[85vh]` + `overflow-y-auto`, see {@see panel()}), and any component
     * that scrolls its list programmatically — setting `scrollTop` to reveal the
     * current value on open — silently stops working, because the element it
     * scrolls is no longer the one that scrolls.
     *
     * 60vh leaves room for the grabber, the panel padding and a footer inside the
     * sheet's own 85vh.
     *
     * Pair with the panel utilities on the panel itself; this goes on the
     * scrollable child.
     */
    public static function scrollArea(?string $breakpoint = null): string
    {
        return match (self::breakpoint($breakpoint)) {
            'md' => 'max-md:w-full max-md:max-h-[60vh]',
            'lg' => 'max-lg:w-full max-lg:max-h-[60vh]',
            default => 'max-sm:w-full max-sm:max-h-[60vh]',
        };
    }

    /**
     * Hide-from-breakpoint utility for the dimming backdrop (mobile-only).
     */
    public static function backdropHide(?string $breakpoint = null): string
    {
        return match (self::breakpoint($breakpoint)) {
            'md' => 'md:hidden',
            'lg' => 'lg:hidden',
            default => 'sm:hidden',
        };
    }
}
