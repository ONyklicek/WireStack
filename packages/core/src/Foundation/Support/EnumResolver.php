<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Support;

use BackedEnum;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasColor;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasIcon;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasLabel;
use NyonCode\WireCore\Foundation\Icons\Icon;
use UnitEnum;

/**
 * Canonical normaliser for PHP enum values across every display and state surface.
 *
 * PHP enums (backed or unit) cannot be stringified with `(string) $enum` — it throws
 * `Object of class ... could not be converted to string`. When an Eloquent model casts
 * an attribute to an enum (`$casts = ['status' => Status::class]`), that raw enum instance
 * flows into columns, infolist entries, exports, grouping and serialization. This is the
 * single owner that turns such a value into a safe scalar / label, and reads the optional
 * {@see HasLabel}, {@see HasColor}, {@see HasIcon} enum contracts.
 *
 * Downstream packages (table, forms, sortable) delegate here instead of re-encoding
 * `(string) $enum` or local `match` maps. Non-enum values pass through untouched, so the
 * helper is safe to call on any value.
 */
final class EnumResolver
{
    /**
     * Normalise a value to its scalar key form.
     *
     * Backed enums collapse to their backing value, unit enums to their case name; any
     * other value (scalars, strings, arrays, objects) is returned unchanged. Use for map
     * keys, copy values, comparisons and wire-safe serialization.
     */
    public static function scalar(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        return $value;
    }

    /**
     * Normalise a value to its human-facing label.
     *
     * Enums implementing {@see HasLabel} use their `getLabel()`; otherwise this falls back
     * to {@see scalar()}. Use wherever the value is shown to a user.
     */
    public static function label(mixed $value): mixed
    {
        if ($value instanceof HasLabel) {
            return $value->getLabel() ?? self::scalar($value);
        }

        return self::scalar($value);
    }

    /**
     * Normalise a value into a display-safe form that `(string)` can never fatal on.
     *
     * Builds on {@see label()} (so enum labels win) and additionally collapses array /
     * JSON-cast attributes to a compact JSON string instead of letting `(string) $array`
     * raise an "Array to string conversion" warning and render the literal "Array".
     * Scalars, strings and null pass through untouched. This is the canonical entry point
     * for every surface that ultimately stringifies an owner-provided value.
     */
    public static function display(mixed $value): mixed
    {
        $value = self::label($value);

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        return $value;
    }

    /**
     * Resolve the palette color an enum carries, or null when none is declared.
     *
     * Only enums implementing {@see HasColor} return a color; every other value yields null
     * so callers can fall back to their own defaults.
     */
    public static function color(mixed $value): string|Color|null
    {
        if ($value instanceof HasColor) {
            return $value->getColor();
        }

        return null;
    }

    /**
     * Resolve the icon an enum carries, or null when none is declared.
     *
     * Only enums implementing {@see HasIcon} return an icon; every other value yields null.
     */
    public static function icon(mixed $value): string|Icon|null
    {
        if ($value instanceof HasIcon) {
            return $value->getIcon();
        }

        return null;
    }

    /**
     * Whether the given value is a PHP enum instance (backed or unit).
     */
    public static function isEnum(mixed $value): bool
    {
        return $value instanceof UnitEnum;
    }
}
