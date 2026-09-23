<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support\Icons;

use NyonCode\WireCore\Foundation\Icons\IconSet;
use NyonCode\WireCore\Foundation\Icons\ProvidesIconMetadata;
use NyonCode\WireCore\Foundation\Icons\ResolvedIcon;
use NyonCode\WireCore\Foundation\Icons\WireIconSet;

/**
 * The glyphs wire-table draws that neither Heroicons nor the shared
 * {@see WireIconSet} can answer for.
 *
 * Today that is the pair of marks inside the table's hand-drawn selection
 * checkbox. The table is the only surface in the stack that draws a checkbox
 * itself rather than styling a native `<input>`, so it is the only one that owns
 * a checkbox mark — which is why the glyphs live here and not in core. See
 * `resources/icons/table.php` for the full reasoning and for the geometry.
 *
 * Registered under the `table` prefix, so a button asks for
 * `icon('table:checkbox-check')` and a consumer can swap the whole set — or one
 * glyph — the way any other icon set is swapped.
 *
 * The bodies ship as a data file loaded on first use, mirroring
 * `Foundation\Icons\HeroiconsSet` and wire-forms' `Support\Icons\FormsIconSet`:
 * a table with no selection column must not pay to read a mark it will not draw.
 */
final class TableIconSet implements IconSet, ProvidesIconMetadata
{
    /**
     * Icon bodies keyed by name, loaded once per process.
     *
     * @var array<string, string>|null
     */
    private static ?array $icons = null;

    public function getPath(string $name): ?string
    {
        return $this->icons()[$name] ?? null;
    }

    /**
     * These glyphs are 16x16 and STROKED, which is neither the format a bare
     * IconSet is wrapped in (Heroicons solid, 20x20 fill) nor the one core's
     * own set uses (24x24 fill) — so the set describes its own format rather
     * than having every icon render at the wrong scale and weight.
     *
     * 16x16 because a checkbox mark is drawn for a 16 px box and nothing else:
     * a glyph authored at the size it is used at needs no hinting fudge. The
     * 2-unit stroke with round caps is what makes it legible there, and it is
     * what matches the mark `@tailwindcss/forms` paints into the native
     * checkboxes elsewhere in the stack.
     */
    public function getIcon(string $name): ?ResolvedIcon
    {
        $body = $this->getPath($name);

        return $body === null
            ? null
            : new ResolvedIcon($body, '0 0 16 16', [
                'fill' => 'none',
                'stroke' => 'currentColor',
                'stroke-width' => '2',
                'stroke-linecap' => 'round',
                'stroke-linejoin' => 'round',
            ]);
    }

    public function has(string $name): bool
    {
        return isset($this->icons()[$name]);
    }

    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_keys($this->icons());
    }

    /**
     * @return array<string, string>
     */
    private function icons(): array
    {
        if (self::$icons !== null) {
            return self::$icons;
        }

        // packages/table/src/Support/Icons -> packages/table
        /** @var array<string, string> $icons */
        $icons = require dirname(__DIR__, 3).'/resources/icons/table.php';

        return self::$icons = $icons;
    }
}
