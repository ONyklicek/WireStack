<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use NyonCode\WireBoost\Support\ComponentScanner;

#[Name('list-wire-components')]
#[Description('List the application Livewire components that build a wire table, form or infolist, with their class, file path and the wire capabilities they expose.')]
class ListWireComponents extends BoostTool
{
    public function __construct(private ComponentScanner $scanner) {}

    public function handle(Request $request): Response
    {
        $components = $this->scanner->scan();

        return $this->json([
            'count' => count($components),
            'components' => $components,
        ]);
    }
}
