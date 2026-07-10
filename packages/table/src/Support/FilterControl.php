<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use NyonCode\WireTable\Columns\Column;

/**
 * Canonical style owner for the inline column header-filter controls.
 *
 * Every filter type (text, select, boolean, date, number-range, multi-select
 * trigger) shares one visual shell — the same height, border, radius, text size
 * and focus ring — so the header filter row reads as a single, unified control
 * group instead of a mix of native and custom widgets. This lives here, not on
 * the per-column {@see Column} config object, because
 * the style is column-independent: a single shared constant with one owner.
 */
final class FilterControl
{
    /**
     * The shared control class string.
     *
     * @param  bool  $withChevron  For select-like controls (select / multi-select
     *                             / boolean): hides the native arrow and reserves
     *                             room for the shared `filter-chevron` overlay, so
     *                             every dropdown shows the identical chevron.
     */
    public static function classes(bool $withChevron = false): string
    {
        // Mirrors the wire-forms field look (TextInput / Select): same border,
        // shadow, background, focus ring and hover, so a filter reads like a
        // (compact) form control rather than a separate widget.
        $base = 'block w-full h-9 rounded-md border border-gray-300 dark:border-gray-600 shadow-sm '
            .'bg-white dark:bg-gray-800 px-3 text-sm text-gray-900 dark:text-white '
            .'placeholder-gray-400 dark:placeholder-gray-500 '
            .'hover:border-gray-400 dark:hover:border-gray-500 transition-colors duration-150 '
            .'focus:border-primary-500 focus:ring-primary-500';

        // `bg-none` strips the @tailwindcss/forms native select chevron so only
        // the shared `filter-chevron` overlay shows (no double arrow).
        return $withChevron ? $base.' appearance-none bg-none pr-9 cursor-pointer' : $base;
    }
}
