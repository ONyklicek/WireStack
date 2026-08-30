<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use NyonCode\WireBoost\Support\ResourceReflector;

#[Name('describe-resource')]
#[Description('List the application\'s registered resources — key, model, labels, which surfaces (table/form/infolist/relation managers) each declares, and its navigation entry. Omit `resource` for all of them.')]
class DescribeResource extends BoostTool
{
    public function __construct(private ResourceReflector $reflector) {}

    protected function run(Request $request): Response
    {
        $key = $request->get('resource');

        if ($key === null || $key === '') {
            return $this->json(['resources' => $this->reflector->all()]);
        }

        $described = $this->reflector->describe((string) $key);

        if ($described !== null) {
            return $this->json($described);
        }

        // The developer is usually one typo away, and the registered keys are
        // the shortest way to show it.
        return $this->json([
            'error' => "No resource is registered under [{$key}].",
            'registered' => array_column($this->reflector->all(), 'key'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'resource' => $schema->string()
                ->description('Resource key (e.g. "orders") or fully-qualified class name. Omit to list every registered resource.'),
        ];
    }
}
