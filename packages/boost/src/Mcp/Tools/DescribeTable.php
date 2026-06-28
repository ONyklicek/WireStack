<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use NyonCode\WireBoost\Support\ComponentReflector;

#[Name('describe-table')]
#[Description('Resolve the columns, filters, header/row/bulk actions, default sort and searchability of a wire-table Livewire component.')]
class DescribeTable extends BoostTool
{
    public function __construct(private ComponentReflector $reflector) {}

    public function handle(Request $request): Response
    {
        return $this->json($this->reflector->describeTable((string) $request->get('component')));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'component' => $schema->string()
                ->description('Fully-qualified class name of the Livewire component that builds the table.')
                ->required(),
        ];
    }
}
