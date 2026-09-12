<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use NyonCode\WireBoost\Support\WirePackages;

#[Name('application-info')]
#[Description('Report PHP, Laravel and Livewire versions, the installed wireStack package versions (the stack, the optional panel/admin layers and the ready-made modules), the companion packages a module switches surfaces on for (Fortify, passkeys, the permission package), and the key effective wire configuration (notification driver, default icon set, table defaults).')]
class ApplicationInfo extends BoostTool
{
    public function __construct(private WirePackages $packages) {}

    protected function run(Request $request): Response
    {
        return $this->json([
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'packages' => array_merge($this->packages->versions(), $this->packages->companions()),
            'config' => [
                'notification_driver' => config('wire-core.notifications.default'),
                'default_icon_set' => config('wire-core.icons.default_set'),
                'table_defaults' => config('wire-table.defaults'),
            ],
        ]);
    }
}
