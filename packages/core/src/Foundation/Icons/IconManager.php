<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Icons;

use NyonCode\WireCore\Foundation\Icons\Icon as IconEnum;

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
     * Values may be either the inner SVG markup (e.g. `<path d="…"/>`) or a
     * complete `<svg>…</svg>` element — the outer `<svg>` wrapper is stripped
     * automatically, so you can paste icons straight from heroicons.com or any
     * SVG file. Icons are expected to use a `0 0 20 20` viewBox.
     *
     * @param  array<string, string>  $icons
     */
    public function registerIcons(array $icons): self
    {
        foreach ($icons as $name => $svg) {
            $this->customIcons[$name] = self::normalizeSvg($svg);
        }

        $this->renderCache = [];

        return $this;
    }

    /**
     * Register every `*.svg` file in a directory as an icon.
     *
     * The icon name is the file name without extension (e.g. `logo.svg` becomes
     * `logo`); an optional prefix lets you namespace a set (e.g. prefix `brand`
     * gives `brand-logo`). This is the simplest way to ship a custom icon set —
     * just drop SVG files in a folder and point at it.
     *
     * @param  string  $directory  Absolute path to a folder of SVG files.
     * @param  string  $prefix  Optional name prefix, joined with a dash.
     */
    public function registerIconsFromDirectory(string $directory, string $prefix = ''): self
    {
        $files = glob(rtrim($directory, '/').'/*.svg') ?: [];

        $icons = [];
        foreach ($files as $file) {
            $contents = @file_get_contents($file);
            if ($contents === false) {
                continue;
            }

            $name = basename($file, '.svg');
            if ($prefix !== '') {
                $name = $prefix.'-'.$name;
            }

            $icons[$name] = $contents;
        }

        return $this->registerIcons($icons);
    }

    /**
     * Get the SVG path content for an icon.
     */
    public function getPath(string $name): string
    {
        $resolved = IconEnum::resolve($name);

        if (isset($this->customIcons[$resolved])) {
            return $this->customIcons[$resolved];
        }

        if ($resolved !== $name && isset($this->customIcons[$name])) {
            return $this->customIcons[$name];
        }

        foreach ($this->iconSets as $iconSet) {
            $path = $iconSet->getPath($resolved);
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
        $resolved = IconEnum::resolve($name);

        if (isset($this->customIcons[$resolved]) || ($resolved !== $name && isset($this->customIcons[$name]))) {
            return true;
        }

        foreach ($this->iconSets as $iconSet) {
            if ($iconSet->has($resolved)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reduce an icon value to the inner SVG markup.
     *
     * Accepts either a bare `<path>`/`<g>` fragment or a complete `<svg>` element
     * and returns just the inner content, ready to be wrapped by {@see render()}.
     */
    private static function normalizeSvg(string $svg): string
    {
        if (stripos($svg, '<svg') === false) {
            return trim($svg);
        }

        $inner = preg_replace('#^.*?<svg[^>]*>#is', '', $svg) ?? $svg;
        $inner = preg_replace('#</svg>\s*$#is', '', $inner) ?? $inner;

        return trim($inner);
    }
}
