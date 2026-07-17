<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use NyonCode\WireBoost\Support\ComponentReflector;

#[Name('describe-infolist')]
#[Description('Resolve the flattened entry schema (name, label, type and wrapping layout) of a wire-core infolist component.')]
class DescribeInfolist extends BoostTool
{
    public function __construct(private ComponentReflector $reflector) {}

    protected function run(Request $request): Response
    {
        return $this->json($this->reflector->describeInfolist((string) $request->get('component')));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'component' => $schema->string()
                ->description('Fully-qualified class name of the Livewire component that builds the infolist.')
                ->required(),
        ];
    }
}
