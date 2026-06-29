<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Install\Agents;

use NyonCode\WireBoost\Contracts\SupportsGuidelines;
use NyonCode\WireBoost\Contracts\SupportsMcp;

class Cursor extends Agent implements SupportsGuidelines, SupportsMcp
{
    public function key(): string
    {
        return 'cursor';
    }

    public function name(): string
    {
        return 'Cursor';
    }

    public function mcpConfigPath(string $basePath): string
    {
        return $basePath.'/.cursor/mcp.json';
    }

    public function mcpServersKey(): string
    {
        return 'mcpServers';
    }

    public function guidelinesPath(string $basePath): string
    {
        return $basePath.'/.cursor/rules/wire-boost.mdc';
    }
}
