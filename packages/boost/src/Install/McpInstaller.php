<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Install;

use NyonCode\WireBoost\Contracts\SupportsMcp;
use NyonCode\WireBoost\Install\Agents\Agent;

/**
 * Writes (and idempotently merges) the wire-boost MCP server entry into an
 * agent's MCP configuration JSON file.
 */
class McpInstaller
{
    /**
     * @return string the configuration file path that was written
     */
    public function install(Agent&SupportsMcp $agent, string $basePath): string
    {
        $path = $agent->mcpConfigPath($basePath);
        $key = $agent->mcpServersKey();

        $config = $this->read($path);
        $servers = is_array($config[$key] ?? null) ? $config[$key] : [];

        $servers['wire-boost'] = [
            'command' => 'php',
            'args' => ['artisan', 'wire-boost:mcp'],
        ];

        $config[$key] = $servers;

        $this->write($path, $config);

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function read(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function write(string $path, array $config): void
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents(
            $path,
            (string) json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL,
        );
    }
}
