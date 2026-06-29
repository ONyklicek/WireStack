<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('database-connections')]
#[Description('List the configured database connections and their drivers, including the default connection.')]
class DatabaseConnections extends BoostTool
{
    public function handle(Request $request): Response
    {
        /** @var array<string, array<string, mixed>> $connections */
        $connections = (array) config('database.connections', []);

        $mapped = [];

        foreach ($connections as $name => $config) {
            $mapped[$name] = $config['driver'] ?? null;
        }

        return $this->json([
            'default' => config('database.default'),
            'connections' => $mapped,
        ]);
    }
}
