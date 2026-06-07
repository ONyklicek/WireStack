<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Icons;

/**
 * Optional capability for {@see IconSet} implementations that can describe an
 * icon's full rendering metadata (viewBox + root attributes), not just its path.
 *
 * Sets that implement this interface can ship icons in any format — stroke-based
 * (Lucide, Feather, Heroicons outline) or with non-`20x20` viewBoxes — and have
 * them render correctly alongside the bundled Heroicons solid set.
 *
 * Sets that implement only {@see IconSet} keep working: the {@see IconManager}
 * wraps their `getPath()` output in the default Heroicons solid format
 * (`0 0 20 20`, `fill="currentColor"`).
 */
interface ProvidesIconMetadata
{
    /**
     * Resolve an icon to its full rendering metadata, or null if not in this set.
     */
    public function getIcon(string $name): ?ResolvedIcon;
}
