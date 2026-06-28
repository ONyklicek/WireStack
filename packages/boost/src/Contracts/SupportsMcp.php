<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Contracts;

interface SupportsMcp
{
    /**
     * Absolute path to the agent's MCP configuration JSON file.
     */
    public function mcpConfigPath(string $basePath): string;

    /**
     * Top-level JSON key under which MCP servers are listed.
     */
    public function mcpServersKey(): string;
}
