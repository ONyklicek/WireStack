<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Icons;

/**
 * Central icon management.
 *
 * Manages registered icon sets and renders SVG icons.
 * Replaces the old HasIcons trait with a proper service class.
 */
final class IconManager
{
    /** @var array<int, IconSet> */
    private array $iconSets = [];

    /** @var array<string, string> */
    private array $renderCache = [];

    /** @var array<string, string> */
    private array $customIcons = [];

    private static string $defaultViewBox = '0 0 20 20';

    private static string $fallbackPath = '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>';

    public function __construct()
    {
        // Register default icon set
        $this->registerIconSet(new DefaultIconSet);
    }

    /**
     * Register an icon set. Later sets take priority.
     */
    public function registerIconSet(IconSet $iconSet): self
    {
        array_unshift($this->iconSets, $iconSet);
        $this->renderCache = [];

        return $this;
    }

    /**
     * Register custom icons (simple key-value pairs).
     *
     * @param  array<string, string>  $icons
     */
    public function registerIcons(array $icons): self
    {
        $this->customIcons = array_merge($this->customIcons, $icons);
        $this->renderCache = [];

        return $this;
    }

    /**
     * Get the SVG path content for an icon.
     */
    public function getPath(string $name): string
    {
        // Custom icons take highest priority
        if (isset($this->customIcons[$name])) {
            return $this->customIcons[$name];
        }

        // Search through registered icon sets
        foreach ($this->iconSets as $iconSet) {
            $path = $iconSet->getPath($name);
            if ($path !== null) {
                return $path;
            }
        }

        return self::$fallbackPath;
    }

    /**
     * Render a complete SVG element for the given icon.
     */
    public function render(string $name, string $size = 'w-4 h-4', string $class = ''): string
    {
        $cacheKey = "{$name}_{$size}_{$class}";

        if (! isset($this->renderCache[$cacheKey])) {
            $path = $this->getPath($name);
            $classes = trim("{$size} {$class}");
            $viewBox = self::$defaultViewBox;
            $this->renderCache[$cacheKey] = "<svg class=\"{$classes}\" fill=\"currentColor\" viewBox=\"{$viewBox}\">{$path}</svg>";
        }

        return $this->renderCache[$cacheKey];
    }

    /**
     * Check if an icon exists in any registered set.
     */
    public function has(string $name): bool
    {
        if (isset($this->customIcons[$name])) {
            return true;
        }

        foreach ($this->iconSets as $iconSet) {
            if ($iconSet->has($name)) {
                return true;
            }
        }

        return false;
    }
}
