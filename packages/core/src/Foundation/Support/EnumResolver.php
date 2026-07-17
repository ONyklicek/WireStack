<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Support;

use BackedEnum;
use Illuminate\Support\Str;
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
     * Recursively reduce every enum instance in a value to its scalar key form.
     *
     * Same rules as {@see scalar()}, but descends into arrays so a nested state bag
     * (repeater rows, multi-select selections) carries no enum instances. Use wherever
     * a whole state structure — not a single cell — must become wire-safe: an enum left
     * in Livewire state cannot round-trip to the browser, and a `<select>` compares its
     * options against the scalar key, never the case object.
     */
    public static function scalarDeep(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(static fn (mixed $item): mixed => self::scalarDeep($item), $value);
        }

        return self::scalar($value);
    }

    /**
     * Normalise a value to its human-facing label.
     *
     * Enums resolve through one canonical order: the {@see HasLabel} contract's `getLabel()`,
     * then a `label()` method, then a headline of the case name (`InReview` → "In Review").
     * This is the same label an enum yields as an option key/label (see {@see options()}), so a
     * label-less enum reads identically on a table cell, an export, and a `<select>` option.
     * Non-enum values pass through untouched. Use wherever the value is shown to a user.
     */
    public static function label(mixed $value): mixed
    {
        if ($value instanceof UnitEnum) {
            return self::enumLabel($value);
        }

        return $value;
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

    /**
     * Whether the given value is the class-string of a PHP enum.
     *
     * Use to detect the Filament-style shorthand where an owner passes an enum
     * class to an options API (`->options(Status::class)`) instead of an array.
     */
    public static function isEnumClass(mixed $value): bool
    {
        return is_string($value) && enum_exists($value);
    }

    /**
     * Build a `[value => label]` option map from a backed/unit enum class.
     *
     * Each case collapses to its {@see scalar()} key and its {@see label()} — the same
     * canonical label resolution used everywhere a value is displayed. This is the canonical
     * owner of the "enum as option source" expansion; downstream option APIs (form
     * Select/Radio/CheckboxList, table SelectColumn/SelectFilter) delegate here instead of
     * re-encoding the map.
     *
     * @param  class-string  $enumClass
     * @return array<string|int, string>
     */
    public static function options(string $enumClass): array
    {
        $map = [];

        foreach ($enumClass::cases() as $case) {
            /** @var string|int $key */
            $key = self::scalar($case);
            $map[$key] = self::enumLabel($case);
        }

        return $map;
    }

    /**
     * Build a `[value => iconName]` map from an enum class implementing {@see HasIcon}.
     *
     * Mirrors {@see options()} but for the opt-in {@see HasIcon} contract: each case
     * that declares an icon collapses to its {@see scalar()} key and the icon's string
     * name ({@see Icon} instances are normalised through `value()`). Cases without an icon
     * are omitted, so the map is empty for enums that do not implement `HasIcon`. This is
     * the canonical owner of the "enum as icon source" expansion; option-driven surfaces
     * (form Radio cards/buttons) delegate here instead of re-deriving per-case icons.
     *
     * @param  class-string  $enumClass
     * @return array<string|int, string>
     */
    public static function icons(string $enumClass): array
    {
        $map = [];

        foreach ($enumClass::cases() as $case) {
            $icon = self::icon($case);

            if ($icon !== null) {
                /** @var string|int $key */
                $key = self::scalar($case);
                $map[$key] = $icon instanceof Icon ? $icon->value() : $icon;
            }
        }

        return $map;
    }

    /**
     * Build a `[value => colorName]` map from an enum class implementing {@see HasColor}.
     *
     * Mirrors {@see icons()} for the opt-in {@see HasColor} contract: each case that declares
     * a color collapses to its {@see scalar()} key and the color's string name ({@see Color}
     * instances are normalised through `value`). Cases without a color are omitted, so the map
     * is empty for enums that do not implement `HasColor`. Canonical owner of the "enum as
     * color source" expansion for option-driven surfaces (form Radio, and any per-option
     * color map) so per-case colors are never re-derived downstream.
     *
     * @param  class-string  $enumClass
     * @return array<string|int, string>
     */
    public static function colors(string $enumClass): array
    {
        $map = [];

        foreach ($enumClass::cases() as $case) {
            $color = self::color($case);

            if ($color !== null) {
                /** @var string|int $key */
                $key = self::scalar($case);
                $map[$key] = $color instanceof Color ? $color->value : $color;
            }
        }

        return $map;
    }

    /**
     * Normalise an owner-provided options value into an array.
     *
     * Enum class-strings expand via {@see options()}; arrays (and anything else)
     * pass through untouched. Closures must already be resolved by the caller.
     *
     * @return array<string|int, string>|mixed
     */
    public static function normalizeOptions(mixed $value): mixed
    {
        if (self::isEnumClass($value)) {
            return self::options($value);
        }

        return $value;
    }

    /**
     * Resolve the canonical human label for a single enum case.
     *
     * Order: the {@see HasLabel} contract's `getLabel()`, then a `label()` method, then a
     * headline of the case name. Shared by {@see label()} and {@see options()} so display
     * surfaces and option lists never diverge for the same enum.
     */
    private static function enumLabel(UnitEnum $case): string
    {
        if ($case instanceof HasLabel) {
            $label = $case->getLabel();

            if ($label !== null) {
                return $label;
            }
        }

        if (method_exists($case, 'label')) {
            /** @var string $label */
            $label = $case->label();

            return $label;
        }

        return Str::headline($case->name);
    }
}
