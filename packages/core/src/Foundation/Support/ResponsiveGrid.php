<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Support;

use NyonCode\WireCore\Foundation\Enums\Breakpoint;

/**
 * Canonical owner of responsive grid-column classes. Accepts a Filament-style
 * per-breakpoint map — e.g. ['default' => 1, 'md' => 2, 'lg' => 3] — and returns
 * the matching literal Tailwind `grid-cols-*` utilities.
 *
 * Tailwind only generates classes it can see as literal text. The runtime line
 * in {@see cols()} interpolates the count, so on its own the scanner would never
 * emit those utilities — {@see scannableClasses()} lists every one as a literal
 * so a consumer scanning the package `src` (see getting-started) generates them.
 * Compatible with Tailwind 3 and 4 (plain `md:grid-cols-2` syntax only).
 */
final class ResponsiveGrid
{
    /** Supported column counts (1–12, matching Tailwind's default grid scale). */
    private const MAX_COLUMNS = 12;

    /**
     * Map columns to a grid-cols class string.
     *
     * An int gives a mobile-first reflow (1 column on phones, up to N from md).
     * An array is a per-breakpoint map: keys are breakpoints ('default'/''/0 =
     * base, then sm|md|lg|xl|2xl), values are column counts (clamped to 1–12);
     * unknown breakpoints are ignored.
     *
     * @param  int|array<string|int, int|string>  $columns
     */
    public static function cols(int|array $columns): string
    {
        if (is_int($columns)) {
            $count = max(1, min($columns, self::MAX_COLUMNS));

            return $count === 1 ? 'grid-cols-1' : 'grid-cols-1 md:grid-cols-'.$count;
        }

        $out = [];

        foreach ($columns as $breakpoint => $count) {
            $prefix = self::prefix((string) $breakpoint);

            if ($prefix === null) {
                continue;
            }

            $count = max(1, min((int) $count, self::MAX_COLUMNS));
            $out[] = $prefix.'grid-cols-'.$count;
        }

        return implode(' ', $out);
    }

    /**
     * Resolve a breakpoint token to its Tailwind prefix, or null if unknown.
     * '' / 'default' / 0 all map to the base (unprefixed) breakpoint; every named
     * breakpoint delegates to the canonical {@see Breakpoint} enum.
     */
    private static function prefix(string $breakpoint): ?string
    {
        if ($breakpoint === '' || $breakpoint === 'default' || $breakpoint === '0') {
            return '';
        }

        return Breakpoint::tryFromToken($breakpoint)?->prefix();
    }

    /**
     * Every grid-cols utility {@see cols()} can emit, written out as LITERAL
     * strings so Tailwind's text scanner generates them (an interpolated
     * `$prefix.'grid-cols-'.$count` would be invisible to it). Never called at
     * runtime — its only job is to exist as scannable text in this file.
     *
     * @return list<string>
     */
    public static function scannableClasses(): array
    {
        return [
            'grid-cols-1', 'grid-cols-2', 'grid-cols-3', 'grid-cols-4', 'grid-cols-5', 'grid-cols-6', 'grid-cols-7', 'grid-cols-8', 'grid-cols-9', 'grid-cols-10', 'grid-cols-11', 'grid-cols-12',
            'sm:grid-cols-1', 'sm:grid-cols-2', 'sm:grid-cols-3', 'sm:grid-cols-4', 'sm:grid-cols-5', 'sm:grid-cols-6', 'sm:grid-cols-7', 'sm:grid-cols-8', 'sm:grid-cols-9', 'sm:grid-cols-10', 'sm:grid-cols-11', 'sm:grid-cols-12',
            'md:grid-cols-1', 'md:grid-cols-2', 'md:grid-cols-3', 'md:grid-cols-4', 'md:grid-cols-5', 'md:grid-cols-6', 'md:grid-cols-7', 'md:grid-cols-8', 'md:grid-cols-9', 'md:grid-cols-10', 'md:grid-cols-11', 'md:grid-cols-12',
            'lg:grid-cols-1', 'lg:grid-cols-2', 'lg:grid-cols-3', 'lg:grid-cols-4', 'lg:grid-cols-5', 'lg:grid-cols-6', 'lg:grid-cols-7', 'lg:grid-cols-8', 'lg:grid-cols-9', 'lg:grid-cols-10', 'lg:grid-cols-11', 'lg:grid-cols-12',
            'xl:grid-cols-1', 'xl:grid-cols-2', 'xl:grid-cols-3', 'xl:grid-cols-4', 'xl:grid-cols-5', 'xl:grid-cols-6', 'xl:grid-cols-7', 'xl:grid-cols-8', 'xl:grid-cols-9', 'xl:grid-cols-10', 'xl:grid-cols-11', 'xl:grid-cols-12',
            '2xl:grid-cols-1', '2xl:grid-cols-2', '2xl:grid-cols-3', '2xl:grid-cols-4', '2xl:grid-cols-5', '2xl:grid-cols-6', '2xl:grid-cols-7', '2xl:grid-cols-8', '2xl:grid-cols-9', '2xl:grid-cols-10', '2xl:grid-cols-11', '2xl:grid-cols-12',
        ];
    }
}
