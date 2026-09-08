<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Icons;

use NyonCode\WireForms\Support\Icons\FormsIconSet;

/**
 * The framework's own glyphs, for what Heroicons has no answer to and more than
 * one package draws.
 *
 * Registered under the `wire` prefix, so a caller asks for `icon('wire:star')`
 * and a consumer can swap the set — or one glyph — the way any other set is
 * swapped.
 *
 * **Why it is here and not in whichever package drew it first.** The rating star
 * is drawn by wire-forms' `Rating` field and by wire-table's read-only
 * `RatingColumn`. Left in either package it would be the other one's dependency
 * on a neighbour's private art, and the two rendered different stars for a while
 * — the same rating looking like two different things depending on whether you
 * could edit it. Core is the lowest layer that can own it, which is where a
 * shared abstraction goes.
 *
 * A glyph only one package draws stays with that package (wire-forms'
 * {@see FormsIconSet} keeps the editor toolbar
 * art). This set is for what crosses a package boundary.
 *
 * The bodies ship as a data file loaded on first use, mirroring
 * {@see HeroiconsSet}: a page that draws no rating must not pay to read one.
 */
final class WireIconSet implements IconSet, ProvidesIconMetadata
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

        // packages/core/src/Foundation/Icons -> packages/core
        /** @var array<string, string> $icons */
        $icons = require dirname(__DIR__, 3).'/resources/icons/wire.php';

        return self::$icons = $icons;
    }
}
