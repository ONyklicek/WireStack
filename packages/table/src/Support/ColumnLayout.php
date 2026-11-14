<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Table;

/**
 * The one reading of a stored column layout — widths and pins — against the
 * table as it is declared now.
 *
 * A width for a column that no longer exists, a pin the table no longer
 * offers, a width outside a sane range: a layout remembered before a column
 * was renamed is the same kind of input as a crafted request, and both go
 * through here — the endpoints, the remembered preferences and saved views.
 */
final class ColumnLayout
{
    /** The narrowest and widest a column may be dragged, in pixels. */
    public const MIN_WIDTH = 48;

    public const MAX_WIDTH = 1200;

    /** @return array<string, int> */
    public static function widths(mixed $widths, Table $table): array
    {
        if (! is_array($widths) || ! $table->hasResizableColumns()) {
            return [];
        }

        $names = self::names($table);
        $clean = [];

        foreach ($widths as $name => $width) {
            if (is_string($name) && in_array($name, $names, true) && is_numeric($width)) {
                $clean[$name] = self::clamp((int) $width);
            }
        }

        return $clean;
    }

    /** @return list<string> */
    public static function pins(mixed $pinned, Table $table): array
    {
        if (! is_array($pinned) || ! $table->hasPinnableColumns()) {
            return [];
        }

        $names = self::names($table);

        return array_values(array_unique(array_filter(
            $pinned,
            static fn (mixed $name): bool => is_string($name) && in_array($name, $names, true),
        )));
    }

    public static function clamp(int $width): int
    {
        return max(self::MIN_WIDTH, min(self::MAX_WIDTH, $width));
    }

    /** @return list<string> */
    private static function names(Table $table): array
    {
        return array_values(array_map(static fn (Column $column): string => $column->getName(), $table->getColumns()));
    }
}
