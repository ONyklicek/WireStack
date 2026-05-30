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
 * "heroicons" npm package instead.
 */
final class DefaultIconSet implements IconSet
{
    /**
     * Path to the generated Heroicons data file, relative to the package root.
     */
    private const ASSET = 'resources/icons/heroicons-solid.php';

    /**
     * Canonical Heroicons (solid, 20x20), keyed by official icon name.
     *
     * @var array<string, string>|null
     */
    private static ?array $canonical = null;

    /**
     * Wire-friendly aliases mapped onto canonical icon names.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
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
    ];

    /**
     * @return array<string, string>
     */
    private static function canonical(): array
    {
        if (self::$canonical !== null) {
            return self::$canonical;
        }

        // packages/core/src/Foundation/Icons -> packages/core
        $packageRoot = dirname(__DIR__, 3);

        /** @var array<string, string> $icons */
        $icons = require $packageRoot.'/'.self::ASSET;

        return self::$canonical = $icons;
    }

    public function getPath(string $name): ?string
    {
        $icons = self::canonical();

        if (isset($icons[$name])) {
            return $icons[$name];
        }

        if (isset(self::ALIASES[$name])) {
            return $icons[self::ALIASES[$name]] ?? null;
        }

        return null;
    }

    public function has(string $name): bool
    {
        return isset(self::canonical()[$name]) || isset(self::ALIASES[$name]);
    }

    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_keys(self::canonical() + self::ALIASES);
    }
}
