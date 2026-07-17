<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Icons;

use NyonCode\WireCore\Exceptions\IconSetRegistrationException;
use NyonCode\WireCore\Foundation\Icons\Icon as IconEnum;

/**
 * Central icon management.
 *
 * The bundled Heroicons set is the default and is addressed with bare names
 * (`pencil`, `user`, `arrow-down-tray`). Every additional set must be registered
 * under a unique prefix and is addressed as `prefix:name` (e.g. `lucide:home`).
 * This keeps icon resolution deterministic — a bare name always means Heroicons,
 * a prefixed name always means that exact set, so the two never collide.
 *
 * Each icon is rendered with its own viewBox and styling attributes, so Heroicons
 * solid (20x20, fill) and stroke-based sets (Lucide, Feather, Heroicons outline,
 * 24x24, stroke) render correctly side by side.
 */
final class IconManager
{
    /**
     * Reserved prefix for the bundled default (Heroicons) set. Bare names and
     * the explicit `default:` prefix both resolve against it.
     */
    private const DEFAULT_PREFIX = '';

    /**
     * Registered icon sets keyed by prefix. The empty-string key is the default
     * set; all other keys are required, user-supplied prefixes.
     *
     * @var array<string, IconSet>
     */
    private array $sets = [];

    /** @var array<string, string> */
    private array $renderCache = [];

    /** @var array<string, ResolvedIcon> */
    private array $customIcons = [];

    /**
     * Per-name resolution cache. A `null` entry records a confirmed miss so we
     * don't rescan on repeated lookups of an unknown icon.
     *
     * @var array<string, ResolvedIcon|null>
     */
    private array $resolveCache = [];

    private static string $fallbackPath = '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>';

    public function __construct()
    {
        // The bundled Heroicons set is always available, unprefixed.
        $this->sets[self::DEFAULT_PREFIX] = new DefaultIconSet;

        // The matching Heroicons outline variant ships with the framework too and
        // is always available under the `outline:` prefix — independent of the
        // user's (possibly already published) config. Config may still override
        // the `outline` prefix with a different set if desired.
        $this->sets['outline'] = new HeroiconsOutlineSet;
    }

    /**
     * Register an additional icon set under a unique, non-empty prefix.
     *
     * Icons from the set are then addressed as `prefix:name` (e.g. `lucide:home`).
     * Only the bundled Heroicons set is available unprefixed; registering any
     * other set without a prefix throws, keeping resolution unambiguous.
     *
     * @throws IconSetRegistrationException when the prefix is reserved or contains a colon.
     */
    public function registerIconSet(IconSet $iconSet, string $prefix = ''): self
    {
        if ($prefix === self::DEFAULT_PREFIX || $prefix === 'default') {
            throw IconSetRegistrationException::prefixReserved();
        }

        if (str_contains($prefix, ':')) {
            throw IconSetRegistrationException::prefixContainsColon($prefix);
        }

        $this->sets[$prefix] = $iconSet;
        $this->flushCaches();

        return $this;
    }

    /**
     * Replace the bundled default (unprefixed) icon set.
     *
     * Use this to swap Heroicons for an entirely different base style while
     * keeping bare-name addressing.
     */
    public function setDefaultIconSet(IconSet $iconSet): self
    {
        $this->sets[self::DEFAULT_PREFIX] = $iconSet;
        $this->flushCaches();

        return $this;
    }

    /**
     * Register custom icons (simple key-value pairs) under bare names.
     *
     * Values may be either the inner SVG markup (e.g. `<path d="…"/>`) or a
     * complete `<svg>…</svg>` element — the outer `<svg>` wrapper is stripped
     * automatically, and its viewBox and styling attributes (fill/stroke/…) are
     * preserved, so you can paste icons straight from heroicons.com, lucide.dev,
     * or any SVG file. Bare fragments default to the Heroicons solid format
     * (`0 0 20 20`, `fill="currentColor"`). Custom icons take priority over the
     * default set for bare-name lookups.
     *
     * @param  array<string, string>  $icons
     */
    public function registerIcons(array $icons): self
    {
        foreach ($icons as $name => $svg) {
            $this->customIcons[$name] = ResolvedIcon::fromSvg($svg);
        }

        $this->flushCaches();

        return $this;
    }

    /**
     * Register every `*.svg` file in a directory as a bare-named custom icon.
     *
     * The icon name is the file name without extension (e.g. `logo.svg` becomes
     * `logo`); an optional prefix is joined with a dash (e.g. prefix `brand` gives
     * `brand-logo`) — note this is a flat name, not a `prefix:name` set namespace.
     * Each file's viewBox and styling attributes are preserved.
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
     * Get the inner SVG markup for an icon (without the `<svg>` wrapper).
     *
     * Prefer {@see render()} when emitting an icon, as it also applies the icon's
     * own viewBox and styling. This method is kept for callers that wrap the
     * markup themselves and is only correct for `0 0 20 20` fill-based icons.
     */
    public function getPath(string $name): string
    {
        return $this->resolveOrFallback($name)->body;
    }

    /**
     * Resolve an icon to its full rendering metadata.
     *
     * Bare names resolve against custom icons then the default set; `prefix:name`
     * resolves against the set registered under that prefix.
     */
    public function resolve(string $name): ?ResolvedIcon
    {
        if (array_key_exists($name, $this->resolveCache)) {
            return $this->resolveCache[$name];
        }

        return $this->resolveCache[$name] = $this->findIcon($name);
    }

    /**
     * Render a complete SVG element for the given icon.
     *
     * @param  string  $label  Accessible label. Empty ⇒ decorative (`aria-hidden`).
     */
    public function render(string $name, string $size = 'w-4 h-4', string $class = '', string $label = ''): string
    {
        $cacheKey = $name."\0".$size."\0".$class."\0".$label;

        if (! isset($this->renderCache[$cacheKey])) {
            $classes = trim($size.' '.$class);
            $this->renderCache[$cacheKey] = $this->resolveOrFallback($name)->toSvg($classes, $label);
        }

        return $this->renderCache[$cacheKey];
    }

    /**
     * Resolve an icon to its rendering metadata, falling back to the placeholder
     * when unknown. Public counterpart to {@see render()} for callers (like the
     * `<x-wire::icon>` Blade component) that build the `<svg>` themselves so they
     * can merge forwarded attributes (Alpine bindings, data-*) onto the root.
     */
    public function resolved(string $name): ResolvedIcon
    {
        return $this->resolveOrFallback($name);
    }

    /**
     * Check if an icon exists (bare name or `prefix:name`).
     */
    public function has(string $name): bool
    {
        return $this->resolve($name) !== null;
    }

    /**
     * Get every available icon name across custom icons and all registered sets,
     * de-duplicated and sorted. Non-default sets are listed as `prefix:name`.
     * Useful for icon pickers, docs, and previews.
     *
     * @return array<int, string>
     */
    public function allNames(): array
    {
        $names = array_keys($this->customIcons);

        foreach ($this->sets as $prefix => $iconSet) {
            foreach ($iconSet->names() as $name) {
                $names[] = $prefix === self::DEFAULT_PREFIX ? $name : $prefix.':'.$name;
            }
        }

        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }

    /**
     * Resolve an icon or fall back to the placeholder, warning on a miss when
     * `wire-core.icons.warn_missing` is enabled (helps catch typos in dev).
     */
    private function resolveOrFallback(string $name): ResolvedIcon
    {
        $icon = $this->resolve($name);

        if ($icon !== null) {
            return $icon;
        }

        $this->warnMissing($name);

        return new ResolvedIcon(self::$fallbackPath);
    }

    private function findIcon(string $name): ?ResolvedIcon
    {
        $separator = strpos($name, ':');

        if ($separator !== false) {
            $prefix = substr($name, 0, $separator);
            $iconName = substr($name, $separator + 1);

            // `default:` is an explicit way to address the bundled base set.
            if ($prefix === 'default') {
                return $this->findInDefault($iconName);
            }

            $set = $this->sets[$prefix] ?? null;

            return $set !== null ? $this->resolveFromSet($set, $iconName) : null;
        }

        return $this->findInDefault($name);
    }

    /**
     * Resolve a bare name: custom icons first, then the default (Heroicons) set.
     */
    private function findInDefault(string $name): ?ResolvedIcon
    {
        $resolved = IconEnum::resolve($name);

        if (isset($this->customIcons[$resolved])) {
            return $this->customIcons[$resolved];
        }

        if ($resolved !== $name && isset($this->customIcons[$name])) {
            return $this->customIcons[$name];
        }

        return $this->resolveFromSet($this->sets[self::DEFAULT_PREFIX], $resolved);
    }

    private function resolveFromSet(IconSet $set, string $name): ?ResolvedIcon
    {
        if ($set instanceof ProvidesIconMetadata) {
            return $set->getIcon($name);
        }

        $path = $set->getPath($name);

        return $path === null ? null : new ResolvedIcon($path);
    }

    private function flushCaches(): void
    {
        $this->renderCache = [];
        $this->resolveCache = [];
    }

    private function warnMissing(string $name): void
    {
        if (! function_exists('config') || ! function_exists('logger')) {
            return;
        }

        if (config('wire-core.icons.warn_missing', false) !== true) {
            return;
        }

        logger()->warning("[wire-core] Unknown icon \"{$name}\" — rendered fallback placeholder.");
    }
}
