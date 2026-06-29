<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Mcp\Tools;

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Base class for wire-boost MCP tools. Provides a JSON response helper so each
 * tool returns a single, parseable text payload.
 */
abstract class BoostTool extends Tool
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function json(array $data): Response
    {
        return Response::json($data);
    }
}
