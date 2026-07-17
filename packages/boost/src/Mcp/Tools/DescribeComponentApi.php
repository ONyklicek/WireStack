<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use NyonCode\WireBoost\Support\ComponentReflector;
use NyonCode\WireBoost\Support\TypeCatalog;

#[Name('describe-component-api')]
#[Description('List the public fluent API (method signatures, and whether each is chainable) of a wireStack component type. Accepts a fully-qualified class name or a built-in type short name such as "text-column".')]
class DescribeComponentApi extends BoostTool
{
    public function __construct(
        private ComponentReflector $reflector,
        private TypeCatalog $catalog,
    ) {}

    protected function run(Request $request): Response
    {
        $class = $this->catalog->resolveOrFail((string) $request->get('class'));

        return $this->json($this->reflector->describeType($class));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'class' => $schema->string()
                ->description('A fully-qualified class name or a built-in type short name (e.g. "badge-column", "select").')
                ->required(),
        ];
    }
}
