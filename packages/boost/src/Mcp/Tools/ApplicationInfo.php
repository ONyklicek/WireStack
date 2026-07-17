<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Mcp\Tools;

use Composer\InstalledVersions;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('application-info')]
#[Description('Report PHP, Laravel and Livewire versions, the installed wireStack package versions, and the key effective wire configuration (notification driver, default icon set, table defaults).')]
class ApplicationInfo extends BoostTool
{
    private const PACKAGES = [
        'nyoncode/wire-core',
        'nyoncode/wire-forms',
        'nyoncode/wire-table',
        'nyoncode/wire-sortable',
        'nyoncode/wire-boost',
        'livewire/livewire',
    ];

    protected function run(Request $request): Response
    {
        $packages = [];

        foreach (self::PACKAGES as $package) {
            if (InstalledVersions::isInstalled($package)) {
                $packages[$package] = InstalledVersions::getPrettyVersion($package);
            }
        }

        return $this->json([
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'packages' => $packages,
            'config' => [
                'notification_driver' => config('wire-core.notifications.default'),
                'default_icon_set' => config('wire-core.icons.default_set'),
                'table_defaults' => config('wire-table.defaults'),
            ],
        ]);
    }
}
