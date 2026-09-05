<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Install;

use JsonException;
use NyonCode\WireBoost\Contracts\SupportsMcp;
use NyonCode\WireBoost\Exceptions\McpConfigException;
use NyonCode\WireBoost\Install\Agents\Agent;

/**
 * Writes (and idempotently merges) the wire-boost MCP server entry into an
 * agent's MCP configuration JSON file.
 */
class McpInstaller
{
    /**
     * @return string the configuration file path that was written
     *
     * @throws McpConfigException When the existing file cannot be merged into, or the result cannot be written.
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
     * The configuration as it stands, or an empty one when there is no file yet.
     *
     * Anything else throws. This is a merge, so "could not read it" and "it was
     * empty" are not the same answer: treating the first as the second makes
     * {@see write()} replace a file it was supposed to add one key to, and every
     * other MCP server in it is gone with no warning anywhere.
     *
     * @return array<string, mixed>
     *
     * @throws McpConfigException When the file is there but is not a JSON object.
     */
    private function read(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw McpConfigException::unreadableConfig($path, 'the file could not be opened');
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw McpConfigException::unreadableConfig($path, $e->getMessage());
        }

        if (! is_array($decoded)) {
            throw McpConfigException::unreadableConfig($path, 'its root is '.get_debug_type($decoded));
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws McpConfigException When the directory cannot be made or the file cannot be written.
     */
    private function write(string $path, array $config): void
    {
        $directory = dirname($path);

        // The `is_dir()` after the failed mkdir() is not redundant: a parallel
        // run may have made the directory between the check and the call, which
        // is a success for both of them.
        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw McpConfigException::configDirectoryNotWritable($directory);
        }

        // Suppressed on purpose, for the reason the exporters give: an unwritable
        // path raises a warning naming `file_put_contents`, and what should reach
        // the developer names their MCP config. The warning is replaced, never
        // ignored — the alternative is a printed tick beside a file that still
        // holds the old contents.
        $written = @file_put_contents(
            $path,
            (string) json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL,
        );

        if ($written === false) {
            throw McpConfigException::configNotWritable($path);
        }
    }
}
