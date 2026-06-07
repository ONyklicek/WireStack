<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Icons;

/**
 * Default icon set with the complete Heroicons collection.
 *
 * Contains every Heroicons 2.2.0 solid icon (20x20 viewBox), plus a small set
 * of Wire-friendly aliases kept for backward compatibility. Canonical names
 * match the official Heroicons file names (e.g. "arrow-down-tray"); the {@see Icon}
 * enum resolves friendly names and aliases to these canonical keys.
 *
 * Icon paths are shipped as a generated PHP data file
 * (resources/icons/heroicons-solid.php) and loaded lazily on first use. Do not
 * edit the icon paths by hand; regenerate the data file from the official
 * "heroicons" npm package instead. The outline variant lives in
 * {@see HeroiconsOutlineSet}; shared loading/alias logic lives in
 * {@see HeroiconsSet}.
 */
final class DefaultIconSet extends HeroiconsSet
{
    /**
     * Path to the generated Heroicons data file, relative to the package root.
     */
    private const ASSET = 'resources/icons/heroicons-solid.php';

    protected function asset(): string
    {
        return self::ASSET;
    }

    protected function resolved(string $body): ResolvedIcon
    {
        // Heroicons solid: 20x20 viewBox, filled with the current text color.
        return new ResolvedIcon($body);
    }
}
