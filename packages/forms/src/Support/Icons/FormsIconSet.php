<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Support\Icons;

use NyonCode\WireCore\Foundation\Icons\IconSet;
use NyonCode\WireCore\Foundation\Icons\ProvidesIconMetadata;
use NyonCode\WireCore\Foundation\Icons\ResolvedIcon;

/**
 * The glyphs wire-forms draws that Heroicons does not have.
 *
 * Bold, italic, the alignment bars, the quote mark, the rating star: Heroicons
 * has some of these and not others, and the fields used to carry the missing
 * ones as inline `<svg>` in their templates — against the Icons rule, invisible
 * to theming, and duplicated between the rich editor, the markdown editor and
 * Tiptap (the quote mark was written out three times).
 *
 * Registered under the `forms` prefix, so a button asks for `icon('forms:bold')`
 * and a consumer can swap the whole set — or one glyph — the way any other icon
 * set is swapped.
 *
 * The bodies ship as a data file loaded on first use, mirroring
 * `Foundation\Icons\HeroiconsSet`: these are on few pages, and a page with no
 * editor and no rating must not pay to read a set it will not draw.
 */
final class FormsIconSet implements IconSet, ProvidesIconMetadata
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
     * The glyphs are 24x24 and fill-based, which is not the default a bare
     * IconSet is wrapped in (Heroicons solid, 20x20) — so the set describes its
     * own format rather than having every icon render at the wrong scale.
     */
    public function getIcon(string $name): ?ResolvedIcon
    {
        $body = $this->getPath($name);

        return $body === null
            ? null
            : new ResolvedIcon($body, '0 0 24 24', ['fill' => 'currentColor']);
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

        // packages/forms/src/Support/Icons -> packages/forms
        /** @var array<string, string> $icons */
        $icons = require dirname(__DIR__, 3).'/resources/icons/forms.php';

        return self::$icons = $icons;
    }
}
