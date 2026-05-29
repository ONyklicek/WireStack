<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Concerns;

use NyonCode\WireCore\Foundation\Icons\IconManager;

trait HasIcons
{
    public function getIconPath(string $icon): string
    {
        return app(IconManager::class)->getPath($icon);
    }

    public function renderIconSvg(string $icon, string $size = 'w-4 h-4', string $class = ''): string
    {
        return app(IconManager::class)->render($icon, $size, $class);
    }

    /**
     * @param  array<string, string>  $icons
     */
    public static function registerIcons(array $icons): void
    {
        app(IconManager::class)->registerIcons($icons);
    }
}
