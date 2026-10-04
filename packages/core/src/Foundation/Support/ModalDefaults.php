<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Support;

use NyonCode\WireCore\Foundation\Enums\ModalWidth;

/**
 * What a modal does when nobody said: `config('wire-core.modals')`.
 *
 * One owner for the four defaults, because a modal is drawn from several
 * places — a modal object (`Modal`, `SlideOver`, `Wizard`, `ConfirmationDialog`,
 * an `ActionHalt`), an action's own modal settings, and the Blade components
 * a view uses directly — and each of them used to carry its own literal `'md'`
 * and `true`. An explicit setting on any of them still wins; this only answers
 * when there is none.
 *
 * Read on every call rather than once, so a config set at runtime applies to the
 * next modal. Without a container — a modal object built standalone — it answers
 * the shipped defaults.
 */
final class ModalDefaults
{
    public const WIDTH = 'md';

    /** The width of a centered dialog: `wire-core.modals.default_width`. */
    public static function width(): string
    {
        return self::widthFrom('wire-core.modals.default_width');
    }

    /** The width of a slide-over panel: `wire-core.modals.slide_over_width`. */
    public static function slideOverWidth(): string
    {
        return self::widthFrom('wire-core.modals.slide_over_width');
    }

    public static function closeOnClickAway(): bool
    {
        return (bool) self::config('wire-core.modals.close_on_click_away', true);
    }

    public static function closeOnEscape(): bool
    {
        return (bool) self::config('wire-core.modals.close_on_escape', true);
    }

    /**
     * A width may be configured as a keyword or as the {@see ModalWidth} enum
     * — the same two shapes `width()` and `modalWidth()` accept.
     */
    private static function widthFrom(string $key): string
    {
        $configured = self::config($key, self::WIDTH);

        if ($configured instanceof ModalWidth) {
            return $configured->value;
        }

        return is_string($configured) && $configured !== '' ? $configured : self::WIDTH;
    }

    private static function config(string $key, mixed $default): mixed
    {
        try {
            return config($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }
}
