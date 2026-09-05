<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Exceptions;

use NyonCode\WireCore\Foundation\Contracts\WireException;
use RuntimeException;

/**
 * An agent's MCP configuration file that `wire-boost:install` will not edit.
 *
 * Every case is about the state of a file the application owns rather than a bad
 * argument — unreadable, unparseable, or not writable — so `RuntimeException` is
 * the base, per ADR 0022.
 *
 * Thrown rather than swallowed, and that is the whole point of the class. The
 * installer *merges*: it reads the file, adds one server to it, and writes the
 * result back. A read that answers "empty" for a file that is not empty turns
 * that merge into a replacement — every other MCP server the developer had
 * configured disappears, the command prints a tick, and nothing anywhere says
 * so. A file this cannot parse is the one case where doing nothing is
 * unambiguously safer than proceeding, so the run stops with the path in the
 * message.
 */
final class McpConfigException extends RuntimeException implements WireException
{
    /**
     * The file is there but is not JSON this can merge into.
     *
     * Reached by a hand-edited config with a trailing comma, a half-finished
     * edit, or a JSON document whose root is a scalar rather than an object.
     */
    public static function unreadableConfig(string $path, string $reason): self
    {
        return new self(
            "[{$path}] exists but could not be read as a JSON object ({$reason}), so the ".
            'wire-boost MCP server was not added to it. Writing anyway would replace the '.
            'file and drop every server already configured there. Fix or move the file, '.
            'then run wire-boost:install again.'
        );
    }

    public static function configDirectoryNotWritable(string $directory): self
    {
        return new self(
            "The MCP configuration could not be written: [{$directory}] does not exist and ".
            'cannot be created. Create it yourself, then run wire-boost:install again.'
        );
    }

    public static function configNotWritable(string $path): self
    {
        return new self(
            "[{$path}] could not be written. Check that the file and its directory are ".
            'writable, then run wire-boost:install again.'
        );
    }
}
