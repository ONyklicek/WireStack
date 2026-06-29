<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Install\Agents;

use NyonCode\WireBoost\Contracts\SupportsGuidelines;
use NyonCode\WireBoost\Contracts\SupportsMcp;

class Codex extends Agent implements SupportsGuidelines, SupportsMcp
{
    public function key(): string
    {
        return 'codex';
    }

    public function name(): string
    {
        return 'Codex';
    }

    public function mcpConfigPath(string $basePath): string
    {
        return $basePath.'/.mcp.json';
    }

    public function mcpServersKey(): string
    {
        return 'mcpServers';
    }

    public function guidelinesPath(string $basePath): string
    {
        return $basePath.'/AGENTS.md';
    }
}
