<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Icons;

/**
 * Shared base for the bundled Heroicons variants (solid and outline).
 *
 * Both variants ship the same 324 canonical icon names and the same set of
 * Wire-friendly aliases; they differ only in their generated data file and the
 * `<svg>` metadata used to wrap each body (solid: 20x20 fill, outline: 24x24
 * stroke). Subclasses provide those two pieces via {@see asset()} and
 * {@see resolved()}; everything else (lazy loading, alias handling, lookups)
 * lives here so the two stay in lockstep.
 */
abstract class HeroiconsSet implements IconSet, ProvidesIconMetadata
{
    /**
     * Wire-friendly aliases mapped onto canonical icon names. Shared by every
     * variant so `x`, `close`, `edit`, … resolve identically in solid and
     * outline.
     *
     * @var array<string, string>
     */
    protected const ALIASES = [
        'pen' => 'pencil',
        'edit' => 'pencil',
        'delete' => 'trash',
        'view' => 'eye',
        'add' => 'plus',
        'download' => 'arrow-down-tray',
        'export' => 'arrow-down-tray',
        'upload' => 'arrow-up-tray',
        'import' => 'arrow-up-tray',
        'duplicate' => 'document-duplicate',
        'copy' => 'document-duplicate',
        'x' => 'x-mark',
        'close' => 'x-mark',
        'settings' => 'cog',
        'mail' => 'envelope',
        'email' => 'envelope',
        'exclamation' => 'exclamation-triangle',
        'warning' => 'exclamation-triangle',
        'information' => 'information-circle',
        'info' => 'information-circle',
        'question' => 'question-mark-circle',
        'archive' => 'archive-box',
        'refresh' => 'arrow-path',
        'shield' => 'shield-check',
        'lock' => 'lock-closed',
        'more' => 'ellipsis-vertical',
        'dots-vertical' => 'ellipsis-vertical',
        'dots-horizontal' => 'ellipsis-horizontal',
        'filter' => 'funnel',
        'external-link' => 'arrow-top-right-on-square',
    ];

    /**
     * Canonical icon bodies keyed by asset path, so the two variants cache
     * independently even though they share this base class.
     *
     * @var array<string, array<string, string>>
     */
    private static array $cache = [];

    /**
     * Path to the generated data file for this variant, relative to the
     * package root (e.g. `resources/icons/heroicons-solid.php`).
     */
    abstract protected function asset(): string;

    /**
     * Wrap an icon body in this variant's `<svg>` metadata (viewBox + fill/
     * stroke attributes).
     */
    abstract protected function resolved(string $body): ResolvedIcon;

    public function getPath(string $name): ?string
    {
        $icons = $this->canonical();

        if (isset($icons[$name])) {
            return $icons[$name];
        }

        if (isset(static::ALIASES[$name])) {
            return $icons[static::ALIASES[$name]] ?? null;
        }

        return null;
    }

    public function getIcon(string $name): ?ResolvedIcon
    {
        $path = $this->getPath($name);

        return $path === null ? null : $this->resolved($path);
    }

    public function has(string $name): bool
    {
        return isset($this->canonical()[$name]) || isset(static::ALIASES[$name]);
    }

    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_keys($this->canonical() + static::ALIASES);
    }

    /**
     * @return array<string, string>
     */
    private function canonical(): array
    {
        $asset = $this->asset();

        if (isset(self::$cache[$asset])) {
            return self::$cache[$asset];
        }

        // packages/core/src/Foundation/Icons -> packages/core
        $packageRoot = dirname(__DIR__, 3);

        /** @var array<string, string> $icons */
        $icons = require $packageRoot.'/'.$asset;

        return self::$cache[$asset] = $icons;
    }
}
