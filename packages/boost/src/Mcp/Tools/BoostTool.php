<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use NyonCode\WireCore\Foundation\Contracts\WireException;

/**
 * Base class for wire-boost MCP tools: a JSON response helper, and the one
 * place a wireStack failure turns into a tool error.
 *
 * Tools implement `run()`; `handle()` wraps it. This exists because an MCP tool
 * must not throw — an escaping exception becomes a JSON-RPC *protocol* error,
 * which tells the agent the server misbehaved rather than that its request was
 * wrong. Equally, an error must not be smuggled back inside a success payload:
 * `Response::json(['error' => ...])` reports `isError: false`, so a client
 * cannot tell a failure from a result without parsing prose.
 *
 * So: the Support layer throws {@see WireException}s and never returns error
 * shapes, and this boundary renders them with `Response::error()`, which sets
 * `isError` properly. Anything that is *not* a WireException is a bug in this
 * package rather than a bad request, and is left to propagate.
 */
abstract class BoostTool extends Tool
{
    public function handle(Request $request): Response
    {
        try {
            return $this->run($request);
        } catch (WireException $e) {
            return Response::error($e->getMessage());
        }
    }

    abstract protected function run(Request $request): Response;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function json(array $data): Response
    {
        return Response::json($data);
    }
}
