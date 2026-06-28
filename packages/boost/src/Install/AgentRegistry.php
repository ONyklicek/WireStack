<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Install;

use NyonCode\WireBoost\Install\Agents\Agent;
use NyonCode\WireBoost\Install\Agents\ClaudeCode;
use NyonCode\WireBoost\Install\Agents\Codex;
use NyonCode\WireBoost\Install\Agents\Cursor;
use NyonCode\WireBoost\Install\Agents\GeminiCli;
use NyonCode\WireBoost\Install\Agents\Junie;
use NyonCode\WireBoost\Install\Agents\Vscode;

/**
 * Registry of the AI agents/IDEs wire-boost knows how to configure.
 */
class AgentRegistry
{
    /**
     * @var array<int, class-string<Agent>>
     */
    private const AGENTS = [
        ClaudeCode::class,
        Codex::class,
        Cursor::class,
        GeminiCli::class,
        Vscode::class,
        Junie::class,
    ];

    /**
     * @return array<string, Agent>
     */
    public function all(): array
    {
        $agents = [];

        foreach (self::AGENTS as $class) {
            $agent = new $class;
            $agents[$agent->key()] = $agent;
        }

        return $agents;
    }

    public function get(string $key): ?Agent
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * @return array<string, string> key => label
     */
    public function options(): array
    {
        return array_map(static fn (Agent $agent): string => $agent->name(), $this->all());
    }
}
