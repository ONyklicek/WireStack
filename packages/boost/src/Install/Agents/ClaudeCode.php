<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Install\Agents;

use NyonCode\WireBoost\Contracts\SupportsGuidelines;
use NyonCode\WireBoost\Contracts\SupportsMcp;
use NyonCode\WireBoost\Contracts\SupportsSkills;

class ClaudeCode extends Agent implements SupportsGuidelines, SupportsMcp, SupportsSkills
{
    public function key(): string
    {
        return 'claude';
    }

    public function name(): string
    {
        return 'Claude Code';
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
        return $basePath.'/CLAUDE.md';
    }

    public function skillsPath(string $basePath): string
    {
        return $basePath.'/.claude/skills';
    }
}
