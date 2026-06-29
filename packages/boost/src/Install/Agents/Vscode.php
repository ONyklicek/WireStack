<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Install\Agents;

use NyonCode\WireBoost\Contracts\SupportsGuidelines;
use NyonCode\WireBoost\Contracts\SupportsMcp;

class Vscode extends Agent implements SupportsGuidelines, SupportsMcp
{
    public function key(): string
    {
        return 'vscode';
    }

    public function name(): string
    {
        return 'GitHub Copilot (VS Code)';
    }

    public function mcpConfigPath(string $basePath): string
    {
        return $basePath.'/.vscode/mcp.json';
    }

    public function mcpServersKey(): string
    {
        return 'servers';
    }

    public function guidelinesPath(string $basePath): string
    {
        return $basePath.'/.github/copilot-instructions.md';
    }
}
