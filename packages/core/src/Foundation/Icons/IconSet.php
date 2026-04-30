<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Icons;

/**
 * Contract for icon sets.
 *
 * Allows registering custom icon sets that replace the default SVG icons.
 */
interface IconSet
{
    /**
     * Get the SVG path content for the given icon name.
     * Returns null if icon is not found in this set.
     */
    public function getPath(string $name): ?string;

    /**
     * Check if this icon set contains the given icon.
     */
    public function has(string $name): bool;

    /**
     * Get all available icon names.
     *
     * @return array<int, string>
     */
    public function names(): array;
}
