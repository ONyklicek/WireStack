<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Support;

use Closure;
use Illuminate\Support\Str;
use NyonCode\WireCore\Exceptions\LabelStyleException;
use NyonCode\WireCore\Foundation\Enums\LabelStyle;

/**
 * The label a component carries when nobody gave it one — the one owner of
 * "name → label" for fields, layouts, actions, columns, filters and imports.
 *
 * Two rules, in this order:
 *
 * 1. **A name that is already text stays text.** A name with a space in it was
 *    written for people (`Section::make('Pro montážníky')`, a translated string),
 *    so it is kept as written and only its first letter raised. Title-casing it
 *    produced "Pro Montážníky" — a heading nobody wrote.
 * 2. **An identifier is humanised by the configured style**
 *    (`wire-core.labels.from_name`, {@see LabelStyle}): `plan_period` → "Plan Period"
 *    (headline, the default), "Plan period" (sentence) or "plan_period" (name).
 *
 * The style is the application's to change: a {@see LabelStyle} case or its
 * value in the config — or, to decide every label itself, rule 1 included, an
 * invokable class name there or a closure at runtime through {@see using()}.
 *
 * Read on every call, so a config set at runtime applies to the next label.
 * Without a container it answers the default style.
 */
final class NameLabel
{
    private static ?Closure $using = null;

    /** The label for a component named `$name`. */
    public static function for(string $name): string
    {
        if (self::$using !== null) {
            return (string) (self::$using)($name);
        }

        $configured = self::configured();

        if (! $configured instanceof LabelStyle) {
            return (string) $configured($name);
        }

        if (self::isText($name)) {
            return Str::ucfirst(trim($name));
        }

        return self::styled($name, $configured);
    }

    /** `$name` humanised by `$style`, without the text rule. */
    public static function styled(string $name, LabelStyle $style): string
    {
        return match ($style) {
            LabelStyle::Headline => Str::headline($name),
            LabelStyle::Sentence => Str::ucfirst(Str::lower(Str::headline($name))),
            LabelStyle::Name => $name,
        };
    }

    /**
     * Decide every label from a name at runtime — `fn (string $name): string`.
     * Null goes back to the config.
     */
    public static function using(?Closure $callback): void
    {
        self::$using = $callback;
    }

    /** A name with whitespace in it is a label already, not an identifier. */
    public static function isText(string $name): bool
    {
        return preg_match('/\s/u', trim($name)) === 1;
    }

    /**
     * @return LabelStyle|callable(string): string
     */
    private static function configured(): LabelStyle|callable
    {
        try {
            $style = config('wire-core.labels.from_name', LabelStyle::Headline);
        } catch (\Throwable) {
            return LabelStyle::Headline;
        }

        if ($style === null || $style === '') {
            return LabelStyle::Headline;
        }

        if ($style instanceof LabelStyle) {
            return $style;
        }

        if (is_string($style)) {
            if (($case = LabelStyle::tryFrom($style)) !== null) {
                return $case;
            }

            if (class_exists($style) && method_exists($style, '__invoke')) {
                return app($style);
            }
        }

        throw LabelStyleException::unknown($style);
    }
}
