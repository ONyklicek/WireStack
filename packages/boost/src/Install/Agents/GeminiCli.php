<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Install\Agents;

use NyonCode\WireBoost\Contracts\SupportsGuidelines;
use NyonCode\WireBoost\Contracts\SupportsMcp;

class GeminiCli extends Agent implements SupportsGuidelines, SupportsMcp
{
    public function key(): string
    {
        return 'gemini';
    }

    public function name(): string
    {
        return 'Gemini CLI';
    }

    public function mcpConfigPath(string $basePath): string
    {
        return $basePath.'/.gemini/settings.json';
    }

    public function mcpServersKey(): string
    {
        return 'mcpServers';
    }

    public function guidelinesPath(string $basePath): string
    {
        return $basePath.'/GEMINI.md';
    }
}
