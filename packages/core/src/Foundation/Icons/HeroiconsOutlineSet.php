<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Icons;

/**
 * Heroicons outline variant (24x24 viewBox, stroke-based).
 *
 * Registered under the `outline` prefix, so its icons are addressed as
 * `outline:name` (e.g. `outline:x-mark`) and render alongside the bundled solid
 * set. Use outline for larger UI chrome — close buttons, toolbars, pagination,
 * empty-state illustrations — and the default solid set for small inline
 * accents (badges, status dots, in-cell icons).
 *
 * Icon paths are shipped as a generated PHP data file
 * (resources/icons/heroicons-outline.php) and loaded lazily on first use. Do
 * not edit the icon paths by hand; regenerate the data file from the official
 * "heroicons" npm package instead.
 */
final class HeroiconsOutlineSet extends HeroiconsSet
{
    private const ASSET = 'resources/icons/heroicons-outline.php';

    protected function asset(): string
    {
        return self::ASSET;
    }

    protected function resolved(string $body): ResolvedIcon
    {
        return new ResolvedIcon($body, '0 0 24 24', [
            'fill' => 'none',
            'stroke' => 'currentColor',
            'stroke-width' => '1.5',
        ]);
    }
}
