<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'wire-boost:mcp', description: 'Start the WireStack Boost MCP server (usually launched from .mcp.json).')]
class McpCommand extends Command
{
    protected $signature = 'wire-boost:mcp';

    protected $description = 'Start the WireStack Boost MCP server (usually launched from .mcp.json).';

    public function handle(): int
    {
        return $this->call('mcp:start', ['handle' => 'wire-boost']);
    }
}
